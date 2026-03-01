<p align="center">
  <strong>WP Interlinking</strong><br>
  <em>AI-Powered SEO Internal Linking for WordPress</em>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/version-4.0.0-blue?style=flat-square" alt="Version 4.0.0">
  <img src="https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress" alt="WordPress 5.8+">
  <img src="https://img.shields.io/badge/PHP-7.2%2B-777bb4?style=flat-square&logo=php" alt="PHP 7.2+">
  <img src="https://img.shields.io/badge/license-GPL--2.0--or--later-green?style=flat-square" alt="License GPL-2.0-or-later">
</p>

---

**WP Interlinking** transforms your WordPress site's internal linking strategy with AI-powered automation, deep click analytics, and a modern admin interface. Define keyword-to-URL mappings and the plugin automatically replaces matching keywords in your content with SEO-optimized anchor links — no manual editing required.

Choose from **6 AI providers** (OpenAI, Anthropic, Google Gemini, Mistral AI, DeepSeek, or self-hosted Ollama) to extract keywords, score relevance, discover content gaps, and auto-generate a complete interlinking strategy. Track every click and impression with built-in analytics featuring CTR calculations, period comparisons, and Chart.js visualizations.

---

## Table of Contents

- [Key Highlights](#key-highlights)
- [AI Providers](#ai-providers)
- [Features in Detail](#features-in-detail)
  - [AI-Powered Analysis](#ai-powered-analysis)
  - [Deep Analytics & Tracking](#deep-analytics--tracking)
  - [Content Discovery](#content-discovery)
  - [Keyword Replacement Engine](#keyword-replacement-engine)
  - [Modern Admin Interface](#modern-admin-interface)
- [Requirements](#requirements)
- [Installation](#installation)
- [Getting Started](#getting-started)
  - [AI Setup](#ai-setup)
  - [Adding Keywords](#adding-keywords)
  - [Using AI Features](#using-ai-features)
  - [Viewing Analytics](#viewing-analytics)
- [Global Settings](#global-settings)
- [Security](#security)
- [File Structure](#file-structure)
- [Changelog](#changelog)
- [Credits](#credits)
- [License](#license)

---

## Key Highlights

| | Feature | Description |
|---|---|---|
| **6 AI Providers** | OpenAI, Anthropic, Gemini, Mistral, DeepSeek, Ollama | Cloud or self-hosted — your choice |
| **Click & Impression Tracking** | Every interlink tracked automatically | Clicks, impressions, and CTR per keyword |
| **Chart.js Visualizations** | Line charts, doughnut charts, sparklines | Period trends and post-type breakdowns |
| **Period Comparisons** | Today, 7 days, 30 days, custom range | Percentage change badges vs prior period |
| **CSV Import/Export** | Bulk keyword management | Export analytics data to CSV |
| **Smart Replacement** | Longest-match-first with content protection | Never breaks existing links or code blocks |
| **5-Tab Admin UI** | Dashboard, Keywords, Analysis, Analytics, Settings | AJAX tab switching — no page reloads |
| **Multisite Ready** | Per-site data with clean uninstall | Works across entire WordPress networks |

---

## AI Providers

WP Interlinking supports **6 AI providers** out of the box. Use cloud APIs or run models locally with Ollama.

| Provider | Default Model | Auth | Notes |
|---|---|---|---|
| **OpenAI** | `gpt-4o-mini` | API key (Bearer) | GPT-4o, GPT-4o-mini, GPT-4-turbo |
| **Anthropic** | `claude-sonnet-4-20250514` | API key (`x-api-key`) | Claude Sonnet, Claude Haiku |
| **Google Gemini** | `gemini-2.0-flash` | API key (query param) | Gemini Pro, Flash models |
| **Mistral AI** | `mistral-small-latest` | API key (Bearer) | OpenAI-compatible format |
| **DeepSeek** | `deepseek-chat` | API key (Bearer) | OpenAI-compatible format |
| **Ollama** | `llama3.2` | None (optional) | Self-hosted, configurable base URL |

All API keys are stored with **sodium encryption** using a key derived from your WordPress `AUTH_KEY`. On servers without the sodium extension, a base64 encoding fallback is used.

---

## Features in Detail

### AI-Powered Analysis

#### AI Keyword Extraction
Select any published post and let AI analyse its content to extract **10-20 SEO-relevant keywords** ranked by relevance score. Each extracted keyword comes with a one-click **Add Mapping** button that instantly creates the keyword-to-URL mapping with the source post as the target URL.

#### AI Relevance Scoring
Enter a keyword and AI evaluates your published posts to find the best link targets. Each post receives a **relevance score from 1-100** with a detailed explanation of why it's a good (or poor) match. Color-coded score badges make it easy to spot the best candidates at a glance.

#### AI Content Gap Analysis
AI scans your recent published content and identifies posts that **should link to each other but don't**. Each gap includes the source post, target post, suggested keyword, confidence score, and a clear reason explaining the recommendation. Build a stronger internal linking structure by filling these gaps with one click.

#### AI Auto-Generate Mappings
One click triggers a comprehensive AI scan of your site content to propose a **complete keyword-to-URL interlinking strategy**. Review suggestions individually or use **Add All Mappings** to create them in bulk. Confidence scores help you prioritize the most impactful links.

---

### Deep Analytics & Tracking

#### Click Tracking
Every interlink click is tracked automatically using `navigator.sendBeacon()` for **non-blocking, zero-impact performance**. No external scripts, no third-party services — all data stays in your WordPress database.

#### Impression Tracking
The plugin records impressions for every keyword that gets linked on a page view. Impressions are batched and written to the database on WordPress's `shutdown` hook using efficient `INSERT ... ON DUPLICATE KEY UPDATE` queries, so there is **zero impact on page load time**.

#### Click-Through Rate (CTR)
With both clicks and impressions tracked, the analytics dashboard calculates **CTR per keyword** (clicks / impressions x 100). Identify which interlinks drive the most engagement and which need optimization.

#### Period Comparisons
Compare performance across time periods with automatic **percentage-change badges**:
- **Today** vs yesterday
- **Last 7 days** vs prior 7 days
- **Last 30 days** vs prior 30 days
- **Custom date range** vs equivalent prior period

Green upward badges and red downward badges give you an instant visual indicator of trends.

#### Chart.js Visualizations
- **Line chart** — Daily click trends with filled area, tooltips, and responsive sizing
- **Doughnut chart** — Clicks broken down by post type
- **Sparklines** — Mini 7-day trend charts on dashboard stat cards

#### Analytics Export
Export analytics data to **CSV** for external reporting. Choose from top keywords, clicks by post, daily trend, or top links — all filtered by your selected time period.

---

### Content Discovery

#### Quick-Add Post Search
A live autocomplete search field at the top of the settings page lets you find any published post or page by typing 2+ characters. Click a result and the keyword and URL fields **auto-fill instantly** — then just hit Add Keyword.

#### Scan per Keyword
Click the **Scan** button on any keyword row in your mappings table. The plugin searches your published content for posts matching that keyword and presents them in an expandable panel. Click any result to assign it as the target URL.

#### Suggest Keywords from Content
A paginated scanner that lists **all published post and page titles** as potential keyword mapping candidates. Already-mapped titles are flagged to avoid duplicates. One-click **Add as Keyword** pre-fills the form with the title and permalink.

---

### Keyword Replacement Engine

The front-end replacement engine is the core of the plugin. When a visitor loads any post or page, the engine:

1. **Loads active keywords** from a transient cache (refreshed every hour)
2. **Sorts by length** (longest first) to prevent partial-match conflicts
3. **Protects existing content** — never modifies:
   - Existing `<a>` anchor links
   - `<script>` and `<style>` blocks
   - `<code>`, `<pre>`, and `<textarea>` elements
   - HTML comments
   - Heading tags (`<h1>` through `<h6>`) — configurable
4. **Prevents self-links** — skips keywords where the target URL matches the current post's permalink
5. **Respects limits** — honours the global and per-keyword max replacements setting
6. **Applies link attributes** — `rel="nofollow"`, `target="_blank"`, `rel="noopener noreferrer"` as configured
7. **Skips non-content requests** — REST API and AJAX requests are ignored
8. **Records impressions** — all linked keyword IDs are batched for impression tracking

---

### Modern Admin Interface

#### 5-Tab Layout
The admin page is organized into five tabs, each loaded via **AJAX without page reloads**:

| Tab | Purpose |
|---|---|
| **Dashboard** | At-a-glance stats with sparklines, recent clicks, quick actions |
| **Keywords** | Full keyword management with add/edit/delete, bulk operations, CSV import/export |
| **Analysis** | Internal PHP analysis engine — orphan page detection, link distribution, top opportunities |
| **Analytics** | Click/impression/CTR tracking with Chart.js charts, period comparisons, export |
| **Settings** | Global options, AI provider configuration, post type filtering, exclusions |

#### UI Enhancements
- **Skeleton loaders** — Animated placeholders while data loads (no more "Loading..." text)
- **Sortable tables** — Click any column header to sort ascending/descending
- **Progress bars** — Visual feedback during bulk operations
- **Empty states** — Helpful messages with action buttons when no data exists yet
- **Color-coded stat cards** — Blue, green, orange, and purple cards with dashicons and hover effects
- **Responsive design** — Clean layouts on desktop, tablet, and mobile

---

## Requirements

| Requirement | Minimum Version |
|---|---|
| WordPress | 5.8 or higher |
| PHP | 7.2 or higher |
| AI features | API key from any supported provider (not required for Ollama) |

---

## Installation

### From GitHub

1. Download or clone this repository:
   ```bash
   git clone https://github.com/magnetoid/wp-interlinking.git wp-content/plugins/fpp-interlinking
   ```
2. Activate through **Plugins > Installed Plugins** in your WordPress admin
3. Navigate to **Settings > WP Interlinking** to configure

### From WordPress Admin

1. Upload the `fpp-interlinking` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins screen
3. Go to **Settings > WP Interlinking**

---

## Getting Started

### AI Setup

1. Go to **Settings > WP Interlinking** and open the **Settings** tab
2. Scroll to **AI Settings** and select your preferred provider
3. Enter your API key (not required for Ollama)
4. For **Ollama**: enter your server's base URL (e.g., `http://localhost:11434`)
5. Choose a model from the available options
6. Click **Save AI Settings**, then **Test Connection** to verify

### Adding Keywords

**Quick method — Post Search:**
1. Use the search field at the top to find a post
2. Click a result to auto-fill the keyword and URL
3. Click **Add Keyword**

**Manual method:**
1. Enter a keyword or phrase in the Keyword field
2. Enter the full target URL (must start with `http://` or `https://`)
3. Optionally set per-keyword overrides for nofollow, new tab, or max replacements
4. Click **Add Keyword**

**Bulk method — CSV Import:**
1. Click **Import CSV** in the Keywords tab
2. Upload a CSV file with `keyword,url` columns (with optional `nofollow,new_tab,max_replacements` columns)
3. Review and confirm the import

### Using AI Features

All AI features are accessible from the **Analysis** tab:

- **Extract Keywords** — Select a post, click Extract, review the AI suggestions, and add mappings with one click
- **Score Relevance** — Enter a keyword, see which posts score highest as targets
- **Analyse Content Gaps** — Run a gap analysis to find missing cross-links
- **Auto-Generate** — Let AI propose a complete interlinking strategy for your site

### Viewing Analytics

Open the **Analytics** tab to see:

- Total clicks, impressions, CTR, and active keywords with comparison badges
- Daily click trend line chart
- Clicks by post type doughnut chart
- Top performing keywords table with CTR data
- Top links table showing keyword-URL pairs by clicks
- Use the period selector (Today, 7 Days, 30 Days, Custom) to filter
- Click **Export CSV** to download data for external analysis

---

## Global Settings

| Setting | Default | Description |
|---|---|---|
| Max replacements per keyword | `1` | How many times each keyword is linked per post (per-keyword overrides available) |
| Add `rel="nofollow"` | Off | Adds `nofollow` to all generated links |
| Open in new tab | On | Adds `target="_blank"` with `noopener noreferrer` |
| Case sensitive | Off | When off, "WordPress" matches "wordpress", "WORDPRESS", etc. |
| Max links per post | `0` (unlimited) | Cap the total number of interlinks inserted into a single post |
| Exclude heading tags | Off | When on, keywords inside `<h1>`-`<h6>` tags are not linked |
| Enabled post types | Posts, Pages | Choose which post types the replacement engine processes |
| Excluded posts/pages | — | Comma-separated post/page IDs to skip entirely |

---

## Security

WP Interlinking follows WordPress security best practices at every layer:

- **CSRF protection** — Nonce verification on every AJAX request
- **Capability checks** — All admin actions require `manage_options`
- **Input sanitization** — `sanitize_text_field()`, `esc_url_raw()`, `absint()` applied on all inputs
- **Output escaping** — `esc_html()`, `esc_url()`, `esc_attr()` applied at the point of output
- **Prepared SQL statements** — `$wpdb->prepare()` on every database query with user input
- **Encrypted API keys** — Sodium encryption with `AUTH_KEY`-derived key (base64 fallback on servers without sodium)
- **No external tracking** — All analytics data stays in your WordPress database

---

## File Structure

```
fpp-interlinking/
├── fpp-interlinking.php                          # Main plugin bootstrap (v4.0.0)
├── uninstall.php                                 # Multisite-safe cleanup on delete
├── readme.txt                                    # WordPress.org readme
├── includes/
│   ├── class-fpp-interlinking-activator.php      # DB table creation + default options
│   ├── class-fpp-interlinking-deactivator.php    # Cache cleanup on deactivation
│   ├── class-fpp-interlinking-db.php             # CRUD operations + duplicate detection
│   ├── class-fpp-interlinking-ai.php             # AI integration (6 providers)
│   ├── class-fpp-interlinking-admin.php          # Admin page, AJAX handlers (30+ endpoints)
│   ├── class-fpp-interlinking-analytics.php      # Click/impression/CTR analytics engine
│   └── class-fpp-interlinking-replacer.php       # Front-end content filter + impression tracking
├── assets/
│   ├── js/
│   │   ├── fpp-interlinking-admin.js             # Admin UI (FPP namespace, 50+ methods)
│   │   └── chart.min.js                          # Chart.js v4.4.7 (bundled)
│   └── css/
│       └── fpp-interlinking-admin.css             # Admin styles (cards, charts, skeletons)
└── languages/
    └── fpp-interlinking.pot                       # Translation template
```

---

## Changelog

### 4.0.0
**Multi-Provider AI, Deep Analytics, Modern UI**
- Added **4 new AI providers**: Google Gemini, Mistral AI, DeepSeek, and Ollama (self-hosted)
- Added configurable base URL for Ollama with optional authentication
- Added **impression tracking** — records every keyword impression per post per day
- Added **CTR calculation** — clicks / impressions displayed per keyword
- Added **period comparison** — percentage-change badges (current vs prior period)
- Added **custom date range** picker for analytics filtering
- Added **Chart.js integration** — line chart for daily trends, doughnut for post type breakdown
- Added **sparkline mini-charts** on dashboard stat cards (7-day trend)
- Added **Top Links** table in analytics (keyword + URL pairs by clicks)
- Added **Clicks by Post Type** doughnut chart
- Added **analytics CSV export** (top keywords, clicks by post, daily trend, top links)
- Added **AJAX tab switching** with `pushState` — no page reloads, browser back/forward supported
- Added **skeleton loaders** — animated placeholders during data loading
- Added **sortable tables** — click column headers to sort asc/desc
- Added **progress bars** for bulk operations
- Added **empty states** with helpful messages and action buttons
- Added **color-coded stat cards** with dashicons and hover effects
- Dynamic provider dropdown generated from provider registry constant
- Provider-specific UI toggling (API key visibility, base URL field)
- Impressions table with compound unique index for efficient upserts
- Old impression data purged alongside click data

### 3.0.0
**Internal Analysis Engine, Tabbed UI, Click Analytics**
- Added internal PHP analysis engine (no AI required) — orphan pages, link distribution, opportunities
- Added 5-tab admin interface: Dashboard, Keywords, Analysis, Analytics, Settings
- Added click tracking with `navigator.sendBeacon()`
- Added analytics dashboard with stat cards, daily trend chart, top keywords table
- Added period filtering (today, 7 days, 30 days)
- Added dashboard with at-a-glance stats and recent clicks
- Added clicks database table with post type tracking
- Added automatic data purge for old analytics (configurable)
- Added rate limiting for click tracking endpoint

### 2.1.0
**Performance, Bulk Operations, CSV, Post Type Filtering**
- Added CSV import/export for keyword mappings
- Added bulk enable/disable/delete operations
- Added post type filtering setting
- Added max links per post setting
- Added heading tag exclusion option
- Added pagination for keyword table
- Performance optimizations for large keyword sets

### 2.0.0
**AI-Powered Interlinking**
- Added AI Keyword Extraction — analyse post content to extract SEO keywords
- Added AI Relevance Scoring — score posts as link targets (1-100)
- Added AI Content Gap Analysis — discover posts that should link to each other
- Added AI Auto-Generate Mappings — one-click complete interlinking strategy
- Added multi-provider support: OpenAI and Anthropic (Claude)
- Added encrypted API key storage (sodium + AUTH_KEY derivation)
- Added AI Settings panel with provider, model, API key, and max tokens config
- Added Test Connection button to verify API setup
- Added one-click "Add Mapping" from any AI suggestion
- Added bulk "Add All Mappings" for auto-generated results
- Added score badges with color-coded confidence levels

### 1.2.0
**Content Discovery Tools**
- Added Quick-Add Post Search with live autocomplete
- Added Scan per Keyword — find matching posts and assign URL in one click
- Added Suggest Keywords from Content — paginated title scanner with one-click add
- Added already-mapped detection in suggestions
- Added highlight animation on form pre-fill
- Performance: `no_found_rows` on scan/search endpoints

### 1.1.0
**Stability & Polish**
- Added self-link prevention
- Added duplicate keyword detection
- Added max replacements validation (capped at 100)
- Added error logging when `WP_DEBUG` is enabled
- Added Settings link on Plugins page
- Added multisite-safe uninstall
- Added HTML comment protection in replacer
- Added `noreferrer` to new-tab links
- Added client-side URL validation
- Full i18n support
- Optimized option autoloading
- REST API and AJAX request skipping in replacer

### 1.0.0
- Initial release

---

## Credits

**Developer:** Marko Tiosavljevic

**Supported by:** [Imba Marketing](https://imbamarketing.com)

---

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
