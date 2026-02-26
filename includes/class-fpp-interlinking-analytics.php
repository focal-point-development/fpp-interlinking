<?php
/**
 * Analytics system: click tracking on frontend, data retrieval for dashboard.
 *
 * Tracks clicks on auto-generated interlinks via a lightweight JS beacon.
 * Provides aggregated statistics for the Analytics admin tab.
 *
 * @since   3.0.0
 * @package FPP_Interlinking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Analytics {

	/**
	 * Register AJAX handlers for click tracking (both logged-in and guest).
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_fpp_interlinking_track_click', array( $this, 'ajax_track_click' ) );
		add_action( 'wp_ajax_nopriv_fpp_interlinking_track_click', array( $this, 'ajax_track_click' ) );
	}

	/**
	 * Enqueue the lightweight frontend click tracker script.
	 *
	 * Only loads on non-admin pages when tracking is enabled.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function enqueue_tracker() {
		if ( is_admin() || is_feed() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		wp_enqueue_script(
			'fpp-interlinking-tracker',
			FPP_INTERLINKING_PLUGIN_URL . 'assets/js/fpp-interlinking-tracker.js',
			array(),
			FPP_INTERLINKING_VERSION,
			true
		);

		wp_localize_script( 'fpp-interlinking-tracker', 'fppTracker', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'fpp_interlinking_tracker_nonce' ),
		) );
	}

	/**
	 * AJAX handler: log a click event from the frontend tracker.
	 *
	 * Rate-limited to 10 clicks per IP per minute via transient.
	 *
	 * @since 3.0.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_track_click() {
		// Verify tracker nonce.
		if ( ! check_ajax_referer( 'fpp_interlinking_tracker_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		$keyword_id = isset( $_POST['keyword_id'] ) ? absint( $_POST['keyword_id'] ) : 0;
		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';

		if ( ! $keyword_id || empty( $target_url ) ) {
			wp_send_json_error( array( 'message' => 'Missing data.' ), 400 );
		}

		// Rate limit: max 10 clicks per IP per minute.
		$ip_hash    = md5( self::get_client_ip() );
		$rate_key   = 'fpp_click_rate_' . $ip_hash;
		$rate_count = (int) get_transient( $rate_key );

		if ( $rate_count >= 10 ) {
			wp_send_json_success( array( 'throttled' => true ) );
		}

		set_transient( $rate_key, $rate_count + 1, 60 );

		// Insert click record.
		global $wpdb;
		$table = self::get_table_name();

		$wpdb->insert(
			$table,
			array(
				'keyword_id' => $keyword_id,
				'post_id'    => $post_id,
				'target_url' => $target_url,
				'clicked_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		wp_send_json_success( array( 'tracked' => true ) );
	}

	/* ── Analytics Data Retrieval ────────────────────────────────────── */

	/**
	 * Get summary statistics for a given period.
	 *
	 * @since 3.0.0
	 *
	 * @param string $period 'today', '7days', '30days', 'all'.
	 * @return array Summary stats.
	 */
	public static function get_summary_stats( $period = '30days' ) {
		global $wpdb;
		$table = self::get_table_name();
		$where = self::period_where( $period );

		$total_clicks = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE 1=1 {$where}"
		);

		$unique_keywords = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT keyword_id) FROM {$table} WHERE 1=1 {$where}"
		);

		$unique_posts = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$table} WHERE post_id > 0 {$where}"
		);

		$avg_clicks = $unique_keywords > 0 ? round( $total_clicks / $unique_keywords, 1 ) : 0;

		// Top keyword.
		$top = $wpdb->get_row(
			"SELECT c.keyword_id, k.keyword, COUNT(*) as clicks
			 FROM {$table} c
			 LEFT JOIN {$wpdb->prefix}fpp_interlinking_keywords k ON k.id = c.keyword_id
			 WHERE 1=1 {$where}
			 GROUP BY c.keyword_id
			 ORDER BY clicks DESC
			 LIMIT 1"
		);

		return array(
			'total_clicks'           => $total_clicks,
			'unique_keywords_clicked' => $unique_keywords,
			'unique_posts_with_clicks' => $unique_posts,
			'avg_clicks_per_keyword' => $avg_clicks,
			'top_keyword'            => $top ? $top->keyword : null,
			'top_keyword_clicks'     => $top ? (int) $top->clicks : 0,
		);
	}

	/**
	 * Get top performing keywords by click count.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $limit  Number of results.
	 * @param string $period Time period filter.
	 * @return array Array of keyword performance data.
	 */
	public static function get_top_keywords( $limit = 20, $period = '30days' ) {
		global $wpdb;
		$table = self::get_table_name();
		$where = self::period_where( $period );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.keyword_id, k.keyword, k.target_url, COUNT(*) as click_count,
			        COUNT(DISTINCT c.post_id) as unique_posts
			 FROM {$table} c
			 LEFT JOIN {$wpdb->prefix}fpp_interlinking_keywords k ON k.id = c.keyword_id
			 WHERE 1=1 {$where}
			 GROUP BY c.keyword_id
			 ORDER BY click_count DESC
			 LIMIT %d",
			$limit
		), ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get top performing links (keyword + URL pairs).
	 *
	 * @since 3.0.0
	 *
	 * @param int    $limit  Number of results.
	 * @param string $period Time period filter.
	 * @return array Array of link performance data.
	 */
	public static function get_top_links( $limit = 20, $period = '30days' ) {
		global $wpdb;
		$table = self::get_table_name();
		$where = self::period_where( $period );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT k.keyword, c.target_url, COUNT(*) as click_count
			 FROM {$table} c
			 LEFT JOIN {$wpdb->prefix}fpp_interlinking_keywords k ON k.id = c.keyword_id
			 WHERE 1=1 {$where}
			 GROUP BY c.keyword_id, c.target_url
			 ORDER BY click_count DESC
			 LIMIT %d",
			$limit
		), ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get click data grouped by post (which posts generate the most clicks).
	 *
	 * @since 3.0.0
	 *
	 * @param int    $limit  Number of results.
	 * @param string $period Time period filter.
	 * @return array Array of post click data.
	 */
	public static function get_clicks_by_post( $limit = 20, $period = '30days' ) {
		global $wpdb;
		$table = self::get_table_name();
		$where = self::period_where( $period );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.post_id, p.post_title, p.post_type, COUNT(*) as click_count,
			        COUNT(DISTINCT c.keyword_id) as unique_keywords
			 FROM {$table} c
			 LEFT JOIN {$wpdb->posts} p ON p.ID = c.post_id
			 WHERE c.post_id > 0 {$where}
			 GROUP BY c.post_id
			 ORDER BY click_count DESC
			 LIMIT %d",
			$limit
		), ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Get daily click trend data for charting.
	 *
	 * @since 3.0.0
	 *
	 * @param int $days Number of days to look back.
	 * @return array Array of ['date' => 'Y-m-d', 'clicks' => int].
	 */
	public static function get_daily_trend( $days = 30 ) {
		global $wpdb;
		$table = self::get_table_name();

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(clicked_at) as date, COUNT(*) as clicks
			 FROM {$table}
			 WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
			 GROUP BY DATE(clicked_at)
			 ORDER BY date ASC",
			$days
		), ARRAY_A );

		// Fill in missing days with 0 clicks.
		$trend = array();
		$date  = new DateTime( '-' . $days . ' days' );
		$end   = new DateTime( 'now' );
		$data  = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$data[ $row['date'] ] = (int) $row['clicks'];
			}
		}

		while ( $date <= $end ) {
			$d = $date->format( 'Y-m-d' );
			$trend[] = array(
				'date'   => $d,
				'clicks' => isset( $data[ $d ] ) ? $data[ $d ] : 0,
			);
			$date->modify( '+1 day' );
		}

		return $trend;
	}

	/**
	 * Get analytics data broken down by post type.
	 *
	 * @since 3.0.0
	 *
	 * @param string $period Time period filter.
	 * @return array Array of post type stats.
	 */
	public static function get_stats_by_post_type( $period = '30days' ) {
		global $wpdb;
		$table = self::get_table_name();
		$where = self::period_where( $period );

		$results = $wpdb->get_results(
			"SELECT p.post_type, COUNT(*) as click_count,
			        COUNT(DISTINCT c.keyword_id) as keyword_count
			 FROM {$table} c
			 LEFT JOIN {$wpdb->posts} p ON p.ID = c.post_id
			 WHERE c.post_id > 0 {$where}
			 GROUP BY p.post_type
			 ORDER BY click_count DESC",
			ARRAY_A
		);

		// Add labels.
		if ( $results ) {
			foreach ( $results as &$row ) {
				$pt_obj = get_post_type_object( $row['post_type'] );
				$row['post_type_label'] = $pt_obj ? $pt_obj->labels->singular_name : $row['post_type'];
			}
			unset( $row );
		}

		return $results ? $results : array();
	}

	/**
	 * Get link coverage statistics.
	 *
	 * @since 3.0.0
	 *
	 * @return array Coverage stats.
	 */
	public static function get_coverage_stats() {
		global $wpdb;

		$active_keywords = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}fpp_interlinking_keywords WHERE is_active = 1"
		);

		$total_keywords = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}fpp_interlinking_keywords"
		);

		$post_types = FPP_Interlinking_DB::get_configured_post_types();
		$pt_in      = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		$total_posts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$pt_in})"
		);

		$coverage = $total_posts > 0 && $active_keywords > 0
			? min( round( ( $active_keywords / $total_posts ) * 100, 1 ), 100 )
			: 0;

		return array(
			'total_active_keywords' => $active_keywords,
			'total_keywords'        => $total_keywords,
			'total_published_posts' => $total_posts,
			'coverage_percentage'   => $coverage,
		);
	}

	/**
	 * Purge analytics data older than retention period.
	 *
	 * @since 3.0.0
	 *
	 * @param int $days_to_keep Number of days to retain (default from option).
	 * @return int Rows deleted.
	 */
	public static function purge_old_data( $days_to_keep = 0 ) {
		if ( $days_to_keep <= 0 ) {
			$days_to_keep = (int) get_option( 'fpp_interlinking_tracking_retention_days', 90 );
		}
		if ( $days_to_keep <= 0 ) {
			$days_to_keep = 90;
		}

		global $wpdb;
		$table = self::get_table_name();

		return (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE clicked_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days_to_keep
		) );
	}

	/**
	 * Get recent click events for the dashboard.
	 *
	 * @since 3.0.0
	 *
	 * @param int $limit Number of recent events.
	 * @return array Array of recent click events.
	 */
	public static function get_recent_clicks( $limit = 10 ) {
		global $wpdb;
		$table = self::get_table_name();

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.keyword_id, c.post_id, c.target_url, c.clicked_at,
			        k.keyword, p.post_title
			 FROM {$table} c
			 LEFT JOIN {$wpdb->prefix}fpp_interlinking_keywords k ON k.id = c.keyword_id
			 LEFT JOIN {$wpdb->posts} p ON p.ID = c.post_id
			 ORDER BY c.clicked_at DESC
			 LIMIT %d",
			$limit
		), ARRAY_A );

		return $results ? $results : array();
	}

	/* ── Private Helpers ──────────────────────────────────────────────── */

	/**
	 * Build a WHERE clause for period filtering.
	 *
	 * @since 3.0.0
	 *
	 * @param string $period 'today', '7days', '30days', 'all'.
	 * @param string $column Date column name.
	 * @return string SQL WHERE fragment (includes AND prefix).
	 */
	private static function period_where( $period, $column = 'clicked_at' ) {
		switch ( $period ) {
			case 'today':
				return " AND {$column} >= CURDATE()";
			case '7days':
				return " AND {$column} >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
			case '30days':
				return " AND {$column} >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
			case 'all':
			default:
				return '';
		}
	}

	/**
	 * Get the clicks table name.
	 *
	 * @since 3.0.0
	 *
	 * @return string Full table name with prefix.
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fpp_interlinking_clicks';
	}

	/**
	 * Get the client IP address (anonymised for privacy).
	 *
	 * @since 3.0.0
	 *
	 * @return string IP address or empty string.
	 */
	private static function get_client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return $ip;
	}
}
