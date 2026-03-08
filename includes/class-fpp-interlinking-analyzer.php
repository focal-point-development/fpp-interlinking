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
		// ── Core English function words ──────────────────────────────────
		'a','about','above','across','after','again','against','all','almost',
		'along','already','also','although','always','am','among','an','and',
		'another','any','anyone','anything','are','aren\'t','around','as','at',
		'away','back','be','became','because','become','becomes','been','before',
		'began','begin','being','below','between','best','better','both','bring',
		'brought','but','by','came','can','can\'t','cannot','certain','certainly',
		'change','close','come','comes','coming','completely','consider','could',
		'couldn\'t','day','days','did','didn\'t','different','do','does',
		'doesn\'t','doing','done','don\'t','down','during','each','easily',
		'either','else','end','enough','entire','especially','even','every',
		'everyone','everything','example','except','far','few','finally','find',
		'first','follow','following','for','found','four','from','further',
		'gave','generally','get','gets','give','given','go','goes','going',
		'gone','good','got','great','had','hadn\'t','happen','has','hasn\'t',
		'have','haven\'t','having','he','he\'d','he\'ll','he\'s','help','her',
		'here','here\'s','hers','herself','high','him','himself','his','how',
		'how\'s','however','i','i\'d','i\'ll','i\'m','i\'ve','if','important',
		'in','include','including','instead','into','is','isn\'t','it','it\'s',
		'its','itself','just','keep','kind','know','known','large','last',
		'later','least','left','less','let','let\'s','like','little','long',
		'look','looking','lot','made','main','mainly','make','makes','many',
		'may','me','mean','means','might','more','most','move','much','must',
		'mustn\'t','my','myself','nearly','need','never','new','next','no',
		'nor','not','nothing','now','number','of','off','often','old','on',
		'once','one','only','open','or','other','ought','our','ours',
		'ourselves','out','over','own','part','people','perhaps','place',
		'please','point','probably','provide','put','quickly','quite','rather',
		'really','recently','right','run','said','same','say','see','seem',
		'seems','set','several','she','she\'d','she\'ll','she\'s','should',
		'shouldn\'t','show','simply','since','small','so','some','something',
		'sometimes','specifically','start','still','stop','such','sure','take',
		'tell','than','that','that\'s','the','their','theirs','them',
		'themselves','then','there','there\'s','these','they','they\'d',
		'they\'ll','they\'re','they\'ve','thing','things','think','this',
		'those','thought','three','through','time','to','today','together',
		'too','toward','try','turn','two','typically','under','unless','until',
		'up','upon','us','use','used','using','usually','very','want','was',
		'wasn\'t','way','we','we\'d','we\'ll','we\'re','we\'ve','well','went',
		'were','weren\'t','what','what\'s','when','when\'s','where','where\'s',
		'whether','which','while','who','who\'s','whom','why','why\'s','will',
		'with','within','without','won\'t','work','would','wouldn\'t','year',
		'years','yet','you','you\'d','you\'ll','you\'re','you\'ve','your',
		'yours','yourself','yourselves',
		// ── Web / content boilerplate ────────────────────────────────────
		'click','subscribe','widget','sidebar','header','footer','menu',
		'navigation','share','reply','login','signup','register','logout',
		'email','cookie','cookies','powered','toggle','submit','loading',
		'scroll','enable','disable','accept','decline','dismiss','cancel',
		'confirm','okay','button','form','field','popup','modal','banner',
		'notification','password','username','account','settings','profile',
		'previous','read','more','skip','admin','editor','posted','updated',
		'written','author','published','archive','category','tag','tags',
		'comments','related','recent','featured','trending','popular',
	);

	/**
	 * Build an Inverse Document Frequency index across all configured post types.
	 *
	 * Counts how many documents contain each stemmed term. Cached as a
	 * transient for 24 hours. The special key '_total_docs' holds the total
	 * document count used for IDF computation.
	 *
	 * @since 5.0.0
	 *
	 * @param array $post_types Optional post type slugs (defaults to configured).
	 * @return array Associative: stemmed_term => document_count, '_total_docs' => int.
	 */
	public static function get_idf_index( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		$cache_key = 'fpp_idf_' . md5( implode( ',', $post_types ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$page     = 1;
		$per_page = 50;
		$df       = array(); // stem => number of documents containing it.
		$total    = 0;

		do {
			$query = new WP_Query( array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			) );

			if ( 1 === $page ) {
				$total = $query->found_posts;
			}

			if ( ! $query->have_posts() ) {
				break;
			}

			foreach ( $query->posts as $pid ) {
				$post = get_post( $pid );
				if ( ! $post ) {
					continue;
				}

				$text   = self::clean_content( $post->post_content ) . ' ' . strtolower( $post->post_title );
				$tokens = self::filter_stop_words( self::tokenize( $text ) );

				// Get unique stems in this document.
				$doc_stems = array();
				foreach ( $tokens as $token ) {
					$doc_stems[ self::stem( $token ) ] = true;
				}

				foreach ( $doc_stems as $stem => $_ ) {
					if ( ! isset( $df[ $stem ] ) ) {
						$df[ $stem ] = 0;
					}
					$df[ $stem ]++;
				}
			}

			$page++;
		} while ( $page <= $query->max_num_pages );

		$df['_total_docs'] = $total;

		set_transient( $cache_key, $df, DAY_IN_SECONDS );

		return $df;
	}

	/**
	 * Extract keywords from a post using TF-IDF text analysis.
	 *
	 * Algorithm:
	 *  1. Strip HTML, decode entities, normalise whitespace.
	 *  2. Tokenise into words, stem, and filter stop words.
	 *  3. Generate n-grams (1-gram, 2-gram, 3-gram).
	 *  4. Score using TF-IDF with title-boost multiplier.
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

		// Build title word set (stemmed) for boost scoring.
		$title_filtered  = self::filter_stop_words( $title_tokens );
		$title_word_set  = array_flip( $title_filtered );
		$title_stem_set  = array();
		foreach ( $title_filtered as $tw ) {
			$title_stem_set[ self::stem( $tw ) ] = true;
		}

		// Load IDF index for TF-IDF scoring.
		$idf_index  = self::get_idf_index();
		$total_docs = isset( $idf_index['_total_docs'] ) ? max( $idf_index['_total_docs'], 1 ) : 1;

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

		// Score each n-gram using TF-IDF.
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

			// TF: term frequency relative to document size.
			$tf = ( $count / $total_words ) * 100;

			// IDF: inverse document frequency (use the average IDF of the phrase's stemmed words).
			$phrase_words = explode( ' ', $phrase );
			$idf_sum      = 0;
			foreach ( $phrase_words as $pw ) {
				$pw_stem = self::stem( $pw );
				$doc_freq = isset( $idf_index[ $pw_stem ] ) ? $idf_index[ $pw_stem ] : 0;
				$idf_sum += log( $total_docs / ( 1 + $doc_freq ) );
			}
			$avg_idf = $idf_sum / max( count( $phrase_words ), 1 );

			// Ensure IDF has a minimum floor so common-but-topical terms still score.
			$idf_factor = max( $avg_idf, 0.1 );

			// N-gram length bonus: prefer multi-word phrases.
			$length_bonus = 1.0;
			if ( $word_count === 2 ) {
				$length_bonus = 1.5;
			} elseif ( $word_count >= 3 ) {
				$length_bonus = 2.0;
			}

			// Title boost: use stemmed matching for better coverage.
			$title_boost   = 1.0;
			$title_overlap = 0;
			foreach ( $phrase_words as $pw ) {
				$pw_stem = self::stem( $pw );
				if ( isset( $title_stem_set[ $pw_stem ] ) || isset( $title_word_set[ $pw ] ) ) {
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

			$score = $tf * $idf_factor * $length_bonus * $title_boost;

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
		// Also deduplicate stemmed variants (e.g. "optimize" vs "optimizing").
		$final       = array();
		$used        = array(); // phrase => phrase.
		$used_stems  = array(); // stemmed phrase => phrase.
		$max_raw     = min( count( $scored ), $max_count * 3 );
		$i           = 0;

		foreach ( $scored as $item ) {
			if ( $i >= $max_raw ) {
				break;
			}
			$i++;

			$phrase      = $item['keyword'];
			$phrase_stem = implode( ' ', array_map( array( __CLASS__, 'stem' ), explode( ' ', $phrase ) ) );

			// Skip if stemmed form already selected.
			if ( isset( $used_stems[ $phrase_stem ] ) && $used_stems[ $phrase_stem ] !== $phrase ) {
				continue;
			}

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
					$sel_stem = implode( ' ', array_map( array( __CLASS__, 'stem' ), explode( ' ', $selected ) ) );
					unset( $final[ $key ] );
					unset( $used[ $key ] );
					unset( $used_stems[ $sel_stem ] );
				}
			}

			$used[ $phrase ]           = $phrase;
			$used_stems[ $phrase_stem ] = $phrase;
			$final[ $phrase ]          = $item;

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
		$keyword_stems = array_map( array( __CLASS__, 'stem' ), $keyword_words );
		$keyword_slug  = sanitize_title( $keyword );

		$results = array();

		foreach ( $candidates as $candidate ) {
			$title_lower   = strtolower( $candidate['title'] );
			$excerpt_lower = strtolower( isset( $candidate['excerpt'] ) ? $candidate['excerpt'] : '' );
			$url_lower     = strtolower( $candidate['url'] );
			$post_slug     = '';

			// Extract slug from URL (handle trailing slashes).
			$path = trim( wp_parse_url( $url_lower, PHP_URL_PATH ), '/' );
			$path_parts = explode( '/', $path );
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
			// Signal 3: Title word overlap — use stemmed matching (+5 per word, max 15).
			else {
				$title_word_stems = array_map( array( __CLASS__, 'stem' ), explode( ' ', $title_lower ) );
				$overlap = count( array_intersect( $keyword_stems, $title_word_stems ) );
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

			// Signal 7: Heading match (+15).
			$post_obj = get_post( $candidate['id'] );
			if ( $post_obj && preg_match_all( '/<h[1-3][^>]*>(.*?)<\/h[1-3]>/is', $post_obj->post_content, $hm ) ) {
				foreach ( $hm[1] as $h_html ) {
					$h_text = strtolower( wp_strip_all_tags( $h_html ) );
					if ( strpos( $h_text, $keyword_lower ) !== false ) {
						$score += 15;
						$reasons[] = __( 'Keyword found in heading', 'fpp-interlinking' );
						break;
					}
				}
			}

			// Signal 8: Category/tag match (+10).
			if ( isset( $candidate['id'] ) ) {
				$terms = wp_get_post_terms( $candidate['id'], array( 'category', 'post_tag' ), array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term_name ) {
						if ( strpos( strtolower( $term_name ), $keyword_lower ) !== false ) {
							$score += 10;
							$reasons[] = __( 'Keyword matches category/tag', 'fpp-interlinking' );
							break;
						}
					}
				}
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
	 * @param int   $batch_size   Number of posts per batch.
	 * @param int   $offset       Pagination offset.
	 * @param array $post_types   Post type slugs to analyse.
	 * @param int   $max_results  Maximum gap results (default 25).
	 * @return array|WP_Error {
	 *     'gaps'        => array of gap objects,
	 *     'total_posts' => int,
	 *     'analysed'    => int,
	 *     'offset'      => int,
	 * }
	 */
	public static function analyse_content_gaps( $batch_size = 20, $offset = 0, $post_types = array(), $max_results = 25 ) {
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

		// Extract keywords for each post and build inverted index (stemmed).
		$post_keywords = array(); // post_id => array of keywords.
		$post_data     = array(); // post_id => {title, url}.
		$inverted      = array(); // keyword => array of post_ids.
		$stem_surface  = array(); // stemmed_key => best surface form.

		while ( $query->have_posts() ) {
			$query->the_post();
			$pid   = get_the_ID();
			$title = get_the_title();
			$url   = get_permalink();

			$post_data[ $pid ] = array(
				'title' => $title,
				'url'   => $url,
			);

			// Extract top 12 keywords for this post.
			$kw_result = self::extract_keywords( $pid, 12 );
			if ( is_wp_error( $kw_result ) ) {
				continue;
			}

			$keywords = array();
			foreach ( $kw_result as $kw ) {
				$phrase     = strtolower( $kw['keyword'] );
				$stem_key   = implode( ' ', array_map( array( __CLASS__, 'stem' ), explode( ' ', $phrase ) ) );
				$keywords[] = $stem_key;

				// Track the best surface form (prefer higher relevance).
				if ( ! isset( $stem_surface[ $stem_key ] ) || $kw['relevance'] > ( $stem_surface[ $stem_key ]['rel'] ?? 0 ) ) {
					$stem_surface[ $stem_key ] = array( 'form' => $phrase, 'rel' => $kw['relevance'] );
				}

				if ( ! isset( $inverted[ $stem_key ] ) ) {
					$inverted[ $stem_key ] = array();
				}
				$inverted[ $stem_key ][] = $pid;
			}
			$post_keywords[ $pid ] = $keywords;
		}
		wp_reset_postdata();

		// Get existing keyword mappings (stemmed) to check which gaps are already covered.
		$existing = FPP_Interlinking_DB::get_all_keywords();
		$existing_map = array();
		foreach ( $existing as $ek ) {
			$ek_stem = implode( ' ', array_map( array( __CLASS__, 'stem' ), explode( ' ', strtolower( $ek['keyword'] ) ) ) );
			$existing_map[ $ek_stem ] = $ek['target_url'];
		}

		// Find gaps: keywords shared by 2+ posts without existing mappings.
		$gaps       = array();
		$seen_pairs = array();

		foreach ( $inverted as $keyword => $pids ) {
			if ( count( $pids ) < 2 ) {
				continue;
			}

			// Skip if this keyword (stemmed) is already mapped.
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

					// Use surface form for display.
					$display_kw = isset( $stem_surface[ $keyword ] ) ? $stem_surface[ $keyword ]['form'] : $keyword;

					$gaps[] = array(
						'keyword'      => $display_kw,
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

		// Cross-reference with topic clusters: posts in same cluster but not linked = high-priority gap.
		$cluster_data = self::detect_topic_clusters( $post_types );
		$pid_cluster  = array(); // post_id => cluster index.
		if ( ! empty( $cluster_data['clusters'] ) ) {
			foreach ( $cluster_data['clusters'] as $ci => $cluster ) {
				$all_members = array( $cluster['pillar']['id'] );
				foreach ( $cluster['pages'] as $cp ) {
					$all_members[] = $cp['id'];
				}
				foreach ( $all_members as $mid ) {
					$pid_cluster[ $mid ] = $ci;
				}
			}
		}

		// Build a set of existing link pairs for quick lookup.
		$graph      = self::build_link_graph( $post_types );
		$linked_set = array();
		if ( ! empty( $graph['links'] ) ) {
			foreach ( $graph['links'] as $link ) {
				$linked_set[ $link['source'] . '-' . $link['target'] ] = true;
			}
		}

		// Add cluster-based gaps: same cluster but no link between them.
		if ( ! empty( $cluster_data['clusters'] ) ) {
			foreach ( $cluster_data['clusters'] as $cluster ) {
				$members = array( $cluster['pillar']['id'] );
				foreach ( $cluster['pages'] as $cp ) {
					$members[] = $cp['id'];
				}
				for ( $ci = 0; $ci < count( $members ); $ci++ ) {
					for ( $cj = $ci + 1; $cj < count( $members ); $cj++ ) {
						$a = $members[ $ci ];
						$b = $members[ $cj ];

						// Skip if already linked in either direction.
						if ( isset( $linked_set[ $a . '-' . $b ] ) || isset( $linked_set[ $b . '-' . $a ] ) ) {
							continue;
						}

						$pair_key = min( $a, $b ) . '-' . max( $a, $b );
						if ( isset( $seen_pairs[ $pair_key ] ) ) {
							continue;
						}
						$seen_pairs[ $pair_key ] = true;

						if ( isset( $post_data[ $a ] ) && isset( $post_data[ $b ] ) ) {
							$gaps[] = array(
								'keyword'      => implode( ', ', array_slice( $cluster['keywords'], 0, 3 ) ),
								'source_id'    => $a,
								'source_title' => $post_data[ $a ]['title'],
								'target_id'    => $b,
								'target_title' => $post_data[ $b ]['title'],
								'target_url'   => $post_data[ $b ]['url'],
								'confidence'   => 75,
								'reason'       => __( 'Same topic cluster but no link between these posts.', 'fpp-interlinking' ),
							);
						}
					}
				}
			}
		}

		// Boost existing gaps: same cluster (+10) and deep crawl depth (+5).
		$depth_data  = self::calculate_crawl_depth( $post_types );
		$page_depths = array();
		if ( ! is_wp_error( $depth_data ) && ! empty( $depth_data['pages'] ) ) {
			foreach ( $depth_data['pages'] as $dp ) {
				$page_depths[ $dp['id'] ] = $dp['depth'];
			}
		}

		foreach ( $gaps as &$gap ) {
			// Boost if source and target share a cluster.
			if ( isset( $pid_cluster[ $gap['source_id'] ], $pid_cluster[ $gap['target_id'] ] )
				&& $pid_cluster[ $gap['source_id'] ] === $pid_cluster[ $gap['target_id'] ] ) {
				$gap['confidence'] = min( $gap['confidence'] + 10, 98 );
			}

			// Boost if target is at crawl depth > 3.
			if ( isset( $page_depths[ $gap['target_id'] ] ) && $page_depths[ $gap['target_id'] ] > 3 ) {
				$gap['confidence'] = min( $gap['confidence'] + 5, 98 );
			}
		}
		unset( $gap );

		// Sort by confidence descending, limit results.
		usort( $gaps, function( $a, $b ) {
			return $b['confidence'] - $a['confidence'];
		} );
		$gaps = array_slice( $gaps, 0, $max_results );

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

		// Compute ILR scores once for authority-blended ranking (cached via transient).
		$ilr_data   = self::calculate_ilr( $post_types );
		$ilr_scores = array();
		if ( ! is_wp_error( $ilr_data ) && ! empty( $ilr_data['pages'] ) ) {
			foreach ( $ilr_data['pages'] as $p ) {
				$ilr_scores[ $p['id'] ] = $p['ilr'];
			}
		}

		$mappings       = array();
		$used_keywords  = array();

		foreach ( $all_posts as $post_item ) {
			// Extract top 8 keywords for this post.
			$kw_result = self::extract_keywords( $post_item['id'], 8 );
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

				// Blend ILR authority: higher-authority pages get up to +15 bonus.
				foreach ( $scored as &$s ) {
					if ( isset( $ilr_scores[ $s['id'] ] ) ) {
						$ilr_bonus   = round( ( $ilr_scores[ $s['id'] ] / 100 ) * 15 );
						$s['score']  = min( $s['score'] + $ilr_bonus, 100 );
						$s['reason'] .= ' ' . sprintf(
							/* translators: %d: ILR bonus points. */
							__( '+%d ILR authority bonus.', 'fpp-interlinking' ),
							$ilr_bonus
						);
					}
				}
				unset( $s );

				// Re-sort after ILR blending.
				usort( $scored, function( $a, $b ) {
					return $b['score'] - $a['score'];
				} );

				$best = $scored[0];

				// Only propose if score >= 55.
				if ( $best['score'] < 55 ) {
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

		// Limit to 30 proposals.
		$mappings = array_slice( $mappings, 0, 30 );

		return array(
			'mappings'    => $mappings,
			'total_posts' => $total_posts,
			'analysed'    => count( $all_posts ),
			'offset'      => $offset,
		);
	}

	/* ── SEO Content Analysis ────────────────────────────────────────── */

	/**
	 * Perform a deep SEO content analysis on a single post.
	 *
	 * Examines keyword placement across headings, first paragraph, meta
	 * description, image alt text, and link profile. Returns an array of
	 * actionable metrics that feed into the Site Health score.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID to analyse.
	 * @return array|WP_Error {
	 *     'word_count'           => int,
	 *     'heading_keywords'     => string[],
	 *     'first_para_keywords'  => string[],
	 *     'keyword_prominence'   => int (0-100),
	 *     'internal_links'       => int,
	 *     'external_links'       => int,
	 *     'images_total'         => int,
	 *     'images_with_alt'      => int,
	 *     'images_without_alt'   => int,
	 *     'has_meta_description' => bool,
	 *     'meta_keywords'        => string[],
	 * }
	 */
	public static function analyze_seo_content( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'fpp-interlinking' ) );
		}

		$raw_content = $post->post_content;
		$home_host   = wp_parse_url( home_url(), PHP_URL_HOST );

		// ── Word count ──────────────────────────────────────────────
		$clean = self::clean_content( $raw_content );
		$word_count = str_word_count( $clean );

		// ── Headings (H1-H3) ────────────────────────────────────────
		$heading_keywords = array();
		if ( preg_match_all( '/<h[1-3][^>]*>(.*?)<\/h[1-3]>/is', $raw_content, $hm ) ) {
			foreach ( $hm[1] as $heading_html ) {
				$heading_text = strtolower( wp_strip_all_tags( $heading_html ) );
				$tokens       = self::filter_stop_words( self::tokenize( $heading_text ) );
				foreach ( $tokens as $t ) {
					$heading_keywords[] = $t;
				}
			}
		}
		$heading_keywords = array_unique( $heading_keywords );

		// ── First paragraph keywords ────────────────────────────────
		$first_para_keywords = array();
		$words_200 = implode( ' ', array_slice( explode( ' ', $clean ), 0, 200 ) );
		$fp_tokens = self::filter_stop_words( self::tokenize( $words_200 ) );
		// Keep only terms that appear 2+ times in first 200 words.
		$fp_freq = array_count_values( $fp_tokens );
		foreach ( $fp_freq as $term => $cnt ) {
			if ( $cnt >= 2 ) {
				$first_para_keywords[] = $term;
			}
		}

		// ── Keyword prominence score (0-100) ────────────────────────
		// Higher when important keywords appear early and in headings.
		$title_tokens = self::filter_stop_words( self::tokenize( strtolower( $post->post_title ) ) );
		$prominence   = 0;
		if ( ! empty( $title_tokens ) ) {
			// How many title keywords appear in headings?
			$in_headings = count( array_intersect( $title_tokens, $heading_keywords ) );
			$prominence += min( ( $in_headings / count( $title_tokens ) ) * 40, 40 );

			// How many title keywords appear in first paragraph?
			$in_first = count( array_intersect( $title_tokens, array_keys( $fp_freq ) ) );
			$prominence += min( ( $in_first / count( $title_tokens ) ) * 35, 35 );

			// Title length reasonableness (5-12 words = full marks).
			$title_word_count = count( self::tokenize( strtolower( $post->post_title ) ) );
			if ( $title_word_count >= 5 && $title_word_count <= 12 ) {
				$prominence += 25;
			} elseif ( $title_word_count >= 3 ) {
				$prominence += 15;
			} else {
				$prominence += 5;
			}
		}
		$prominence = min( (int) $prominence, 100 );

		// ── Links ───────────────────────────────────────────────────
		$internal_links = 0;
		$external_links = 0;
		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/is', $raw_content, $lm ) ) {
			foreach ( $lm[1] as $href ) {
				$link_host = wp_parse_url( $href, PHP_URL_HOST );
				if ( empty( $link_host ) || $link_host === $home_host ) {
					$internal_links++;
				} else {
					$external_links++;
				}
			}
		}

		// ── Images ──────────────────────────────────────────────────
		$images_total       = 0;
		$images_with_alt    = 0;
		$images_without_alt = 0;
		if ( preg_match_all( '/<img\s[^>]*>/is', $raw_content, $im ) ) {
			$images_total = count( $im[0] );
			foreach ( $im[0] as $img_tag ) {
				if ( preg_match( '/alt=["\']([^"\']+)["\']/i', $img_tag, $am ) && ! empty( trim( $am[1] ) ) ) {
					$images_with_alt++;
				} else {
					$images_without_alt++;
				}
			}
		}

		// ── Meta description ────────────────────────────────────────
		$meta_desc     = '';
		$meta_keywords = array();

		// Check popular SEO plugins.
		$meta_keys = array(
			'_yoast_wpseo_metadesc',
			'rank_math_description',
			'_aioseo_description',
		);
		foreach ( $meta_keys as $mk ) {
			$val = get_post_meta( $post_id, $mk, true );
			if ( ! empty( $val ) ) {
				$meta_desc = $val;
				break;
			}
		}

		// Fallback to excerpt.
		if ( empty( $meta_desc ) && ! empty( $post->post_excerpt ) ) {
			$meta_desc = $post->post_excerpt;
		}

		$has_meta = ! empty( $meta_desc );
		if ( $has_meta ) {
			$meta_tokens   = self::filter_stop_words( self::tokenize( strtolower( $meta_desc ) ) );
			$meta_keywords = array_values( array_unique( $meta_tokens ) );
		}

		return array(
			'word_count'           => $word_count,
			'heading_keywords'     => array_values( $heading_keywords ),
			'first_para_keywords'  => $first_para_keywords,
			'keyword_prominence'   => $prominence,
			'internal_links'       => $internal_links,
			'external_links'       => $external_links,
			'images_total'         => $images_total,
			'images_with_alt'      => $images_with_alt,
			'images_without_alt'   => $images_without_alt,
			'has_meta_description' => $has_meta,
			'meta_keywords'        => $meta_keywords,
		);
	}

	/* ── Site Health Score ────────────────────────────────────────────── */

	/**
	 * Calculate a composite Site Health Score (0-100) with letter grade.
	 *
	 * Weighted signals:
	 *  - Orphan page ratio (25%)
	 *  - Avg links per post (20%)
	 *  - Keyword coverage (20%)
	 *  - Link distribution evenness (15%)
	 *  - Active keyword ratio (10%)
	 *  - Content with headings (10%)
	 *
	 * @since 5.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return array {
	 *     'score'      => int (0-100),
	 *     'grade'      => string (A-F),
	 *     'breakdown'  => array of { signal, score, weight, status, recommendation },
	 * }
	 */
	public static function calculate_site_health( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		$breakdown = array();

		// ── 1. Orphan page ratio (25%) ──────────────────────────────
		$orphan_data      = self::detect_orphan_pages( $post_types );
		$orphan_pct       = $orphan_data['orphan_percentage'];
		$orphan_score     = max( 0, 100 - ( $orphan_pct * 2 ) ); // 0% orphans = 100, 50% = 0.
		$orphan_status    = $orphan_pct <= 10 ? 'good' : ( $orphan_pct <= 30 ? 'warning' : 'critical' );
		$breakdown[]      = array(
			'signal'         => __( 'Orphan Pages', 'fpp-interlinking' ),
			'score'          => (int) $orphan_score,
			'weight'         => 25,
			'status'         => $orphan_status,
			'recommendation' => $orphan_pct > 10
				? sprintf( __( '%d pages have no inbound links. Create keyword mappings to connect them.', 'fpp-interlinking' ), $orphan_data['orphan_count'] )
				: __( 'Great! Most pages are well connected.', 'fpp-interlinking' ),
		);

		// ── 2. Avg links per post (20%) ─────────────────────────────
		$dist_data  = self::analyze_link_distribution( $post_types );
		$avg_links  = ( $dist_data['avg_inbound'] + $dist_data['avg_outbound'] ) / 2;
		// Optimal range: 3-5 links per post.
		if ( $avg_links >= 3 && $avg_links <= 5 ) {
			$links_score = 100;
		} elseif ( $avg_links >= 2 && $avg_links <= 8 ) {
			$links_score = 75;
		} elseif ( $avg_links >= 1 ) {
			$links_score = 50;
		} else {
			$links_score = max( 0, $avg_links * 50 );
		}
		$links_status = $links_score >= 75 ? 'good' : ( $links_score >= 50 ? 'warning' : 'critical' );
		$breakdown[]  = array(
			'signal'         => __( 'Links Per Post', 'fpp-interlinking' ),
			'score'          => (int) $links_score,
			'weight'         => 20,
			'status'         => $links_status,
			'recommendation' => $avg_links < 2
				? __( 'Most posts have very few internal links. Add more keyword mappings.', 'fpp-interlinking' )
				: ( $avg_links > 8
					? __( 'Some posts have too many links. Consider reducing to 3-5 per post.', 'fpp-interlinking' )
					: __( 'Good link density across your content.', 'fpp-interlinking' ) ),
		);

		// ── 3. Keyword coverage (20%) ───────────────────────────────
		$existing_keywords = FPP_Interlinking_DB::get_all_keywords();
		$keyword_count     = count( $existing_keywords );
		$total_pages       = $dist_data['total_pages'];
		$coverage_ratio    = $total_pages > 0 ? min( $keyword_count / $total_pages, 2.0 ) : 0;
		// Target: at least 1 keyword per post.
		$coverage_score    = min( 100, (int) ( $coverage_ratio * 50 ) );
		$coverage_status   = $coverage_score >= 70 ? 'good' : ( $coverage_score >= 40 ? 'warning' : 'critical' );
		$breakdown[]       = array(
			'signal'         => __( 'Keyword Coverage', 'fpp-interlinking' ),
			'score'          => $coverage_score,
			'weight'         => 20,
			'status'         => $coverage_status,
			'recommendation' => $keyword_count < $total_pages
				? sprintf( __( 'You have %d keywords for %d pages. Use Extract Keywords to discover more.', 'fpp-interlinking' ), $keyword_count, $total_pages )
				: __( 'Solid keyword coverage across your content.', 'fpp-interlinking' ),
		);

		// ── 4. Link distribution evenness (15%) ─────────────────────
		$under_pct     = $total_pages > 0 ? ( $dist_data['under_linked'] / $total_pages ) * 100 : 0;
		$over_pct      = $total_pages > 0 ? ( $dist_data['over_linked'] / $total_pages ) * 100 : 0;
		$imbalance     = $under_pct + $over_pct;
		$even_score    = max( 0, 100 - ( $imbalance * 2 ) );
		$even_status   = $even_score >= 70 ? 'good' : ( $even_score >= 40 ? 'warning' : 'critical' );
		$breakdown[]   = array(
			'signal'         => __( 'Link Evenness', 'fpp-interlinking' ),
			'score'          => (int) $even_score,
			'weight'         => 15,
			'status'         => $even_status,
			'recommendation' => $imbalance > 30
				? sprintf( __( '%d under-linked and %d over-linked pages. Redistribute links for better SEO.', 'fpp-interlinking' ), $dist_data['under_linked'], $dist_data['over_linked'] )
				: __( 'Links are well distributed across your site.', 'fpp-interlinking' ),
		);

		// ── 5. Active keyword ratio (10%) ───────────────────────────
		$active_count = 0;
		foreach ( $existing_keywords as $ek ) {
			if ( ! empty( $ek['is_active'] ) ) {
				$active_count++;
			}
		}
		$active_ratio = $keyword_count > 0 ? ( $active_count / $keyword_count ) * 100 : 0;
		$active_score = (int) $active_ratio;
		$active_status = $active_score >= 70 ? 'good' : ( $active_score >= 40 ? 'warning' : 'critical' );
		$breakdown[]   = array(
			'signal'         => __( 'Active Keywords', 'fpp-interlinking' ),
			'score'          => $active_score,
			'weight'         => 10,
			'status'         => $active_status,
			'recommendation' => $active_ratio < 70
				? sprintf( __( 'Only %d%% of keywords are active. Review and enable more.', 'fpp-interlinking' ), (int) $active_ratio )
				: __( 'Most keywords are active and working.', 'fpp-interlinking' ),
		);

		// ── 6. Content with headings (10%) ──────────────────────────
		$with_headings    = 0;
		$sample_size      = min( $total_pages, 50 );
		if ( $sample_size > 0 ) {
			$sample_query = new WP_Query( array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $sample_size,
				'orderby'        => 'rand',
				'fields'         => 'ids',
			) );
			foreach ( $sample_query->posts as $spid ) {
				$sp = get_post( $spid );
				if ( $sp && preg_match( '/<h[1-6][^>]*>/i', $sp->post_content ) ) {
					$with_headings++;
				}
			}
		}
		$heading_pct    = $sample_size > 0 ? ( $with_headings / $sample_size ) * 100 : 0;
		$heading_score  = (int) $heading_pct;
		$heading_status = $heading_score >= 70 ? 'good' : ( $heading_score >= 40 ? 'warning' : 'critical' );
		$breakdown[]    = array(
			'signal'         => __( 'Content Structure', 'fpp-interlinking' ),
			'score'          => $heading_score,
			'weight'         => 10,
			'status'         => $heading_status,
			'recommendation' => $heading_pct < 70
				? sprintf( __( 'Only %d%% of sampled pages use headings. Add H2/H3 tags for better SEO.', 'fpp-interlinking' ), (int) $heading_pct )
				: __( 'Most content uses proper heading structure.', 'fpp-interlinking' ),
		);

		// ── Weighted composite ──────────────────────────────────────
		$total_score = 0;
		foreach ( $breakdown as $item ) {
			$total_score += ( $item['score'] * $item['weight'] ) / 100;
		}
		$total_score = (int) round( $total_score );

		// Letter grade.
		if ( $total_score >= 90 ) {
			$grade = 'A';
		} elseif ( $total_score >= 75 ) {
			$grade = 'B';
		} elseif ( $total_score >= 60 ) {
			$grade = 'C';
		} elseif ( $total_score >= 40 ) {
			$grade = 'D';
		} else {
			$grade = 'F';
		}

		return array(
			'score'     => $total_score,
			'grade'     => $grade,
			'breakdown' => $breakdown,
		);
	}

	/**
	 * Clear all analysis transient caches.
	 *
	 * Called when settings change (e.g. post types updated).
	 *
	 * @since 5.0.0
	 */
	public static function clear_caches() {
		global $wpdb;

		// Delete all fpp_ analysis transients (IDF, link graph, ILR, crawl depth).
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_fpp_idf_%'
			    OR option_name LIKE '_transient_timeout_fpp_idf_%'
			    OR option_name LIKE '_transient_fpp_link_graph_%'
			    OR option_name LIKE '_transient_timeout_fpp_link_graph_%'
			    OR option_name LIKE '_transient_fpp_ilr_%'
			    OR option_name LIKE '_transient_timeout_fpp_ilr_%'
			    OR option_name LIKE '_transient_fpp_crawl_depth_%'
			    OR option_name LIKE '_transient_timeout_fpp_crawl_depth_%'"
		);
	}

	/* ── Link Graph, Orphan Pages & Distribution ────────────────────── */

	/**
	 * Build an internal link graph across all published content.
	 *
	 * Parses every post's raw HTML for `<a href>` tags, normalises URLs
	 * against `home_url()`, and builds inbound / outbound count maps.
	 * Cached as a transient for 6 hours.
	 *
	 * @since 5.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return array {
	 *     'inbound'   => array post_id => count,
	 *     'outbound'  => array post_id => count,
	 *     'post_info' => array post_id => { title, url, type, word_count },
	 *     'total'     => int,
	 * }
	 */
	private static function build_link_graph( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		$cache_key = 'fpp_link_graph_' . md5( implode( ',', $post_types ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$home_url  = home_url();
		$home_host = wp_parse_url( $home_url, PHP_URL_HOST );

		// Build URL → post_id map + post info.
		$url_to_id  = array();
		$post_info  = array();
		$inbound    = array();
		$outbound   = array();

		$page     = 1;
		$per_page = 100;
		$all_ids  = array();

		do {
			$query = new WP_Query( array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'no_found_rows'  => false,
			) );

			if ( ! $query->have_posts() ) {
				break;
			}

			while ( $query->have_posts() ) {
				$query->the_post();
				$pid = get_the_ID();
				$url = get_permalink();

				$all_ids[] = $pid;

				// Normalise URL for lookup.
				$norm = untrailingslashit( strtolower( $url ) );
				$url_to_id[ $norm ] = $pid;

				$post_info[ $pid ] = array(
					'title'      => get_the_title(),
					'url'        => $url,
					'type'       => get_post_type(),
					'word_count' => str_word_count( self::clean_content( get_the_content() ) ),
				);

				$inbound[ $pid ]  = 0;
				$outbound[ $pid ] = 0;
			}

			$page++;
		} while ( $page <= $query->max_num_pages );
		wp_reset_postdata();

		$total = count( $all_ids );

		// v6.0.0: Track individual link pairs for ILR and crawl depth algorithms.
		$links       = array();
		$anchor_data = array(); // v6.0.0: Anchor text tracking for analysis.

		// Second pass: parse links.
		foreach ( $all_ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}

			$raw = $post->post_content;
			if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/isu', $raw, $lm, PREG_SET_ORDER ) ) {
				continue;
			}

			$linked_ids = array(); // Deduplicate per-post.
			foreach ( $lm as $match ) {
				$href        = $match[1];
				$anchor_text = wp_strip_all_tags( $match[2] );
				$link_host   = wp_parse_url( $href, PHP_URL_HOST );

				// Only count internal links.
				if ( ! empty( $link_host ) && $link_host !== $home_host ) {
					continue;
				}

				// Resolve relative URLs.
				if ( empty( $link_host ) ) {
					$href = $home_url . '/' . ltrim( $href, '/' );
				}

				$norm_href = untrailingslashit( strtolower( $href ) );

				// Remove query strings and fragments for matching.
				$norm_href = preg_replace( '/[?#].*$/', '', $norm_href );

				if ( isset( $url_to_id[ $norm_href ] ) ) {
					$target_id = $url_to_id[ $norm_href ];

					// Don't count self-links.
					if ( $target_id === $pid ) {
						continue;
					}

					// v6.0.0: Store anchor text data for anchor analysis.
					$anchor_data[] = array(
						'source'      => $pid,
						'target'      => $target_id,
						'anchor_text' => trim( $anchor_text ),
					);

					if ( ! isset( $linked_ids[ $target_id ] ) ) {
						$linked_ids[ $target_id ] = true;
						$outbound[ $pid ]++;
						$inbound[ $target_id ]++;
						$links[] = array(
							'source' => $pid,
							'target' => $target_id,
						);
					}
				}
			}
		}

		$result = array(
			'inbound'     => $inbound,
			'outbound'    => $outbound,
			'post_info'   => $post_info,
			'total'       => $total,
			'links'       => $links,       // v6.0.0: Directed link pairs.
			'anchor_data' => $anchor_data, // v6.0.0: Anchor text per link.
		);

		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Detect orphan pages — pages with zero inbound internal links.
	 *
	 * @since 5.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return array {
	 *     'orphan_pages'      => array of { id, title, url, type, word_count },
	 *     'total_pages'       => int,
	 *     'orphan_count'      => int,
	 *     'orphan_percentage' => float,
	 * }
	 */
	public static function detect_orphan_pages( $post_types = array() ) {
		$graph   = self::build_link_graph( $post_types );
		$orphans = array();

		foreach ( $graph['inbound'] as $pid => $count ) {
			if ( 0 === $count && isset( $graph['post_info'][ $pid ] ) ) {
				$info      = $graph['post_info'][ $pid ];
				$orphans[] = array(
					'id'         => $pid,
					'title'      => $info['title'],
					'url'        => $info['url'],
					'type'       => $info['type'],
					'word_count' => $info['word_count'],
				);
			}
		}

		// Sort by word count descending (longer orphan pages are higher priority).
		usort( $orphans, function( $a, $b ) {
			return $b['word_count'] - $a['word_count'];
		} );

		// Generate "Link From" suggestions: find pages that could link to each orphan.
		$ilr_data   = self::calculate_ilr( $post_types );
		$ilr_scores = array();
		if ( ! is_wp_error( $ilr_data ) && ! empty( $ilr_data['pages'] ) ) {
			foreach ( $ilr_data['pages'] as $p ) {
				$ilr_scores[ $p['id'] ] = $p['ilr'];
			}
		}

		$orphan_limit = min( count( $orphans ), 20 );
		for ( $oi = 0; $oi < $orphan_limit; $oi++ ) {
			$orphan = &$orphans[ $oi ];
			$orphan['suggestions'] = array();

			// Extract top keyword for this orphan page.
			$kws = self::extract_keywords( $orphan['id'], 1 );
			if ( is_wp_error( $kws ) || empty( $kws ) ) {
				continue;
			}

			$top_keyword = $kws[0]['keyword'];

			// Score non-orphan pages as potential link sources.
			$source_candidates = array();
			foreach ( $graph['post_info'] as $pid => $info ) {
				if ( $pid === $orphan['id'] ) {
					continue;
				}
				$source_candidates[] = array(
					'id'      => $pid,
					'title'   => $info['title'],
					'url'     => $info['url'],
					'excerpt' => '',
				);
			}

			if ( empty( $source_candidates ) ) {
				continue;
			}

			// Limit candidates for performance.
			$source_candidates = array_slice( $source_candidates, 0, 30 );
			$scored = self::score_relevance( $top_keyword, $source_candidates, $post_types );

			// Blend ILR scores: prefer high-authority pages as link sources.
			foreach ( $scored as &$s ) {
				if ( isset( $ilr_scores[ $s['id'] ] ) ) {
					$s['score'] = min( $s['score'] + round( ( $ilr_scores[ $s['id'] ] / 100 ) * 10 ), 100 );
				}
			}
			unset( $s );

			usort( $scored, function( $a, $b ) {
				return $b['score'] - $a['score'];
			} );

			// Take top 3 suggestions.
			foreach ( array_slice( $scored, 0, 3 ) as $suggestion ) {
				if ( $suggestion['score'] < 20 ) {
					break;
				}
				$orphan['suggestions'][] = array(
					'title' => $suggestion['title'],
					'url'   => $suggestion['url'],
				);
			}
		}
		unset( $orphan );

		$total = $graph['total'];
		$count = count( $orphans );

		return array(
			'orphan_pages'      => $orphans,
			'total_pages'       => $total,
			'orphan_count'      => $count,
			'orphan_percentage' => $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Analyse the distribution of internal links across the site.
	 *
	 * Returns per-page inbound/outbound counts plus site-wide averages
	 * and flags pages that are over-linked or under-linked.
	 *
	 * @since 5.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return array {
	 *     'pages'          => array of { id, title, url, type, inbound, outbound, status },
	 *     'avg_inbound'    => float,
	 *     'avg_outbound'   => float,
	 *     'total_pages'    => int,
	 *     'over_linked'    => int,
	 *     'under_linked'   => int,
	 *     'well_linked'    => int,
	 * }
	 */
	public static function analyze_link_distribution( $post_types = array() ) {
		$graph = self::build_link_graph( $post_types );
		$total = $graph['total'];

		if ( 0 === $total ) {
			return array(
				'pages'        => array(),
				'avg_inbound'  => 0,
				'avg_outbound' => 0,
				'total_pages'  => 0,
				'over_linked'  => 0,
				'under_linked' => 0,
				'well_linked'  => 0,
			);
		}

		$sum_in  = array_sum( $graph['inbound'] );
		$sum_out = array_sum( $graph['outbound'] );
		$avg_in  = $sum_in / $total;
		$avg_out = $sum_out / $total;

		$pages        = array();
		$over_linked  = 0;
		$under_linked = 0;
		$well_linked  = 0;

		foreach ( $graph['post_info'] as $pid => $info ) {
			$in  = isset( $graph['inbound'][ $pid ] ) ? $graph['inbound'][ $pid ] : 0;
			$out = isset( $graph['outbound'][ $pid ] ) ? $graph['outbound'][ $pid ] : 0;

			// Determine status based on deviation from average.
			if ( $avg_in > 0 && $in > $avg_in * 2 ) {
				$status = 'over-linked';
				$over_linked++;
			} elseif ( $in < max( $avg_in * 0.25, 1 ) ) {
				$status = 'under-linked';
				$under_linked++;
			} else {
				$status = 'normal';
				$well_linked++;
			}

			$pages[] = array(
				'id'       => $pid,
				'title'    => $info['title'],
				'url'      => $info['url'],
				'type'     => $info['type'],
				'inbound'  => $in,
				'outbound' => $out,
				'status'   => $status,
			);
		}

		// Sort: under-linked first, then by inbound ascending.
		usort( $pages, function( $a, $b ) {
			$order = array( 'under-linked' => 0, 'normal' => 1, 'over-linked' => 2 );
			$sa    = isset( $order[ $a['status'] ] ) ? $order[ $a['status'] ] : 1;
			$sb    = isset( $order[ $b['status'] ] ) ? $order[ $b['status'] ] : 1;
			if ( $sa !== $sb ) {
				return $sa - $sb;
			}
			return $a['inbound'] - $b['inbound'];
		} );

		return array(
			'pages'        => $pages,
			'avg_inbound'  => round( $avg_in, 1 ),
			'avg_outbound' => round( $avg_out, 1 ),
			'total_pages'  => $total,
			'over_linked'  => $over_linked,
			'under_linked' => $under_linked,
			'well_linked'  => $well_linked,
		);
	}

	/* ── v6.0.0: Deep Linking Intelligence ───────────────────────────── */

	/**
	 * Calculate Internal Link Rank (ILR) for all pages.
	 *
	 * Implements a simplified PageRank algorithm that distributes link
	 * equity through the site graph. Pages are classified as strong
	 * (top 30%), medium (middle 40%), or weak (bottom 30%) based on
	 * their computed ILR score.
	 *
	 * Per Semrush best practices: link FROM strong pages TO weak pages
	 * with business value to distribute authority effectively.
	 *
	 * @since 6.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return array {
	 *     'pages'          => array[] Per-page ILR data,
	 *     'total_pages'    => int,
	 *     'strong_count'   => int,
	 *     'medium_count'   => int,
	 *     'weak_count'     => int,
	 *     'recommendations' => array[] Strategic linking suggestions,
	 * }
	 */
	public static function calculate_ilr( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		$cache_key = 'fpp_ilr_' . md5( implode( ',', $post_types ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$graph = self::build_link_graph( $post_types );
		$total = count( $graph['post_info'] );

		if ( 0 === $total ) {
			return array(
				'pages'           => array(),
				'total_pages'     => 0,
				'strong_count'    => 0,
				'medium_count'    => 0,
				'weak_count'      => 0,
				'recommendations' => array(),
			);
		}

		$post_ids = array_keys( $graph['post_info'] );
		$damping  = 0.85;

		// Initialize ILR scores equally.
		$ilr     = array();
		$initial = 100.0 / $total;
		foreach ( $post_ids as $pid ) {
			$ilr[ $pid ] = $initial;
		}

		// Build outbound link map: source_pid => array of target_pids.
		$outlinks = array();
		foreach ( $post_ids as $pid ) {
			$outlinks[ $pid ] = array();
		}
		if ( ! empty( $graph['links'] ) ) {
			foreach ( $graph['links'] as $link ) {
				$src = $link['source'];
				$tgt = $link['target'];
				if ( isset( $outlinks[ $src ] ) ) {
					$outlinks[ $src ][] = $tgt;
				}
			}
		}

		// Build inbound link map: target_pid => array of source_pids.
		$inlinks = array();
		foreach ( $post_ids as $pid ) {
			$inlinks[ $pid ] = array();
		}
		foreach ( $outlinks as $src => $targets ) {
			foreach ( $targets as $tgt ) {
				if ( isset( $inlinks[ $tgt ] ) ) {
					$inlinks[ $tgt ][] = $src;
				}
			}
		}

		// Iterative PageRank-lite (10 iterations).
		for ( $iter = 0; $iter < 10; $iter++ ) {
			$new_ilr = array();
			foreach ( $post_ids as $pid ) {
				$rank = ( 1 - $damping ) / $total * 100;
				foreach ( $inlinks[ $pid ] as $src ) {
					$out_count = count( $outlinks[ $src ] );
					if ( $out_count > 0 ) {
						$rank += $damping * ( $ilr[ $src ] / $out_count );
					}
				}
				$new_ilr[ $pid ] = $rank;
			}
			$ilr = $new_ilr;
		}

		// Normalize to 0-100 scale.
		$max_ilr = max( $ilr );
		$min_ilr = min( $ilr );
		$range   = $max_ilr - $min_ilr;
		if ( $range > 0 ) {
			foreach ( $ilr as $pid => $score ) {
				$ilr[ $pid ] = ( ( $score - $min_ilr ) / $range ) * 100;
			}
		}

		// Classify: determine thresholds using percentiles.
		$sorted_scores = array_values( $ilr );
		sort( $sorted_scores );
		$p30 = $sorted_scores[ (int) floor( $total * 0.3 ) ];
		$p70 = $sorted_scores[ (int) floor( $total * 0.7 ) ];

		$pages        = array();
		$strong       = array();
		$weak         = array();
		$strong_count = 0;
		$medium_count = 0;
		$weak_count   = 0;

		foreach ( $graph['post_info'] as $pid => $info ) {
			$score = round( $ilr[ $pid ], 1 );
			if ( $score > $p70 ) {
				$class = 'strong';
				$strong_count++;
				$strong[] = $pid;
			} elseif ( $score <= $p30 ) {
				$class = 'weak';
				$weak_count++;
				$weak[] = $pid;
			} else {
				$class = 'medium';
				$medium_count++;
			}

			$pages[] = array(
				'id'       => $pid,
				'title'    => $info['title'],
				'url'      => $info['url'],
				'type'     => $info['type'],
				'ilr'      => $score,
				'class'    => $class,
				'inbound'  => isset( $graph['inbound'][ $pid ] ) ? $graph['inbound'][ $pid ] : 0,
				'outbound' => isset( $graph['outbound'][ $pid ] ) ? $graph['outbound'][ $pid ] : 0,
			);
		}

		// Sort by ILR descending.
		usort( $pages, function( $a, $b ) {
			return $b['ilr'] <=> $a['ilr'];
		} );

		// Generate recommendations: link FROM strong TO weak.
		$recommendations = array();
		$max_recs        = min( 10, count( $strong ), count( $weak ) );
		for ( $i = 0; $i < $max_recs; $i++ ) {
			$s_pid  = $strong[ $i ];
			$w_pid  = $weak[ $i ];
			$s_info = $graph['post_info'][ $s_pid ];
			$w_info = $graph['post_info'][ $w_pid ];

			$recommendations[] = array(
				'from_title' => $s_info['title'],
				'from_url'   => $s_info['url'],
				'from_ilr'   => round( $ilr[ $s_pid ], 1 ),
				'to_title'   => $w_info['title'],
				'to_url'     => $w_info['url'],
				'to_ilr'     => round( $ilr[ $w_pid ], 1 ),
			);
		}

		$result = array(
			'pages'           => $pages,
			'total_pages'     => $total,
			'strong_count'    => $strong_count,
			'medium_count'    => $medium_count,
			'weak_count'      => $weak_count,
			'recommendations' => $recommendations,
		);

		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Calculate crawl depth for all pages via BFS from homepage.
	 *
	 * Per Google's link best practices: every page should be reachable
	 * from at least one other page. Pages deeper than 3 clicks from the
	 * homepage are harder for search engines to discover and index.
	 *
	 * @since 6.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return array {
	 *     'pages'             => array[] Per-page depth data,
	 *     'unreachable'       => array[] Pages not reachable from homepage,
	 *     'depth_distribution' => array  depth_level => count,
	 *     'max_depth'         => int,
	 *     'avg_depth'         => float,
	 *     'total_pages'       => int,
	 * }
	 */
	public static function calculate_crawl_depth( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		$cache_key = 'fpp_crawl_depth_' . md5( implode( ',', $post_types ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$graph = self::build_link_graph( $post_types );
		$total = count( $graph['post_info'] );

		if ( 0 === $total ) {
			return array(
				'pages'              => array(),
				'unreachable'        => array(),
				'depth_distribution' => array(),
				'max_depth'          => 0,
				'avg_depth'          => 0,
				'total_pages'        => 0,
			);
		}

		// Find the homepage post ID.
		$front_page_id = (int) get_option( 'page_on_front', 0 );
		$blog_page_id  = (int) get_option( 'page_for_posts', 0 );
		$home_url      = home_url( '/' );

		// Try to find a post matching the homepage URL.
		$start_pid = 0;
		if ( $front_page_id && isset( $graph['post_info'][ $front_page_id ] ) ) {
			$start_pid = $front_page_id;
		} elseif ( $blog_page_id && isset( $graph['post_info'][ $blog_page_id ] ) ) {
			$start_pid = $blog_page_id;
		} else {
			// Fall back to the page with most outbound links.
			$max_out = -1;
			foreach ( $graph['post_info'] as $pid => $info ) {
				$out = isset( $graph['outbound'][ $pid ] ) ? $graph['outbound'][ $pid ] : 0;
				if ( $out > $max_out ) {
					$max_out   = $out;
					$start_pid = $pid;
				}
			}
		}

		// Build outbound adjacency list.
		$adj = array();
		foreach ( array_keys( $graph['post_info'] ) as $pid ) {
			$adj[ $pid ] = array();
		}
		if ( ! empty( $graph['links'] ) ) {
			foreach ( $graph['links'] as $link ) {
				if ( isset( $adj[ $link['source'] ] ) ) {
					$adj[ $link['source'] ][] = $link['target'];
				}
			}
		}

		// BFS from start.
		$depth   = array();
		$visited = array();
		$queue   = array( array( $start_pid, 0 ) );
		$visited[ $start_pid ] = true;
		$depth[ $start_pid ]   = 0;

		while ( ! empty( $queue ) ) {
			list( $current, $d ) = array_shift( $queue );
			if ( isset( $adj[ $current ] ) ) {
				foreach ( $adj[ $current ] as $neighbor ) {
					if ( ! isset( $visited[ $neighbor ] ) ) {
						$visited[ $neighbor ] = true;
						$depth[ $neighbor ]   = $d + 1;
						$queue[]              = array( $neighbor, $d + 1 );
					}
				}
			}
		}

		// Build results.
		$pages         = array();
		$unreachable   = array();
		$distribution  = array();
		$total_depth   = 0;
		$reachable_cnt = 0;
		$max_depth     = 0;

		foreach ( $graph['post_info'] as $pid => $info ) {
			if ( isset( $depth[ $pid ] ) ) {
				$d = $depth[ $pid ];
				$pages[] = array(
					'id'    => $pid,
					'title' => $info['title'],
					'url'   => $info['url'],
					'type'  => $info['type'],
					'depth' => $d,
				);
				if ( ! isset( $distribution[ $d ] ) ) {
					$distribution[ $d ] = 0;
				}
				$distribution[ $d ]++;
				$total_depth += $d;
				$reachable_cnt++;
				if ( $d > $max_depth ) {
					$max_depth = $d;
				}
			} else {
				$unreachable[] = array(
					'id'    => $pid,
					'title' => $info['title'],
					'url'   => $info['url'],
					'type'  => $info['type'],
				);
			}
		}

		// Sort pages by depth ascending.
		usort( $pages, function( $a, $b ) {
			return $a['depth'] - $b['depth'];
		} );

		ksort( $distribution );

		$result = array(
			'pages'              => $pages,
			'unreachable'        => $unreachable,
			'depth_distribution' => $distribution,
			'max_depth'          => $max_depth,
			'avg_depth'          => $reachable_cnt > 0 ? round( $total_depth / $reachable_cnt, 1 ) : 0,
			'total_pages'        => $total,
		);

		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Get total published post count for configured post types.
	 *
	 * Used for progress calculation in batch operations.
	 *
	 * @since 6.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return int Total count.
	 */
	public static function get_total_post_count( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		$query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		) );

		return (int) $query->found_posts;
	}

	/**
	 * Analyse anchor text quality across all internal links.
	 *
	 * Per Google's link best practices: anchor text should be descriptive,
	 * concise, and tell users and search engines about the linked page.
	 * Avoid generic phrases like "click here" or "read more".
	 *
	 * @since 6.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return array {
	 *     'links'            => array[] Per-link anchor data with quality,
	 *     'overall_quality'  => float   0-100 quality percentage,
	 *     'total_links'      => int,
	 *     'good_count'       => int,
	 *     'warning_count'    => int,
	 *     'poor_count'       => int,
	 * }
	 */
	public static function analyze_anchor_text( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		$graph = self::build_link_graph( $post_types );

		// Generic anchor patterns to flag.
		$generic_anchors = array(
			'click here', 'read more', 'learn more', 'this article',
			'this post', 'this page', 'here', 'link', 'website', 'page',
			'more info', 'more information', 'find out more', 'see more',
			'check it out', 'go here', 'visit', 'continue reading',
			'full article', 'source', 'read on', 'details',
		);

		$links       = array();
		$good_count  = 0;
		$warn_count  = 0;
		$poor_count  = 0;
		$kw_cache    = array(); // Cache keyword lookups per target ID.

		if ( ! empty( $graph['anchor_data'] ) ) {
			foreach ( $graph['anchor_data'] as $ad ) {
				$anchor = trim( $ad['anchor_text'] );
				$src    = $ad['source'];
				$tgt    = $ad['target'];

				$src_info = isset( $graph['post_info'][ $src ] ) ? $graph['post_info'][ $src ] : null;
				$tgt_info = isset( $graph['post_info'][ $tgt ] ) ? $graph['post_info'][ $tgt ] : null;

				if ( ! $src_info || ! $tgt_info ) {
					continue;
				}

				// Evaluate anchor quality.
				$quality    = 'good';
				$suggestion = '';
				$lower      = mb_strtolower( $anchor, 'UTF-8' );

				if ( empty( $anchor ) ) {
					$quality    = 'poor';
					$suggestion = __( 'Empty anchor text — add descriptive text about the linked page.', 'fpp-interlinking' );
				} elseif ( in_array( $lower, $generic_anchors, true ) ) {
					$quality    = 'poor';
					$suggestion = sprintf(
						__( 'Generic anchor "%s" — use descriptive text related to the target page.', 'fpp-interlinking' ),
						$anchor
					);
				} elseif ( mb_strlen( $anchor, 'UTF-8' ) > 60 ) {
					$quality    = 'warning';
					$suggestion = __( 'Anchor text is too long — keep it under 5 words for best readability.', 'fpp-interlinking' );
				} elseif ( mb_strlen( $anchor, 'UTF-8' ) < 3 ) {
					$quality    = 'warning';
					$suggestion = __( 'Anchor text is very short — add more descriptive context.', 'fpp-interlinking' );
				}

				// For poor/warning anchors, suggest the target page's top keyword.
				if ( 'good' !== $quality ) {
					if ( ! isset( $kw_cache[ $tgt ] ) ) {
						$tgt_kws = self::extract_keywords( $tgt, 1 );
						$kw_cache[ $tgt ] = ( ! is_wp_error( $tgt_kws ) && ! empty( $tgt_kws ) ) ? $tgt_kws[0]['keyword'] : '';
					}
					if ( ! empty( $kw_cache[ $tgt ] ) ) {
						$suggestion .= ' ' . sprintf(
							/* translators: %s: suggested keyword to use as anchor text. */
							__( 'Try: "%s"', 'fpp-interlinking' ),
							$kw_cache[ $tgt ]
						);
					}
				}

				if ( 'good' === $quality ) {
					$good_count++;
				} elseif ( 'warning' === $quality ) {
					$warn_count++;
				} else {
					$poor_count++;
				}

				$links[] = array(
					'source_title' => $src_info['title'],
					'source_url'   => $src_info['url'],
					'target_title' => $tgt_info['title'],
					'target_url'   => $tgt_info['url'],
					'anchor_text'  => $anchor,
					'quality'      => $quality,
					'suggestion'   => $suggestion,
				);
			}
		}

		$total = count( $links );

		// Sort: poor first, then warning, then good.
		usort( $links, function( $a, $b ) {
			$order = array( 'poor' => 0, 'warning' => 1, 'good' => 2 );
			return ( $order[ $a['quality'] ] ?? 2 ) - ( $order[ $b['quality'] ] ?? 2 );
		} );

		return array(
			'links'           => array_slice( $links, 0, 100 ), // Limit to 100 for UI.
			'overall_quality' => $total > 0 ? round( ( $good_count / $total ) * 100, 1 ) : 100,
			'total_links'     => $total,
			'good_count'      => $good_count,
			'warning_count'   => $warn_count,
			'poor_count'      => $poor_count,
		);
	}

	/**
	 * Detect topic clusters — groups of related content.
	 *
	 * Uses TF-IDF keywords to find posts sharing 3+ keywords, groups
	 * them into clusters using union-find, and identifies the pillar page
	 * (most inbound links) per cluster.
	 *
	 * Per Semrush best practices: interconnected topic clusters signal
	 * topical expertise and improve E-E-A-T signals.
	 *
	 * @since 6.0.0
	 *
	 * @param array $post_types Post type slugs.
	 * @return array {
	 *     'clusters'       => array[] Each with 'pillar', 'pages', 'keywords',
	 *     'total_clusters' => int,
	 *     'clustered_pages' => int,
	 *     'unclustered_pages' => int,
	 * }
	 */
	public static function detect_topic_clusters( $post_types = array() ) {
		if ( empty( $post_types ) ) {
			$post_types = FPP_Interlinking_DB::get_configured_post_types();
		}

		// Fetch posts.
		$posts = get_posts( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 200, // Limit for performance.
			'fields'         => 'ids',
		) );

		if ( count( $posts ) < 2 ) {
			return array(
				'clusters'          => array(),
				'total_clusters'    => 0,
				'clustered_pages'   => 0,
				'unclustered_pages' => count( $posts ),
			);
		}

		// Extract top 5 keywords per post.
		$post_keywords = array();
		foreach ( $posts as $pid ) {
			$kws = self::extract_keywords( $pid, 5 );
			if ( ! empty( $kws ) ) {
				$post_keywords[ $pid ] = array_column( $kws, 'keyword' );
			}
		}

		// Build co-occurrence: find post pairs sharing 3+ keywords.
		$pairs     = array();
		$post_ids  = array_keys( $post_keywords );
		$count_ids = count( $post_ids );

		for ( $i = 0; $i < $count_ids; $i++ ) {
			for ( $j = $i + 1; $j < $count_ids; $j++ ) {
				$a = $post_ids[ $i ];
				$b = $post_ids[ $j ];

				$shared = array_intersect(
					array_map( function( $k ) { return mb_strtolower( $k, 'UTF-8' ); }, $post_keywords[ $a ] ),
					array_map( function( $k ) { return mb_strtolower( $k, 'UTF-8' ); }, $post_keywords[ $b ] )
				);

				if ( count( $shared ) >= 2 ) { // 2+ shared keywords = related.
					$pairs[] = array( $a, $b, array_values( $shared ) );
				}
			}
		}

		// Union-Find to group connected posts.
		$parent = array();
		foreach ( $post_ids as $pid ) {
			$parent[ $pid ] = $pid;
		}

		$find = function( $x ) use ( &$parent, &$find ) {
			if ( $parent[ $x ] !== $x ) {
				$parent[ $x ] = $find( $parent[ $x ] );
			}
			return $parent[ $x ];
		};

		$union = function( $x, $y ) use ( &$parent, &$find ) {
			$rx = $find( $x );
			$ry = $find( $y );
			if ( $rx !== $ry ) {
				$parent[ $rx ] = $ry;
			}
		};

		$cluster_keywords = array(); // Track keywords per cluster.
		foreach ( $pairs as $pair ) {
			$union( $pair[0], $pair[1] );
		}

		// Group posts by cluster root.
		$groups = array();
		foreach ( $post_ids as $pid ) {
			$root = $find( $pid );
			if ( ! isset( $groups[ $root ] ) ) {
				$groups[ $root ] = array();
			}
			$groups[ $root ][] = $pid;
		}

		// Collect shared keywords per cluster.
		foreach ( $pairs as $pair ) {
			$root = $find( $pair[0] );
			if ( ! isset( $cluster_keywords[ $root ] ) ) {
				$cluster_keywords[ $root ] = array();
			}
			foreach ( $pair[2] as $kw ) {
				$cluster_keywords[ $root ][ $kw ] = true;
			}
		}

		// Build link graph for pillar detection.
		$graph = self::build_link_graph( $post_types );

		// Filter clusters with 2+ posts and identify pillars.
		$clusters        = array();
		$clustered_count = 0;

		foreach ( $groups as $root => $members ) {
			if ( count( $members ) < 2 ) {
				continue;
			}

			// Find pillar page: most inbound links within cluster.
			$pillar_id  = $members[0];
			$max_inbound = -1;
			foreach ( $members as $pid ) {
				$in = isset( $graph['inbound'][ $pid ] ) ? $graph['inbound'][ $pid ] : 0;
				if ( $in > $max_inbound ) {
					$max_inbound = $in;
					$pillar_id   = $pid;
				}
			}

			$pillar_info = isset( $graph['post_info'][ $pillar_id ] ) ? $graph['post_info'][ $pillar_id ] : null;
			if ( ! $pillar_info ) {
				continue;
			}

			$pages = array();
			foreach ( $members as $pid ) {
				if ( $pid === $pillar_id ) {
					continue;
				}
				$info = isset( $graph['post_info'][ $pid ] ) ? $graph['post_info'][ $pid ] : null;
				if ( $info ) {
					$pages[] = array(
						'id'    => $pid,
						'title' => $info['title'],
						'url'   => $info['url'],
						'type'  => $info['type'],
					);
				}
			}

			$kws = isset( $cluster_keywords[ $root ] ) ? array_keys( $cluster_keywords[ $root ] ) : array();

			$clusters[] = array(
				'pillar' => array(
					'id'    => $pillar_id,
					'title' => $pillar_info['title'],
					'url'   => $pillar_info['url'],
					'type'  => $pillar_info['type'],
				),
				'pages'    => $pages,
				'keywords' => array_slice( $kws, 0, 10 ),
				'size'     => count( $members ),
			);

			$clustered_count += count( $members );
		}

		// Sort by cluster size descending.
		usort( $clusters, function( $a, $b ) {
			return $b['size'] - $a['size'];
		} );

		return array(
			'clusters'          => $clusters,
			'total_clusters'    => count( $clusters ),
			'clustered_pages'   => $clustered_count,
			'unclustered_pages' => count( $posts ) - $clustered_count,
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

		// Collapse whitespace (Unicode-safe).
		$content = preg_replace( '/\s+/u', ' ', $content );
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
		// v6.0.0: Use mb_strtolower for proper Unicode lowercase (ö, ü, ä, é, ñ).
		$text = mb_strtolower( $text, 'UTF-8' );

		// v6.0.0: Use Unicode-aware character classes so accented chars (ö, ü, ä, é)
		// are treated as word characters. \p{L} = Unicode letter, \p{N} = Unicode number.
		$text = preg_replace( '/[^\p{L}\p{N}\s\'-]/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );

		$tokens = explode( ' ', trim( $text ) );

		// Filter out purely numeric tokens and very short tokens.
		// v6.0.0: Use mb_strlen for proper multi-byte character counting.
		return array_values( array_filter( $tokens, function( $t ) {
			return mb_strlen( $t, 'UTF-8' ) >= 2 && ! is_numeric( $t );
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

	/* ── Porter Stemmer ──────────────────────────────────────────────── */

	/**
	 * Simplified Porter stemmer with memoization cache.
	 *
	 * Reduces English words to their root form so that "running", "runs",
	 * and "run" all map to the same stem. This dramatically improves
	 * keyword matching and TF-IDF accuracy.
	 *
	 * @since 5.0.0
	 *
	 * @param string $word Lowercase word.
	 * @return string Stemmed word.
	 */
	private static function stem( $word ) {
		static $cache = array();

		if ( isset( $cache[ $word ] ) ) {
			return $cache[ $word ];
		}

		$stem = $word;

		// Don't stem short words.
		if ( mb_strlen( $stem, 'UTF-8' ) <= 3 ) {
			$cache[ $word ] = $stem;
			return $stem;
		}

		// v6.0.0: Skip stemming for non-ASCII words (Göring, naïve, café, etc.).
		// The Porter stemmer is English-only and would corrupt multi-byte chars.
		if ( preg_match( '/[^\x00-\x7F]/', $stem ) ) {
			$cache[ $word ] = $stem;
			return $stem;
		}

		// Step 1a: plurals.
		if ( substr( $stem, -4 ) === 'sses' ) {
			$stem = substr( $stem, 0, -2 );
		} elseif ( substr( $stem, -3 ) === 'ies' ) {
			$stem = substr( $stem, 0, -2 );
		} elseif ( substr( $stem, -2 ) !== 'ss' && substr( $stem, -1 ) === 's' ) {
			$stem = substr( $stem, 0, -1 );
		}

		// Step 1b: -eed, -ed, -ing.
		if ( substr( $stem, -3 ) === 'eed' ) {
			if ( self::stem_measure( substr( $stem, 0, -3 ) ) > 0 ) {
				$stem = substr( $stem, 0, -1 );
			}
		} elseif ( preg_match( '/^(.+?)(ed|ing)$/i', $stem, $m ) && preg_match( '/[aeiou]/', $m[1] ) ) {
			$stem = $m[1];
			if ( preg_match( '/(at|bl|iz)$/', $stem ) ) {
				$stem .= 'e';
			} elseif ( preg_match( '/([^aeiouslz])\1$/', $stem ) ) {
				$stem = substr( $stem, 0, -1 );
			} elseif ( self::stem_measure( $stem ) === 1 && self::stem_cvc( $stem ) ) {
				$stem .= 'e';
			}
		}

		// Step 1c: y → i when stem contains a vowel.
		if ( substr( $stem, -1 ) === 'y' && preg_match( '/[aeiou]/', substr( $stem, 0, -1 ) ) ) {
			$stem = substr( $stem, 0, -1 ) . 'i';
		}

		// Step 2: double-suffix removal (m > 0).
		$step2 = array(
			'ational' => 'ate',  'tional'  => 'tion', 'enci'    => 'ence',
			'anci'    => 'ance', 'izer'    => 'ize',  'abli'    => 'able',
			'alli'    => 'al',   'entli'   => 'ent',  'eli'     => 'e',
			'ousli'   => 'ous',  'ization' => 'ize',  'ation'   => 'ate',
			'ator'    => 'ate',  'alism'   => 'al',   'iveness' => 'ive',
			'fulness' => 'ful',  'ousness' => 'ous',  'aliti'   => 'al',
			'iviti'   => 'ive',  'biliti'  => 'ble',
		);
		foreach ( $step2 as $suffix => $replacement ) {
			if ( substr( $stem, -strlen( $suffix ) ) === $suffix ) {
				$base = substr( $stem, 0, -strlen( $suffix ) );
				if ( self::stem_measure( $base ) > 0 ) {
					$stem = $base . $replacement;
				}
				break;
			}
		}

		// Step 3: suffix removal (m > 0).
		$step3 = array(
			'icate' => 'ic', 'ative' => '', 'alize' => 'al',
			'iciti' => 'ic', 'ical'  => 'ic', 'ful' => '', 'ness' => '',
		);
		foreach ( $step3 as $suffix => $replacement ) {
			if ( substr( $stem, -strlen( $suffix ) ) === $suffix ) {
				$base = substr( $stem, 0, -strlen( $suffix ) );
				if ( self::stem_measure( $base ) > 0 ) {
					$stem = $base . $replacement;
				}
				break;
			}
		}

		// Step 4: final suffix removal (m > 1).
		$step4 = array(
			'al', 'ance', 'ence', 'er', 'ic', 'able', 'ible', 'ant',
			'ement', 'ment', 'ent', 'ion', 'ou', 'ism', 'ate', 'iti',
			'ous', 'ive', 'ize',
		);
		foreach ( $step4 as $suffix ) {
			if ( substr( $stem, -strlen( $suffix ) ) === $suffix ) {
				$base = substr( $stem, 0, -strlen( $suffix ) );
				if ( 'ion' === $suffix ) {
					if ( self::stem_measure( $base ) > 1 && preg_match( '/(s|t)$/', $base ) ) {
						$stem = $base;
					}
				} elseif ( self::stem_measure( $base ) > 1 ) {
					$stem = $base;
				}
				break;
			}
		}

		// Step 5a: remove trailing 'e'.
		if ( substr( $stem, -1 ) === 'e' ) {
			$base = substr( $stem, 0, -1 );
			if ( self::stem_measure( $base ) > 1 || ( self::stem_measure( $base ) === 1 && ! self::stem_cvc( $base ) ) ) {
				$stem = $base;
			}
		}

		// Step 5b: double-l.
		if ( substr( $stem, -2 ) === 'll' && self::stem_measure( $stem ) > 1 ) {
			$stem = substr( $stem, 0, -1 );
		}

		$cache[ $word ] = $stem;
		return $stem;
	}

	/**
	 * Count the "measure" (m) of a stem — number of VC sequences.
	 *
	 * @since 5.0.0
	 *
	 * @param string $str Stem string.
	 * @return int Measure value.
	 */
	private static function stem_measure( $str ) {
		$str = preg_replace( '/^[^aeiou]+/', '', $str );
		$str = preg_replace( '/[^aeiou]+$/', '', $str );
		if ( empty( $str ) ) {
			return 0;
		}
		preg_match_all( '/[aeiou]+[^aeiou]+/', $str, $m );
		return count( $m[0] );
	}

	/**
	 * Check if stem ends with consonant-vowel-consonant (not w, x, y).
	 *
	 * @since 5.0.0
	 *
	 * @param string $str Stem string.
	 * @return bool
	 */
	private static function stem_cvc( $str ) {
		$len = strlen( $str );
		if ( $len < 3 ) {
			return false;
		}
		$c3 = $str[ $len - 1 ];
		$c2 = $str[ $len - 2 ];
		$c1 = $str[ $len - 3 ];
		if ( in_array( $c3, array( 'w', 'x', 'y' ), true ) ) {
			return false;
		}
		$vowels = array( 'a', 'e', 'i', 'o', 'u' );
		return ! in_array( $c3, $vowels, true ) && in_array( $c2, $vowels, true ) && ! in_array( $c1, $vowels, true );
	}

	/**
	 * Stem an array of tokens and track the most common surface form per stem.
	 *
	 * Returns both the stemmed token list and a mapping from each stem back
	 * to the most-frequently-seen original word form.
	 *
	 * @since 5.0.0
	 *
	 * @param string[] $tokens Lowercase word tokens.
	 * @return array {
	 *     'stems'       => string[] Stemmed tokens (same order/length as input),
	 *     'surface_map' => array     stem => most-common original form,
	 * }
	 */
	private static function stem_tokens( $tokens ) {
		$stemmed         = array();
		$stem_to_surface = array();

		foreach ( $tokens as $token ) {
			$stem = self::stem( $token );

			if ( ! isset( $stem_to_surface[ $stem ] ) ) {
				$stem_to_surface[ $stem ] = array();
			}
			if ( ! isset( $stem_to_surface[ $stem ][ $token ] ) ) {
				$stem_to_surface[ $stem ][ $token ] = 0;
			}
			$stem_to_surface[ $stem ][ $token ]++;
			$stemmed[] = $stem;
		}

		// Map each stem to its most common surface form.
		$surface_map = array();
		foreach ( $stem_to_surface as $stem => $forms ) {
			arsort( $forms );
			$surface_map[ $stem ] = key( $forms );
		}

		return array(
			'stems'       => $stemmed,
			'surface_map' => $surface_map,
		);
	}
}
