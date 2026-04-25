# Kris Risner MCP Abilities

A WordPress plugin that exposes site content, SEO data, structure, page builder content, and media metadata to AI assistants via the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) using the [WordPress Abilities API](https://github.com/WordPress/abilities-api).

Built for AI-assisted SEO audits, content edits, schema management, and bulk operations on WordPress sites — with first-class support for [Rank Math](https://rankmath.com/), [Bricks Builder](https://bricksbuilder.io/), and [Gravity Forms](https://www.gravityforms.com/).

---

## Features

The plugin registers **12 abilities** under the `krisrisner` category. Each is exposed as an MCP tool that an AI client (e.g. Claude) can call.

### Read

| Ability | Description |
|---|---|
| `krisrisner/get-content` | Returns published pages and posts with full content, Rank Math SEO meta, URL, date, and word count. Supports filtering by post type and keyword. |
| `krisrisner/seo-audit` | Returns SEO meta for all published pages/posts, flagging issues like missing titles, missing meta descriptions, missing focus keywords, thin content, and overlong fields. |
| `krisrisner/site-structure` | Returns the page hierarchy with parent/child relationships, slugs, menu order, depth, and page templates. |
| `krisrisner/internal-links` | Analyzes internal linking for a specific page or all pages — extracts every internal `<a href>` and its anchor text. |
| `krisrisner/plugins-status` | Returns installed plugins with version, active status, and pending updates. |
| `krisrisner/gravity-forms` | Returns the list of Gravity Forms or recent entries for a specific form. |
| `krisrisner/get-bricks-content` | Returns Bricks Builder elements with IDs, types, parent relationships, and editable settings (text, link, tag, code, classes, etc.). Optional `include_raw` flag returns full raw settings. |
| `krisrisner/get-images-missing-alt` | Returns all media library images with empty or missing alt text, including their public URL — letting an AI client view each image before writing alt text. |

### Write

| Ability | Description |
|---|---|
| `krisrisner/update-seo-meta` | Updates Rank Math SEO meta: title, description, focus keyword, robots, canonical URL, schema type. |
| `krisrisner/update-bricks-content` | Updates existing Bricks elements by ID. Merges settings, backs up the prior content before writing, and verifies the write directly against the database (bypassing object cache) to detect filter-level rejections. Supports `dry_run`. |
| `krisrisner/manage-page-schema` | Add, list, remove, or clear JSON-LD schema blocks rendered in `<head>` via `wp_head`. Schemas are also editable in the WP admin page editor via a meta box. |
| `krisrisner/update-image-alt` | Batch-updates alt text for one or more media library images by attachment ID. |

### Page Schema Meta Box

In addition to the MCP abilities, the plugin adds a **Page Schemas (JSON-LD)** meta box to the post and page editor screens. Each schema is stored under a unique key, validated for `@type`, and rendered as a `<script type="application/ld+json">` block in the page `<head>`.

---

## Requirements

- **WordPress 6.4+** with the [Abilities API](https://github.com/WordPress/abilities-api) available (required — the plugin registers abilities via `wp_register_ability` and `wp_register_ability_category`)
- **PHP 7.4+** (uses arrow functions in one place)

### Optional integrations

These plugins are not required, but the relevant abilities only return useful data when they are installed:

- [Rank Math SEO](https://rankmath.com/) — for SEO meta read/write (`get-content`, `seo-audit`, `update-seo-meta`)
- [Bricks Builder](https://bricksbuilder.io/) — for page builder content read/write (`get-bricks-content`, `update-bricks-content`)
- [Gravity Forms](https://www.gravityforms.com/) — for form data (`gravity-forms`)

The plugin degrades gracefully — abilities that depend on a missing plugin will return an `error` field rather than fatal.

---

## Installation

### Manual install

1. Download or clone this repository.
2. Copy `kris-risner-mcp.php` into `/wp-content/plugins/kris-risner-mcp/` on your WordPress site (create the folder if needed).
3. In the WordPress admin, go to **Plugins** and activate **Kris Risner MCP Abilities**.
4. Make sure the [Abilities API](https://github.com/WordPress/abilities-api) is available on your site — either bundled with WordPress core, or installed as a separate plugin.

### Via Git

```bash
cd wp-content/plugins
git clone https://github.com/<your-username>/kris-risner-mcp.git
```

Then activate in WP admin.

---

## Connecting to an MCP client

Once active, the abilities are exposed via the WordPress Abilities API's MCP endpoint. Configure your MCP client (Claude Desktop, Claude.ai connectors, etc.) to point at the site's MCP URL — typically:

```
https://your-site.com/wp-json/abilities/v1/mcp
```

Refer to the [Abilities API documentation](https://github.com/WordPress/abilities-api) for the exact endpoint path and authentication details for your version.

---

## Security note

By default every ability uses `permission_callback => '__return_true'`, meaning **the endpoints are publicly accessible to anyone who can reach the MCP route**. This is appropriate for a development site behind authentication, or behind an authenticating reverse proxy.

For production use, you should harden access. Two reasonable patterns:

**1. Restrict to authenticated admins:**

```php
'permission_callback' => function () {
    return current_user_can( 'manage_options' );
},
```

**2. Restrict by IP / shared secret / nonce / application password** at the Abilities API layer, depending on how your MCP client authenticates.

The write abilities (`update-seo-meta`, `update-bricks-content`, `manage-page-schema`, `update-image-alt`) are particularly sensitive and should never be left open on a public site.

---

## How writes are protected

The plugin is defensive about destructive writes:

- **Bricks updates** create a timestamped backup of the prior content (`_bricks_page_content_2_backup_YYYYMMDD_HHMMSS`) before writing, then verify the write by reading directly from `wp_postmeta` — bypassing all WordPress and object-cache layers — to confirm the value was persisted. If the standard `update_post_meta` call is silently rejected by a filter (Wordfence, code-signature checks, etc.), the plugin falls back to a direct `$wpdb` write and re-verifies.
- **Bricks updates** support `dry_run: true`, which returns the diff without writing.
- **Schema writes** validate that each block is valid JSON with an `@type` field before saving.
- **SEO updates** sanitize all string fields with `sanitize_text_field` before writing.

---

## Data storage

The plugin stores its own data in just one post-meta key:

| Meta key | Purpose |
|---|---|
| `_kr_page_schemas` | Per-post array of JSON-LD schema blocks rendered in `<head>` |

It reads from (but does not own) these keys:

- Rank Math: `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, `rank_math_robots`, `rank_math_canonical_url`, `rank_math_rich_snippet`, `rank_math_seo_score`
- Bricks: `_bricks_page_content_2`
- WordPress core: `_wp_attachment_image_alt`

---

## Development

The plugin is a single PHP file. There's no build step.

To rebrand again or fork, the prefixes are:
- PHP function/var prefix: `kr_`
- CSS class / DOM ID prefix: `kr-`
- JS function prefix: `kr` (camelCase)
- Ability namespace: `krisrisner/`
- Meta key prefix: `_kr_`

---

## License

GPL-2.0-or-later (matching WordPress).

## Author

Kris Risner
