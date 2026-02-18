# FPP Interlinking

Automate SEO internal linking for WordPress. Map keywords to target URLs and the plugin replaces them with anchor links across your posts and pages automatically.

## Features

- **Keyword-to-URL mapping** with unlimited entries
- **Global defaults** for max replacements, nofollow, new-tab, and case sensitivity
- **Per-keyword overrides** for nofollow, new-tab, and max replacements
- **Self-link prevention** — skips keywords when the target URL matches the current post
- **Duplicate detection** — prevents adding the same keyword twice
- **Smart content protection** — never modifies existing links, `<script>`, `<style>`, `<code>`, `<pre>`, `<textarea>`, or HTML comments
- **Longest-match-first** processing to avoid partial match conflicts
- **Post/page exclusions** by ID
- **Transient caching** (1-hour) for minimal DB overhead
- **AJAX admin UI** — no page reloads for add/edit/delete/toggle
- **Multisite compatible** — clean uninstall across all network sites
- **i18n ready** — all strings wrapped for translation

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.2+ |

## Installation

1. Download or clone this repository into `/wp-content/plugins/fpp-interlinking/`
2. Activate through **Plugins > Installed Plugins**
3. Go to **Settings > FPP Interlinking** to configure

## Usage

### Global Settings

Navigate to **Settings > FPP Interlinking** and configure:

| Setting | Default | Description |
|---|---|---|
| Max replacements per keyword | 1 | How many times each keyword is linked per post |
| Add rel="nofollow" | Off | Adds `nofollow` to generated links |
| Open in new tab | On | Adds `target="_blank"` with `noopener noreferrer` |
| Case sensitive | Off | When off, "WordPress" matches "wordpress" |
| Excluded posts/pages | — | Comma-separated post/page IDs to skip |

### Adding Keywords

1. Enter a keyword or phrase
2. Enter the target URL (must be an absolute URL starting with `http://` or `https://`)
3. Optionally override nofollow, new-tab, or max replacements for this specific mapping
4. Click **Add Keyword**

### How Replacement Works

On every front-end page load:
1. Active keywords are fetched from cache (or DB on cache miss)
2. Keywords are sorted longest-first to prevent partial matches
3. Post content is split into protected segments (HTML tags, existing links, script/style/code blocks) and plain text
4. Only plain-text segments are scanned using word-boundary regex
5. Matches are replaced with `<a>` tags up to the configured max

Keywords whose target URL matches the current post are automatically skipped (self-link prevention).

## Security

- **CSRF protection** — nonce verification on every AJAX request
- **Capability checks** — all admin actions require `manage_options`
- **Input sanitization** — `sanitize_text_field()`, `esc_url_raw()`, `absint()` on all inputs
- **Late output escaping** — `esc_html()`, `esc_url()`, `esc_attr()` at the point of output
- **Prepared statements** — `$wpdb->prepare()` for parameterized queries

## File Structure

```
fpp-interlinking/
├── fpp-interlinking.php                          # Main plugin bootstrap
├── uninstall.php                                 # Cleanup on delete (multisite-safe)
├── readme.txt                                    # WordPress.org readme
├── includes/
│   ├── class-fpp-interlinking-activator.php      # DB table creation + default options
│   ├── class-fpp-interlinking-deactivator.php    # Cache cleanup
│   ├── class-fpp-interlinking-db.php             # CRUD operations + duplicate detection
│   ├── class-fpp-interlinking-admin.php          # Admin page, AJAX handlers, assets
│   └── class-fpp-interlinking-replacer.php       # Front-end content filter
└── assets/
    ├── js/fpp-interlinking-admin.js              # Admin UI logic
    └── css/fpp-interlinking-admin.css             # Admin styles
```

## Changelog

### 1.1.0
- Self-link prevention
- Duplicate keyword detection
- Max replacements validation (capped at 100)
- Error logging when `WP_DEBUG` is enabled
- Settings link on Plugins page
- Multisite-safe uninstall
- HTML comment protection in replacer
- `noreferrer` added to new-tab links
- Client-side URL validation
- Full i18n support
- Optimized option autoloading
- REST API and AJAX request skipping in replacer

### 1.0.0
- Initial release

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
