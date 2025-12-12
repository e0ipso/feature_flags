<?php

declare(strict_types=1);

namespace Drupal\Tests\feature_flags\Kernel;

use Drupal\Core\Config\StorageInterface;
use Drupal\feature_flags\Entity\FeatureFlag;
use Drupal\feature_flags\Plugin\DecisionAlgorithm\DecisionAlgorithmInterface;
use Drupal\feature_flags\Plugin\AlgorithmCondition\AlgorithmConditionInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests feature flag entity and plugin integration.
 *
 * @group feature_flags
 */
final class FeatureFlagKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['feature_flags', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['feature_flags']);
    $this->installEntitySchema('feature_flag');
  }

  /**
   * Tests feature flag entity CRUD operations and plugin integration.
   *
   * This single test method covers:
   * - Entity CRUD operations (create, load, update, delete)
   * - Plugin discovery for decision algorithms and conditions
   * - Plugin instantiation and interface compliance
   * - Config export filtering based on settings.
   */
  public function testFeatureFlagEntityAndPlugins(): void {
    // Entity CRUD section.
    $flag = $this->createTestFlag();
    $this->assertNotNull($flag->id(), 'Feature flag ID should be set after save');

    $loaded_flag = FeatureFlag::load($flag->id());
    $this->assertNotNull($loaded_flag, 'Feature flag should be loadable');
    $this->assertEquals($flag->label(), $loaded_flag->label(), 'Loaded flag label should match');
    $this->assertCount(2, $loaded_flag->getVariants(), 'Loaded flag should have 2 variants');

    $loaded_flag->set('label', 'Updated Test Flag');
    $loaded_flag->save();
    $reloaded_flag = FeatureFlag::load($flag->id());
    $this->assertEquals('Updated Test Flag', $reloaded_flag->label(), 'Updated label should persist');

    $flag_id = $flag->id();
    $flag->delete();
    $deleted_flag = FeatureFlag::load($flag_id);
    $this->assertNull($deleted_flag, 'Deleted flag should not be loadable');

    // Plugin discovery section.
    $this->assertPluginDiscovered('percentage_rollout', 'decision_algorithm');
    $this->assertPluginDiscovered('user_id', 'algorithm_condition');
    $this->assertPluginDiscovered('user_tier', 'algorithm_condition');

    // Config export section.
    $export_flag = $this->createTestFlag();
    $this->assertConfigExport($export_flag, TRUE);
    $this->assertConfigExport($export_flag, FALSE);
  }

  /**
   * Creates a test feature flag with two variants.
   *
   * @return \Drupal\feature_flags\Entity\FeatureFlag
   *   The created feature flag entity.
   */
  protected function createTestFlag(): FeatureFlag {
    $flag = FeatureFlag::create([
      'id' => 'test_flag_' . $this->randomMachineName(),
      'label' => 'Test Flag',
      'description' => 'A test feature flag',
      'status' => TRUE,
    ]);

    $flag->addVariant('Control', '{"enabled": false}');
    $flag->addVariant('Treatment', '{"enabled": true}');

    $flag->addAlgorithm('percentage_rollout', [
      'percentages' => [],
    ]);

    $flag->save();

    return $flag;
  }

  /**
   * Asserts that a plugin is properly discovered.
   *
   * Tests:
   * - Plugin appears in getDefinitions()
   * - Plugin can be instantiated
   * - Plugin instance implements correct interface.
   *
   * @param string $plugin_id
   *   The plugin ID to verify.
   * @param string $plugin_type
   *   The plugin type: 'decision_algorithm' or 'algorithm_condition'.
   */
  protected function assertPluginDiscovered(string $plugin_id, string $plugin_type): void {
    $service_name = match ($plugin_type) {
      'decision_algorithm' => 'plugin.manager.feature_flags.decision_algorithm',
      'algorithm_condition' => 'plugin.manager.feature_flags.algorithm_condition',
      default => throw new \InvalidArgumentException("Unknown plugin type: {$plugin_type}"),
    };

    $expected_interface = match ($plugin_type) {
      'decision_algorithm' => DecisionAlgorithmInterface::class,
      'algorithm_condition' => AlgorithmConditionInterface::class,
      default => throw new \InvalidArgumentException("Unknown plugin type: {$plugin_type}"),
    };

    $manager = $this->container->get($service_name);
    $definitions = $manager->getDefinitions();

    $this->assertArrayHasKey(
      $plugin_id,
      $definitions,
      "Plugin '{$plugin_id}' should be discovered by {$plugin_type} manager"
    );

    $instance = $manager->createInstance($plugin_id);
    $this->assertInstanceOf(
      $expected_interface,
      $instance,
      "Plugin instance should implement {$expected_interface}"
    );
  }

  /**
   * Asserts config export behavior based on exclusion setting.
   *
   * Tests:
   * - When exclusion is FALSE, flag appears in export
   * - When exclusion is TRUE, flag is filtered from export.
   *
   * @param \Drupal\feature_flags\Entity\FeatureFlag $flag
   *   The feature flag to test export for.
   * @param bool $should_export
   *   Whether the flag should appear in export (TRUE) or be excluded (FALSE).
   */
  protected function assertConfigExport(FeatureFlag $flag, bool $should_export): void {
    $settings = $this->config('feature_flags.settings');
    $settings->set('exclude_from_config_export', !$should_export);
    $settings->save();

    // Rebuild container to get a fresh ManagedStorage instance.
    // This ensures the export event is fired again with updated settings.
    $this->container->get('kernel')->rebuildContainer();
    // @phpstan-ignore-next-line assign.propertyType
    $this->container = \Drupal::getContainer();

    $export_storage = $this->container->get('config.storage.export');
    $this->assertInstanceOf(StorageInterface::class, $export_storage);

    $config_name = 'feature_flags.feature_flag.' . $flag->id();

    $has_export = $export_storage->exists($config_name);

    if ($should_export) {
      $this->assertTrue(
        $has_export,
        "Feature flag '{$flag->id()}' should be exported when exclusion is disabled"
      );
    }
    else {
      $this->assertFalse(
        $has_export,
        "Feature flag '{$flag->id()}' should be excluded from export when exclusion is enabled"
      );
    }
  }

}
