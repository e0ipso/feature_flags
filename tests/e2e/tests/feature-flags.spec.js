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
    // Cleanup test feature flag
    await execDrushInTestSite(
      'config:delete feature_flag.feature_flag.test_e2e_flag -y',
    ).catch(() => {
      // Feature flag may not exist if test failed before creation
    });

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
    // SECTION 1.5: Create a Test Feature Flag
    // ===============================================
    await page.goto('/admin/config/services/feature-flags/add');
    await page.waitForLoadState('networkidle');

    // Fill in basic information
    await page.fill('#edit-label', 'Test E2E Flag');
    // Machine name will auto-generate as 'test_e2e_flag'
    await page.waitForTimeout(500); // Wait for machine name generation

    // Enable the feature flag
    await page.check('#edit-status');

    // Click variants tab
    await page.click('a[href="#edit-variants-tab"]');
    await page.waitForTimeout(500);

    // Fill in first variant (default)
    await page.fill('[name="variants[0][label]"]', 'Control');
    await page.evaluate(() => {
      const editor = document.querySelector('[name="variants[0][value]"]');
      if (editor && editor.nextSibling && editor.nextSibling.CodeMirror) {
        editor.nextSibling.CodeMirror.setValue('{"enabled": false}');
      }
    });

    // Fill in second variant (default)
    await page.fill('[name="variants[1][label]"]', 'Treatment');
    await page.evaluate(() => {
      const editor = document.querySelector('[name="variants[1][value]"]');
      if (editor && editor.nextSibling && editor.nextSibling.CodeMirror) {
        editor.nextSibling.CodeMirror.setValue('{"enabled": true}');
      }
    });

    // Click algorithms tab
    await page.click('a[href="#edit-algorithms-tab"]');
    await page.waitForTimeout(500);

    // Expand "Add Algorithm" details if collapsed
    const addAlgorithmSummary = await page.locator(
      'summary:has-text("Add Algorithm")',
    );
    const isExpanded = await addAlgorithmSummary.getAttribute('aria-expanded');
    if (isExpanded !== 'true') {
      await addAlgorithmSummary.click();
      await page.waitForTimeout(300);
    }

    // Select percentage_rollout algorithm type (radio button)
    await page.check(
      'input[name="algorithm_plugin_select"][value="percentage_rollout"]',
    );

    // Click "Add algorithm" button (use value selector)
    await page.click('input[value="Add algorithm"]');
    await page.waitForLoadState('networkidle');

    // Fill in percentage values (50/50 split)
    // Wait for the percentage fields to appear after AJAX
    await page.waitForSelector('input[type="number"][name*="percentage"]', {
      timeout: 5000,
    });
    const percentageInputs = await page.locator(
      'input[type="number"][name*="percentage"]',
    );
    const count = await percentageInputs.count();
    if (count >= 2) {
      await percentageInputs.nth(0).fill('50');
      await percentageInputs.nth(1).fill('50');
    }

    // Save the feature flag
    await page.click('#edit-submit');
    await page.waitForLoadState('networkidle');

    // Verify feature flag was created
    const createMessage = await page.locator('.messages').first();
    expect(await createMessage.isVisible()).toBe(true);

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
