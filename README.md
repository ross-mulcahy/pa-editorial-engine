# PA Editorial Engine

Editorial workflow engine for PA Media on WordPress VIP Go. Provides hardened post locking, taxonomy dependency mapping, story cloning, and syndication hooks for high-volume newsroom operations.

## Requirements

- **WordPress** 7.0+
- **PHP** 8.4+ (8.5 recommended)
- WordPress VIP Go environment (or compatible)

## Installation

1. Clone or copy the `pa-editorial-engine/` directory into `wp-content/plugins/`.
2. Install PHP dependencies:
   ```bash
   composer install --no-dev
   ```
3. Build front-end assets:
   ```bash
   npm install && npm run build
   ```
4. Activate the plugin in the WordPress admin.

### VIP Go Deployment

For VIP Go, commit `vendor/` and `assets/` (built output) to the repository. Do **not** commit `node_modules/`.

```bash
npm run build
git add vendor/ assets/
```

## Configuration

### Environment Variables

API credentials must be defined as PHP constants in `vip-config.php` (or equivalent). They are **never** stored in the database.

```php
define( 'PA_WIRE_API_KEY', 'your-api-key-here' );
define( 'PA_WIRE_API_ENDPOINT', 'https://api.pa.media/v1/corrections' );
define( 'PA_DIGITAL_API_KEY', 'your-digital-key-here' );
```

### Admin Settings

Navigate to **Settings > PA Editorial Settings** to configure:

- **Feature Toggles** — Enable/disable Nuclear Locking, Cloning Engine, and Syndication Hooks independently.
- **Global Priority Offset** — Baseline priority for taxonomy mapping rules (default: 10).
- **Taxonomy Dependency Map** — Visual rule builder for automating taxonomy term selection.
- **API Credentials** — Status display for environment variable configuration.

## Features

### Nuclear Locking

Replaces WordPress's native soft lock with a hard lock that physically prevents non-lock-holders from interacting with the editor.

- **Server-side:** Blocks all REST saves (including autosaves) with HTTP 403 when a post is locked by another user.
- **Client-side:** Greys out the editor, disables pointer events, and shows a non-dismissible modal.
- **Role priority:** Junior roles (reporter/contributor) cannot take over locks held by senior roles (editor/administrator).
- **WP 7.0:** Disables native real-time collaboration via the Abilities API when a nuclear lock is active.

### Taxonomy Dependency Map (Metadata Engine)

Automates the relationship between Topics, Services, and Territories based on configurable rules.

- **Rule Builder:** Visual admin UI for creating rules with AND/OR conditions and automated actions.
- **Auto-mapping:** When a mapped Topic is selected in the editor, corresponding Service and Territory terms are automatically checked.
- **Async search:** Term selectors search via REST API to handle PA's large taxonomy trees (15M+ items).
- **Audit trail:** Applied rules are logged to `_pa_auto_mapped_rules` post meta.

#### JSON Schema

Rules are stored in the `pa_taxonomy_map` option and validated against this structure:

```json
{
  "rule_id": "politics-uk",
  "label": "Politics → UK",
  "priority": 10,
  "active": true,
  "conditions": {
    "operator": "AND",
    "rules": [
      { "type": "taxonomy", "slug": "topic", "term_id": 42 }
    ]
  },
  "actions": {
    "select_taxonomies": {
      "territory": [10],
      "service": [20, 30]
    },
    "force_meta": {},
    "ui_notices": [
      { "type": "info", "message": "Territory and Service auto-selected." }
    ]
  }
}
```

### Cloning Engine ("New Lead")

Allows editors to branch a story into a new draft version while tracking lineage.

- **"Add New Lead"** button appears in post list row actions and the Gutenberg editor sidebar.
- **Copies:** post content, author, all taxonomy terms.
- **Strips:** title, excerpt, featured image, editorial stop flag.
- **Tracks:** Parent relationship via `_pa_parent_story_id` meta.
- **Redirects** to the new draft with a success notice.

### Syndication & Correction Hooks

Controls outgoing data flow based on editorial status.

- **Editorial Stop:** Toggle in the editor sidebar. When active, prevents publishing — forces status back to `pending` server-side.
- **Correction Flag:** Toggle + note textarea in the editor sidebar. On publish, schedules an async outbound API call to the PA Wire endpoint via Action Scheduler.
- **Async:** Outbound API calls never block the editor. Failures are logged via VIP logging and retried by Action Scheduler.

## Post Meta Keys

| Key | Type | Purpose |
|-----|------|---------|
| `_pa_editorial_stop` | boolean | Prevents publishing when true |
| `_pa_is_correction` | boolean | Flags post as correction |
| `_pa_correction_note` | string | Correction description for syndication |
| `_pa_parent_story_id` | integer | Links cloned post to original |
| `_pa_auto_mapped_rules` | array | Audit trail of applied mapping rules |

## Development

### Commands

```bash
# Build
npm run build              # Production build
npm run start              # Watch mode

# PHP
composer test              # PHPUnit tests
composer lint              # PHPCS (WordPress VIP Go)
composer lint:fix          # Auto-fix lint issues
composer phpstan           # Static analysis (level 6, needs: php -d memory_limit=1G vendor/bin/phpstan analyse)

# JavaScript
npm run lint:js            # ESLint
npm run lint:css           # Stylelint
npm run test:e2e           # Playwright E2E tests
```

### Architecture

The plugin uses a **Service-Oriented Architecture** with a central Feature Manager:

1. On `plugins_loaded`, the `FeatureManager` reads settings from the object cache (falling back to `get_option`).
2. It iterates the feature registry and calls `init()` only on enabled features.
3. Each feature implements `FeatureInterface` (`is_enabled()`, `init()`, `get_defaults()`).

### Adding a New Feature

1. Create a class in `src/PHP/Features/YourFeature/YourFeature.php` implementing `PA\EditorialEngine\Core\FeatureInterface`.
2. Add the class to the `$features` array in `FeatureManager`.
3. Add a toggle key in `get_defaults()` and the Settings schema.
4. Add JS components in `src/JS/features/your-feature/` and import in `src/JS/editor/index.js`.

### Directory Structure

```
src/PHP/Core/           — FeatureInterface, FeatureManager, Cache
src/PHP/Admin/          — Settings page, REST schema, Abilities API
src/PHP/Features/       — Locking, Metadata, Cloning, Syndication
src/PHP/Utilities/      — SyndicationClient
src/JS/admin/           — Settings UI (DataForm, Rule Builder)
src/JS/editor/          — Block editor extensions entry point
src/JS/features/        — Feature-specific Gutenberg components
tests/php/              — PHPUnit tests with WP function stubs
tests/e2e/              — Playwright E2E test specs
```

## License

GPL-2.0-or-later
