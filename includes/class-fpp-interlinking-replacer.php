<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Replacer {

	public function __construct() {
		add_filter( 'the_content', array( $this, 'replace_keywords' ), 999 );
	}

	public function replace_keywords( $content ) {
		if ( is_admin() || is_feed() || empty( $content ) ) {
			return $content;
		}

		$excluded = $this->get_excluded_post_ids();
		$post_id  = get_the_ID();
		if ( $post_id && in_array( $post_id, $excluded, true ) ) {
			return $content;
		}

		$keywords = $this->get_cached_keywords();
		if ( empty( $keywords ) ) {
			return $content;
		}

		$global_max      = (int) get_option( 'fpp_interlinking_max_replacements', 1 );
		$global_nofollow = (int) get_option( 'fpp_interlinking_nofollow', 0 );
		$global_new_tab  = (int) get_option( 'fpp_interlinking_new_tab', 1 );
		$case_sensitive  = (int) get_option( 'fpp_interlinking_case_sensitive', 0 );

		// Sort by keyword length descending to prevent partial matches.
		usort( $keywords, function( $a, $b ) {
			return strlen( $b['keyword'] ) - strlen( $a['keyword'] );
		} );

		foreach ( $keywords as $mapping ) {
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

	private function replace_single_keyword( $content, $mapping, $global_max, $global_nofollow, $global_new_tab, $case_sensitive ) {
		$keyword = preg_quote( $mapping['keyword'], '/' );
		$max     = ( (int) $mapping['max_replacements'] > 0 ) ? (int) $mapping['max_replacements'] : $global_max;
		$nofollow = (int) $mapping['nofollow'] ? true : (bool) $global_nofollow;
		$new_tab  = (int) $mapping['new_tab'] ? true : (bool) $global_new_tab;

		$url         = esc_url( $mapping['target_url'] );
		$rel_parts   = array();
		if ( $nofollow ) {
			$rel_parts[] = 'nofollow';
		}
		if ( $new_tab ) {
			$rel_parts[] = 'noopener';
		}
		$rel_attr    = ! empty( $rel_parts ) ? ' rel="' . implode( ' ', $rel_parts ) . '"' : '';
		$target_attr = $new_tab ? ' target="_blank"' : '';

		$flags = $case_sensitive ? '' : 'i';

		// Split content into protected (HTML tags, existing links, script/style/code/pre/textarea blocks) and text segments.
		$protected_pattern = '/'
			. '(<a\b[^>]*>.*?<\/a>'        // Existing anchor links
			. '|<script\b[^>]*>.*?<\/script>' // Script blocks
			. '|<style\b[^>]*>.*?<\/style>'   // Style blocks
			. '|<code\b[^>]*>.*?<\/code>'     // Code blocks
			. '|<pre\b[^>]*>.*?<\/pre>'       // Preformatted blocks
			. '|<textarea\b[^>]*>.*?<\/textarea>' // Textarea blocks
			. '|<[^>]+>'                       // Any HTML tag
			. ')/is';

		$parts = preg_split( $protected_pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( false === $parts ) {
			return $content;
		}

		$count          = 0;
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
				function( $matches ) use ( $url, $rel_attr, $target_attr, &$count, $max ) {
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

	private function get_cached_keywords() {
		$keywords = get_transient( 'fpp_interlinking_keywords_cache' );
		if ( false === $keywords ) {
			$keywords = FPP_Interlinking_DB::get_all_keywords( true );
			set_transient( 'fpp_interlinking_keywords_cache', $keywords, HOUR_IN_SECONDS );
		}
		return $keywords;
	}

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
}
