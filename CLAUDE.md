# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Working directory: pa-editorial-engine/

# Build
npm run build              # Production build → assets/admin.js + assets/editor.js
npm run start              # Watch mode for development

# PHP quality
composer test              # PHPUnit (47 tests, 61 assertions)
composer lint              # PHPCS — WordPress VIP Go standard
composer lint:fix          # Auto-fix PHPCS violations
composer phpstan           # PHPStan level 6 (use: php -d memory_limit=1G vendor/bin/phpstan analyse)

# JS quality
npm run lint:js            # ESLint (WordPress config + prettier)
npm run lint:css           # Stylelint

# E2E (requires wp-env running)
npm run test:e2e           # Playwright tests

# Run single PHP test class
vendor/bin/phpunit --filter=Locking
vendor/bin/phpunit --filter=Cloning
vendor/bin/phpunit --filter=Syndication
vendor/bin/phpunit --filter=Metadata
vendor/bin/phpunit --filter=SettingsTermIntegrity
```

## Architecture

### Plugin Bootstrap

`pa-editorial-engine.php` → registers activation hook → hooks `FeatureManager::boot()` on `plugins_loaded`.

The FeatureManager is the central orchestrator:
1. Loads `pa_engine_settings` from object cache (falls back to `get_option`)
2. Always initialises `Admin\Settings` (not toggleable)
3. Iterates the feature registry, calls `init()` only on enabled features
4. Registers all post meta keys globally (independent of feature toggles)

### PHP Class Map

```
PA\EditorialEngine\
├── Core\
│   ├── FeatureInterface        — Contract: is_enabled(), init(), get_defaults()
│   ├── FeatureManager          — Boots plugin, loads features, registers meta
│   └── Cache                   — Static wrapper around wp_cache_* with GROUP constant
├── Admin\
│   └── Settings                — REST-exposed settings, admin page, Abilities API,
│                                 taxonomy map schema + sanitization + term integrity
├── Features\
│   ├── Locking\Locking         — rest_pre_insert_post, heartbeat_received, role weights
│   ├── Metadata\Metadata       — Enqueues editor assets, exposes taxonomy map to JS
│   ├── Cloning\Cloning         — post_row_actions, admin_action handler, clone_post()
│   └── Syndication\Syndication — wp_insert_post_data (editorial stop),
│                                 transition_post_status (correction flag → Action Scheduler)
└── Utilities\
    └── SyndicationClient       — wp_safe_remote_post to PA Wire API, VIP logging
```

### JS Entry Points

Two webpack entry points build to `assets/`:

**`admin` (src/JS/admin/index.js)** — Settings page React app:
- `SettingsPage.js` — feature toggles, priority offset, API status, taxonomy map
- `components/RuleBuilder.js` — visual rule editor with JSON source fallback
- `components/ConditionGroup.js` — AND/OR conditions (taxonomy or meta)
- `components/ActionGroup.js` — auto-select taxonomies, force meta, UI notices
- `components/TermSearchControl.js` — async REST search for large taxonomy trees

**`editor` (src/JS/editor/index.js)** — Block editor extensions:
- `features/locking/` — subscribes to lock state, CSS lockdown, non-dismissible modal, blocks Cmd+S
- `features/metadata/` — subscribes to taxonomy changes, evaluates rules, auto-selects terms, snackbar notices
- `features/cloning/` — PluginPostStatusInfo "Add New Lead" button
- `features/syndication/` — PluginDocumentSettingPanel for Editorial Stop toggle + Correction Flag toggle/note

### Data Flow

**Settings** — `pa_engine_settings` option (object with boolean toggles + integer priority offset).
Read via Cache → get_option fallback. Written via REST (useEntityProp). Cache flushed on `update_option`.

**Taxonomy Map** — `pa_taxonomy_map` option (array of rule objects).
Same cache pattern. Full JSON schema validation on save. Term integrity check cross-references term_ids against DB.

**Post Meta** — 5 keys registered globally in FeatureManager::register_meta():
| Key | Type | Written By |
|-----|------|------------|
| `_pa_editorial_stop` | boolean | Editor sidebar (Syndication) |
| `_pa_is_correction` | boolean | Editor sidebar (Syndication) |
| `_pa_correction_note` | string | Editor sidebar (Syndication) |
| `_pa_parent_story_id` | integer | clone_post() (Cloning) |
| `_pa_auto_mapped_rules` | string[] | Metadata rule evaluation JS |

### WordPress Hooks Registered

| Hook | Class | Method | Priority |
|------|-------|--------|----------|
| `plugins_loaded` | FeatureManager | `boot()` | 10 |
| `init` | FeatureManager | `register_meta()` | 10 |
| `admin_init` | Settings | `register_settings()` | 10 |
| `admin_menu` | Settings | `add_settings_page()` | 10 |
| `admin_enqueue_scripts` | Settings | `enqueue_admin_assets()` | 10 |
| `update_option_pa_engine_settings` | Settings | `flush_settings_cache()` | 10 |
| `update_option_pa_taxonomy_map` | Settings | `flush_taxonomy_map_cache()` | 10 |
| `wp_abilities_api_categories_init` | Settings | `register_ability_category()` | 10 |
| `wp_abilities_api_init` | Settings | `register_abilities()` | 10 |
| `rest_pre_insert_post` | Locking | `block_locked_post_saves()` | 10 |
| `heartbeat_received` | Locking | `filter_heartbeat_for_role_priority()` | 10 |
| `enqueue_block_editor_assets` | Locking | `enqueue_editor_assets()` | 10 |
| `enqueue_block_editor_assets` | Metadata | `enqueue_editor_assets()` | 10 |
| `save_post` | Metadata | `log_auto_mapped_rules()` | 20 |
| `post_row_actions` | Cloning | `add_clone_action()` | 10 |
| `admin_action_pa_clone_post` | Cloning | `handle_clone_request()` | 10 |
| `admin_notices` | Cloning | `show_clone_notice()` | 10 |
| `enqueue_block_editor_assets` | Cloning | `enqueue_editor_assets()` | 10 |
| `wp_insert_post_data` | Syndication | `enforce_editorial_stop()` | 10 |
| `transition_post_status` | Syndication | `handle_correction_flag()` | 10 |
| `pa_send_correction` | Syndication | `send_correction()` | 10 |
| `enqueue_block_editor_assets` | Syndication | `enqueue_editor_assets()` | 10 |

### JS Global Config Objects

Each PHP feature passes configuration to JS via `wp_localize_script()`:

| Variable | Set By | Keys |
|----------|--------|------|
| `paEditorialEngine` | Settings | `apiCredentialsConfigured.paWire`, `.digital` |
| `paEditorialLocking` | Locking | `enabled` |
| `paEditorialMetadata` | Metadata | `enabled`, `taxonomyMap`, `brokenRules` |
| `paEditorialCloning` | Cloning | `enabled`, `cloneUrl` |
| `paEditorialSyndication` | Syndication | `enabled` |

## Key Conventions

### Adding a New Feature

1. Create `src/PHP/Features/NewFeature/NewFeature.php` implementing `FeatureInterface`
2. Add to `$features` array in `FeatureManager.php`
3. Add toggle key to `get_defaults()` and `Settings::get_settings_schema()`
4. Add sanitization in `Settings::sanitize_settings()`
5. Create JS in `src/JS/features/new-feature/`, import in `src/JS/editor/index.js`
6. Add tests in `tests/php/Unit/Features/NewFeature/`

### Testing

- PHP tests use minimal WordPress function stubs in `tests/php/stubs.php` — no WP bootstrap needed
- Add new WP function stubs to `stubs.php` as needed (check existing patterns)
- E2E tests require `wp-env` running with the plugin active
- Use `$GLOBALS['pa_test_*']` variables to control stub behaviour in unit tests

### WordPress VIP Go Rules

- No direct DB queries — use WP APIs only
- No plain-text API keys in DB — use `define()` constants (`PA_WIRE_API_KEY`, `PA_WIRE_API_ENDPOINT`, `PA_DIGITAL_API_KEY`)
- Remote request timeout max 3 seconds (VIP rule)
- Log via `wpcomvip_log()` with `error_log()` fallback
- Outbound API calls must be async via Action Scheduler
- Object cache everything read in hot paths

### Security Checklist

- Every admin action: `check_admin_referer()` + `current_user_can()`
- Every `$_GET`/`$_POST` access: `wp_unslash()` + type-specific sanitization
- Every output: `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`
- All scripts enqueued (no inline JS)
- All options/transients/meta prefixed with `pa_`
- `uninstall.php` checks `WP_UNINSTALL_PLUGIN` before cleanup

### PHPStan

- Uses `phpstan-constants.php` to define plugin constants for analysis
- Level 6 with `szepeviktor/phpstan-wordpress` extension
- Requires `php -d memory_limit=1G` to run (WordPress stubs are large)

## Platform Requirements

- PHP >= 8.4
- WordPress >= 7.0
- Node.js (for `@wordpress/scripts` build)
- PHPUnit 11+
- Playwright (for E2E tests, via `@wordpress/scripts`)

## Spec Reference

The original specification is in `../WordPress 7.0 Spec Review.md`. The phased build plan is in `../PLAN.md`.
