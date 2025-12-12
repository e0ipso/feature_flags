<?php

declare(strict_types=1);

namespace Drupal\Tests\feature_flags\Functional;

use Drupal\feature_flags\Entity\FeatureFlag;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests feature flag admin form workflows and validation.
 *
 * @group feature_flags
 */
final class FeatureFlagFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['feature_flags'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user with feature flag permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer feature flags',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests complete feature flag workflow via admin UI and entity API.
   *
   * This single test method covers:
   * - Settings form persistence
   * - Feature flag creation via entity API (AJAX not supported in
   *   BrowserTestBase)
   * - Entity edit via admin UI
   * - Entity delete via admin UI
   * - List display verification.
   *
   * Note: Algorithm and variant AJAX operations require JavaScript and are
   * tested in FunctionalJavascript test suite.
   */
  public function testFeatureFlagWorkflow(): void {
    // Settings form section.
    $this->configureSettings();

    // Feature flag creation via entity API.
    // Cannot test full form workflow due to AJAX requirements for adding
    // algorithms. Form structure is verified by accessing the add page.
    $this->verifyAddFormStructure();
    $flag_id = $this->createFeatureFlagProgrammatically();

    // Entity operations section.
    $this->verifyListDisplay($flag_id);
    $this->editFeatureFlag($flag_id);
    $this->deleteFeatureFlag($flag_id);
  }

  /**
   * Tests settings form persistence.
   *
   * Verifies:
   * - Navigate to settings form
   * - Enable all settings
   * - Submit and verify success message
   * - Reload page and verify values persisted.
   */
  protected function configureSettings(): void {
    $this->drupalGet('/admin/config/services/feature-flags');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Feature Flags');

    // Enable all settings.
    $edit = [
      'debug_mode' => TRUE,
      'persist_decisions' => TRUE,
      'exclude_from_config_export' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');

    // Verify success message.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Reload page and verify values persisted.
    $this->drupalGet('/admin/config/services/feature-flags');
    $this->assertSession()->checkboxChecked('debug_mode');
    $this->assertSession()->checkboxChecked('persist_decisions');
    $this->assertSession()->checkboxChecked('exclude_from_config_export');
  }

  /**
   * Verifies add form structure without submitting.
   *
   * Tests:
   * - Navigate to add form
   * - Verify form sections are present
   * - Verify pre-populated variants
   * - Verify algorithm section exists.
   */
  protected function verifyAddFormStructure(): void {
    $this->drupalGet('/admin/config/services/feature-flags/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Add feature flag');

    // Verify form sections.
    $this->assertSession()->pageTextContains('Basic Information');
    $this->assertSession()->pageTextContains('Variants');
    $this->assertSession()->pageTextContains('Decision Algorithms');

    // Verify variant fields exist.
    $this->assertSession()->fieldExists('variants[0][label]');
    $this->assertSession()->fieldExists('variants[0][value]');
    $this->assertSession()->fieldExists('variants[1][label]');
    $this->assertSession()->fieldExists('variants[1][value]');

    // Verify algorithm section.
    $this->assertSession()->pageTextContains('Add algorithm');
    $this->assertSession()->buttonExists('Add algorithm');
  }

  /**
   * Creates a feature flag programmatically.
   *
   * Creates via entity API because AJAX interactions for algorithms cannot be
   * tested in BrowserTestBase.
   *
   * @return string
   *   The ID of the created feature flag.
   */
  protected function createFeatureFlagProgrammatically(): string {
    $flag = FeatureFlag::create([
      'id' => 'test_feature',
      'label' => 'Test Feature',
      'description' => 'A test feature flag for functional testing',
      'status' => TRUE,
    ]);

    $flag->addVariant('Control', '{"enabled": false}');
    $flag->addVariant('Treatment', '{"enabled": true, "color": "blue"}');

    $flag->addAlgorithm('percentage_rollout', [
      'percentages' => [],
    ]);

    $flag->save();

    return 'test_feature';
  }

  /**
   * Verifies the feature flag appears in the list.
   *
   * @param string $flag_id
   *   The ID of the feature flag to verify.
   */
  protected function verifyListDisplay(string $flag_id): void {
    $this->drupalGet('/admin/config/services/feature-flags/list');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test Feature');
    $this->assertSession()->linkExists('Edit');
    $this->assertSession()->linkExists('Delete');
  }

  /**
   * Tests editing an existing feature flag.
   *
   * Verifies:
   * - Load edit form for created flag
   * - Verify values populate correctly
   * - Update label
   * - Submit and verify update message.
   *
   * @param string $flag_id
   *   The ID of the feature flag to edit.
   */
  protected function editFeatureFlag(string $flag_id): void {
    $this->drupalGet("/admin/config/services/feature-flags/{$flag_id}/edit");
    $this->assertSession()->statusCodeEquals(200);

    // Verify the form loaded with existing values.
    $this->assertSession()->fieldValueEquals('label', 'Test Feature');
    $this->assertSession()->fieldValueEquals('description', 'A test feature flag for functional testing');

    // Update the label.
    $edit = [
      'label' => 'Updated Test Feature',
    ];
    $this->submitForm($edit, 'Save');

    // Verify update message.
    $this->assertSession()->pageTextContains('Updated the Updated Test Feature feature flag.');
  }

  /**
   * Tests deleting a feature flag.
   *
   * Verifies:
   * - Navigate to delete form
   * - Confirm deletion
   * - Verify flag is removed.
   *
   * @param string $flag_id
   *   The ID of the feature flag to delete.
   */
  protected function deleteFeatureFlag(string $flag_id): void {
    $this->drupalGet("/admin/config/services/feature-flags/{$flag_id}/delete");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to delete');

    $this->submitForm([], 'Delete');

    // Verify deletion message.
    $this->assertSession()->pageTextContains('deleted');

    // Verify the flag no longer exists.
    $this->drupalGet("/admin/config/services/feature-flags/{$flag_id}/edit");
    $this->assertSession()->statusCodeEquals(404);
  }

}
