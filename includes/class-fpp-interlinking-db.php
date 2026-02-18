<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_DB {

	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fpp_interlinking_keywords';
	}

	public static function get_all_keywords( $active_only = false ) {
		global $wpdb;
		$table = self::get_table_name();

		if ( $active_only ) {
			return $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY keyword ASC", ARRAY_A );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY keyword ASC", ARRAY_A );
	}

	public static function get_keyword( $id ) {
		global $wpdb;
		$table = self::get_table_name();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	}

	public static function insert_keyword( $data ) {
		global $wpdb;
		$table = self::get_table_name();

		$result = $wpdb->insert(
			$table,
			array(
				'keyword'          => sanitize_text_field( $data['keyword'] ),
				'target_url'       => esc_url_raw( $data['target_url'] ),
				'nofollow'         => absint( $data['nofollow'] ),
				'new_tab'          => absint( $data['new_tab'] ),
				'max_replacements' => absint( $data['max_replacements'] ),
				'is_active'        => 1,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	public static function update_keyword( $id, $data ) {
		global $wpdb;
		$table = self::get_table_name();

		return $wpdb->update(
			$table,
			array(
				'keyword'          => sanitize_text_field( $data['keyword'] ),
				'target_url'       => esc_url_raw( $data['target_url'] ),
				'nofollow'         => absint( $data['nofollow'] ),
				'new_tab'          => absint( $data['new_tab'] ),
				'max_replacements' => absint( $data['max_replacements'] ),
				'is_active'        => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%d', '%d', '%d', '%d' ),
			array( '%d' )
		);
	}

	public static function delete_keyword( $id ) {
		global $wpdb;
		$table = self::get_table_name();

		return $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function toggle_keyword( $id, $is_active ) {
		global $wpdb;
		$table = self::get_table_name();

		return $wpdb->update(
			$table,
			array( 'is_active' => absint( $is_active ) ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}
}
