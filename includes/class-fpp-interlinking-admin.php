<?php
/**
 * Admin-side functionality: settings page, AJAX handlers, asset loading.
 *
 * Security model:
 *  - Every AJAX handler verifies a nonce (CSRF) AND the `manage_options` capability.
 *  - Input is sanitised early; output is escaped late.
 *
 * @since   1.0.0
 * @package FPP_Interlinking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Admin {

	/**
	 * Wire up hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX actions – all require nonce + manage_options.
		add_action( 'wp_ajax_fpp_interlinking_add_keyword', array( $this, 'ajax_add_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_update_keyword', array( $this, 'ajax_update_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_delete_keyword', array( $this, 'ajax_delete_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_toggle_keyword', array( $this, 'ajax_toggle_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_save_settings', array( $this, 'ajax_save_settings' ) );

		// v1.2.0: Scan, suggest, and search endpoints.
		add_action( 'wp_ajax_fpp_interlinking_search_posts', array( $this, 'ajax_search_posts' ) );
		add_action( 'wp_ajax_fpp_interlinking_scan_keyword', array( $this, 'ajax_scan_keyword' ) );
		add_action( 'wp_ajax_fpp_interlinking_suggest_keywords', array( $this, 'ajax_suggest_keywords' ) );
	}

	/**
	 * Register the settings page under Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'FPP Interlinking', 'fpp-interlinking' ),
			__( 'FPP Interlinking', 'fpp-interlinking' ),
			'manage_options',
			'fpp-interlinking',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on the plugin's own settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
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
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'nonce'                => wp_create_nonce( 'fpp_interlinking_nonce' ),
			'max_replacements_cap' => FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT,
			'i18n'                 => array(
				'confirm_delete'   => esc_html__( 'Are you sure you want to delete this keyword mapping?', 'fpp-interlinking' ),
				'required'         => esc_html__( 'Keyword and Target URL are required.', 'fpp-interlinking' ),
				'request_failed'   => esc_html__( 'Request failed. Please try again.', 'fpp-interlinking' ),
				// Scan per keyword.
				'scan_found'       => esc_html__( 'Found %d matching posts/pages:', 'fpp-interlinking' ),
				'scan_no_results'  => esc_html__( 'No posts or pages found matching this keyword.', 'fpp-interlinking' ),
				'use_this_url'     => esc_html__( 'Use this URL', 'fpp-interlinking' ),
				'updating'         => esc_html__( 'Updating...', 'fpp-interlinking' ),
				'close'            => esc_html__( 'Close', 'fpp-interlinking' ),
				// Suggest keywords.
				'scanning'         => esc_html__( 'Scanning...', 'fpp-interlinking' ),
				'scan_post_titles' => esc_html__( 'Scan Post Titles', 'fpp-interlinking' ),
				'no_suggestions'   => esc_html__( 'No published posts or pages found.', 'fpp-interlinking' ),
				'already_mapped'   => esc_html__( 'Already mapped', 'fpp-interlinking' ),
				'available'        => esc_html__( 'Available', 'fpp-interlinking' ),
				'add_as_keyword'   => esc_html__( 'Add as Keyword', 'fpp-interlinking' ),
				'page_info'        => esc_html__( 'Page %1$d of %2$d (%3$d total)', 'fpp-interlinking' ),
				// Quick-add search.
				'no_posts_found'   => esc_html__( 'No posts found.', 'fpp-interlinking' ),
			),
		) );
	}

	/**
	 * Render the admin settings page.
	 *
	 * All dynamic values are escaped at the point of output (late escaping).
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		$keywords         = FPP_Interlinking_DB::get_all_keywords();
		$max_replacements = get_option( 'fpp_interlinking_max_replacements', 1 );
		$nofollow         = get_option( 'fpp_interlinking_nofollow', 0 );
		$new_tab          = get_option( 'fpp_interlinking_new_tab', 1 );
		$case_sensitive   = get_option( 'fpp_interlinking_case_sensitive', 0 );
		$excluded_posts   = get_option( 'fpp_interlinking_excluded_posts', '' );
		$max_cap          = FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="fpp-notices"></div>

			<!-- Global Settings -->
			<div class="fpp-section fpp-settings-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-settings">
					<?php esc_html_e( 'Global Settings', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-settings-content">
					<table class="form-table">
						<tr>
							<th><label for="fpp-global-max-replacements"><?php esc_html_e( 'Max replacements per keyword', 'fpp-interlinking' ); ?></label></th>
							<td>
								<input type="number" id="fpp-global-max-replacements" min="1" max="<?php echo esc_attr( $max_cap ); ?>" value="<?php echo esc_attr( $max_replacements ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Maximum number of times each keyword gets linked per post. Set to 1 to only link the first occurrence.', 'fpp-interlinking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-global-nofollow"><?php esc_html_e( 'Add rel="nofollow"', 'fpp-interlinking' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="fpp-global-nofollow" value="1" <?php checked( $nofollow, 1 ); ?> />
									<?php esc_html_e( 'Add nofollow attribute to generated links', 'fpp-interlinking' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-global-new-tab"><?php esc_html_e( 'Open in new tab', 'fpp-interlinking' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="fpp-global-new-tab" value="1" <?php checked( $new_tab, 1 ); ?> />
									<?php esc_html_e( 'Open links in a new browser tab', 'fpp-interlinking' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-global-case-sensitive"><?php esc_html_e( 'Case sensitive', 'fpp-interlinking' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="fpp-global-case-sensitive" value="1" <?php checked( $case_sensitive, 1 ); ?> />
									<?php esc_html_e( 'Match keywords with exact case', 'fpp-interlinking' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When unchecked, "WordPress" will also match "wordpress", "WORDPRESS", etc.', 'fpp-interlinking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-global-excluded-posts"><?php esc_html_e( 'Excluded posts/pages', 'fpp-interlinking' ); ?></label></th>
							<td>
								<textarea id="fpp-global-excluded-posts" rows="3" class="large-text"><?php echo esc_textarea( $excluded_posts ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Comma-separated list of post/page IDs to exclude from keyword replacement.', 'fpp-interlinking' ); ?></p>
							</td>
						</tr>
					</table>
					<p>
						<button type="button" id="fpp-save-settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'fpp-interlinking' ); ?></button>
					</p>
				</div>
			</div>

			<hr />

			<!-- Quick-Add from Post Search -->
			<div class="fpp-section fpp-quick-add-section">
				<h2><?php esc_html_e( 'Quick Add from Post Search', 'fpp-interlinking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Search for an existing post or page to auto-fill the keyword and URL fields below.', 'fpp-interlinking' ); ?></p>
				<div class="fpp-search-wrapper">
					<input type="text" id="fpp-post-search" class="regular-text"
						placeholder="<?php esc_attr_e( 'Type to search posts and pages...', 'fpp-interlinking' ); ?>"
						autocomplete="off" />
					<div id="fpp-post-search-results" class="fpp-search-dropdown" style="display:none;"></div>
				</div>
			</div>

			<hr />

			<!-- Add / Edit Keyword Form -->
			<div class="fpp-section fpp-add-keyword-section">
				<h2 id="fpp-form-title"><?php esc_html_e( 'Add New Keyword Mapping', 'fpp-interlinking' ); ?></h2>
				<input type="hidden" id="fpp-edit-id" value="" />
				<table class="form-table">
					<tr>
						<th><label for="fpp-keyword"><?php esc_html_e( 'Keyword', 'fpp-interlinking' ); ?></label></th>
						<td><input type="text" id="fpp-keyword" class="regular-text" placeholder="<?php esc_attr_e( 'Enter keyword or phrase', 'fpp-interlinking' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="fpp-target-url"><?php esc_html_e( 'Target URL', 'fpp-interlinking' ); ?></label></th>
						<td><input type="url" id="fpp-target-url" class="regular-text" placeholder="https://example.com/page" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Per-mapping overrides', 'fpp-interlinking' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" id="fpp-per-nofollow" value="1" />
									<?php esc_html_e( 'Nofollow', 'fpp-interlinking' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" id="fpp-per-new-tab" value="1" checked />
									<?php esc_html_e( 'Open in new tab', 'fpp-interlinking' ); ?>
								</label>
								<br />
								<label>
									<?php esc_html_e( 'Max replacements:', 'fpp-interlinking' ); ?>
									<input type="number" id="fpp-per-max-replacements" min="0" max="<?php echo esc_attr( $max_cap ); ?>" value="0" class="small-text" />
								</label>
								<p class="description"><?php esc_html_e( 'Set to 0 to use the global setting.', 'fpp-interlinking' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" id="fpp-add-keyword" class="button button-primary"><?php esc_html_e( 'Add Keyword', 'fpp-interlinking' ); ?></button>
					<button type="button" id="fpp-update-keyword" class="button button-primary" style="display:none;"><?php esc_html_e( 'Update Keyword', 'fpp-interlinking' ); ?></button>
					<button type="button" id="fpp-cancel-edit" class="button" style="display:none;"><?php esc_html_e( 'Cancel', 'fpp-interlinking' ); ?></button>
				</p>
			</div>

			<hr />

			<!-- Keywords Table -->
			<div class="fpp-section">
				<h2><?php esc_html_e( 'Keyword Mappings', 'fpp-interlinking' ); ?></h2>
				<?php if ( empty( $keywords ) ) : ?>
					<p id="fpp-no-keywords"><?php esc_html_e( 'No keyword mappings found. Add your first one above.', 'fpp-interlinking' ); ?></p>
				<?php endif; ?>
				<table class="wp-list-table widefat fixed striped" id="fpp-keywords-table" <?php echo empty( $keywords ) ? 'style="display:none;"' : ''; ?>>
					<thead>
						<tr>
							<th class="column-keyword"><?php esc_html_e( 'Keyword', 'fpp-interlinking' ); ?></th>
							<th class="column-url"><?php esc_html_e( 'Target URL', 'fpp-interlinking' ); ?></th>
							<th class="column-nofollow"><?php esc_html_e( 'Nofollow', 'fpp-interlinking' ); ?></th>
							<th class="column-newtab"><?php esc_html_e( 'New Tab', 'fpp-interlinking' ); ?></th>
							<th class="column-max"><?php esc_html_e( 'Max', 'fpp-interlinking' ); ?></th>
							<th class="column-active"><?php esc_html_e( 'Active', 'fpp-interlinking' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'fpp-interlinking' ); ?></th>
						</tr>
					</thead>
					<tbody id="fpp-keywords-tbody">
						<?php foreach ( $keywords as $kw ) : ?>
							<tr id="fpp-keyword-row-<?php echo esc_attr( $kw['id'] ); ?>">
								<td class="column-keyword"><?php echo esc_html( $kw['keyword'] ); ?></td>
								<td class="column-url">
									<a href="<?php echo esc_url( $kw['target_url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $kw['target_url'] ); ?>
									</a>
								</td>
								<td class="column-nofollow"><?php echo esc_html( $kw['nofollow'] ? __( 'Yes', 'fpp-interlinking' ) : __( 'No', 'fpp-interlinking' ) ); ?></td>
								<td class="column-newtab"><?php echo esc_html( $kw['new_tab'] ? __( 'Yes', 'fpp-interlinking' ) : __( 'No', 'fpp-interlinking' ) ); ?></td>
								<td class="column-max"><?php echo $kw['max_replacements'] ? esc_html( $kw['max_replacements'] ) : esc_html__( 'Global', 'fpp-interlinking' ); ?></td>
								<td class="column-active">
									<span class="<?php echo $kw['is_active'] ? 'fpp-badge-active' : 'fpp-badge-inactive'; ?>">
										<?php echo esc_html( $kw['is_active'] ? __( 'Active', 'fpp-interlinking' ) : __( 'Inactive', 'fpp-interlinking' ) ); ?>
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
										<?php esc_html_e( 'Edit', 'fpp-interlinking' ); ?>
									</button>
									<button type="button" class="button button-small fpp-scan-keyword"
										data-id="<?php echo esc_attr( $kw['id'] ); ?>"
										data-keyword="<?php echo esc_attr( $kw['keyword'] ); ?>">
										<?php esc_html_e( 'Scan', 'fpp-interlinking' ); ?>
									</button>
									<button type="button" class="button button-small fpp-toggle-keyword"
										data-id="<?php echo esc_attr( $kw['id'] ); ?>"
										data-active="<?php echo esc_attr( $kw['is_active'] ); ?>">
										<?php echo esc_html( $kw['is_active'] ? __( 'Disable', 'fpp-interlinking' ) : __( 'Enable', 'fpp-interlinking' ) ); ?>
									</button>
									<button type="button" class="button button-small fpp-delete-keyword"
										data-id="<?php echo esc_attr( $kw['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'fpp-interlinking' ); ?>
									</button>
								</td>
							</tr>
							<tr id="fpp-scan-results-row-<?php echo esc_attr( $kw['id'] ); ?>" class="fpp-scan-results-row" style="display:none;">
								<td colspan="7">
									<div class="fpp-scan-results-container">
										<p class="fpp-scan-results-loading" style="display:none;">
											<span class="spinner is-active"></span>
											<?php esc_html_e( 'Scanning...', 'fpp-interlinking' ); ?>
										</p>
										<div class="fpp-scan-results-list"></div>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<hr />

			<!-- Suggest Keywords from Content -->
			<div class="fpp-section fpp-suggest-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-suggestions">
					<?php esc_html_e( 'Suggest Keywords from Content', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-suggestions-content" style="display:none;">
					<p class="description">
						<?php esc_html_e( 'Scan your published posts and pages to discover potential keyword mappings based on their titles.', 'fpp-interlinking' ); ?>
					</p>
					<p>
						<button type="button" id="fpp-scan-titles" class="button button-secondary">
							<?php esc_html_e( 'Scan Post Titles', 'fpp-interlinking' ); ?>
						</button>
					</p>
					<div id="fpp-suggestions-results" style="display:none;">
						<table class="wp-list-table widefat fixed striped" id="fpp-suggestions-table">
							<thead>
								<tr>
									<th class="column-sg-title"><?php esc_html_e( 'Post Title (Keyword)', 'fpp-interlinking' ); ?></th>
									<th class="column-sg-type"><?php esc_html_e( 'Type', 'fpp-interlinking' ); ?></th>
									<th class="column-sg-url"><?php esc_html_e( 'URL', 'fpp-interlinking' ); ?></th>
									<th class="column-sg-status"><?php esc_html_e( 'Status', 'fpp-interlinking' ); ?></th>
									<th class="column-sg-actions"><?php esc_html_e( 'Actions', 'fpp-interlinking' ); ?></th>
								</tr>
							</thead>
							<tbody id="fpp-suggestions-tbody"></tbody>
						</table>
						<div id="fpp-suggestions-pagination" class="tablenav bottom">
							<div class="tablenav-pages">
								<span class="fpp-suggestions-info"></span>
								<button type="button" id="fpp-suggestions-prev" class="button button-small" disabled>&laquo; <?php esc_html_e( 'Previous', 'fpp-interlinking' ); ?></button>
								<button type="button" id="fpp-suggestions-next" class="button button-small"><?php esc_html_e( 'Next', 'fpp-interlinking' ); ?> &raquo;</button>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	/* ── AJAX Handlers ─────────────────────────────────────────────────────
	 *
	 * Security: each handler checks nonce + capability before proceeding.
	 * Input:    sanitised early with sanitize_text_field / esc_url_raw / absint.
	 * Output:   wp_send_json_* handles JSON encoding and Content-Type header.
	 * ──────────────────────────────────────────────────────────────────── */

	/**
	 * AJAX: Add a new keyword mapping.
	 *
	 * @since 1.0.0
	 */
	public function ajax_add_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$keyword    = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';

		if ( empty( $keyword ) || empty( $target_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Keyword and URL are required.', 'fpp-interlinking' ) ) );
		}

		// Duplicate check.
		if ( FPP_Interlinking_DB::keyword_exists( $keyword ) ) {
			wp_send_json_error( array(
				'message' => __( 'This keyword already exists. Please use a different keyword or edit the existing one.', 'fpp-interlinking' ),
			) );
		}

		$nofollow         = isset( $_POST['nofollow'] ) ? absint( $_POST['nofollow'] ) : 0;
		$new_tab          = isset( $_POST['new_tab'] ) ? absint( $_POST['new_tab'] ) : 1;
		$max_replacements = isset( $_POST['max_replacements'] ) ? absint( $_POST['max_replacements'] ) : 0;

		$id = FPP_Interlinking_DB::insert_keyword( array(
			'keyword'          => $keyword,
			'target_url'       => $target_url,
			'nofollow'         => $nofollow,
			'new_tab'          => $new_tab,
			'max_replacements' => $max_replacements,
		) );

		if ( $id ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array(
				'message' => __( 'Keyword added successfully.', 'fpp-interlinking' ),
				'keyword' => array(
					'id'               => $id,
					'keyword'          => $keyword,
					'target_url'       => $target_url,
					'nofollow'         => $nofollow,
					'new_tab'          => $new_tab,
					'max_replacements' => min( $max_replacements, FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT ),
					'is_active'        => 1,
				),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add keyword.', 'fpp-interlinking' ) ) );
		}
	}

	/**
	 * AJAX: Update an existing keyword mapping.
	 *
	 * @since 1.0.0
	 */
	public function ajax_update_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$id         = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$keyword    = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';

		if ( ! $id || empty( $keyword ) || empty( $target_url ) ) {
			wp_send_json_error( array( 'message' => __( 'ID, keyword, and URL are required.', 'fpp-interlinking' ) ) );
		}

		// Duplicate check (exclude current row).
		if ( FPP_Interlinking_DB::keyword_exists( $keyword, $id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Another keyword with this text already exists.', 'fpp-interlinking' ),
			) );
		}

		$nofollow         = isset( $_POST['nofollow'] ) ? absint( $_POST['nofollow'] ) : 0;
		$new_tab          = isset( $_POST['new_tab'] ) ? absint( $_POST['new_tab'] ) : 1;
		$max_replacements = isset( $_POST['max_replacements'] ) ? absint( $_POST['max_replacements'] ) : 0;

		$result = FPP_Interlinking_DB::update_keyword( $id, array(
			'keyword'          => $keyword,
			'target_url'       => $target_url,
			'nofollow'         => $nofollow,
			'new_tab'          => $new_tab,
			'max_replacements' => $max_replacements,
		) );

		if ( false !== $result ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array(
				'message' => __( 'Keyword updated successfully.', 'fpp-interlinking' ),
				'keyword' => array(
					'id'               => $id,
					'keyword'          => $keyword,
					'target_url'       => $target_url,
					'nofollow'         => $nofollow,
					'new_tab'          => $new_tab,
					'max_replacements' => min( $max_replacements, FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT ),
					'is_active'        => 1,
				),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update keyword.', 'fpp-interlinking' ) ) );
		}
	}

	/**
	 * AJAX: Delete a keyword mapping.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid keyword ID.', 'fpp-interlinking' ) ) );
		}

		$result = FPP_Interlinking_DB::delete_keyword( $id );

		if ( $result ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array( 'message' => __( 'Keyword deleted successfully.', 'fpp-interlinking' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete keyword.', 'fpp-interlinking' ) ) );
		}
	}

	/**
	 * AJAX: Toggle a keyword's active state.
	 *
	 * @since 1.0.0
	 */
	public function ajax_toggle_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$id        = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$is_active = isset( $_POST['is_active'] ) ? absint( $_POST['is_active'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid keyword ID.', 'fpp-interlinking' ) ) );
		}

		$result = FPP_Interlinking_DB::toggle_keyword( $id, $is_active );

		if ( false !== $result ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array(
				'message'   => $is_active
					? __( 'Keyword enabled.', 'fpp-interlinking' )
					: __( 'Keyword disabled.', 'fpp-interlinking' ),
				'is_active' => $is_active,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to toggle keyword.', 'fpp-interlinking' ) ) );
		}
	}

	/**
	 * AJAX: Save global settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		// Clamp max_replacements between 1 and the defined cap.
		$max_replacements = isset( $_POST['max_replacements'] ) ? absint( $_POST['max_replacements'] ) : 1;
		$max_replacements = max( 1, min( $max_replacements, FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT ) );

		update_option( 'fpp_interlinking_max_replacements', $max_replacements );
		update_option( 'fpp_interlinking_nofollow', isset( $_POST['nofollow'] ) ? absint( $_POST['nofollow'] ) : 0 );
		update_option( 'fpp_interlinking_new_tab', isset( $_POST['new_tab'] ) ? absint( $_POST['new_tab'] ) : 0 );
		update_option( 'fpp_interlinking_case_sensitive', isset( $_POST['case_sensitive'] ) ? absint( $_POST['case_sensitive'] ) : 0 );
		update_option( 'fpp_interlinking_excluded_posts', isset( $_POST['excluded_posts'] ) ? sanitize_text_field( wp_unslash( $_POST['excluded_posts'] ) ) : '' );

		delete_transient( 'fpp_interlinking_keywords_cache' );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'fpp-interlinking' ) ) );
	}

	/* ── v1.2.0: Scan, Suggest & Search Handlers ──────────────────────── */

	/**
	 * AJAX: Search posts/pages by title for autocomplete (Quick-Add).
	 *
	 * Returns a lightweight result set (max 10) for fast response.
	 *
	 * @since 1.2.0
	 */
	public function ajax_search_posts() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$query = new WP_Query( array(
			's'                => $search,
			'post_type'        => array( 'post', 'page' ),
			'post_status'      => 'publish',
			'posts_per_page'   => 10,
			'orderby'          => 'relevance',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		) );

		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$type_obj  = get_post_type_object( get_post_type() );
				$results[] = array(
					'id'        => get_the_ID(),
					'title'     => get_the_title(),
					'permalink' => get_permalink(),
					'post_type' => $type_obj ? $type_obj->labels->singular_name : get_post_type(),
				);
			}
			wp_reset_postdata();
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Scan for posts/pages whose title matches a keyword.
	 *
	 * Used by the "Scan" button on each keyword row.
	 *
	 * @since 1.2.0
	 */
	public function ajax_scan_keyword() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

		if ( empty( $keyword ) ) {
			wp_send_json_error( array( 'message' => __( 'Keyword is required.', 'fpp-interlinking' ) ) );
		}

		$query = new WP_Query( array(
			's'                => $keyword,
			'post_type'        => array( 'post', 'page' ),
			'post_status'      => 'publish',
			'posts_per_page'   => 20,
			'orderby'          => 'relevance',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		) );

		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$type_obj  = get_post_type_object( get_post_type() );
				$results[] = array(
					'id'        => get_the_ID(),
					'title'     => get_the_title(),
					'permalink' => get_permalink(),
					'post_type' => $type_obj ? $type_obj->labels->singular_name : get_post_type(),
				);
			}
			wp_reset_postdata();
		}

		wp_send_json_success( array(
			'results' => $results,
			'keyword' => $keyword,
		) );
	}

	/**
	 * AJAX: Suggest keywords from published post/page titles.
	 *
	 * Returns paginated results with an `already_added` flag for each title
	 * that matches an existing keyword mapping.
	 *
	 * @since 1.2.0
	 */
	public function ajax_suggest_keywords() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 30;

		$query = new WP_Query( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => max( 1, $page ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		// Build a lookup of existing keywords (lowercased) for O(1) checks.
		$existing_keywords = FPP_Interlinking_DB::get_all_keywords();
		$existing_map      = array();
		foreach ( $existing_keywords as $ek ) {
			$existing_map[ strtolower( $ek['keyword'] ) ] = true;
		}

		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$title    = get_the_title();
				$type_obj = get_post_type_object( get_post_type() );
				$results[] = array(
					'id'            => get_the_ID(),
					'title'         => $title,
					'permalink'     => get_permalink(),
					'post_type'     => $type_obj ? $type_obj->labels->singular_name : get_post_type(),
					'already_added' => isset( $existing_map[ strtolower( $title ) ] ),
				);
			}
			wp_reset_postdata();
		}

		wp_send_json_success( array(
			'results'     => $results,
			'page'        => max( 1, $page ),
			'total_pages' => $query->max_num_pages,
			'total_posts' => $query->found_posts,
		) );
	}
}
