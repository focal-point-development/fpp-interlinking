<?php
/**
 * Front-end content filter that replaces configured keywords with links.
 *
 * Hooks into `the_content` at priority 999 so that other plugins can finish
 * processing the content first. Keyword matching is performed only on plain
 * text segments – existing links, script/style/code/pre/textarea blocks, and
 * HTML tags are left untouched.
 *
 * Security: output is escaped late using esc_url() and esc_html().
 *
 * @since   1.0.0
 * @package FPP_Interlinking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Replacer {

	/**
	 * Register the content filter.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'replace_keywords' ), 999 );
	}

	/**
	 * Main filter callback.
	 *
	 * Iterates over active keywords (longest first to prevent partial matches)
	 * and replaces plain-text occurrences with anchor links.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $content Post content.
	 * @return string Modified content.
	 */
	public function replace_keywords( $content ) {
		// Bail early in contexts where replacement is not desired.
		if ( is_admin() || is_feed() || empty( $content ) ) {
			return $content;
		}

		// Skip REST API and AJAX requests.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $content;
		}
		if ( wp_doing_ajax() ) {
			return $content;
		}

		// Skip excluded posts.
		$excluded = $this->get_excluded_post_ids();
		$post_id  = get_the_ID();
		if ( $post_id && in_array( $post_id, $excluded, true ) ) {
			return $content;
		}

		$keywords = $this->get_cached_keywords();
		if ( empty( $keywords ) ) {
			return $content;
		}

		// Resolve the current post's permalink once for self-link prevention.
		$current_url = ( $post_id ) ? get_permalink( $post_id ) : '';

		$global_max      = (int) get_option( 'fpp_interlinking_max_replacements', 1 );
		$global_nofollow = (int) get_option( 'fpp_interlinking_nofollow', 0 );
		$global_new_tab  = (int) get_option( 'fpp_interlinking_new_tab', 1 );
		$case_sensitive  = (int) get_option( 'fpp_interlinking_case_sensitive', 0 );

		// Sort by keyword length descending to prevent partial matches.
		// e.g. "WordPress SEO" is processed before "WordPress".
		usort( $keywords, function ( $a, $b ) {
			return strlen( $b['keyword'] ) - strlen( $a['keyword'] );
		} );

		foreach ( $keywords as $mapping ) {
			// Self-link prevention: skip when the target URL matches the current post.
			if ( $current_url && $this->urls_match( $current_url, $mapping['target_url'] ) ) {
				continue;
			}

			$content = $this->replace_single_keyword(
				$content,
				$mapping,
				$global_max,
				$global_nofollow,
				$global_new_tab,
				$case_sensitive
			);
		}

		return $content;
	}

	/**
	 * Replace occurrences of a single keyword in the content.
	 *
	 * The content is split into protected (HTML) and plain-text segments.
	 * Replacement only happens in plain-text segments using word boundaries.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $content         Post content.
	 * @param  array  $mapping         Keyword row from the database.
	 * @param  int    $global_max      Global max replacements setting.
	 * @param  int    $global_nofollow Global nofollow setting.
	 * @param  int    $global_new_tab  Global new-tab setting.
	 * @param  int    $case_sensitive  Case sensitivity flag.
	 * @return string Modified content.
	 */
	private function replace_single_keyword( $content, $mapping, $global_max, $global_nofollow, $global_new_tab, $case_sensitive ) {
		$keyword  = preg_quote( $mapping['keyword'], '/' );
		$max      = ( (int) $mapping['max_replacements'] > 0 ) ? (int) $mapping['max_replacements'] : $global_max;
		$nofollow = (int) $mapping['nofollow'] ? true : (bool) $global_nofollow;
		$new_tab  = (int) $mapping['new_tab'] ? true : (bool) $global_new_tab;

		// Escape URL late, at the point of output.
		$url       = esc_url( $mapping['target_url'] );
		$rel_parts = array();
		if ( $nofollow ) {
			$rel_parts[] = 'nofollow';
		}
		if ( $new_tab ) {
			$rel_parts[] = 'noopener';
			$rel_parts[] = 'noreferrer';
		}
		$rel_attr    = ! empty( $rel_parts ) ? ' rel="' . implode( ' ', $rel_parts ) . '"' : '';
		$target_attr = $new_tab ? ' target="_blank"' : '';

		$flags = $case_sensitive ? '' : 'i';

		/*
		 * Split content into protected and plain-text segments.
		 *
		 * Protected segments (captured by the regex) include:
		 *  - Existing <a> ... </a> blocks (DOTALL so nested tags are handled).
		 *  - <script>, <style>, <code>, <pre>, <textarea> blocks.
		 *  - HTML comments <!-- ... -->.
		 *  - Any remaining HTML tag (<...>).
		 *
		 * The `s` (DOTALL) flag ensures `.*?` matches across newlines inside
		 * block-level protected elements.
		 */
		$protected_pattern = '/'
			. '(<a\b[^>]*>.*?<\/a>'              // Existing anchor links.
			. '|<script\b[^>]*>.*?<\/script>'     // Script blocks.
			. '|<style\b[^>]*>.*?<\/style>'       // Style blocks.
			. '|<code\b[^>]*>.*?<\/code>'         // Code blocks.
			. '|<pre\b[^>]*>.*?<\/pre>'           // Preformatted blocks.
			. '|<textarea\b[^>]*>.*?<\/textarea>' // Textarea blocks.
			. '|<!--.*?-->'                        // HTML comments.
			. '|<[^>]+>'                           // Any HTML tag.
			. ')/is';

		$parts = preg_split( $protected_pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( false === $parts ) {
			return $content;
		}

		$count           = 0;
		$keyword_pattern = '/\b(' . $keyword . ')\b/' . $flags;

		foreach ( $parts as &$part ) {
			// Skip protected segments (anything starting with <).
			if ( isset( $part[0] ) && '<' === $part[0] ) {
				continue;
			}

			if ( $max > 0 && $count >= $max ) {
				break;
			}

			$part = preg_replace_callback(
				$keyword_pattern,
				function ( $matches ) use ( $url, $rel_attr, $target_attr, &$count, $max ) {
					if ( $max > 0 && $count >= $max ) {
						return $matches[0];
					}
					$count++;
					return '<a href="' . $url . '"' . $rel_attr . $target_attr . '>' . esc_html( $matches[1] ) . '</a>';
				},
				$part
			);
		}
		unset( $part );

		return implode( '', $parts );
	}

	/**
	 * Retrieve active keywords, with transient caching (1 hour).
	 *
	 * Cache is busted whenever keywords or settings change via the admin.
	 *
	 * @since  1.0.0
	 * @return array<array<string,mixed>>
	 */
	private function get_cached_keywords() {
		$keywords = get_transient( 'fpp_interlinking_keywords_cache' );
		if ( false === $keywords ) {
			$keywords = FPP_Interlinking_DB::get_all_keywords( true );
			set_transient( 'fpp_interlinking_keywords_cache', $keywords, HOUR_IN_SECONDS );
		}
		return $keywords;
	}

	/**
	 * Parse the excluded-posts option into an array of integer IDs.
	 *
	 * @since  1.0.0
	 * @return int[]
	 */
	private function get_excluded_post_ids() {
		$excluded_raw = get_option( 'fpp_interlinking_excluded_posts', '' );
		if ( empty( $excluded_raw ) ) {
			return array();
		}

		$items = array_map( 'trim', explode( ',', $excluded_raw ) );
		$ids   = array();

		foreach ( $items as $item ) {
			if ( is_numeric( $item ) ) {
				$ids[] = (int) $item;
			}
		}

		return $ids;
	}

	/**
	 * Compare two URLs ignoring trailing slashes and scheme differences.
	 *
	 * Used for self-link prevention: we don't want to link a post to itself.
	 *
	 * @since  1.1.0
	 *
	 * @param  string $url_a First URL.
	 * @param  string $url_b Second URL.
	 * @return bool   True if the URLs effectively point to the same resource.
	 */
	private function urls_match( $url_a, $url_b ) {
		$normalize = function ( $url ) {
			$url = strtolower( trim( $url ) );
			$url = preg_replace( '#^https?://#', '', $url );
			$url = untrailingslashit( $url );
			return $url;
		};

		return $normalize( $url_a ) === $normalize( $url_b );
	}
}
