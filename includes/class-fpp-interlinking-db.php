<?php
/**
 * Database abstraction layer for keyword CRUD operations.
 *
 * All public methods sanitise their inputs and use $wpdb->prepare()
 * or the insert/update/delete helpers (which handle escaping internally)
 * to prevent SQL-injection.
 *
 * @since   1.0.0
 * @package FPP_Interlinking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_DB {

	/**
	 * Return the fully-prefixed table name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fpp_interlinking_keywords';
	}

	/**
	 * Get the configured post types from settings.
	 *
	 * Used across admin, AI, analyzer, and replacer classes to ensure
	 * consistent post type handling everywhere.
	 *
	 * @since  3.0.0
	 *
	 * @return string[] Array of post type slugs.
	 */
	public static function get_configured_post_types() {
		$setting = get_option( 'fpp_interlinking_post_types', 'post,page' );
		if ( empty( $setting ) ) {
			return array( 'post', 'page' );
		}
		$types = array_filter( array_map( 'trim', explode( ',', $setting ) ) );
		return ! empty( $types ) ? $types : array( 'post', 'page' );
	}

	/**
	 * Retrieve all keywords, optionally filtered to active-only.
	 *
	 * @since  1.0.0
	 *
	 * @param  bool $active_only Whether to return only active keywords.
	 * @return array<array<string,mixed>>
	 */
	public static function get_all_keywords( $active_only = false ) {
		global $wpdb;
		$table = self::get_table_name();

		if ( $active_only ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe.
			return $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY keyword ASC", ARRAY_A );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY keyword ASC", ARRAY_A );
	}

	/**
	 * Retrieve a single keyword row by ID.
	 *
	 * @since  1.0.0
	 *
	 * @param  int $id Row ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_keyword( $id ) {
		global $wpdb;
		$table = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	/**
	 * Check whether a keyword already exists (case-insensitive).
	 *
	 * @since  1.1.0
	 *
	 * @param  string   $keyword    Keyword text.
	 * @param  int|null $exclude_id Optional row ID to exclude (for updates).
	 * @return bool
	 */
	public static function keyword_exists( $keyword, $exclude_id = null ) {
		global $wpdb;
		$table = self::get_table_name();

		if ( $exclude_id ) {
			$row = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE keyword = %s AND id != %d",
					$keyword,
					$exclude_id
				)
			);
		} else {
			$row = $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE keyword = %s", $keyword )
			);
		}

		return ( (int) $row > 0 );
	}

	/**
	 * Insert a new keyword mapping.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $data {
	 *     @type string $keyword          Keyword text.
	 *     @type string $target_url       Target URL.
	 *     @type int    $nofollow         Whether to add nofollow (0|1).
	 *     @type int    $new_tab          Whether to open in new tab (0|1).
	 *     @type int    $max_replacements Per-keyword max (0 = use global).
	 * }
	 * @return int|false Insert ID on success, false on failure.
	 */
	public static function insert_keyword( $data ) {
		global $wpdb;
		$table = self::get_table_name();

		$max = isset( $data['max_replacements'] ) ? absint( $data['max_replacements'] ) : 0;
		if ( $max > FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT ) {
			$max = FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'keyword'          => sanitize_text_field( $data['keyword'] ),
				'target_url'       => esc_url_raw( $data['target_url'] ),
				'nofollow'         => absint( $data['nofollow'] ),
				'new_tab'          => absint( $data['new_tab'] ),
				'max_replacements' => $max,
				'is_active'        => 1,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf(
					'[WP Interlinking] insert_keyword failed: %s',
					$wpdb->last_error
				) );
			}
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update an existing keyword mapping.
	 *
	 * @since  1.0.0
	 *
	 * @param  int   $id   Row ID.
	 * @param  array $data Same structure as insert_keyword().
	 * @return int|false Number of rows updated, or false on error.
	 */
	public static function update_keyword( $id, $data ) {
		global $wpdb;
		$table = self::get_table_name();

		$max = isset( $data['max_replacements'] ) ? absint( $data['max_replacements'] ) : 0;
		if ( $max > FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT ) {
			$max = FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT;
		}

		$result = $wpdb->update(
			$table,
			array(
				'keyword'          => sanitize_text_field( $data['keyword'] ),
				'target_url'       => esc_url_raw( $data['target_url'] ),
				'nofollow'         => absint( $data['nofollow'] ),
				'new_tab'          => absint( $data['new_tab'] ),
				'max_replacements' => $max,
				'is_active'        => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%d', '%d', '%d', '%d' ),
			array( '%d' )
		);

		if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[WP Interlinking] update_keyword(%d) failed: %s',
				$id,
				$wpdb->last_error
			) );
		}

		return $result;
	}

	/**
	 * Delete a keyword mapping by ID.
	 *
	 * @since  1.0.0
	 *
	 * @param  int $id Row ID.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public static function delete_keyword( $id ) {
		global $wpdb;
		$table = self::get_table_name();

		$result = $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );

		if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[WP Interlinking] delete_keyword(%d) failed: %s',
				$id,
				$wpdb->last_error
			) );
		}

		return $result;
	}

	/**
	 * Toggle a keyword's active state.
	 *
	 * @since  1.0.0
	 *
	 * @param  int $id        Row ID.
	 * @param  int $is_active New active state (0|1).
	 * @return int|false Number of rows updated, or false on error.
	 */
	public static function toggle_keyword( $id, $is_active ) {
		global $wpdb;
		$table = self::get_table_name();

		$result = $wpdb->update(
			$table,
			array( 'is_active' => absint( $is_active ) ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf(
				'[WP Interlinking] toggle_keyword(%d) failed: %s',
				$id,
				$wpdb->last_error
			) );
		}

		return $result;
	}

	/* ── v2.1.0: Pagination, Search & Bulk Ops ──────────────────────── */

	/**
	 * Retrieve keywords with pagination and optional search filter.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $page     Current page (1-based).
	 * @param int    $per_page Items per page.
	 * @param string $search   Optional search string to filter by keyword or URL.
	 * @param string $orderby  Column to sort by (keyword, target_url, is_active, created_at).
	 * @param string $order    Sort direction (ASC or DESC).
	 * @return array { keywords: array, total: int, pages: int, page: int }
	 */
	public static function get_keywords_paginated( $page = 1, $per_page = 20, $search = '', $orderby = 'keyword', $order = 'ASC' ) {
		global $wpdb;
		$table = self::get_table_name();

		// Whitelist sortable columns.
		$allowed_orderby = array( 'keyword', 'target_url', 'is_active', 'created_at', 'id' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'keyword';
		}
		$order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';

		$where = '';
		$args  = array();

		if ( ! empty( $search ) ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$where = ' WHERE keyword LIKE %s OR target_url LIKE %s';
			$args  = array( $like, $like );
		}

		// Get total count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table}" . $where;
		if ( ! empty( $args ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $args );
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$pages  = max( 1, (int) ceil( $total / $per_page ) );
		$page   = max( 1, min( $page, $pages ) );
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$table}{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_args = array_merge( $args, array( $per_page, $offset ) );
		$keywords = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'keywords' => $keywords ? $keywords : array(),
			'total'    => $total,
			'pages'    => $pages,
			'page'     => $page,
		);
	}

	/**
	 * Bulk delete keywords by IDs.
	 *
	 * @since 2.1.0
	 *
	 * @param int[] $ids Array of keyword IDs to delete.
	 * @return int Number of rows deleted.
	 */
	public static function bulk_delete( $ids ) {
		global $wpdb;
		$table = self::get_table_name();

		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE id IN ({$placeholders})",
			$ids
		) );

		return ( false !== $result ) ? $result : 0;
	}

	/**
	 * Bulk update active state for keywords by IDs.
	 *
	 * @since 2.1.0
	 *
	 * @param int[] $ids       Array of keyword IDs.
	 * @param int   $is_active New active state (0|1).
	 * @return int Number of rows updated.
	 */
	public static function bulk_toggle( $ids, $is_active ) {
		global $wpdb;
		$table = self::get_table_name();

		$ids       = array_map( 'absint', $ids );
		$ids       = array_filter( $ids );
		$is_active = absint( $is_active ) ? 1 : 0;

		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$args         = array_merge( array( $is_active ), $ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET is_active = %d WHERE id IN ({$placeholders})",
			$args
		) );

		return ( false !== $result ) ? $result : 0;
	}

	/**
	 * Export all keywords as an array suitable for CSV generation.
	 *
	 * @since 2.1.0
	 *
	 * @return array<array<string,mixed>>
	 */
	public static function export_all() {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			"SELECT keyword, target_url, nofollow, new_tab, max_replacements, is_active FROM {$table} ORDER BY keyword ASC",
			ARRAY_A
		);
	}

	/**
	 * Import keywords from parsed CSV data.
	 *
	 * Skips duplicates (keywords that already exist).
	 *
	 * @since 2.1.0
	 *
	 * @param array $rows Array of associative arrays with keyword, target_url, etc.
	 * @return array { imported: int, skipped: int, errors: int }
	 */
	public static function import_keywords( $rows ) {
		$imported = 0;
		$skipped  = 0;
		$errors   = 0;

		foreach ( $rows as $row ) {
			$keyword    = isset( $row['keyword'] ) ? sanitize_text_field( $row['keyword'] ) : '';
			$target_url = isset( $row['target_url'] ) ? esc_url_raw( $row['target_url'] ) : '';

			if ( empty( $keyword ) || empty( $target_url ) ) {
				$errors++;
				continue;
			}

			if ( self::keyword_exists( $keyword ) ) {
				$skipped++;
				continue;
			}

			$result = self::insert_keyword( array(
				'keyword'          => $keyword,
				'target_url'       => $target_url,
				'nofollow'         => isset( $row['nofollow'] ) ? absint( $row['nofollow'] ) : 0,
				'new_tab'          => isset( $row['new_tab'] ) ? absint( $row['new_tab'] ) : 1,
				'max_replacements' => isset( $row['max_replacements'] ) ? absint( $row['max_replacements'] ) : 0,
			) );

			if ( $result ) {
				$imported++;
			} else {
				$errors++;
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}
}
