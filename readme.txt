=== FPP Interlinking ===
Contributors: fpp
Tags: interlinking, internal links, seo, keywords, auto link
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.2.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automate SEO internal linking by mapping keywords to target URLs with full control over link behavior. Scan your content to discover interlinking opportunities.

== Description ==

FPP Interlinking automatically replaces configured keywords in your posts and pages with anchor links pointing to target URLs. Improve your site's SEO and user experience by building a strong internal linking structure without editing every post by hand.

**How It Works**

1. Add keyword-to-URL mappings in Settings > FPP Interlinking.
2. The plugin scans post and page content on the front end.
3. Matching keywords are replaced with anchor links using your configured settings.

**Key Features**

* **Keyword-to-URL mapping** - Define unlimited keyword/phrase mappings, each pointing to a target URL.
* **Quick-Add Post Search** - Type to search your existing posts and pages, then click to auto-fill the keyword and URL fields instantly.
* **Scan per Keyword** - Click "Scan" on any keyword row to find matching posts/pages and assign a target URL in one click.
* **Suggest Keywords from Content** - Scan all published post/page titles to discover interlinking opportunities. One-click "Add as Keyword" pre-fills the form.
* **Global settings** - Set default max replacements per keyword, nofollow, new-tab behavior, and case sensitivity across all mappings.
* **Per-keyword overrides** - Override the global nofollow, new-tab, and max-replacements settings on individual mappings.
* **Self-link prevention** - Keywords are automatically skipped when the target URL matches the current post, avoiding circular links.
* **Duplicate detection** - Prevents adding the same keyword twice.
* **Smart content protection** - Existing links, script, style, code, pre, textarea blocks, and HTML comments are never modified.
* **Longest-match-first** - Keywords are sorted by length before processing, so "WordPress SEO" is matched before "WordPress".
* **Post/page exclusions** - Exclude specific posts or pages by ID.
* **Transient caching** - Active keywords are cached for one hour to minimize database queries on every page load.
* **AJAX admin interface** - Add, edit, delete, toggle, and scan keywords without page reloads.
* **Settings link on Plugins page** - Quick access to configuration from the Plugins list.
* **Multisite compatible** - Clean uninstall removes data from every site in a multisite network.
* **i18n ready** - All strings are wrapped for translation.

**Security**

* Nonce verification and capability checks on every AJAX request.
* Input sanitized early, output escaped late.
* Prepared SQL statements for all database queries.

== Installation ==

1. Upload the `fpp-interlinking` folder to the `/wp-content/plugins/` directory, or install directly from the WordPress plugin screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **Settings > FPP Interlinking** to configure global options and add your first keyword mapping.

== Frequently Asked Questions ==

= How many keywords can I add? =

There is no hard limit. The plugin caches active keywords in a WordPress transient so performance stays fast even with hundreds of mappings.

= What happens if a keyword appears inside an existing link? =

Nothing. The plugin protects existing anchor tags, script/style blocks, code/pre blocks, textarea elements, and HTML comments from modification.

= Can I control how many times a keyword is linked per post? =

Yes. There is a global "Max replacements per keyword" setting (default: 1). You can also override this on a per-keyword basis. Setting the per-keyword value to 0 uses the global default.

= Will the plugin link a post to itself? =

No. Self-link prevention compares each keyword's target URL against the current post's permalink and skips it if they match.

= Is the matching case sensitive? =

By default, matching is case-insensitive ("WordPress" matches "wordpress"). You can enable case-sensitive matching in the global settings.

= How does Quick-Add Post Search work? =

Type at least 2 characters in the search field. The plugin searches your published posts and pages by title in real time. Click a result to auto-fill the keyword and URL fields in the form below.

= How does Scan per Keyword work? =

Click the "Scan" button on any keyword row. The plugin searches published posts/pages whose title contains that keyword. Each result has a "Use this URL" button that updates the keyword's target URL in one click.

= How does Suggest Keywords from Content work? =

Open the "Suggest Keywords from Content" section and click "Scan Post Titles." The plugin lists all published posts and pages with their titles as potential keywords. Posts already mapped are flagged. Click "Add as Keyword" to pre-fill the form.

= Does it work with custom post types? =

Yes. The plugin hooks into the `the_content` filter, which fires for any post type that renders content through that filter.

= What happens when I deactivate the plugin? =

Your keyword mappings and settings are preserved. Only the transient cache is cleared. Data is permanently removed only when you delete (uninstall) the plugin.

= Does it support multisite? =

Yes. On uninstall, data is cleaned up for every site in the network.

== Screenshots ==

1. Quick-Add Post Search with live autocomplete dropdown.
2. Global settings panel with max replacements, nofollow, new-tab, case sensitivity, and post exclusion options.
3. Add/edit keyword mapping form with per-keyword override options.
4. Keyword mappings table with Scan, Edit, Disable, and Delete actions.
5. Scan results panel showing matching posts with "Use this URL" buttons.
6. Suggest Keywords section with paginated post titles and "Add as Keyword" buttons.

== Changelog ==

= 1.2.0 =
* Added Quick-Add Post Search - live autocomplete to find posts/pages and auto-fill keyword + URL fields.
* Added Scan per Keyword - click "Scan" on any keyword row to discover matching posts and assign a target URL in one click.
* Added Suggest Keywords from Content - paginated scanner that lists all published titles as potential keyword mappings.
* Already-mapped titles are flagged in the suggestions table to avoid duplicates.
* Scan results panel uses expandable rows with a blue accent border for clear visual hierarchy.
* Added highlight animation when the form is pre-filled by search, scan, or suggestion actions.
* Performance: scan and search endpoints use `no_found_rows` to skip unnecessary count queries.

= 1.1.0 =
* Added self-link prevention - keywords are skipped when the target URL matches the current post.
* Added duplicate keyword detection to prevent identical mappings.
* Added max replacements upper-bound validation (capped at 100) on server and client.
* Added error logging for failed database operations when WP_DEBUG is enabled.
* Added Settings link on the Plugins list page.
* Added multisite-safe uninstall with per-site cleanup.
* Added HTML comment protection in the content replacer.
* Added noreferrer to rel attribute for new-tab links.
* Added client-side URL validation (must start with http:// or https://).
* Made all user-facing strings translation-ready.
* Optimized option autoloading (admin-only options no longer autoload).
* Improved PHPDoc blocks across all files.
* Content replacer now skips REST API and AJAX requests.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Major feature update: Quick-Add Post Search, Scan per Keyword, and Suggest Keywords from Content. Discover interlinking opportunities across your site with one click.

= 1.1.0 =
Adds self-link prevention, duplicate detection, input validation, multisite uninstall, and i18n support. Recommended update.

= 1.0.0 =
Initial release.
