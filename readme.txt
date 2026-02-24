=== WP Interlinking ===
Contributors: fpp
Tags: interlinking, internal links, seo, keywords, ai
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 2.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered SEO internal linking. Map keywords to URLs with AI keyword extraction, relevance scoring, content gap analysis, and auto-generated strategies.

== Description ==

WP Interlinking automatically replaces configured keywords in your posts and pages with anchor links pointing to target URLs. Now powered by AI to help you discover and build the optimal internal linking structure.

**How It Works**

1. Add keyword-to-URL mappings in Settings > WP Interlinking.
2. The plugin scans post and page content on the front end.
3. Matching keywords are replaced with anchor links using your configured settings.

**AI-Powered Features (v2.0.0)**

* **AI Keyword Extraction** - Select any post and AI extracts 10-20 SEO-relevant keywords ranked by relevance.
* **AI Relevance Scoring** - Enter a keyword and AI scores your pages as link targets (1-100) with explanations.
* **AI Content Gap Analysis** - AI scans your content to find posts that should link to each other but don't.
* **AI Auto-Generate Mappings** - One-click AI scan proposes a complete interlinking strategy for your site.
* **Multi-provider** - Works with OpenAI (GPT-4o, GPT-4o-mini) and Anthropic (Claude Sonnet, Haiku).
* **Encrypted storage** - API keys stored with sodium encryption.

**Content Discovery**

* **Quick-Add Post Search** - Type to search your existing posts and pages, then click to auto-fill the keyword and URL fields instantly.
* **Scan per Keyword** - Click "Scan" on any keyword row to find matching posts/pages and assign a target URL in one click.
* **Suggest Keywords from Content** - Scan all published post/page titles to discover interlinking opportunities. One-click "Add as Keyword" pre-fills the form.

**Core Features**

* **Keyword-to-URL mapping** - Define unlimited keyword/phrase mappings, each pointing to a target URL.
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
* API keys stored encrypted (sodium with AUTH_KEY derivation).

== Installation ==

1. Upload the `fpp-interlinking` folder to the `/wp-content/plugins/` directory, or install directly from the WordPress plugin screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **Settings > WP Interlinking** to configure global options and add your first keyword mapping.

**AI Setup**

1. Expand **AI Settings** on the plugin page.
2. Select your AI provider (OpenAI or Anthropic).
3. Enter your API key — it will be stored encrypted.
4. Choose a model and click **Save AI Settings**.
5. Click **Test Connection** to verify.

== Frequently Asked Questions ==

= How many keywords can I add? =

There is no hard limit. The plugin caches active keywords in a WordPress transient so performance stays fast even with hundreds of mappings.

= What happens if a keyword appears inside an existing link? =

Nothing. The plugin protects existing anchor tags, script/style blocks, code/pre blocks, textarea elements, and HTML comments from modification.

= Can I control how many times a keyword is linked per post? =

Yes. There is a global "Max replacements per keyword" setting (default: 1). You can also override this on a per-keyword basis. Setting the per-keyword value to 0 uses the global default.

= Will the plugin link a post to itself? =

No. Self-link prevention compares each keyword's target URL against the current post's permalink and skips it if they match.

= What AI providers are supported? =

OpenAI (GPT-4o, GPT-4o-mini, GPT-4-turbo) and Anthropic (Claude Sonnet, Claude Haiku). You need an API key from your chosen provider.

= Is my API key stored securely? =

Yes. API keys are encrypted using PHP's sodium extension (with a key derived from your WordPress AUTH_KEY). On servers without sodium, a base64 encoding is used as a fallback.

= How does AI Keyword Extraction work? =

Select a post, click "Extract Keywords", and AI analyses the content to find 10-20 SEO-relevant phrases ranked by relevance. Each result can be added as a keyword mapping with one click.

= How does AI Auto-Generate work? =

Click "Auto-Generate Mappings" and AI scans your recent posts to propose keyword-to-URL mappings that create a strong internal linking structure. You can add them individually or in bulk.

= Does it work with custom post types? =

Yes. The plugin hooks into the `the_content` filter, which fires for any post type that renders content through that filter.

= What happens when I deactivate the plugin? =

Your keyword mappings, settings, and AI configuration are preserved. Only the transient cache is cleared. Data is permanently removed only when you delete (uninstall) the plugin.

= Does it support multisite? =

Yes. On uninstall, data is cleaned up for every site in the network.

== Screenshots ==

1. Quick-Add Post Search with live autocomplete dropdown.
2. Global settings panel with max replacements, nofollow, new-tab, case sensitivity, and post exclusion options.
3. Add/edit keyword mapping form with per-keyword override options.
4. Keyword mappings table with Scan, Edit, Disable, and Delete actions.
5. AI Settings panel with provider, model, and API key configuration.
6. AI Keyword Extraction results with relevance scores and one-click add.
7. AI Relevance Scoring results with scores and reasoning.
8. AI Content Gap Analysis showing interlinking opportunities.
9. AI Auto-Generate Mappings with confidence scores and bulk add.

== Changelog ==

= 2.0.0 =
* Added AI Keyword Extraction - analyse post content to extract SEO keywords.
* Added AI Relevance Scoring - score posts as link targets for a keyword (1-100).
* Added AI Content Gap Analysis - discover posts that should link to each other.
* Added AI Auto-Generate Mappings - one-click complete interlinking strategy.
* Added multi-provider support: OpenAI and Anthropic (Claude).
* Added encrypted API key storage (sodium + AUTH_KEY derivation).
* Added AI Settings panel with provider, model, API key, and max tokens configuration.
* Added Test Connection button to verify API setup.
* Added one-click "Add Mapping" from any AI suggestion.
* Added bulk "Add All Mappings" for auto-generated results.
* Added color-coded score badges for AI confidence levels.
* Added 7 new AJAX endpoints for AI features.

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

= 2.0.0 =
Major AI update: AI Keyword Extraction, Relevance Scoring, Content Gap Analysis, and Auto-Generate Mappings. Supports OpenAI and Anthropic. API key required for AI features.

= 1.2.0 =
Major feature update: Quick-Add Post Search, Scan per Keyword, and Suggest Keywords from Content. Discover interlinking opportunities across your site with one click.

= 1.1.0 =
Adds self-link prevention, duplicate detection, input validation, multisite uninstall, and i18n support. Recommended update.

= 1.0.0 =
Initial release.
