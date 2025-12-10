#!/bin/bash
# Helper script to check module status and run drush commands

echo "=== Module Status Check ==="
/var/www/html/vendor/bin/drush pm:list --filter=feature_flags

echo ""
echo "=== Enabling module if not enabled ==="
/var/www/html/vendor/bin/drush pm:enable feature_flags -y

echo ""
echo "=== Cache rebuild ==="
/var/www/html/vendor/bin/drush cache:rebuild

echo ""
echo "=== Check routes ==="
/var/www/html/vendor/bin/drush route:list | grep feature

echo ""
echo "=== Module is ready for testing ==="
