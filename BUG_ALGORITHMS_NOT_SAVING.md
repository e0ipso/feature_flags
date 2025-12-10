# Critical Bug: Algorithms Not Saving in FeatureFlagForm

## Issue Description

When editing a feature flag through the admin UI and attempting to add or modify algorithms, the algorithms are not saved to the configuration. The form shows a success message, but the `algorithms` property remains empty `{}` in the configuration.

## Steps to Reproduce

1. Navigate to `/admin/config/services/feature-flags/add` or edit an existing flag
2. Fill in Basic Information and Variants tabs
3. Go to Decision Algorithms tab
4. Click "Add algorithm" button
5. **Result**: AJAX error: "Oops, something went wrong. Check your browser's developer console for more details."
6. If you try to save the form without algorithms, it bypasses validation and saves with empty algorithms

## Expected Behavior

- AJAX "Add algorithm" should work without errors
- Algorithms should be saved to configuration
- Form validation should prevent saving without at least one algorithm

## Actual Behavior

- AJAX fails with generic error
- Algorithms are not saved (remain as `{}`)
- Form validation appears to be bypassed

## Error Details

When trying to save a form with algorithms configured via AJAX:
```
TypeError: Cannot assign string to property Drupal\feature_flags\Entity\FeatureFlag::$algorithms of type array
in Drupal\Core\Config\Entity\ConfigEntityBase->set()
(line 173 of core/lib/Drupal/Core/Config/Entity/ConfigEntityBase.php)
```

This suggests that somewhere in the form processing, the `algorithms` value is being converted to a string instead of remaining as an array.

## Investigation Needed

1. Check `FeatureFlagForm::save()` method around lines 753-790
2. Check `FeatureFlagForm::buildAlgorithmsForm()` - the AJAX callbacks
3. Verify form state handling for the `algorithms` field
4. Check if `copyFormValuesToEntity()` is incorrectly processing the algorithms field

## Workaround

Until this is fixed, algorithms can be set manually via configuration import:

```bash
# Create YAML file with algorithms structure
cd /var/www/html
vendor/bin/drush config:export --destination=/tmp/config-export
# Edit the feature_flags.feature_flag.{id}.yml file to add algorithms
vendor/bin/drush config:import --partial --source=/tmp/config-export
vendor/bin/drush cache:rebuild
```

## Impact

- **Severity**: Critical
- **Affects**: Admin UI for managing feature flags
- **Blocking**: Tests that require configured algorithms cannot be tested via UI
- **Users Affected**: Site administrators trying to create/edit feature flags

## Related Code

- `/var/www/html/web/modules/contrib/feature_flags/src/Form/FeatureFlagForm.php`
- `/var/www/html/web/modules/contrib/feature_flags/src/Entity/FeatureFlag.php`

## Session Discovered

Session 22 - December 10, 2025

## Status

**OPEN** - Needs investigation and fix
