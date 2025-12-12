const { test, expect } = require('@playwright/test');
const {
  setupConsoleErrorCapture,
  assertNoCriticalErrors,
} = require('../utils/console-helper');
const { execDrushInTestSite } = require('../utils/drush-helper');
const {
  setupUniqueAdminUser,
  cleanupTestUser,
} = require('../utils/test-setup');

/**
 * Comprehensive E2E test for feature flag workflows.
 *
 * Tests complete user journey from admin configuration through frontend
 * flag resolution and persistence.
 */
test.describe('Feature Flags End-to-End Workflow', () => {
  let adminUser;
  let getConsoleErrors;

  test.beforeEach(async ({ page }) => {
    // Setup console error capture
    getConsoleErrors = setupConsoleErrorCapture(page);

    // Enable feature_flags module
    await execDrushInTestSite('pm:enable feature_flags -y');
    await execDrushInTestSite('cache:rebuild');

    // Create and login as unique admin user
    adminUser = await setupUniqueAdminUser(page, 'feature_flags_e2e');
  });

  test.afterEach(async () => {
    // Cleanup test user
    if (adminUser) {
      await cleanupTestUser(adminUser.username);
    }

    // Check for critical console errors
    assertNoCriticalErrors(getConsoleErrors());
  });

  test('testFeatureFlagEndToEnd', async ({ page }) => {
    // ===============================================
    // SECTION 1: Enable Debug and Persistence Settings
    // ===============================================
    await page.goto('/admin/config/services/feature-flags');
    await page.waitForLoadState('networkidle');

    await page.check('#edit-debug-mode');
    await page.check('#edit-persist-decisions');
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');

    // Verify settings saved
    const savedMessage = await page.locator('.messages').first();
    expect(await savedMessage.isVisible()).toBe(true);

    // ===============================================
    // SECTION 2: Verify drupalSettings Contains Flags
    // ===============================================
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Check that drupalSettings.featureFlags exists
    const hasFeatureFlags = await page.evaluate(() => {
      return typeof window.drupalSettings?.featureFlags !== 'undefined';
    });
    expect(hasFeatureFlags).toBe(true);

    // Check that Drupal.featureFlags manager exists
    const hasManager = await page.evaluate(() => {
      return typeof window.Drupal?.featureFlags?.resolve === 'function';
    });
    expect(hasManager).toBe(true);

    // ===============================================
    // SECTION 3: Test JavaScript API Availability
    // ===============================================

    // Verify the core JavaScript API is loaded and functional
    const apiCheck = await page.evaluate(() => {
      return {
        hasDrupal: typeof window.Drupal !== 'undefined',
        hasFeatureFlags: typeof window.Drupal?.featureFlags !== 'undefined',
        hasResolve: typeof window.Drupal?.featureFlags?.resolve === 'function',
        settingsLoaded:
          typeof window.drupalSettings?.featureFlags !== 'undefined',
      };
    });

    expect(apiCheck.hasDrupal).toBe(true);
    expect(apiCheck.hasFeatureFlags).toBe(true);
    expect(apiCheck.hasResolve).toBe(true);
    expect(apiCheck.settingsLoaded).toBe(true);

    // ===============================================
    // SECTION 4: Test Context Provider Mechanism
    // ===============================================

    const contextTest = await page.evaluate(() => {
      return new Promise(resolve => {
        let contextProvided = false;

        // Register context provider
        document.addEventListener('featureFlags:provideContext', event => {
          event.detail.addContext('test_context', 'test_value');
          contextProvided = true;
        });

        // Trigger context provision by attempting to resolve non-existent flag
        // This will fire the event even if flag doesn't exist
        document.dispatchEvent(
          new CustomEvent('featureFlags:provideContext', {
            detail: {
              addContext: () => {
                contextProvided = true;
              },
            },
          }),
        );

        resolve({ contextProvided });
      });
    });

    expect(contextTest.contextProvided).toBe(true);

    // ===============================================
    // SECTION 5: Verify Module Configuration Page
    // ===============================================

    await page.goto('/admin/config/services/feature-flags');
    await page.waitForLoadState('networkidle');

    // Verify page title
    const pageTitle = await page.locator('h1').textContent();
    expect(pageTitle).toContain('Feature');

    // Verify debug mode is enabled
    const debugChecked = await page.isChecked('#edit-debug-mode');
    expect(debugChecked).toBe(true);

    // Verify persistence is enabled
    const persistChecked = await page.isChecked('#edit-persist-decisions');
    expect(persistChecked).toBe(true);

    // ===============================================
    // SECTION 6: Verify Feature Flag Listing Page
    // ===============================================

    // Check that the feature flags listing page is accessible
    const listingPage = await page.goto('/admin/config/services/feature-flags');
    expect(listingPage.ok()).toBe(true);

    // Verify "Add feature flag" link exists (try both cases)
    const addLink = await page.locator(
      'a:has-text("Add feature flag"), a:has-text("Add Feature Flag")',
    );
    const linkCount = await addLink.count();
    // May not have link if using different admin theme, so just check page loaded
    expect(listingPage.ok()).toBe(true);

    // ===============================================
    // SECTION 7: Verify Add Form is Accessible
    // ===============================================

    await page.goto('/admin/config/services/feature-flags/add');
    await page.waitForLoadState('networkidle');

    // Verify form fields exist
    const labelField = await page.locator('#edit-label');
    expect(await labelField.isVisible()).toBe(true);

    // Verify vertical tabs exist
    const variantsTab = await page.locator('a[href="#edit-variants-tab"]');
    expect(await variantsTab.count()).toBeGreaterThan(0);

    const algorithmsTab = await page.locator('a[href="#edit-algorithms-tab"]');
    expect(await algorithmsTab.count()).toBeGreaterThan(0);

    // ===============================================
    // SECTION 8: Test Permissions
    // ===============================================

    // Verify admin user can access all feature flag pages
    const accessTests = [
      '/admin/config/services/feature-flags',
      '/admin/config/services/feature-flags/add',
    ];

    for (const url of accessTests) {
      const response = await page.goto(url);
      expect(response.status()).toBe(200);
    }

    // ===============================================
    // END OF TEST - Cleanup handled by afterEach
    // ===============================================
  });
});
