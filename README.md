# WP Interlinking

AI-powered SEO internal linking for WordPress. Map keywords to target URLs with automatic replacement, AI keyword extraction, relevance scoring, content gap analysis, and auto-generated interlinking strategies. Supports OpenAI and Anthropic.

## Features

### AI-Powered Features (v2.0.0)
- **AI Keyword Extraction** — Select any post and let AI analyse its content to extract SEO-relevant keywords for interlinking
- **AI Relevance Scoring** — Enter a keyword and AI scores your posts by how well they match as link targets (1-100)
- **AI Content Gap Analysis** — AI scans your published content to find posts that should link to each other but don't
- **AI Auto-Generate Mappings** — One-click AI scan proposes a complete keyword-to-URL interlinking strategy
- **Multi-provider support** — Works with OpenAI (GPT-4o, GPT-4o-mini) and Anthropic (Claude Sonnet, Haiku)
- **Encrypted API key storage** — API keys stored with sodium encryption (base64 fallback)
- **One-click add** — Every AI suggestion has an "Add Mapping" button to instantly create the keyword

### Content Discovery (v1.2.0)
- **Quick-Add Post Search** — live autocomplete: type to search posts/pages, click to auto-fill keyword + URL
- **Scan per Keyword** — click "Scan" on any keyword row to find matching posts and assign a URL in one click
- **Suggest Keywords from Content** — paginated scanner lists all published titles as potential keywords with one-click add

### Core
- **Keyword-to-URL mapping** with unlimited entries
- **Global defaults** for max replacements, nofollow, new-tab, and case sensitivity
- **Per-keyword overrides** for nofollow, new-tab, and max replacements
- **Self-link prevention** — skips keywords when the target URL matches the current post
- **Duplicate detection** — prevents adding the same keyword twice
- **Smart content protection** — never modifies existing links, `<script>`, `<style>`, `<code>`, `<pre>`, `<textarea>`, or HTML comments
- **Longest-match-first** processing to avoid partial match conflicts
- **Post/page exclusions** by ID
- **Transient caching** (1-hour) for minimal DB overhead
- **AJAX admin UI** — no page reloads for any operation
- **Multisite compatible** — clean uninstall across all network sites
- **i18n ready** — all strings wrapped for translation

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.2+ |
| AI features | OpenAI or Anthropic API key |

## Installation

1. Download or clone this repository into `/wp-content/plugins/fpp-interlinking/`
2. Activate through **Plugins > Installed Plugins**
3. Go to **Settings > WP Interlinking** to configure

## AI Setup

1. Go to **Settings > WP Interlinking**
2. Expand **AI Settings**
3. Select your provider (OpenAI or Anthropic)
4. Enter your API key — it will be stored encrypted
5. Choose a model (default: `gpt-4o-mini` for OpenAI, `claude-sonnet-4-20250514` for Anthropic)
6. Click **Save AI Settings**, then **Test Connection**

### AI Keyword Extraction

1. Expand **AI Keyword Extraction**
2. Search for and select a post to analyse
3. Click **Extract Keywords** — AI analyses the content and returns 10-20 keywords ranked by relevance
4. Click **Add Mapping** on any keyword to create it instantly

### AI Relevance Scoring

1. Expand **AI Relevance Scoring**
2. Enter a keyword you want to find the best link target for
3. Click **Score Relevance** — AI finds matching posts and scores them 1-100 with explanations
4. Click **Add Mapping** on the best match

### AI Content Gap Analysis

1. Expand **AI Content Gap Analysis**
2. Click **Analyse Content Gaps** — AI scans your recent posts and identifies missing interlinking opportunities
3. Each gap shows: keyword, source post, target post, confidence score, and reason
4. Click **Add Mapping** to create any suggested link

### AI Auto-Generate Mappings

1. Expand **AI Auto-Generate Mappings**
2. Click **Auto-Generate Mappings** — AI scans your content and proposes a complete interlinking strategy
3. Review the suggestions with confidence scores
4. Click **Add Mapping** individually or **Add All Mappings** to create them in bulk

## Usage

### Quick-Add Post Search

At the top of the settings page, use the search field to find existing posts and pages:

1. Type 2+ characters
2. A dropdown shows matching posts/pages with title, type, and URL
3. Click a result — the keyword and URL fields auto-fill in the form below
4. Review and click **Add Keyword**

### Adding Keywords Manually

1. Enter a keyword or phrase
2. Enter the target URL (must be an absolute URL starting with `http://` or `https://`)
3. Optionally override nofollow, new-tab, or max replacements for this specific mapping
4. Click **Add Keyword**

### Global Settings

| Setting | Default | Description |
|---|---|---|
| Max replacements per keyword | 1 | How many times each keyword is linked per post |
| Add rel="nofollow" | Off | Adds `nofollow` to generated links |
| Open in new tab | On | Adds `target="_blank"` with `noopener noreferrer` |
| Case sensitive | Off | When off, "WordPress" matches "wordpress" |
| Excluded posts/pages | — | Comma-separated post/page IDs to skip |

## Security

- **CSRF protection** — nonce verification on every AJAX request
- **Capability checks** — all admin actions require `manage_options`
- **Input sanitization** — `sanitize_text_field()`, `esc_url_raw()`, `absint()` on all inputs
- **Late output escaping** — `esc_html()`, `esc_url()`, `esc_attr()` at the point of output
- **Prepared statements** — `$wpdb->prepare()` for parameterized queries
- **Encrypted API keys** — sodium encryption with AUTH_KEY-derived key (base64 fallback)

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
│   ├── class-fpp-interlinking-ai.php             # AI integration (OpenAI + Anthropic)
│   ├── class-fpp-interlinking-admin.php          # Admin page, AJAX handlers (15 endpoints), assets
│   └── class-fpp-interlinking-replacer.php       # Front-end content filter
└── assets/
    ├── js/fpp-interlinking-admin.js              # Admin UI logic (FPP object, 35+ methods)
    └── css/fpp-interlinking-admin.css             # Admin styles
```

## Changelog

### 2.0.0
- AI Keyword Extraction — analyse post content to extract SEO keywords
- AI Relevance Scoring — score posts as link targets for a keyword (1-100)
- AI Content Gap Analysis — discover posts that should link to each other
- AI Auto-Generate Mappings — one-click complete interlinking strategy
- Multi-provider support: OpenAI and Anthropic (Claude)
- Encrypted API key storage (sodium + AUTH_KEY)
- AI Settings panel with provider, model, API key, and max tokens config
- Test Connection button to verify API setup
- One-click "Add Mapping" from any AI suggestion
- Bulk "Add All Mappings" for auto-generated results
- Score badges with color-coded confidence levels
- 7 new AJAX endpoints for AI features

### 1.2.0
- Quick-Add Post Search with live autocomplete
- Scan per Keyword — find matching posts and assign URL in one click
- Suggest Keywords from Content — paginated title scanner with one-click add
- Already-mapped detection in suggestions
- Highlight animation on form pre-fill
- Performance: `no_found_rows` on scan/search endpoints

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
