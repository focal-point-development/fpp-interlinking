=== FPP Interlinking ===
Contributors: fpp
Tags: interlinking, internal links, seo, keywords, auto link
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.1.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automate SEO internal linking by mapping keywords to target URLs with full control over link behavior.

== Description ==

FPP Interlinking automatically replaces configured keywords in your posts and pages with anchor links pointing to target URLs. Improve your site's SEO and user experience by building a strong internal linking structure without editing every post by hand.

**How It Works**

1. Add keyword-to-URL mappings in Settings > FPP Interlinking.
2. The plugin scans post and page content on the front end.
3. Matching keywords are replaced with anchor links using your configured settings.

**Key Features**

* **Keyword-to-URL mapping** - Define unlimited keyword/phrase mappings, each pointing to a target URL.
* **Global settings** - Set default max replacements per keyword, nofollow, new-tab behavior, and case sensitivity across all mappings.
* **Per-keyword overrides** - Override the global nofollow, new-tab, and max-replacements settings on individual mappings.
* **Self-link prevention** - Keywords are automatically skipped when the target URL matches the current post, avoiding circular links.
* **Duplicate detection** - Prevents adding the same keyword twice.
* **Smart content protection** - Existing links, script, style, code, pre, textarea blocks, and HTML comments are never modified.
* **Longest-match-first** - Keywords are sorted by length before processing, so "WordPress SEO" is matched before "WordPress".
* **Post/page exclusions** - Exclude specific posts or pages by ID.
* **Transient caching** - Active keywords are cached for one hour to minimize database queries on every page load.
* **AJAX admin interface** - Add, edit, delete, and toggle keywords without page reloads.
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

= Does it work with custom post types? =

Yes. The plugin hooks into the `the_content` filter, which fires for any post type that renders content through that filter.

= What happens when I deactivate the plugin? =

Your keyword mappings and settings are preserved. Only the transient cache is cleared. Data is permanently removed only when you delete (uninstall) the plugin.

= Does it support multisite? =

Yes. On uninstall, data is cleaned up for every site in the network.

== Screenshots ==

1. Global settings panel with max replacements, nofollow, new-tab, case sensitivity, and post exclusion options.
2. Add/edit keyword mapping form with per-keyword override options.
3. Keyword mappings table with inline edit, toggle, and delete actions.

== Changelog ==

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

= 1.1.0 =
Adds self-link prevention, duplicate detection, input validation, multisite uninstall, and i18n support. Recommended update.

= 1.0.0 =
Initial release.
