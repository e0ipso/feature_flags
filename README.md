# Feature Flags - Drupal Module

A powerful Drupal module for client-side feature flag management and execution. This module enables site administrators to create feature flags with multiple variants, configure decision algorithms with conditions, and resolve variants client-side via JavaScript—allowing personalized experiences even behind caching layers.

## Overview

The Feature Flags module provides:

- **Config Entity Management**: Create and manage feature flags through Drupal's admin interface
- **Multiple Variants**: Define 2 or more variants for each feature flag with JSON values
- **Decision Algorithms**: Plugin-based algorithms (e.g., percentage rollout) to determine which variant a user receives
- **Conditions**: Apply conditions (user ID, user tier, etc.) to algorithms for targeted rollouts
- **Client-Side Resolution**: JavaScript-based resolution for maximum caching compatibility
- **Persistence**: Optional localStorage caching for consistent user experiences
- **Debug Mode**: Console logging for development and troubleshooting
- **Config Export Control**: Optionally exclude feature flags from configuration exports

## Requirements

- **Drupal**: 10.3.x, 10.4.x, 11.0.x, or 11.1.x
- **PHP**: 8.2 or higher
- **JavaScript**: Modern browser with ES6 support

## Installation

### Via Composer (Recommended)

```bash
composer require drupal/feature_flags
drush pm:enable feature_flags -y
drush cache:rebuild
```

### Manual Installation

1. Download and extract the module to `web/modules/contrib/feature_flags`
2. Enable the module: `drush pm:enable feature_flags -y`
3. Clear caches: `drush cache:rebuild`

### Development Setup

For developers working on this module:

```bash
cd web/modules/contrib/feature_flags
./init.sh
```

The `init.sh` script will:

- Install all dependencies
- Enable the module
- Set up the testing environment
- Display helpful information about available commands

## Quick Start

### 1. Configure Module Settings

Navigate to **Configuration → Services → Feature Flags** (`/admin/config/services/feature-flags`)

- **Debug mode**: Enable console logging for development
- **Persist decisions**: Store decisions in localStorage for consistency
- **Exclude from config export**: Prevent feature flags from being exported with configuration

### 2. Create a Feature Flag

1. Go to the **Feature Flags** tab (`/admin/config/services/feature-flags/list`)
2. Click **Add feature flag**
3. Fill in basic information:
   - **Label**: Human-readable name (e.g., "New Checkout Flow")
   - **Machine name**: Auto-generated from label (e.g., `new_checkout_flow`)
   - **Description**: Internal notes about the flag
   - **Enabled**: Check to activate the flag

### 3. Define Variants

In the **Variants** tab:

1. Add at least 2 variants
2. Give each variant a label (e.g., "Control", "Variant A")
3. Set each variant's value as valid JSON:
   ```json
   { "enabled": false, "color": "blue" }
   ```
4. The CodeMirror editor provides syntax highlighting and validation

### 4. Configure Decision Algorithms

In the **Decision Algorithms** tab:

1. Click **Add Algorithm** and select **Percentage Rollout**
2. Distribute percentages across variants (must sum to 100%)
3. Optionally add conditions:
   - **User ID**: Target specific user IDs
   - **User Tier**: Target user tiers (free, premium, etc.)
4. Add multiple algorithms for sophisticated targeting
5. Drag to reorder (first matching algorithm wins)

**Important**: At least one algorithm must have no conditions (catch-all) to handle all users.

### 5. Use Feature Flags in JavaScript

```javascript
// Resolve a feature flag
const result = await Drupal.featureFlags.resolve('new_checkout_flow');

// Access the variant value (parsed JSON)
if (result.result.enabled) {
  console.log('New checkout is enabled!');
  applyNewCheckoutFlow();
}

// Access variant metadata
console.log('Variant:', result.variant.label);
console.log('Feature:', result.featureFlag.label);
```

### 6. Provide Custom Context

```javascript
// Listen for context provider event
document.addEventListener('featureFlags:provideContext', (event) => {
  // Add custom context values
  event.detail.addContext('user_id', drupalSettings.user.uid);
  event.detail.addContext('user_tier', 'premium');

  // Async context providers are supported
  event.detail.addContext('subscription', await fetchSubscriptionStatus());
});
```

## Architecture

### Plugin System

The module uses Drupal's plugin system for extensibility:

- **Decision Algorithms**: Plugins that determine which variant to select
  - `PercentageRollout`: Distribute users across variants by percentage
- **Algorithm Conditions**: Plugins that evaluate context to determine if an algorithm applies
  - `UserId`: Match against specific user IDs
  - `UserTier`: Match against user tier values

Each plugin has both PHP (for admin configuration) and JavaScript (for client-side execution) implementations.

### JavaScript Architecture

```
drupalSettings.featureFlags = {
  settings: { debug: true, persist: true },
  flags: {
    flag_machine_name: {
      id, label, variants, algorithms
    }
  }
}

Drupal.featureFlags = new FeatureFlagManager()
```

**Resolution Flow**:

1. Call `Drupal.featureFlags.resolve('flag_id')`
2. Check localStorage cache (if persistence enabled)
3. Fire `featureFlags:provideContext` event to gather context
4. Evaluate algorithms in order
5. First algorithm with matching conditions determines variant
6. Cache decision (if persistence enabled)
7. Return `FeatureFlagResult` with parsed variant value

## Testing

### PHP Unit Tests

```bash
# Run all tests
vendor/bin/phpunit web/modules/contrib/feature_flags

# Run only unit tests (fast)
vendor/bin/phpunit --testsuite=unit web/modules/contrib/feature_flags

# Run specific test
vendor/bin/phpunit web/modules/contrib/feature_flags/tests/src/Unit/Plugin/DecisionAlgorithm/PercentageRolloutTest.php
```

### JavaScript Tests (Jest)

```bash
cd web/modules/contrib/feature_flags
npm test

# Watch mode for development
npm run test:watch

# Coverage report
npm run test:coverage
```

### Code Quality

```bash
# Static analysis
vendor/bin/phpstan analyse web/modules/contrib/feature_flags

# Coding standards
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/contrib/feature_flags

# Auto-fix coding standards
vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/contrib/feature_flags
```

## Development Workflow

### Feature Progress Tracking

This module uses `feature_list.json` to track implementation progress:

```json
[
  {
    "category": "functional",
    "description": "Feature description",
    "steps": ["Step 1", "Step 2", "Step 3"],
    "passes": false // Change to true when feature is complete and tested
  }
]
```

**Important**: Never remove or edit features in `feature_list.json`. Only change `"passes": false` to `"passes": true` when a feature is fully implemented and tested.

### Implementation Steps

1. **Module Foundation** - Core files, services, config schema
2. **Plugin System** - Algorithm and condition plugins
3. **Config Entity** - FeatureFlag entity class
4. **Admin Forms** - Settings and feature flag forms with AJAX
5. **Routing and Menu** - Admin interface navigation
6. **JavaScript Base Classes** - Core client-side architecture
7. **JavaScript Implementations** - Algorithm and condition JS classes
8. **JSON Editor Integration** - CodeMirror for variant values
9. **drupalSettings Integration** - Attach flags to pages
10. **Config Export Exclusion** - Optional export control
11. **PHP Unit Tests** - Complete test coverage
12. **Jest Tests** - JavaScript test coverage
13. **Browser Tests** - End-to-end testing
14. **Polish and Documentation** - Final refinements

### Git Workflow

Always commit with clear, descriptive messages:

```bash
# Good commit messages
git commit -m "Add PercentageRollout decision algorithm plugin"
git commit -m "Implement client-side context provider event system"
git commit -m "Add validation for minimum 2 variants requirement"

# After code changes, always rebuild cache
drush cache:rebuild
```

## API Reference

### PHP APIs

```php
// Load a feature flag
$flag = \Drupal::entityTypeManager()
  ->getStorage('feature_flag')
  ->load('my_flag_id');

// Check if enabled
if ($flag->status()) {
  // Flag is active
}

// Get variants
$variants = $flag->get('variants');

// Get algorithms
$algorithms = $flag->get('algorithms');
```

### JavaScript APIs

```javascript
// Resolve a feature flag
const result = await Drupal.featureFlags.resolve('flag_id');

// FeatureFlagResult properties
result.featureFlag; // FeatureFlagConfig instance
result.result; // Parsed JSON value (object, array, scalar)
result.variant; // Variant object {uuid, label, value}

// Provide context
document.addEventListener('featureFlags:provideContext', event => {
  event.detail.addContext('key', value);
});
```

## Extending the Module

### Creating a Custom Algorithm

1. Create PHP plugin in `src/Plugin/DecisionAlgorithm/MyAlgorithm.php`
2. Create JS class in `js/algorithm/MyAlgorithm.js` extending `BaseAlgorithm`
3. Define library in `feature_flags.libraries.yml`
4. Implement `decide()` method to return selected variant

### Creating a Custom Condition

1. Create PHP plugin in `src/Plugin/AlgorithmCondition/MyCondition.php`
2. Create JS class in `js/condition/MyCondition.js` extending `BaseCondition`
3. Define library in `feature_flags.libraries.yml`
4. Implement `evaluate()` method to return boolean

## Troubleshooting

### Feature flag not resolving

1. Check if module is enabled: `drush pm:list --status=enabled | grep feature_flags`
2. Clear caches: `drush cache:rebuild`
3. Enable debug mode and check browser console
4. Verify flag is enabled in admin UI
5. Check that at least one algorithm has no conditions

### CodeMirror not loading

1. Verify CDN is accessible (check browser console for 404s)
2. Clear browser cache
3. Check library is attached to form

### Percentages don't sum to 100%

This is enforced by validation. Adjust percentages so they total exactly 100%.

### Decisions not persisting

1. Check "Persist decisions" is enabled in settings
2. Verify localStorage is available (browser privacy settings)
3. Check browser console for errors
4. Try clearing localStorage and resolving again

## License

This module is licensed under the GPL v2 (or later) license.

## Maintainers

- Initial development: Autonomous coding project
- Current maintainers: [To be determined]

## Contributing

Contributions are welcome! Please:

1. Create feature branches from main
2. Write tests for new functionality
3. Ensure all tests pass: `vendor/bin/phpunit && npm test`
4. Follow Drupal coding standards
5. Update `feature_list.json` to mark features as passing
6. Submit pull requests with clear descriptions

## Support

- Issue queue: [To be created on drupal.org]
- Documentation: See `app_spec.txt` for complete technical specification
- Feature tracking: See `feature_list.json` for implementation status

## Changelog

### 1.0.0-alpha (In Development)

- Initial development release
- Core feature flag entity and admin UI
- Percentage rollout algorithm
- User ID and User Tier conditions
- Client-side resolution with persistence
- Debug mode and config export control
