<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_fpp_interlinking_add_keyword', array( $this, 'ajax_add_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_update_keyword', array( $this, 'ajax_update_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_delete_keyword', array( $this, 'ajax_delete_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_toggle_keyword', array( $this, 'ajax_toggle_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_save_settings', array( $this, 'ajax_save_settings' ) );
	}

	public function add_admin_menu() {
		add_options_page(
			'FPP Interlinking',
			'FPP Interlinking',
			'manage_options',
			'fpp-interlinking',
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_fpp-interlinking' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'fpp-interlinking-admin',
			FPP_INTERLINKING_PLUGIN_URL . 'assets/css/fpp-interlinking-admin.css',
			array(),
			FPP_INTERLINKING_VERSION
		);

		wp_enqueue_script(
			'fpp-interlinking-admin',
			FPP_INTERLINKING_PLUGIN_URL . 'assets/js/fpp-interlinking-admin.js',
			array( 'jquery' ),
			FPP_INTERLINKING_VERSION,
			true
		);

		wp_localize_script( 'fpp-interlinking-admin', 'fppInterlinking', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'fpp_interlinking_nonce' ),
		) );
	}

	public function render_admin_page() {
		$keywords        = FPP_Interlinking_DB::get_all_keywords();
		$max_replacements = get_option( 'fpp_interlinking_max_replacements', 1 );
		$nofollow        = get_option( 'fpp_interlinking_nofollow', 0 );
		$new_tab         = get_option( 'fpp_interlinking_new_tab', 1 );
		$case_sensitive  = get_option( 'fpp_interlinking_case_sensitive', 0 );
		$excluded_posts  = get_option( 'fpp_interlinking_excluded_posts', '' );
		?>
		<div class="wrap">
			<h1>FPP Interlinking</h1>
			<div id="fpp-notices"></div>

			<!-- Global Settings -->
			<div class="fpp-section fpp-settings-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-settings">
					Global Settings
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-settings-content">
					<table class="form-table">
						<tr>
							<th><label for="fpp-global-max-replacements">Max replacements per keyword</label></th>
							<td>
								<input type="number" id="fpp-global-max-replacements" min="1" max="100" value="<?php echo esc_attr( $max_replacements ); ?>" class="small-text" />
								<p class="description">Maximum number of times each keyword gets linked per post. Set to 1 to only link the first occurrence.</p>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-global-nofollow">Add rel="nofollow"</label></th>
							<td>
								<label>
									<input type="checkbox" id="fpp-global-nofollow" value="1" <?php checked( $nofollow, 1 ); ?> />
									Add nofollow attribute to generated links
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-global-new-tab">Open in new tab</label></th>
							<td>
								<label>
									<input type="checkbox" id="fpp-global-new-tab" value="1" <?php checked( $new_tab, 1 ); ?> />
									Open links in a new browser tab
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-global-case-sensitive">Case sensitive</label></th>
							<td>
								<label>
									<input type="checkbox" id="fpp-global-case-sensitive" value="1" <?php checked( $case_sensitive, 1 ); ?> />
									Match keywords with exact case
								</label>
								<p class="description">When unchecked, "WordPress" will also match "wordpress", "WORDPRESS", etc.</p>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-global-excluded-posts">Excluded posts/pages</label></th>
							<td>
								<textarea id="fpp-global-excluded-posts" rows="3" class="large-text"><?php echo esc_textarea( $excluded_posts ); ?></textarea>
								<p class="description">Comma-separated list of post/page IDs to exclude from keyword replacement.</p>
							</td>
						</tr>
					</table>
					<p>
						<button type="button" id="fpp-save-settings" class="button button-primary">Save Settings</button>
					</p>
				</div>
			</div>

			<hr />

			<!-- Add / Edit Keyword Form -->
			<div class="fpp-section fpp-add-keyword-section">
				<h2 id="fpp-form-title">Add New Keyword Mapping</h2>
				<input type="hidden" id="fpp-edit-id" value="" />
				<table class="form-table">
					<tr>
						<th><label for="fpp-keyword">Keyword</label></th>
						<td><input type="text" id="fpp-keyword" class="regular-text" placeholder="Enter keyword or phrase" /></td>
					</tr>
					<tr>
						<th><label for="fpp-target-url">Target URL</label></th>
						<td><input type="url" id="fpp-target-url" class="regular-text" placeholder="https://example.com/page" /></td>
					</tr>
					<tr>
						<th>Per-mapping overrides</th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" id="fpp-per-nofollow" value="1" />
									Nofollow
								</label>
								<br />
								<label>
									<input type="checkbox" id="fpp-per-new-tab" value="1" checked />
									Open in new tab
								</label>
								<br />
								<label>
									Max replacements:
									<input type="number" id="fpp-per-max-replacements" min="0" max="100" value="0" class="small-text" />
								</label>
								<p class="description">Set to 0 to use the global setting.</p>
							</fieldset>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" id="fpp-add-keyword" class="button button-primary">Add Keyword</button>
					<button type="button" id="fpp-update-keyword" class="button button-primary" style="display:none;">Update Keyword</button>
					<button type="button" id="fpp-cancel-edit" class="button" style="display:none;">Cancel</button>
				</p>
			</div>

			<hr />

			<!-- Keywords Table -->
			<div class="fpp-section">
				<h2>Keyword Mappings</h2>
				<?php if ( empty( $keywords ) ) : ?>
					<p id="fpp-no-keywords">No keyword mappings found. Add your first one above.</p>
				<?php endif; ?>
				<table class="wp-list-table widefat fixed striped" id="fpp-keywords-table" <?php echo empty( $keywords ) ? 'style="display:none;"' : ''; ?>>
					<thead>
						<tr>
							<th class="column-keyword">Keyword</th>
							<th class="column-url">Target URL</th>
							<th class="column-nofollow">Nofollow</th>
							<th class="column-newtab">New Tab</th>
							<th class="column-max">Max</th>
							<th class="column-active">Active</th>
							<th class="column-actions">Actions</th>
						</tr>
					</thead>
					<tbody id="fpp-keywords-tbody">
						<?php foreach ( $keywords as $kw ) : ?>
							<tr id="fpp-keyword-row-<?php echo esc_attr( $kw['id'] ); ?>">
								<td class="column-keyword"><?php echo esc_html( $kw['keyword'] ); ?></td>
								<td class="column-url">
									<a href="<?php echo esc_url( $kw['target_url'] ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( $kw['target_url'] ); ?>
									</a>
								</td>
								<td class="column-nofollow"><?php echo $kw['nofollow'] ? 'Yes' : 'No'; ?></td>
								<td class="column-newtab"><?php echo $kw['new_tab'] ? 'Yes' : 'No'; ?></td>
								<td class="column-max"><?php echo $kw['max_replacements'] ? esc_html( $kw['max_replacements'] ) : 'Global'; ?></td>
								<td class="column-active">
									<span class="<?php echo $kw['is_active'] ? 'fpp-badge-active' : 'fpp-badge-inactive'; ?>">
										<?php echo $kw['is_active'] ? 'Active' : 'Inactive'; ?>
									</span>
								</td>
								<td class="column-actions">
									<button type="button" class="button button-small fpp-edit-keyword"
										data-id="<?php echo esc_attr( $kw['id'] ); ?>"
										data-keyword="<?php echo esc_attr( $kw['keyword'] ); ?>"
										data-url="<?php echo esc_attr( $kw['target_url'] ); ?>"
										data-nofollow="<?php echo esc_attr( $kw['nofollow'] ); ?>"
										data-newtab="<?php echo esc_attr( $kw['new_tab'] ); ?>"
										data-max="<?php echo esc_attr( $kw['max_replacements'] ); ?>">
										Edit
									</button>
									<button type="button" class="button button-small fpp-toggle-keyword"
										data-id="<?php echo esc_attr( $kw['id'] ); ?>"
										data-active="<?php echo esc_attr( $kw['is_active'] ); ?>">
										<?php echo $kw['is_active'] ? 'Disable' : 'Enable'; ?>
									</button>
									<button type="button" class="button button-small fpp-delete-keyword"
										data-id="<?php echo esc_attr( $kw['id'] ); ?>">
										Delete
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/* ── AJAX Handlers ────────────────────────────────────────────────── */

	public function ajax_add_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$keyword    = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';

		if ( empty( $keyword ) || empty( $target_url ) ) {
			wp_send_json_error( array( 'message' => 'Keyword and URL are required.' ) );
		}

		$id = FPP_Interlinking_DB::insert_keyword( array(
			'keyword'          => $keyword,
			'target_url'       => $target_url,
			'nofollow'         => isset( $_POST['nofollow'] ) ? absint( $_POST['nofollow'] ) : 0,
			'new_tab'          => isset( $_POST['new_tab'] ) ? absint( $_POST['new_tab'] ) : 1,
			'max_replacements' => isset( $_POST['max_replacements'] ) ? absint( $_POST['max_replacements'] ) : 0,
		) );

		if ( $id ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array(
				'message' => 'Keyword added successfully.',
				'keyword' => array(
					'id'               => $id,
					'keyword'          => $keyword,
					'target_url'       => $target_url,
					'nofollow'         => isset( $_POST['nofollow'] ) ? absint( $_POST['nofollow'] ) : 0,
					'new_tab'          => isset( $_POST['new_tab'] ) ? absint( $_POST['new_tab'] ) : 1,
					'max_replacements' => isset( $_POST['max_replacements'] ) ? absint( $_POST['max_replacements'] ) : 0,
					'is_active'        => 1,
				),
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to add keyword.' ) );
		}
	}

	public function ajax_update_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$id         = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$keyword    = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';

		if ( ! $id || empty( $keyword ) || empty( $target_url ) ) {
			wp_send_json_error( array( 'message' => 'ID, keyword, and URL are required.' ) );
		}

		$result = FPP_Interlinking_DB::update_keyword( $id, array(
			'keyword'          => $keyword,
			'target_url'       => $target_url,
			'nofollow'         => isset( $_POST['nofollow'] ) ? absint( $_POST['nofollow'] ) : 0,
			'new_tab'          => isset( $_POST['new_tab'] ) ? absint( $_POST['new_tab'] ) : 1,
			'max_replacements' => isset( $_POST['max_replacements'] ) ? absint( $_POST['max_replacements'] ) : 0,
		) );

		if ( false !== $result ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array(
				'message' => 'Keyword updated successfully.',
				'keyword' => array(
					'id'               => $id,
					'keyword'          => $keyword,
					'target_url'       => $target_url,
					'nofollow'         => isset( $_POST['nofollow'] ) ? absint( $_POST['nofollow'] ) : 0,
					'new_tab'          => isset( $_POST['new_tab'] ) ? absint( $_POST['new_tab'] ) : 1,
					'max_replacements' => isset( $_POST['max_replacements'] ) ? absint( $_POST['max_replacements'] ) : 0,
					'is_active'        => 1,
				),
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update keyword.' ) );
		}
	}

	public function ajax_delete_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Invalid keyword ID.' ) );
		}

		$result = FPP_Interlinking_DB::delete_keyword( $id );

		if ( $result ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array( 'message' => 'Keyword deleted successfully.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete keyword.' ) );
		}
	}

	public function ajax_toggle_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$id        = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$is_active = isset( $_POST['is_active'] ) ? absint( $_POST['is_active'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Invalid keyword ID.' ) );
		}

		$result = FPP_Interlinking_DB::toggle_keyword( $id, $is_active );

		if ( false !== $result ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array(
				'message'   => $is_active ? 'Keyword enabled.' : 'Keyword disabled.',
				'is_active' => $is_active,
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to toggle keyword.' ) );
		}
	}

	public function ajax_save_settings() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$max_replacements = isset( $_POST['max_replacements'] ) ? absint( $_POST['max_replacements'] ) : 1;
		if ( $max_replacements < 1 ) {
			$max_replacements = 1;
		}

		update_option( 'fpp_interlinking_max_replacements', $max_replacements );
		update_option( 'fpp_interlinking_nofollow', isset( $_POST['nofollow'] ) ? absint( $_POST['nofollow'] ) : 0 );
		update_option( 'fpp_interlinking_new_tab', isset( $_POST['new_tab'] ) ? absint( $_POST['new_tab'] ) : 0 );
		update_option( 'fpp_interlinking_case_sensitive', isset( $_POST['case_sensitive'] ) ? absint( $_POST['case_sensitive'] ) : 0 );
		update_option( 'fpp_interlinking_excluded_posts', isset( $_POST['excluded_posts'] ) ? sanitize_text_field( wp_unslash( $_POST['excluded_posts'] ) ) : '' );

		delete_transient( 'fpp_interlinking_keywords_cache' );

		wp_send_json_success( array( 'message' => 'Settings saved successfully.' ) );
	}
}
