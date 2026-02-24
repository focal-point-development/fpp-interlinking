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

		// v2.0.0: AI-powered endpoints.
		add_action( 'wp_ajax_fpp_interlinking_save_ai_settings', array( $this, 'ajax_save_ai_settings' ) );
		add_action( 'wp_ajax_fpp_interlinking_test_ai_connection', array( $this, 'ajax_test_ai_connection' ) );
		add_action( 'wp_ajax_fpp_interlinking_ai_extract_keywords', array( $this, 'ajax_ai_extract_keywords' ) );
		add_action( 'wp_ajax_fpp_interlinking_ai_score_relevance', array( $this, 'ajax_ai_score_relevance' ) );
		add_action( 'wp_ajax_fpp_interlinking_ai_content_gaps', array( $this, 'ajax_ai_content_gaps' ) );
		add_action( 'wp_ajax_fpp_interlinking_ai_auto_generate', array( $this, 'ajax_ai_auto_generate' ) );
		add_action( 'wp_ajax_fpp_interlinking_ai_add_mapping', array( $this, 'ajax_ai_add_mapping' ) );

		// v2.1.0: Paginated table, bulk ops, import/export.
		add_action( 'wp_ajax_fpp_interlinking_load_keywords', array( $this, 'ajax_load_keywords' ) );
		add_action( 'wp_ajax_fpp_interlinking_bulk_action', array( $this, 'ajax_bulk_action' ) );
		add_action( 'wp_ajax_fpp_interlinking_export_csv', array( $this, 'ajax_export_csv' ) );
		add_action( 'wp_ajax_fpp_interlinking_import_csv', array( $this, 'ajax_import_csv' ) );
	}

	/**
	 * Register the settings page under Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'WP Interlinking', 'fpp-interlinking' ),
			__( 'WP Interlinking', 'fpp-interlinking' ),
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
				// AI features.
				'ai_processing'          => esc_html__( 'AI is processing...', 'fpp-interlinking' ),
				'ai_extract_btn'         => esc_html__( 'Extract Keywords', 'fpp-interlinking' ),
				'ai_score_btn'           => esc_html__( 'Score Relevance', 'fpp-interlinking' ),
				'ai_gaps_btn'            => esc_html__( 'Analyse Gaps', 'fpp-interlinking' ),
				'ai_generate_btn'        => esc_html__( 'Auto-Generate', 'fpp-interlinking' ),
				'ai_no_results'          => esc_html__( 'No results found. Try again with different content.', 'fpp-interlinking' ),
				'ai_add_mapping'         => esc_html__( 'Add Mapping', 'fpp-interlinking' ),
				'ai_add_all'             => esc_html__( 'Add All', 'fpp-interlinking' ),
				'ai_added'               => esc_html__( 'Added!', 'fpp-interlinking' ),
				'ai_connection_ok'       => esc_html__( 'Connection successful!', 'fpp-interlinking' ),
				'ai_select_post'         => esc_html__( 'Select a post to analyse', 'fpp-interlinking' ),
				'ai_enter_keyword'       => esc_html__( 'Enter a keyword first', 'fpp-interlinking' ),
				'ai_analysed_info'       => esc_html__( 'Analysed %1$d of %2$d posts', 'fpp-interlinking' ),
				'ai_confidence'          => esc_html__( 'Confidence', 'fpp-interlinking' ),
				'ai_relevance'           => esc_html__( 'Relevance', 'fpp-interlinking' ),
				'ai_no_gaps'             => esc_html__( 'No content gaps found — your interlinking looks good!', 'fpp-interlinking' ),
				/* translators: %d: number of keyword mappings added. */
				'ai_added_count'         => esc_html__( 'Added %d keyword mappings.', 'fpp-interlinking' ),
				// Shared button labels.
				'saving'                 => esc_html__( 'Saving...', 'fpp-interlinking' ),
				'adding'                 => esc_html__( 'Adding...', 'fpp-interlinking' ),
				'adding_all'             => esc_html__( 'Adding all...', 'fpp-interlinking' ),
				'add_keyword'            => esc_html__( 'Add Keyword', 'fpp-interlinking' ),
				'update_keyword'         => esc_html__( 'Update Keyword', 'fpp-interlinking' ),
				'save_settings'          => esc_html__( 'Save Settings', 'fpp-interlinking' ),
				'save_ai_settings'       => esc_html__( 'Save AI Settings', 'fpp-interlinking' ),
				'invalid_url'            => esc_html__( 'Please enter a valid URL starting with http:// or https://.', 'fpp-interlinking' ),
				'add_new_mapping'        => esc_html__( 'Add New Keyword Mapping', 'fpp-interlinking' ),
				'edit_mapping'           => esc_html__( 'Edit Keyword Mapping', 'fpp-interlinking' ),
				'active'                 => esc_html__( 'Active', 'fpp-interlinking' ),
				'inactive'               => esc_html__( 'Inactive', 'fpp-interlinking' ),
				'disable'                => esc_html__( 'Disable', 'fpp-interlinking' ),
				'enable'                 => esc_html__( 'Enable', 'fpp-interlinking' ),
				// Bulk operations.
				'bulk_select_action'     => esc_html__( 'Please select a bulk action.', 'fpp-interlinking' ),
				'bulk_select_items'      => esc_html__( 'Please select at least one keyword.', 'fpp-interlinking' ),
				'bulk_confirm_delete'    => esc_html__( 'Are you sure you want to delete the selected keywords?', 'fpp-interlinking' ),
				'bulk_success'           => esc_html__( 'Bulk action completed successfully.', 'fpp-interlinking' ),
				// Import/Export.
				'export_empty'           => esc_html__( 'No keywords to export.', 'fpp-interlinking' ),
				'import_select_file'     => esc_html__( 'Please select a CSV file to import.', 'fpp-interlinking' ),
				'import_success'         => esc_html__( 'Import complete: %1$d imported, %2$d skipped, %3$d errors.', 'fpp-interlinking' ),
				'importing'              => esc_html__( 'Importing...', 'fpp-interlinking' ),
				// Pagination.
				'loading'                => esc_html__( 'Loading...', 'fpp-interlinking' ),
				'no_keywords'            => esc_html__( 'No keyword mappings found.', 'fpp-interlinking' ),
				'keyword_page_info'      => esc_html__( 'Showing %1$d–%2$d of %3$d', 'fpp-interlinking' ),
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
		$keywords            = FPP_Interlinking_DB::get_all_keywords();
		$max_replacements    = get_option( 'fpp_interlinking_max_replacements', 1 );
		$nofollow            = get_option( 'fpp_interlinking_nofollow', 0 );
		$new_tab             = get_option( 'fpp_interlinking_new_tab', 1 );
		$case_sensitive      = get_option( 'fpp_interlinking_case_sensitive', 0 );
		$excluded_posts      = get_option( 'fpp_interlinking_excluded_posts', '' );
		$max_links_per_post  = get_option( 'fpp_interlinking_max_links_per_post', 0 );
		$post_types_setting  = get_option( 'fpp_interlinking_post_types', 'post,page' );
		$active_post_types   = array_map( 'trim', explode( ',', $post_types_setting ) );
		$max_cap             = FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="fpp-notices" role="alert" aria-live="polite"></div>

			<!-- Global Settings -->
			<div class="fpp-section fpp-settings-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-settings" role="button" tabindex="0" aria-expanded="true" aria-controls="fpp-settings-content">
					<?php esc_html_e( 'Global Settings', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-settings-content" role="region" aria-labelledby="fpp-toggle-settings">
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
							<th><label for="fpp-global-max-links-per-post"><?php esc_html_e( 'Max links per post', 'fpp-interlinking' ); ?></label></th>
							<td>
								<input type="number" id="fpp-global-max-links-per-post" min="0" max="500" value="<?php echo esc_attr( $max_links_per_post ); ?>" class="small-text" />
								<p class="description"><?php esc_html_e( 'Maximum total auto-generated links per post/page. Set to 0 for unlimited. Recommended: 10–50 for SEO.', 'fpp-interlinking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Post types', 'fpp-interlinking' ); ?></th>
							<td>
								<fieldset id="fpp-post-types-fieldset">
									<?php
									$all_post_types = get_post_types( array( 'public' => true ), 'objects' );
									foreach ( $all_post_types as $pt ) :
										if ( 'attachment' === $pt->name ) {
											continue;
										}
									?>
										<label>
											<input type="checkbox" class="fpp-post-type-checkbox" value="<?php echo esc_attr( $pt->name ); ?>"
												<?php checked( in_array( $pt->name, $active_post_types, true ) ); ?> />
											<?php echo esc_html( $pt->labels->singular_name ); ?>
											<code>(<?php echo esc_html( $pt->name ); ?>)</code>
										</label><br />
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Select which post types should have keyword replacement applied.', 'fpp-interlinking' ); ?></p>
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

			<!-- Keywords Table (AJAX-powered with pagination, search, bulk ops) -->
			<div class="fpp-section">
				<h2><?php esc_html_e( 'Keyword Mappings', 'fpp-interlinking' ); ?></h2>

				<!-- Table toolbar: search, bulk actions, import/export -->
				<div class="fpp-table-toolbar">
					<div class="fpp-toolbar-left">
						<select id="fpp-bulk-action">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'fpp-interlinking' ); ?></option>
							<option value="enable"><?php esc_html_e( 'Enable', 'fpp-interlinking' ); ?></option>
							<option value="disable"><?php esc_html_e( 'Disable', 'fpp-interlinking' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'fpp-interlinking' ); ?></option>
						</select>
						<button type="button" id="fpp-bulk-apply" class="button"><?php esc_html_e( 'Apply', 'fpp-interlinking' ); ?></button>
					</div>
					<div class="fpp-toolbar-center">
						<button type="button" id="fpp-export-csv" class="button">
							<span class="dashicons dashicons-download" aria-hidden="true"></span>
							<?php esc_html_e( 'Export CSV', 'fpp-interlinking' ); ?>
						</button>
						<label class="button fpp-import-label" for="fpp-import-csv-file">
							<span class="dashicons dashicons-upload" aria-hidden="true"></span>
							<?php esc_html_e( 'Import CSV', 'fpp-interlinking' ); ?>
						</label>
						<input type="file" id="fpp-import-csv-file" accept=".csv" style="display:none;" />
					</div>
					<div class="fpp-toolbar-right">
						<input type="search" id="fpp-keyword-search" class="regular-text"
							placeholder="<?php esc_attr_e( 'Search keywords...', 'fpp-interlinking' ); ?>" />
					</div>
				</div>

				<p id="fpp-no-keywords" style="display:none;"><?php esc_html_e( 'No keyword mappings found. Add your first one above.', 'fpp-interlinking' ); ?></p>
				<table class="wp-list-table widefat fixed striped" id="fpp-keywords-table">
					<thead>
						<tr>
							<th class="column-cb check-column"><input type="checkbox" id="fpp-select-all" /></th>
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
						<tr><td colspan="8"><span class="spinner is-active" style="float:none;"></span> <?php esc_html_e( 'Loading...', 'fpp-interlinking' ); ?></td></tr>
					</tbody>
				</table>
				<!-- Pagination -->
				<div id="fpp-keywords-pagination" class="tablenav bottom" style="display:none;">
					<div class="tablenav-pages">
						<span class="fpp-keywords-info"></span>
						<button type="button" id="fpp-keywords-prev" class="button button-small" disabled>&laquo; <?php esc_html_e( 'Previous', 'fpp-interlinking' ); ?></button>
						<button type="button" id="fpp-keywords-next" class="button button-small"><?php esc_html_e( 'Next', 'fpp-interlinking' ); ?> &raquo;</button>
					</div>
				</div>
			</div>

			<hr />

			<!-- Suggest Keywords from Content -->
			<div class="fpp-section fpp-suggest-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-suggestions" role="button" tabindex="0" aria-expanded="false" aria-controls="fpp-suggestions-content">
					<?php esc_html_e( 'Suggest Keywords from Content', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-suggestions-content" role="region" aria-labelledby="fpp-toggle-suggestions" style="display:none;">
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

			<hr />

			<!-- AI Settings -->
			<div class="fpp-section fpp-ai-settings-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-ai-settings" role="button" tabindex="0" aria-expanded="false" aria-controls="fpp-ai-settings-content">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Settings', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-ai-settings-content" role="region" aria-labelledby="fpp-toggle-ai-settings" style="display:none;">
					<table class="form-table">
						<tr>
							<th><label for="fpp-ai-provider"><?php esc_html_e( 'AI Provider', 'fpp-interlinking' ); ?></label></th>
							<td>
								<select id="fpp-ai-provider">
									<option value="openai" <?php selected( FPP_Interlinking_AI::get_provider(), 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'fpp-interlinking' ); ?></option>
									<option value="anthropic" <?php selected( FPP_Interlinking_AI::get_provider(), 'anthropic' ); ?>><?php esc_html_e( 'Anthropic (Claude)', 'fpp-interlinking' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-ai-api-key"><?php esc_html_e( 'API Key', 'fpp-interlinking' ); ?></label></th>
							<td>
								<?php $masked = FPP_Interlinking_AI::get_masked_key(); ?>
								<input type="password" id="fpp-ai-api-key" class="regular-text"
									placeholder="<?php echo $masked ? esc_attr( $masked ) : esc_attr__( 'Enter your API key', 'fpp-interlinking' ); ?>"
									autocomplete="off" />
								<p class="description">
									<?php if ( $masked ) : ?>
										<?php printf( esc_html__( 'Current key: %s — Leave blank to keep existing key.', 'fpp-interlinking' ), '<code>' . esc_html( $masked ) . '</code>' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Enter your OpenAI or Anthropic API key. It will be stored encrypted.', 'fpp-interlinking' ); ?>
									<?php endif; ?>
								</p>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-ai-model"><?php esc_html_e( 'Model', 'fpp-interlinking' ); ?></label></th>
							<td>
								<input type="text" id="fpp-ai-model" class="regular-text"
									value="<?php echo esc_attr( FPP_Interlinking_AI::get_model() ); ?>"
									placeholder="gpt-4o-mini" />
								<p class="description"><?php esc_html_e( 'OpenAI: gpt-4o-mini, gpt-4o, gpt-4-turbo. Anthropic: claude-sonnet-4-20250514, claude-haiku-4-5-20251001.', 'fpp-interlinking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="fpp-ai-max-tokens"><?php esc_html_e( 'Max Tokens', 'fpp-interlinking' ); ?></label></th>
							<td>
								<input type="number" id="fpp-ai-max-tokens" class="small-text" min="500" max="8000"
									value="<?php echo esc_attr( FPP_Interlinking_AI::get_max_tokens() ); ?>" />
								<p class="description"><?php esc_html_e( 'Maximum tokens for AI responses (500-8000). Higher values cost more but allow richer analysis.', 'fpp-interlinking' ); ?></p>
							</td>
						</tr>
					</table>
					<p>
						<button type="button" id="fpp-save-ai-settings" class="button button-primary"><?php esc_html_e( 'Save AI Settings', 'fpp-interlinking' ); ?></button>
						<button type="button" id="fpp-test-ai-connection" class="button"><?php esc_html_e( 'Test Connection', 'fpp-interlinking' ); ?></button>
						<span id="fpp-ai-connection-status"></span>
					</p>
				</div>
			</div>

			<hr />

			<!-- AI Keyword Extraction -->
			<div class="fpp-section fpp-ai-section fpp-ai-extract-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-ai-extract" role="button" tabindex="0" aria-expanded="false" aria-controls="fpp-ai-extract-content">
					<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Keyword Extraction', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-ai-extract-content" role="region" aria-labelledby="fpp-toggle-ai-extract" style="display:none;">
					<p class="description"><?php esc_html_e( 'Select a post or page to analyse its content and extract SEO keywords for interlinking.', 'fpp-interlinking' ); ?></p>
					<div class="fpp-ai-controls">
						<div class="fpp-search-wrapper">
							<input type="text" id="fpp-ai-extract-search" class="regular-text"
								placeholder="<?php esc_attr_e( 'Search for a post to analyse...', 'fpp-interlinking' ); ?>"
								autocomplete="off" />
							<div id="fpp-ai-extract-search-results" class="fpp-search-dropdown" style="display:none;"></div>
						</div>
						<input type="hidden" id="fpp-ai-extract-post-id" value="" />
						<span id="fpp-ai-extract-selected" class="fpp-ai-selected-post"></span>
						<button type="button" id="fpp-ai-extract-btn" class="button button-primary" disabled>
							<span class="dashicons dashicons-lightbulb"></span>
							<?php esc_html_e( 'Extract Keywords', 'fpp-interlinking' ); ?>
						</button>
					</div>
					<div id="fpp-ai-extract-results" class="fpp-ai-results" style="display:none;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th class="column-ai-keyword"><?php esc_html_e( 'Keyword', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-relevance"><?php esc_html_e( 'Relevance', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-actions"><?php esc_html_e( 'Actions', 'fpp-interlinking' ); ?></th>
								</tr>
							</thead>
							<tbody id="fpp-ai-extract-tbody"></tbody>
						</table>
					</div>
				</div>
			</div>

			<hr />

			<!-- AI Relevance Scoring -->
			<div class="fpp-section fpp-ai-section fpp-ai-score-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-ai-score" role="button" tabindex="0" aria-expanded="false" aria-controls="fpp-ai-score-content">
					<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Relevance Scoring', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-ai-score-content" role="region" aria-labelledby="fpp-toggle-ai-score" style="display:none;">
					<p class="description"><?php esc_html_e( 'Enter a keyword to find and score the most relevant pages to link to.', 'fpp-interlinking' ); ?></p>
					<div class="fpp-ai-controls">
						<input type="text" id="fpp-ai-score-keyword" class="regular-text"
							placeholder="<?php esc_attr_e( 'Enter keyword to score...', 'fpp-interlinking' ); ?>" />
						<button type="button" id="fpp-ai-score-btn" class="button button-primary">
							<span class="dashicons dashicons-chart-bar"></span>
							<?php esc_html_e( 'Score Relevance', 'fpp-interlinking' ); ?>
						</button>
					</div>
					<div id="fpp-ai-score-results" class="fpp-ai-results" style="display:none;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th class="column-ai-title"><?php esc_html_e( 'Page', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-url"><?php esc_html_e( 'URL', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-score"><?php esc_html_e( 'Score', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-reason"><?php esc_html_e( 'Reason', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-actions"><?php esc_html_e( 'Actions', 'fpp-interlinking' ); ?></th>
								</tr>
							</thead>
							<tbody id="fpp-ai-score-tbody"></tbody>
						</table>
					</div>
				</div>
			</div>

			<hr />

			<!-- AI Content Gap Analysis -->
			<div class="fpp-section fpp-ai-section fpp-ai-gaps-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-ai-gaps" role="button" tabindex="0" aria-expanded="false" aria-controls="fpp-ai-gaps-content">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Content Gap Analysis', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-ai-gaps-content" role="region" aria-labelledby="fpp-toggle-ai-gaps" style="display:none;">
					<p class="description"><?php esc_html_e( 'Analyse your published content to discover posts that should link to each other but currently don\'t.', 'fpp-interlinking' ); ?></p>
					<div class="fpp-ai-controls">
						<button type="button" id="fpp-ai-gaps-btn" class="button button-primary">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Analyse Content Gaps', 'fpp-interlinking' ); ?>
						</button>
						<span id="fpp-ai-gaps-status" class="fpp-ai-status"></span>
					</div>
					<div id="fpp-ai-gaps-results" class="fpp-ai-results" style="display:none;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th class="column-ai-keyword"><?php esc_html_e( 'Keyword', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-source"><?php esc_html_e( 'Source Post', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-target"><?php esc_html_e( 'Target Post', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-confidence"><?php esc_html_e( 'Confidence', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-reason"><?php esc_html_e( 'Reason', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-actions"><?php esc_html_e( 'Actions', 'fpp-interlinking' ); ?></th>
								</tr>
							</thead>
							<tbody id="fpp-ai-gaps-tbody"></tbody>
						</table>
					</div>
				</div>
			</div>

			<hr />

			<!-- AI Auto-Generate Mappings -->
			<div class="fpp-section fpp-ai-section fpp-ai-generate-section">
				<h2 class="fpp-section-toggle" id="fpp-toggle-ai-generate" role="button" tabindex="0" aria-expanded="false" aria-controls="fpp-ai-generate-content">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Auto-Generate Mappings', 'fpp-interlinking' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</h2>
				<div class="fpp-section-content" id="fpp-ai-generate-content" role="region" aria-labelledby="fpp-toggle-ai-generate" style="display:none;">
					<p class="description"><?php esc_html_e( 'Let AI scan your content and automatically propose keyword-to-URL mappings for a complete interlinking strategy.', 'fpp-interlinking' ); ?></p>
					<div class="fpp-ai-controls">
						<button type="button" id="fpp-ai-generate-btn" class="button button-primary">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Auto-Generate Mappings', 'fpp-interlinking' ); ?>
						</button>
						<button type="button" id="fpp-ai-add-all-btn" class="button" style="display:none;">
							<?php esc_html_e( 'Add All Mappings', 'fpp-interlinking' ); ?>
						</button>
						<span id="fpp-ai-generate-status" class="fpp-ai-status"></span>
					</div>
					<div id="fpp-ai-generate-results" class="fpp-ai-results" style="display:none;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th class="column-ai-keyword"><?php esc_html_e( 'Keyword', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-url"><?php esc_html_e( 'Target URL', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-target"><?php esc_html_e( 'Target Page', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-confidence"><?php esc_html_e( 'Confidence', 'fpp-interlinking' ); ?></th>
									<th class="column-ai-actions"><?php esc_html_e( 'Actions', 'fpp-interlinking' ); ?></th>
								</tr>
							</thead>
							<tbody id="fpp-ai-generate-tbody"></tbody>
						</table>
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
	 *
	 * @return void Sends JSON response and dies.
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
	 *
	 * @return void Sends JSON response and dies.
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
	 *
	 * @return void Sends JSON response and dies.
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
	 *
	 * @return void Sends JSON response and dies.
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
	 *
	 * @return void Sends JSON response and dies.
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

		// Max links per post (0 = unlimited).
		$max_links = isset( $_POST['max_links_per_post'] ) ? absint( $_POST['max_links_per_post'] ) : 0;
		if ( $max_links > 500 ) {
			$max_links = 500;
		}
		update_option( 'fpp_interlinking_max_links_per_post', $max_links );

		// Post types.
		$post_types = isset( $_POST['post_types'] ) ? sanitize_text_field( wp_unslash( $_POST['post_types'] ) ) : 'post,page';
		update_option( 'fpp_interlinking_post_types', $post_types );

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
	 *
	 * @return void Sends JSON response and dies.
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
	 *
	 * @return void Sends JSON response and dies.
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
	 *
	 * @return void Sends JSON response and dies.
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

	/* ── v2.0.0: AI-Powered AJAX Handlers ────────────────────────────── */

	/**
	 * AJAX: Save AI settings (provider, model, API key, max tokens).
	 *
	 * @since 2.0.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_save_ai_settings() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$provider   = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : 'openai';
		$model      = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		$api_key    = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$max_tokens = isset( $_POST['max_tokens'] ) ? absint( $_POST['max_tokens'] ) : 2000;

		// Validate provider.
		if ( ! in_array( $provider, array( 'openai', 'anthropic' ), true ) ) {
			$provider = 'openai';
		}

		// Clamp max tokens.
		$max_tokens = max( 500, min( $max_tokens, 8000 ) );

		update_option( FPP_Interlinking_AI::OPTION_PROVIDER, $provider, false );
		update_option( FPP_Interlinking_AI::OPTION_MAX_TOKENS, $max_tokens, false );

		if ( ! empty( $model ) ) {
			update_option( FPP_Interlinking_AI::OPTION_MODEL, $model, false );
		}

		// Only update API key if a new one was provided.
		if ( ! empty( $api_key ) ) {
			FPP_Interlinking_AI::save_api_key( $api_key );
		}

		wp_send_json_success( array(
			'message'    => __( 'AI settings saved.', 'fpp-interlinking' ),
			'masked_key' => FPP_Interlinking_AI::get_masked_key(),
		) );
	}

	/**
	 * AJAX: Test the AI API connection.
	 *
	 * @since 2.0.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_test_ai_connection() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$result = FPP_Interlinking_AI::test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful! Your AI provider is working.', 'fpp-interlinking' ) ) );
	}

	/**
	 * AJAX: Extract keywords from a post using AI.
	 *
	 * @since 2.0.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_ai_extract_keywords() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		// Rate limit check.
		$rate_check = FPP_Interlinking_AI::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select a post to analyse.', 'fpp-interlinking' ) ) );
		}

		$result = FPP_Interlinking_AI::extract_keywords( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Check which keywords already exist.
		$existing = FPP_Interlinking_DB::get_all_keywords();
		$existing_map = array();
		foreach ( $existing as $ek ) {
			$existing_map[ strtolower( $ek['keyword'] ) ] = true;
		}

		foreach ( $result as &$kw ) {
			$kw['already_exists'] = isset( $existing_map[ strtolower( $kw['keyword'] ?? '' ) ] );
		}
		unset( $kw );

		$post = get_post( $post_id );
		wp_send_json_success( array(
			'keywords'   => $result,
			'post_title' => $post ? $post->post_title : '',
			'post_url'   => get_permalink( $post_id ),
		) );
	}

	/**
	 * AJAX: Score relevance of pages for a keyword using AI.
	 *
	 * @since 2.0.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_ai_score_relevance() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		// Rate limit check.
		$rate_check = FPP_Interlinking_AI::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
		}

		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

		if ( empty( $keyword ) ) {
			wp_send_json_error( array( 'message' => __( 'Keyword is required.', 'fpp-interlinking' ) ) );
		}

		// Find candidate posts matching the keyword.
		$query = new WP_Query( array(
			's'                => $keyword,
			'post_type'        => array( 'post', 'page' ),
			'post_status'      => 'publish',
			'posts_per_page'   => 15,
			'orderby'          => 'relevance',
			'order'            => 'DESC',
			'no_found_rows'    => true,
		) );

		$candidates = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$candidates[] = array(
					'id'      => get_the_ID(),
					'title'   => get_the_title(),
					'url'     => get_permalink(),
					'excerpt' => wp_strip_all_tags( get_the_excerpt() ),
				);
			}
			wp_reset_postdata();
		}

		if ( empty( $candidates ) ) {
			wp_send_json_error( array( 'message' => __( 'No matching posts found for this keyword.', 'fpp-interlinking' ) ) );
		}

		$result = FPP_Interlinking_AI::score_relevance( $keyword, $candidates );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'keyword' => $keyword,
			'scores'  => $result,
		) );
	}

	/**
	 * AJAX: Analyse content gaps using AI.
	 *
	 * @since 2.0.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_ai_content_gaps() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		// Rate limit check.
		$rate_check = FPP_Interlinking_AI::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
		}

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		$result = FPP_Interlinking_AI::analyse_content_gaps( 20, $offset );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Auto-generate keyword mappings using AI.
	 *
	 * @since 2.0.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_ai_auto_generate() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		// Rate limit check.
		$rate_check = FPP_Interlinking_AI::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
		}

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		$result = FPP_Interlinking_AI::auto_generate_mappings( 20, $offset );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Add a single AI-suggested mapping to the keywords table.
	 *
	 * @since 2.0.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_ai_add_mapping() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$keyword    = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';

		if ( empty( $keyword ) || empty( $target_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Keyword and URL are required.', 'fpp-interlinking' ) ) );
		}

		if ( FPP_Interlinking_DB::keyword_exists( $keyword ) ) {
			wp_send_json_error( array( 'message' => __( 'This keyword already exists.', 'fpp-interlinking' ) ) );
		}

		$id = FPP_Interlinking_DB::insert_keyword( array(
			'keyword'          => $keyword,
			'target_url'       => $target_url,
			'nofollow'         => 0,
			'new_tab'          => 1,
			'max_replacements' => 0,
		) );

		if ( $id ) {
			delete_transient( 'fpp_interlinking_keywords_cache' );
			wp_send_json_success( array(
				'message' => sprintf( __( 'Keyword "%s" added successfully.', 'fpp-interlinking' ), $keyword ),
				'keyword' => array(
					'id'               => $id,
					'keyword'          => $keyword,
					'target_url'       => $target_url,
					'nofollow'         => 0,
					'new_tab'          => 1,
					'max_replacements' => 0,
					'is_active'        => 1,
				),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add keyword.', 'fpp-interlinking' ) ) );
		}
	}

	/* ── v2.1.0: Paginated Table, Bulk Ops, Import/Export ────────────── */

	/**
	 * AJAX: Load keywords with pagination and search.
	 *
	 * @since 2.1.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_load_keywords() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 20;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$orderby  = isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : 'keyword';
		$order    = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : 'ASC';

		$result = FPP_Interlinking_DB::get_keywords_paginated( $page, $per_page, $search, $orderby, $order );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Bulk action on selected keywords.
	 *
	 * @since 2.1.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_bulk_action() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$ids    = array_filter( $ids );

		if ( empty( $action ) || empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid bulk action or no items selected.', 'fpp-interlinking' ) ) );
		}

		$count = 0;

		switch ( $action ) {
			case 'delete':
				$count = FPP_Interlinking_DB::bulk_delete( $ids );
				break;

			case 'enable':
				$count = FPP_Interlinking_DB::bulk_toggle( $ids, 1 );
				break;

			case 'disable':
				$count = FPP_Interlinking_DB::bulk_toggle( $ids, 0 );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown bulk action.', 'fpp-interlinking' ) ) );
				return;
		}

		delete_transient( 'fpp_interlinking_keywords_cache' );

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of keywords affected. */
				__( '%d keyword(s) updated.', 'fpp-interlinking' ),
				$count
			),
			'affected' => $count,
		) );
	}

	/**
	 * AJAX: Export all keywords as CSV.
	 *
	 * @since 2.1.0
	 *
	 * @return void Sends CSV data as JSON (to be downloaded client-side).
	 */
	public function ajax_export_csv() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$keywords = FPP_Interlinking_DB::export_all();

		if ( empty( $keywords ) ) {
			wp_send_json_error( array( 'message' => __( 'No keywords to export.', 'fpp-interlinking' ) ) );
		}

		// Build CSV string.
		$csv = "keyword,target_url,nofollow,new_tab,max_replacements,is_active\n";
		foreach ( $keywords as $kw ) {
			$csv .= sprintf(
				'"%s","%s",%d,%d,%d,%d' . "\n",
				str_replace( '"', '""', $kw['keyword'] ),
				str_replace( '"', '""', $kw['target_url'] ),
				(int) $kw['nofollow'],
				(int) $kw['new_tab'],
				(int) $kw['max_replacements'],
				(int) $kw['is_active']
			);
		}

		wp_send_json_success( array(
			'csv'      => $csv,
			'filename' => 'wp-interlinking-keywords-' . gmdate( 'Y-m-d' ) . '.csv',
			'count'    => count( $keywords ),
		) );
	}

	/**
	 * AJAX: Import keywords from uploaded CSV data.
	 *
	 * @since 2.1.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function ajax_import_csv() {
		check_ajax_referer( 'fpp_interlinking_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'fpp-interlinking' ) ) );
		}

		$csv_data = isset( $_POST['csv_data'] ) ? wp_unslash( $_POST['csv_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $csv_data ) ) {
			wp_send_json_error( array( 'message' => __( 'No CSV data provided.', 'fpp-interlinking' ) ) );
		}

		// Parse CSV.
		$lines = explode( "\n", trim( $csv_data ) );
		if ( count( $lines ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'CSV file is empty or has no data rows.', 'fpp-interlinking' ) ) );
		}

		// Parse header.
		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map( 'trim', $header );
		$header = array_map( 'strtolower', $header );

		if ( ! in_array( 'keyword', $header, true ) || ! in_array( 'target_url', $header, true ) ) {
			wp_send_json_error( array( 'message' => __( 'CSV must have "keyword" and "target_url" columns.', 'fpp-interlinking' ) ) );
		}

		$rows = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}
			$values = str_getcsv( $line );
			$row    = array();
			foreach ( $header as $i => $key ) {
				$row[ $key ] = isset( $values[ $i ] ) ? $values[ $i ] : '';
			}
			$rows[] = $row;
		}

		$result = FPP_Interlinking_DB::import_keywords( $rows );
		delete_transient( 'fpp_interlinking_keywords_cache' );

		wp_send_json_success( array(
			'message'  => sprintf(
				/* translators: 1: imported count, 2: skipped count, 3: error count. */
				__( 'Import complete: %1$d imported, %2$d skipped (duplicates), %3$d errors.', 'fpp-interlinking' ),
				$result['imported'],
				$result['skipped'],
				$result['errors']
			),
			'imported' => $result['imported'],
			'skipped'  => $result['skipped'],
			'errors'   => $result['errors'],
		) );
	}
}
