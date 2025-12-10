#!/bin/bash

###############################################################################
# Feature Flags Module - Development Environment Initialization Script
###############################################################################
#
# This script sets up the development environment for the Feature Flags
# Drupal module. It installs dependencies, enables the module, and starts
# any necessary services for development and testing.
#
# Usage:
#   ./init.sh
#
###############################################################################

set -e  # Exit on error

echo "======================================================================="
echo "Feature Flags Module - Environment Initialization"
echo "======================================================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Determine the script's directory (module root)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DRUPAL_ROOT="/var/www/html"

echo -e "${YELLOW}Module directory:${NC} $SCRIPT_DIR"
echo -e "${YELLOW}Drupal root:${NC} $DRUPAL_ROOT"
echo ""

###############################################################################
# Step 1: Check Prerequisites
###############################################################################
echo -e "${GREEN}[1/7] Checking prerequisites...${NC}"

# Check if we're in a container/have access to Drupal
if [ ! -f "$DRUPAL_ROOT/web/index.php" ]; then
    echo -e "${RED}Error: Drupal installation not found at $DRUPAL_ROOT/web/${NC}"
    echo "Please ensure you're running this script from within the Drupal container."
    exit 1
fi

# Check for required commands
REQUIRED_COMMANDS=("composer" "drush" "php")
for cmd in "${REQUIRED_COMMANDS[@]}"; do
    if ! command -v $cmd &> /dev/null; then
        echo -e "${RED}Error: Required command '$cmd' not found${NC}"
        exit 1
    fi
done

echo -e "${GREEN}✓ All prerequisites met${NC}"
echo ""

###############################################################################
# Step 2: Install Composer Dependencies
###############################################################################
echo -e "${GREEN}[2/7] Installing Composer dependencies...${NC}"

cd "$DRUPAL_ROOT"

# Update composer dependencies if needed
if [ -f "composer.json" ]; then
    echo "Running composer install..."
    composer install --no-interaction
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
else
    echo -e "${YELLOW}! No composer.json found, skipping${NC}"
fi
echo ""

###############################################################################
# Step 3: Install Node Dependencies (for Jest testing)
###############################################################################
echo -e "${GREEN}[3/7] Checking Node.js dependencies...${NC}"

cd "$SCRIPT_DIR"

# Check if Node.js is available for Jest tests
if command -v node &> /dev/null; then
    echo "Node.js version: $(node --version)"

    # Initialize package.json if it doesn't exist
    if [ ! -f "package.json" ]; then
        echo "Creating package.json for Jest testing..."
        cat > package.json <<EOF
{
  "name": "feature_flags",
  "version": "1.0.0",
  "description": "Feature Flags Drupal Module - JavaScript Tests",
  "scripts": {
    "test": "jest",
    "test:watch": "jest --watch",
    "test:coverage": "jest --coverage"
  },
  "devDependencies": {
    "jest": "^29.0.0",
    "jest-environment-jsdom": "^29.0.0"
  },
  "jest": {
    "testEnvironment": "jsdom",
    "roots": ["<rootDir>/tests/js"],
    "testMatch": ["**/*.test.js"],
    "setupFilesAfterEnv": ["<rootDir>/tests/js/setup.js"]
  }
}
EOF
    fi

    # Install npm dependencies
    if [ -f "package.json" ]; then
        if command -v npm &> /dev/null; then
            echo "Installing npm dependencies..."
            npm install
            echo -e "${GREEN}✓ Node dependencies installed${NC}"
        else
            echo -e "${YELLOW}! npm not found, skipping JavaScript dependencies${NC}"
        fi
    fi
else
    echo -e "${YELLOW}! Node.js not found, JavaScript tests will not be available${NC}"
fi
echo ""

###############################################################################
# Step 4: Enable the Feature Flags Module
###############################################################################
echo -e "${GREEN}[4/7] Enabling Feature Flags module...${NC}"

cd "$DRUPAL_ROOT"

# Check if module is already enabled
if vendor/bin/drush pm:list --status=enabled --format=list | grep -q "^feature_flags$"; then
    echo -e "${YELLOW}Module is already enabled${NC}"
else
    echo "Enabling module..."
    vendor/bin/drush pm:enable feature_flags -y
    echo -e "${GREEN}✓ Module enabled${NC}"
fi
echo ""

###############################################################################
# Step 5: Clear Caches
###############################################################################
echo -e "${GREEN}[5/7] Clearing Drupal caches...${NC}"

vendor/bin/drush cache:rebuild
echo -e "${GREEN}✓ Caches cleared${NC}"
echo ""

###############################################################################
# Step 6: Set Up Test Environment
###############################################################################
echo -e "${GREEN}[6/7] Setting up test environment...${NC}"

# Create simpletest directory if it doesn't exist
TEST_OUTPUT_DIR="$DRUPAL_ROOT/web/sites/simpletest/browser_output"
if [ ! -d "$TEST_OUTPUT_DIR" ]; then
    echo "Creating browser output directory for tests..."
    mkdir -p "$TEST_OUTPUT_DIR"
    chmod -R 777 "$DRUPAL_ROOT/web/sites/simpletest"
fi

# Check PHPUnit configuration
if [ -f "$DRUPAL_ROOT/phpunit.xml" ]; then
    echo -e "${GREEN}✓ PHPUnit configuration found${NC}"
else
    echo -e "${YELLOW}! PHPUnit configuration not found at $DRUPAL_ROOT/phpunit.xml${NC}"
    echo "  You may need to copy phpunit.xml.dist to phpunit.xml and configure it."
fi
echo ""

###############################################################################
# Step 7: Display Access Information
###############################################################################
echo -e "${GREEN}[7/7] Environment setup complete!${NC}"
echo ""
echo "======================================================================="
echo -e "${GREEN}Development Environment Ready${NC}"
echo "======================================================================="
echo ""
echo -e "${YELLOW}Access Points:${NC}"
echo "  • Main site:    https://drupal-contrib.ddev.site"
echo "  • Admin area:   https://drupal-contrib.ddev.site/admin/config/services/feature-flags"
echo "  • Storybook:    https://drupal-contrib.ddev.site:6006 (run 'yarn storybook')"
echo "  • Mailpit:      https://drupal-contrib.ddev.site:8026"
echo ""
echo -e "${YELLOW}Common Commands:${NC}"
echo "  • Clear cache:           vendor/bin/drush cache:rebuild"
echo "  • Run PHPUnit tests:     vendor/bin/phpunit web/modules/contrib/feature_flags"
echo "  • Run unit tests only:   vendor/bin/phpunit --testsuite=unit"
echo "  • Run Jest tests:        cd $SCRIPT_DIR && npm test"
echo "  • Static analysis:       vendor/bin/phpstan analyse web/modules/contrib/feature_flags"
echo "  • Code standards check:  vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/contrib/feature_flags"
echo ""
echo -e "${YELLOW}Module Paths:${NC}"
echo "  • Module root:    $SCRIPT_DIR"
echo "  • PHP source:     $SCRIPT_DIR/src"
echo "  • JavaScript:     $SCRIPT_DIR/js"
echo "  • Tests:          $SCRIPT_DIR/tests"
echo "  • Config:         $SCRIPT_DIR/config"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "  1. Review feature_list.json for all features to implement"
echo "  2. Start with Step 1: Module Foundation (see app_spec.txt)"
echo "  3. Run tests frequently: vendor/bin/phpunit && npm test"
echo "  4. Check feature_list.json and mark tests as passing as you complete them"
echo ""
echo -e "${GREEN}Happy coding!${NC}"
echo "======================================================================="
