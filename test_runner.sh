#!/bin/bash

# Feature Flags Test Runner
# This script helps verify features systematically using curl and drush commands

BASE_URL="https://drupal-contrib.ddev.site"
DRUSH="/var/www/html/vendor/bin/drush"

echo "=== Feature Flags Test Runner ==="
echo ""

# Test 1: Module Installation
echo "Test 1: Module can be installed and is enabled"
$DRUSH pm:list --filter=feature_flags --status=enabled --format=json | grep -q "feature_flags" && echo "✅ PASS" || echo "❌ FAIL"
echo ""

# Test 2: Menu entries exist
echo "Test 2: Module creates proper menu entries"
curl -s -k "$BASE_URL/admin/config/services/feature-flags" -H "User-Agent: test" | grep -q "Feature" && echo "✅ PASS (accessible)" || echo "❌ FAIL"
echo ""

# Test 3: Settings form has required fields
echo "Test 3: Settings form displays all required fields"
$DRUSH config:get feature_flags.settings debug_mode --format=string > /dev/null 2>&1 && echo "✅ PASS (debug_mode exists)" || echo "❌ FAIL"
$DRUSH config:get feature_flags.settings persist_decisions --format=string > /dev/null 2>&1 && echo "✅ PASS (persist_decisions exists)" || echo "❌ FAIL"
$DRUSH config:get feature_flags.settings exclude_from_config_export --format=string > /dev/null 2>&1 && echo "✅ PASS (exclude_from_config_export exists)" || echo "❌ FAIL"
echo ""

# Test 4: Settings can be saved and loaded
echo "Test 4: Settings form saves values correctly"
DEBUG=$($DRUSH config:get feature_flags.settings debug_mode --format=string)
PERSIST=$($DRUSH config:get feature_flags.settings persist_decisions --format=string)
echo "  debug_mode: $DEBUG"
echo "  persist_decisions: $PERSIST"
[ "$DEBUG" = "true" ] && [ "$PERSIST" = "true" ] && echo "✅ PASS" || echo "❌ FAIL"
echo ""

# Test 5: Feature flags list exists
echo "Test 5: Feature flags can be created and listed"
FLAG_COUNT=$($DRUSH eval "echo count(\Drupal::entityTypeManager()->getStorage('feature_flag')->loadByProperties(['status' => TRUE]));")
echo "  Enabled flags: $FLAG_COUNT"
[ "$FLAG_COUNT" -gt 0 ] && echo "✅ PASS" || echo "❌ FAIL"
echo ""

# Test 6: Frontend JavaScript attachment
echo "Test 6: JavaScript and drupalSettings are attached to pages"
curl -s -k "$BASE_URL/" | grep -q "featureFlags" && echo "✅ PASS (drupalSettings.featureFlags exists)" || echo "❌ FAIL"
curl -s -k "$BASE_URL/" | grep -q "feature_flags/js/base/FeatureFlagManager.js" && echo "✅ PASS (JS files loaded)" || echo "❌ FAIL"
echo ""

# Test 7: Feature flag configuration structure
echo "Test 7: Feature flags have correct data structure"
$DRUSH eval "
\$flag = \Drupal::entityTypeManager()->getStorage('feature_flag')->load('test_feature_flag');
if (\$flag) {
  \$variants = \$flag->get('variants');
  \$algorithms = \$flag->get('algorithms');
  echo 'Variants: ' . count(\$variants) . PHP_EOL;
  echo 'Algorithms: ' . count(\$algorithms) . PHP_EOL;
  if (count(\$variants) >= 2 && count(\$algorithms) >= 1) {
    echo '✅ PASS' . PHP_EOL;
  } else {
    echo '❌ FAIL' . PHP_EOL;
  }
} else {
  echo '❌ FAIL (flag not found)' . PHP_EOL;
}
"
echo ""

echo "=== Test Runner Complete ==="
