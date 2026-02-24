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
}
