<?php
/**
 * Internal PHP text analysis engine for keyword extraction, relevance
 * scoring, content gap analysis, and auto-generating keyword mappings.
 *
 * All methods run entirely in PHP — no external API calls, no token costs.
 * Uses term-frequency analysis, n-gram extraction, stop-word filtering,
 * and multi-signal relevance scoring.
 *
 * @since   3.0.0
 * @package FPP_Interlinking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Analyzer {

	/**
	 * Common English stop words to exclude from keyword extraction.
	 *
	 * @var string[]
	 */
	const STOP_WORDS = array(
		'a','about','above','after','again','against','all','also','am','an',
		'and','any','are','aren\'t','as','at','be','because','been','before',
		'being','below','between','both','but','by','can','can\'t','cannot',
		'could','couldn\'t','did','didn\'t','do','does','doesn\'t','doing',
		'don\'t','down','during','each','even','few','for','from','further',
		'get','gets','got','had','hadn\'t','has','hasn\'t','have','haven\'t',
		'having','he','he\'d','he\'ll','he\'s','her','here','here\'s','hers',
		'herself','him','himself','his','how','how\'s','i','i\'d','i\'ll',
		'i\'m','i\'ve','if','in','into','is','isn\'t','it','it\'s','its',
		'itself','just','let','let\'s','like','make','makes','made','may',
		'me','might','more','most','much','mustn\'t','my','myself','need',
		'new','no','nor','not','now','of','off','on','once','one','only',
		'or','other','ought','our','ours','ourselves','out','over','own',
		'really','right','same','say','she','she\'d','she\'ll','she\'s',
		'should','shouldn\'t','since','so','some','such','take','than',
		'that','that\'s','the','their','theirs','them','themselves','then',
		'there','there\'s','these','they','they\'d','they\'ll','they\'re',
		'they\'ve','this','those','through','to','too','two','under','until',
		'up','upon','us','use','used','using','very','want','was','wasn\'t',
		'way','we','we\'d','we\'ll','we\'re','we\'ve','well','were',
		'weren\'t','what','what\'s','when','when\'s','where','where\'s',
		'which','while','who','who\'s','whom','why','why\'s','will','with',
		'won\'t','work','would','wouldn\'t','you','you\'d','you\'ll',
		'you\'re','you\'ve','your','yours','yourself','yourselves',
	);

	/**
	 * Extract keywords from a post using PHP text analysis.
	 *
	 * Algorithm:
	 *  1. Strip HTML, decode entities, normalise whitespace.
	 *  2. Tokenise into words and filter stop words.
	 *  3. Generate n-grams (1-gram, 2-gram, 3-gram).
	 *  4. Score using TF with title-boost multiplier.
	 *  5. Deduplicate (suppress substrings of higher-ranked phrases).
	 *  6. Return top N results sorted by score descending.
	 *
	 * @since 3.0.0
	 *
	 * @param int $post_id   Post ID to analyse.
	 * @param int $max_count Maximum keywords to return (default 20).
	 * @return array|WP_Error Array of ['keyword' => string, 'relevance' => int (1-10)].
	 */
	public static function extract_keywords( $post_id, $max_count = 20 ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'fpp-interlinking' ) );
		}

		$title   = $post->post_title;
		$content = self::clean_content( $post->post_content );

		if ( strlen( $content ) < 50 ) {
			return new WP_Error( 'content_too_short', __( 'Post content is too short to analyse.', 'fpp-interlinking' ) );
		}

		// Tokenise content and title.
		$content_tokens = self::tokenize( $content );
		$title_tokens   = self::tokenize( $title );
		$title_lower    = strtolower( $title );

		// Build title word set for boost scoring.
		$title_word_set = array_flip( self::filter_stop_words( $title_tokens ) );

		// Generate n-grams from content (filtered).
		$filtered_tokens = self::filter_stop_words( $content_tokens );
		$ngrams          = self::generate_ngrams( $filtered_tokens, 3 );

		// Also generate n-grams from the full token stream (for phrases that span stop words).
		$full_ngrams = self::generate_ngrams( $content_tokens, 3 );

		// Merge: prefer the higher count.
		foreach ( $full_ngrams as $phrase => $count ) {
			if ( self::is_stop_phrase( $phrase ) ) {
				continue;
			}
			if ( ! isset( $ngrams[ $phrase ] ) || $ngrams[ $phrase ] < $count ) {
				$ngrams[ $phrase ] = $count;
			}
		}

		// Total word count for frequency scoring.
		$total_words = max( count( $content_tokens ), 1 );

		// Score each n-gram.
		$scored = array();
		foreach ( $ngrams as $phrase => $count ) {
			// Skip very short or very long phrases.
			$word_count = substr_count( $phrase, ' ' ) + 1;
			if ( strlen( $phrase ) < 3 ) {
				continue;
			}

			// Minimum frequency: unigrams need count >= 2, bigrams >= 2, trigrams >= 1.
			$min_count = $word_count >= 3 ? 1 : 2;
			if ( $count < $min_count ) {
				continue;
			}

			// Base score: term frequency relative to document size.
			$tf = ( $count / $total_words ) * 100;

			// N-gram length bonus: prefer multi-word phrases.
			$length_bonus = 1.0;
			if ( $word_count === 2 ) {
				$length_bonus = 1.5;
			} elseif ( $word_count >= 3 ) {
				$length_bonus = 2.0;
			}

			// Title boost: words appearing in the title are more relevant.
			$title_boost = 1.0;
			$phrase_words = explode( ' ', $phrase );
			$title_overlap = 0;
			foreach ( $phrase_words as $pw ) {
				if ( isset( $title_word_set[ $pw ] ) ) {
					$title_overlap++;
				}
			}
			if ( $title_overlap > 0 ) {
				$title_boost = 1.0 + ( $title_overlap / count( $phrase_words ) ) * 2.0;
			}

			// Exact title match gets maximum boost.
			if ( $phrase === $title_lower || strpos( $title_lower, $phrase ) !== false ) {
				$title_boost = max( $title_boost, 3.0 );
			}

			$score = $tf * $length_bonus * $title_boost;

			$scored[ $phrase ] = array(
				'keyword'   => $phrase,
				'score'     => $score,
				'count'     => $count,
				'words'     => $word_count,
			);
		}

		// Sort by score descending.
		uasort( $scored, function( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		// Deduplicate: if "wordpress seo" exists, suppress "wordpress" and "seo".
		$final   = array();
		$used    = array();
		$max_raw = min( count( $scored ), $max_count * 3 ); // Process more to compensate for dedup.
		$i       = 0;

		foreach ( $scored as $item ) {
			if ( $i >= $max_raw ) {
				break;
			}
			$i++;

			$phrase = $item['keyword'];

			// Skip if this phrase is a substring of an already-selected longer phrase.
			$is_substring = false;
			foreach ( $used as $selected ) {
				if ( strpos( $selected, $phrase ) !== false && $selected !== $phrase ) {
					$is_substring = true;
					break;
				}
			}
			if ( $is_substring ) {
				continue;
			}

			// Remove previously selected phrases that are substrings of this one.
			foreach ( $used as $key => $selected ) {
				if ( strpos( $phrase, $selected ) !== false && $selected !== $phrase ) {
					unset( $final[ $key ] );
					unset( $used[ $key ] );
				}
			}

			$used[ $phrase ] = $phrase;
			$final[ $phrase ] = $item;

			if ( count( $final ) >= $max_count ) {
				break;
			}
		}

		// Normalise scores to 1-10 relevance scale.
		$max_score = 0;
		foreach ( $final as $item ) {
			if ( $item['score'] > $max_score ) {
				$max_score = $item['score'];
			}
		}

		$results = array();
		foreach ( $final as $item ) {
			$relevance = $max_score > 0
				? max( 1, (int) round( ( $item['score'] / $max_score ) * 10 ) )
				: 5;

			$results[] = array(
				'keyword'   => $item['keyword'],
				'relevance' => $relevance,
			);
		}

		return $results;
	}

	/**
	 * Score how relevant candidate posts are for a given keyword.
	 *
	 * Uses multiple signals: title match, content density, excerpt match,
	 * slug match, word overlap. Normalised to 1-100.
	 *
	 * @since 3.0.0
	 *
	 * @param string $keyword    The keyword to evaluate.
	 * @param array  $candidates Array of ['id', 'title', 'url', 'excerpt'].
	 * @param array  $post_types Optional post type filter.
	 * @return array Scored candidates with 'score' (1-100) and 'reason' keys.
	 */
	public static function score_relevance( $keyword, $candidates, $post_types = array() ) {
		$keyword_lower = strtolower( trim( $keyword ) );
		$keyword_words = explode( ' ', $keyword_lower );
		$keyword_slug  = sanitize_title( $keyword );

		$results = array();

		foreach ( $candidates as $candidate ) {
			$title_lower   = strtolower( $candidate['title'] );
			$excerpt_lower = strtolower( isset( $candidate['excerpt'] ) ? $candidate['excerpt'] : '' );
			$url_lower     = strtolower( $candidate['url'] );
			$post_slug     = '';

			// Extract slug from URL.
			$path_parts = explode( '/', trim( wp_parse_url( $url_lower, PHP_URL_PATH ), '/' ) );
			if ( ! empty( $path_parts ) ) {
				$post_slug = end( $path_parts );
			}

			$score   = 0;
			$reasons = array();

			// Signal 1: Title exact match (+40).
			if ( $title_lower === $keyword_lower ) {
				$score += 40;
				$reasons[] = __( 'Exact title match', 'fpp-interlinking' );
			}
			// Signal 2: Title contains keyword (+25).
			elseif ( strpos( $title_lower, $keyword_lower ) !== false ) {
				$score += 25;
				$reasons[] = __( 'Title contains keyword', 'fpp-interlinking' );
			}
			// Signal 3: Title word overlap (+5 per word, max 15).
			else {
				$title_words = explode( ' ', $title_lower );
				$overlap     = 0;
				foreach ( $keyword_words as $kw ) {
					if ( in_array( $kw, $title_words, true ) ) {
						$overlap++;
					}
				}
				if ( $overlap > 0 ) {
					$pts = min( $overlap * 5, 15 );
					$score += $pts;
					$reasons[] = sprintf(
						/* translators: %d: number of matching words. */
						__( '%d word(s) match in title', 'fpp-interlinking' ),
						$overlap
					);
				}
			}

			// Signal 4: Content/excerpt density.
			$full_text    = $title_lower . ' ' . $excerpt_lower;
			$occurrences  = substr_count( $full_text, $keyword_lower );
			$total_words  = max( str_word_count( $full_text ), 1 );
			$density_pts  = min( (int) ( ( $occurrences / $total_words ) * 200 ), 20 );
			if ( $density_pts > 0 ) {
				$score += $density_pts;
				$reasons[] = sprintf(
					/* translators: %d: number of keyword occurrences. */
					__( 'Keyword appears %d time(s) in content', 'fpp-interlinking' ),
					$occurrences
				);
			}

			// Signal 5: Excerpt contains keyword (+10).
			if ( ! empty( $excerpt_lower ) && strpos( $excerpt_lower, $keyword_lower ) !== false ) {
				$score += 10;
				$reasons[] = __( 'Found in excerpt', 'fpp-interlinking' );
			}

			// Signal 6: Slug match (+10).
			if ( ! empty( $post_slug ) && ( $post_slug === $keyword_slug || strpos( $post_slug, $keyword_slug ) !== false ) ) {
				$score += 10;
				$reasons[] = __( 'URL slug matches keyword', 'fpp-interlinking' );
			}

			// Clamp to 1-100.
			$score = max( 1, min( $score, 100 ) );

			$results[] = array(
				'id'     => $candidate['id'],
				'title'  => $candidate['title'],
				'url'    => $candidate['url'],
				'score'  => $score,
				'reason' => ! empty( $reasons ) ? implode( '. ', $reasons ) . '.' : __( 'Low relevance.', 'fpp-interlinking' ),
			);
		}

		// Sort by score descending.
		usort( $results, function( $a, $b ) {
			return $b['score'] - $a['score'];
		} );

		return $results;
	}

	/**
	 * Analyse content gaps: find posts that share terms but don't interlink.
	 *
	 * @since 3.0.0
	 *
	 * @param int   $batch_size Number of posts per batch.
	 * @param int   $offset     Pagination offset.
	 * @param array $post_types Post type slugs to analyse.
	 * @return array|WP_Error {
	 *     'gaps'        => array of gap objects,
	 *     'total_posts' => int,
	 *     'analysed'    => int,
	 *     'offset'      => int,
	 * }
	 */
	public static function analyse_content_gaps( $batch_size = 20, $offset = 0, $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		// Fetch posts.
		$query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$total_posts = $query->found_posts;

		if ( ! $query->have_posts() ) {
			return new WP_Error( 'no_posts', __( 'No published posts found for the selected post types.', 'fpp-interlinking' ) );
		}

		// Extract keywords for each post and build inverted index.
		$post_keywords = array(); // post_id => array of keywords.
		$post_data     = array(); // post_id => {title, url}.
		$inverted      = array(); // keyword => array of post_ids.

		while ( $query->have_posts() ) {
			$query->the_post();
			$pid   = get_the_ID();
			$title = get_the_title();
			$url   = get_permalink();

			$post_data[ $pid ] = array(
				'title' => $title,
				'url'   => $url,
			);

			// Extract top 10 keywords for this post.
			$kw_result = self::extract_keywords( $pid, 10 );
			if ( is_wp_error( $kw_result ) ) {
				continue;
			}

			$keywords = array();
			foreach ( $kw_result as $kw ) {
				$phrase = strtolower( $kw['keyword'] );
				$keywords[] = $phrase;
				if ( ! isset( $inverted[ $phrase ] ) ) {
					$inverted[ $phrase ] = array();
				}
				$inverted[ $phrase ][] = $pid;
			}
			$post_keywords[ $pid ] = $keywords;
		}
		wp_reset_postdata();

		// Get existing keyword mappings to check which gaps are already covered.
		$existing = FPP_Interlinking_DB::get_all_keywords();
		$existing_map = array();
		foreach ( $existing as $ek ) {
			$existing_map[ strtolower( $ek['keyword'] ) ] = $ek['target_url'];
		}

		// Find gaps: keywords shared by 2+ posts without existing mappings.
		$gaps       = array();
		$seen_pairs = array();

		foreach ( $inverted as $keyword => $pids ) {
			if ( count( $pids ) < 2 ) {
				continue;
			}

			// Skip if this keyword is already mapped.
			if ( isset( $existing_map[ $keyword ] ) ) {
				continue;
			}

			// Generate pairs.
			$unique_pids = array_unique( $pids );
			for ( $i = 0; $i < count( $unique_pids ); $i++ ) {
				for ( $j = $i + 1; $j < count( $unique_pids ); $j++ ) {
					$source = $unique_pids[ $i ];
					$target = $unique_pids[ $j ];

					// Avoid duplicate pairs.
					$pair_key = min( $source, $target ) . '-' . max( $source, $target );
					if ( isset( $seen_pairs[ $pair_key ] ) ) {
						continue;
					}
					$seen_pairs[ $pair_key ] = true;

					// Count shared keywords between these two posts.
					$shared = 0;
					if ( isset( $post_keywords[ $source ] ) && isset( $post_keywords[ $target ] ) ) {
						$shared = count( array_intersect( $post_keywords[ $source ], $post_keywords[ $target ] ) );
					}

					// Score: more shared keywords = higher confidence.
					$confidence = min( 30 + ( $shared * 15 ), 95 );

					// Boost if keyword appears in either title.
					if ( isset( $post_data[ $source ] ) && strpos( strtolower( $post_data[ $source ]['title'] ), $keyword ) !== false ) {
						$confidence = min( $confidence + 10, 98 );
					}
					if ( isset( $post_data[ $target ] ) && strpos( strtolower( $post_data[ $target ]['title'] ), $keyword ) !== false ) {
						$confidence = min( $confidence + 10, 98 );
					}

					$gaps[] = array(
						'keyword'      => $keyword,
						'source_id'    => $source,
						'source_title' => isset( $post_data[ $source ] ) ? $post_data[ $source ]['title'] : '',
						'target_id'    => $target,
						'target_title' => isset( $post_data[ $target ] ) ? $post_data[ $target ]['title'] : '',
						'target_url'   => isset( $post_data[ $target ] ) ? $post_data[ $target ]['url'] : '',
						'confidence'   => $confidence,
						'reason'       => sprintf(
							/* translators: %d: number of shared keywords between posts. */
							__( '%d shared keyword(s) between these posts.', 'fpp-interlinking' ),
							$shared
						),
					);
				}
			}
		}

		// Sort by confidence descending, limit to 15 results.
		usort( $gaps, function( $a, $b ) {
			return $b['confidence'] - $a['confidence'];
		} );
		$gaps = array_slice( $gaps, 0, 15 );

		return array(
			'gaps'        => $gaps,
			'total_posts' => $total_posts,
			'analysed'    => min( $batch_size, $total_posts - $offset ),
			'offset'      => $offset,
		);
	}

	/**
	 * Auto-generate keyword-to-URL mapping proposals.
	 *
	 * @since 3.0.0
	 *
	 * @param int   $batch_size Number of posts to process.
	 * @param int   $offset     Pagination offset.
	 * @param array $post_types Post type slugs to analyse.
	 * @return array|WP_Error {
	 *     'mappings'    => array of mapping proposals,
	 *     'total_posts' => int,
	 *     'analysed'    => int,
	 *     'offset'      => int,
	 * }
	 */
	public static function auto_generate_mappings( $batch_size = 20, $offset = 0, $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		// Fetch posts.
		$query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$total_posts = $query->found_posts;

		if ( ! $query->have_posts() ) {
			return new WP_Error( 'no_posts', __( 'No published posts found for the selected post types.', 'fpp-interlinking' ) );
		}

		// Get existing keywords (case-insensitive) to avoid duplicates.
		$existing = FPP_Interlinking_DB::get_all_keywords();
		$existing_map = array();
		foreach ( $existing as $ek ) {
			$existing_map[ strtolower( $ek['keyword'] ) ] = true;
		}

		// Collect all posts data for candidate scoring.
		$all_posts = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$all_posts[] = array(
				'id'      => get_the_ID(),
				'title'   => get_the_title(),
				'url'     => get_permalink(),
				'excerpt' => wp_strip_all_tags( get_the_excerpt() ),
			);
		}
		wp_reset_postdata();

		$mappings       = array();
		$used_keywords  = array();

		foreach ( $all_posts as $post_item ) {
			// Extract top 5 keywords for this post.
			$kw_result = self::extract_keywords( $post_item['id'], 5 );
			if ( is_wp_error( $kw_result ) ) {
				continue;
			}

			foreach ( $kw_result as $kw ) {
				$keyword_lower = strtolower( $kw['keyword'] );

				// Skip if already mapped or already proposed.
				if ( isset( $existing_map[ $keyword_lower ] ) || isset( $used_keywords[ $keyword_lower ] ) ) {
					continue;
				}

				// Find best target (exclude the source post).
				$candidates = array_filter( $all_posts, function( $p ) use ( $post_item ) {
					return $p['id'] !== $post_item['id'];
				} );

				if ( empty( $candidates ) ) {
					continue;
				}

				$scored = self::score_relevance( $kw['keyword'], array_values( $candidates ), $post_types );

				if ( empty( $scored ) ) {
					continue;
				}

				$best = $scored[0];

				// Only propose if score >= 40.
				if ( $best['score'] < 40 ) {
					continue;
				}

				$used_keywords[ $keyword_lower ] = true;

				$mappings[] = array(
					'keyword'      => $kw['keyword'],
					'target_url'   => $best['url'],
					'target_title' => $best['title'],
					'confidence'   => $best['score'],
				);
			}
		}

		// Sort by confidence descending.
		usort( $mappings, function( $a, $b ) {
			return $b['confidence'] - $a['confidence'];
		} );

		// Limit to 20 proposals.
		$mappings = array_slice( $mappings, 0, 20 );

		return array(
			'mappings'    => $mappings,
			'total_posts' => $total_posts,
			'analysed'    => count( $all_posts ),
			'offset'      => $offset,
		);
	}

	/* ── Private Helpers ──────────────────────────────────────────────── */

	/**
	 * Strip HTML, decode entities, normalise whitespace.
	 *
	 * @since 3.0.0
	 *
	 * @param string $content Raw post content.
	 * @return string Plain text.
	 */
	private static function clean_content( $content ) {
		// Run shortcodes then strip tags.
		$content = do_shortcode( $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

		// Collapse whitespace.
		$content = preg_replace( '/\s+/', ' ', $content );
		return trim( $content );
	}

	/**
	 * Tokenise text into lowercase word tokens.
	 *
	 * @since 3.0.0
	 *
	 * @param string $text Plain text.
	 * @return string[] Lowercase word tokens.
	 */
	private static function tokenize( $text ) {
		$text = strtolower( $text );

		// Remove punctuation except hyphens and apostrophes within words.
		$text = preg_replace( '/[^\w\s\'-]/', ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );

		$tokens = explode( ' ', trim( $text ) );

		// Filter out purely numeric tokens and very short tokens.
		return array_values( array_filter( $tokens, function( $t ) {
			return strlen( $t ) >= 2 && ! is_numeric( $t );
		} ) );
	}

	/**
	 * Filter stop words from token array.
	 *
	 * @since 3.0.0
	 *
	 * @param string[] $tokens Word tokens.
	 * @return string[] Filtered tokens (re-indexed).
	 */
	private static function filter_stop_words( $tokens ) {
		static $stop_set = null;
		if ( null === $stop_set ) {
			$stop_set = array_flip( self::STOP_WORDS );
		}

		return array_values( array_filter( $tokens, function( $t ) use ( $stop_set ) {
			return ! isset( $stop_set[ $t ] );
		} ) );
	}

	/**
	 * Generate n-grams (1 to max_n) from a token array.
	 *
	 * @since 3.0.0
	 *
	 * @param string[] $tokens Word tokens.
	 * @param int      $max_n  Maximum n-gram size.
	 * @return array Associative: 'phrase' => frequency count.
	 */
	private static function generate_ngrams( $tokens, $max_n = 3 ) {
		$ngrams = array();
		$len    = count( $tokens );

		for ( $n = 1; $n <= $max_n; $n++ ) {
			for ( $i = 0; $i <= $len - $n; $i++ ) {
				$phrase = implode( ' ', array_slice( $tokens, $i, $n ) );

				// Skip phrases that start or end with a stop word for n >= 2.
				if ( $n >= 2 ) {
					if ( self::is_stop_word( $tokens[ $i ] ) || self::is_stop_word( $tokens[ $i + $n - 1 ] ) ) {
						continue;
					}
				}

				if ( ! isset( $ngrams[ $phrase ] ) ) {
					$ngrams[ $phrase ] = 0;
				}
				$ngrams[ $phrase ]++;
			}
		}

		return $ngrams;
	}

	/**
	 * Check if a word is a stop word.
	 *
	 * @since 3.0.0
	 *
	 * @param string $word Single word.
	 * @return bool
	 */
	private static function is_stop_word( $word ) {
		static $stop_set = null;
		if ( null === $stop_set ) {
			$stop_set = array_flip( self::STOP_WORDS );
		}
		return isset( $stop_set[ $word ] );
	}

	/**
	 * Check if a phrase consists entirely of stop words.
	 *
	 * @since 3.0.0
	 *
	 * @param string $phrase Space-separated phrase.
	 * @return bool
	 */
	private static function is_stop_phrase( $phrase ) {
		$words = explode( ' ', $phrase );
		foreach ( $words as $w ) {
			if ( ! self::is_stop_word( $w ) ) {
				return false;
			}
		}
		return true;
	}
}
