# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Context

This is a Drupal module for client-side feature flag management. Feature flags can have multiple variants (each with JSON values), decision algorithms (like percentage rollout), and conditions (like user ID matching). Everything resolves client-side via JavaScript for maximum caching compatibility.

## Essential Commands

```bash
# Testing
npm test                          # Run all Jest tests (JavaScript)
npm run test:watch                # Watch mode for JS tests
npm run test:coverage             # JS test coverage report

vendor/bin/phpunit web/modules/contrib/feature_flags
                                  # Run all PHPUnit tests (from Drupal root)
vendor/bin/phpunit --group=feature_flags
                                  # Run only feature_flags tests
vendor/bin/phpunit web/modules/contrib/feature_flags/tests/src/Unit/Plugin/DecisionAlgorithm/PercentageRolloutTest.php
                                  # Run specific test file

npm run e2e:test                  # Run Playwright end-to-end tests
npm run e2e:test:headed           # Run E2E with browser visible
npm run e2e:test:debug            # Debug E2E tests
npm run e2e:report                # View last E2E test report

# Code Quality
npm run check                     # Run all checks (lint, format, spell)
npm run js:check                  # ESLint + Prettier check
npm run js:fix                    # Auto-fix JS issues
npm run stylelint:check           # Check CSS files
npm run cspell:check              # Spell check

vendor/bin/phpstan analyse web/modules/contrib/feature_flags
                                  # Static analysis (from Drupal root)
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/contrib/feature_flags
                                  # Check PHP coding standards
vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/contrib/feature_flags
                                  # Auto-fix PHP coding standards

# Development
vendor/bin/drush cache:rebuild    # ALWAYS run after code changes
vendor/bin/drush config:export    # Export feature flags to config
vendor/bin/drush config:import    # Import feature flag config
```

## Architecture Overview

### Dual-Layer Plugin System

This module uses a **paired PHP+JavaScript plugin architecture** inspired by Lullabot's ab_tests module:

- **PHP plugins** define admin configuration forms and validation
- **JavaScript classes** execute the actual logic client-side
- Both share configuration via `drupalSettings.featureFlags`

#### Decision Algorithms

Determine which variant a user receives:

- **PHP**: `src/Plugin/DecisionAlgorithm/` (e.g., `PercentageRollout.php`)
- **JS**: `js/algorithm/` (e.g., `PercentageRollout.js` extends `BaseAlgorithm`)
- **Config**: Percentage distributions, variant weights, etc.

#### Algorithm Conditions

Filter when algorithms apply:

- **PHP**: `src/Plugin/AlgorithmCondition/` (e.g., `UserId.php`, `UserTier.php`)
- **JS**: `js/condition/` (e.g., `UserId.js` extends `BaseCondition`)
- **Operators**: AND, OR, NOT applied to condition matches

### Client-Side Resolution Flow

1. `hook_page_attachments()` loads enabled feature flags into `drupalSettings`
2. `Drupal.featureFlags.resolve('flag_id')` is called from JavaScript
3. Fire `featureFlags:provideContext` event to gather context (user_id, user_tier, etc.)
4. Evaluate algorithms in weight order
5. First algorithm with all conditions passing executes its `decide()` method
6. Variant selected and returned as `FeatureFlagResult` with parsed JSON value
7. If persistence enabled, decision cached in `localStorage`

### Config Entity Structure

```yaml
feature_flag.example:
  id: example_flag
  label: 'Example Feature'
  variants:
    - uuid: 'uuid-1'
      label: 'Control'
      value: '{"enabled": false}'
    - uuid: 'uuid-2'
      label: 'Treatment'
      value: '{"enabled": true, "color": "blue"}'
  algorithms:
    - uuid: 'uuid-3'
      plugin_id: percentage_rollout
      weight: 0
      configuration:
        percentages:
          uuid-1: 50
          uuid-2: 50
      conditions: [] # No conditions = catch-all
```

### JavaScript Base Classes

Located in `js/base/`:

- `FeatureFlagManager.js` - Main entry point, orchestrates resolution
- `FeatureFlagResult.js` - Encapsulates resolved variant + parsed JSON value
- `FeatureFlagConfig.js` - Represents flag configuration from drupalSettings
- `BaseAlgorithm.js` - Abstract base for algorithm implementations
- `BaseCondition.js` - Abstract base for condition implementations

Key methods:

- `BaseAlgorithm.decide(context)` - Returns selected Variant
- `BaseCondition.evaluate(context)` - Returns boolean
- `FeatureFlagManager.resolve(flagId)` - Returns Promise<FeatureFlagResult>

### Form Architecture

`src/Form/FeatureFlagForm.php` uses vertical tabs:

1. **Basic Information** - Label, machine name, description, enabled status
2. **Variants** - Repeatable fieldsets with CodeMirror JSON editor (minimum 2)
3. **Decision Algorithms** - Draggable table with nested conditions

Key form patterns:

- AJAX callbacks for add/remove operations
- SubformState for plugin configuration forms
- Plugin configuration embedded via `buildConfigurationForm()`
- Validation via `validateConfigurationForm()` (e.g., percentages sum to 100%)

### Libraries and Dependencies

All libraries defined in `feature_flags.libraries.yml`:

- `feature_flags/base` - Core JS classes (always loaded with flags)
- `feature_flags/feature_flags` - Drupal.behaviors initialization
- `feature_flags/algorithm.{plugin_id}` - Loaded dynamically per flag
- `feature_flags/condition.{plugin_id}` - Loaded dynamically per flag
- `feature_flags/json_editor` - CodeMirror 5 from CDN for admin forms
- `feature_flags/admin_form` - Admin UI enhancements (tabledrag, etc.)

### Persistence and Caching

When `persist_decisions` setting is enabled:

- Decisions stored in `localStorage` as `feature_flags:{flag_id}`
- Deterministic hashing: `hash(flag_id + user_id)` → bucket 0-99
- Same user always gets same variant (based on percentage distribution)
- Cache invalidation: clear localStorage or change flag configuration

When disabled:

- Random selection on each page load
- No localStorage writes

### Config Export Exclusion

`src/EventSubscriber/ConfigExcludeSubscriber.php` implements config export filtering:

- When `exclude_from_config_export` setting enabled
- Feature flag entities excluded from `drush config:export`
- Allows environment-specific flags without config sync conflicts

## Key Implementation Details

### Minimum Requirements

- **Variants**: Minimum 2 per feature flag (enforced in validation)
- **Algorithms**: Minimum 1 catch-all (no conditions) required
- **JSON Values**: All variant values must be valid JSON
- **Percentage Sum**: PercentageRollout requires exactly 100% total

### UUID Generation

UUIDs auto-generated for:

- Variants (stable across edits for algorithm references)
- Algorithms (for frontend identification)
- Conditions (for debug logging)

### Context Provider Pattern

External code provides runtime context via event:

```javascript
document.addEventListener('featureFlags:provideContext', (event) => {
  event.detail.addContext('user_id', drupalSettings.user.uid);
  event.detail.addContext('user_tier', 'premium');
  // Async providers supported:
  event.detail.addContext('subscription', await fetchStatus());
});
```

Default context if not provided:

- `user_id`: Random UUID (different each time unless persisted)

### Debug Logging

When `debug_mode` enabled, resolution steps logged to `console.debug()`:

- Flag being resolved
- Context object values
- Each algorithm evaluation
- Condition results (true/false)
- Final decision with variant label and UUID

## Testing Strategy

### PHPUnit Tests

- **Unit**: Plugin logic (algorithm/condition plugins) in `tests/src/Unit/`
- **Kernel**: Entity operations, plugin managers in `tests/src/Kernel/`
- **Functional**: Form workflows, permissions in `tests/src/Functional/`
- **FunctionalJavascript**: Browser-based admin UI in `tests/src/FunctionalJavascript/`

### Jest Tests (JavaScript)

- Located in `tests/js/`
- Mock Drupal global and drupalSettings
- Test algorithm logic, condition evaluation, manager orchestration
- Run with: `npm test`

### Playwright E2E Tests

- Located in `tests/e2e/`
- Full admin-to-frontend workflows
- Uses `@lullabot/playwright-drupal` for Drupal integration
- Run with: `npm run e2e:test`

## Feature Progress Tracking

`feature_list.json` tracks all features with test steps:

- **NEVER remove or edit features**
- **ONLY change `"passes": false` to `"passes": true`** when feature fully implemented and tested
- Each feature has detailed step-by-step verification instructions
- 96/96 features currently passing (module is feature-complete)

## Common Workflows

### Adding a New Decision Algorithm Plugin

1. Create PHP plugin in `src/Plugin/DecisionAlgorithm/MyAlgorithm.php`
2. Implement `DecisionAlgorithmInterface` (extend `DecisionAlgorithmPluginBase`)
3. Define annotation with `id`, `label`, `js_library`, `js_class`
4. Implement `buildConfigurationForm()`, `validateConfigurationForm()`, `getJavaScriptSettings()`
5. Create JS class in `js/algorithm/MyAlgorithm.js` extending `BaseAlgorithm`
6. Implement `decide(context)` method returning selected variant
7. Add library definition to `feature_flags.libraries.yml`
8. Write PHPUnit tests in `tests/src/Unit/Plugin/DecisionAlgorithm/`
9. Write Jest tests in `tests/js/algorithm/`
10. Run `drush cache:rebuild`

### Adding a New Condition Plugin

1. Create PHP plugin in `src/Plugin/AlgorithmCondition/MyCondition.php`
2. Implement `AlgorithmConditionInterface` (extend `AlgorithmConditionPluginBase`)
3. Define annotation with `context_key` (what context value this reads)
4. Create JS class in `js/condition/MyCondition.js` extending `BaseCondition`
5. Implement `evaluate(context)` method returning boolean
6. Add library definition to `feature_flags.libraries.yml`
7. Write tests and rebuild cache

### Debugging Feature Flag Resolution

1. Enable debug mode: `/admin/config/services/feature-flags`
2. Open browser console
3. Resolve flag: `await Drupal.featureFlags.resolve('flag_id')`
4. Inspect console.debug output showing each step
5. Check localStorage for cached decisions: `localStorage.getItem('feature_flags:flag_id')`

### Modifying Feature Flag Forms

Forms use complex AJAX with plugin subforms:

- Main form: `src/Form/FeatureFlagForm.php`
- Uses `SubformState::createForSubform()` to pass parent form data to plugins
- AJAX callbacks in `addVariant()`, `removeVariant()`, `addAlgorithm()`, etc.
- Plugin forms built via `$plugin->buildConfigurationForm()`
- Test thoroughly after changes - AJAX state management is complex

## Important Constraints

- **No build step**: Pure ES6 JavaScript, no transpilation
- **PHP 8.2+**: Use readonly properties, constructor promotion, match expressions
- **Drupal 10.3+ and 11.x**: Must work on both major versions
- **CodeMirror 5 from CDN**: No local assets, uses cdnjs.cloudflare.com
- **Client-side only resolution**: Never resolve server-side (defeats caching purpose)
