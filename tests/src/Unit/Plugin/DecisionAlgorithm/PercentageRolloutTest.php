<?php

namespace Drupal\Tests\feature_flags\Unit\Plugin\DecisionAlgorithm;

use Drupal\Tests\UnitTestCase;
use Drupal\feature_flags\Plugin\DecisionAlgorithm\PercentageRollout;
use Drupal\Core\Form\FormStateInterface;

/**
 * Tests the PercentageRollout decision algorithm plugin.
 *
 * @coversDefaultClass \Drupal\feature_flags\Plugin\DecisionAlgorithm\PercentageRollout
 * @group feature_flags
 */
class PercentageRolloutTest extends UnitTestCase {

  /**
   * The plugin instance under test.
   *
   * @var \Drupal\feature_flags\Plugin\DecisionAlgorithm\PercentageRollout
   */
  protected PercentageRollout $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $configuration = [];
    $plugin_id = 'percentage_rollout';
    $plugin_definition = [
      'id' => 'percentage_rollout',
      'label' => 'Percentage Rollout',
      'description' => 'Distribute users across variants based on configurable percentages.',
      'js_library' => 'feature_flags/algorithm.percentage_rollout',
      'js_class' => 'PercentageRollout',
    ];

    $this->plugin = new PercentageRollout(
      configuration: $configuration,
      plugin_id: $plugin_id,
      plugin_definition: $plugin_definition
    );

    // Mock string translation for $this->t() calls.
    $this->plugin->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests default configuration.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration(): void {
    $default_config = $this->plugin->defaultConfiguration();

    $this->assertIsArray($default_config);
    $this->assertArrayHasKey('percentages', $default_config);
    $this->assertIsArray($default_config['percentages']);
    $this->assertEmpty($default_config['percentages']);
  }

  /**
   * Tests getting and setting configuration.
   *
   * @covers ::getConfiguration
   * @covers ::setConfiguration
   */
  public function testGetSetConfiguration(): void {
    $config = [
      'percentages' => [
        'variant-uuid-1' => 50,
        'variant-uuid-2' => 50,
      ],
    ];

    $this->plugin->setConfiguration($config);
    $retrieved_config = $this->plugin->getConfiguration();

    $this->assertEquals($config, $retrieved_config);
  }

  /**
   * Tests JavaScript settings generation.
   *
   * @covers ::getJavaScriptSettings
   */
  public function testGetJavaScriptSettings(): void {
    $config = [
      'percentages' => [
        'variant-uuid-1' => 60,
        'variant-uuid-2' => 40,
      ],
    ];

    $this->plugin->setConfiguration($config);
    $js_settings = $this->plugin->getJavaScriptSettings();

    $this->assertIsArray($js_settings);
    $this->assertArrayHasKey('percentages', $js_settings);
    $this->assertEquals($config['percentages'], $js_settings['percentages']);
  }

  /**
   * Tests form building with variants.
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationForm(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    // Mock form state with variants stored using ->get().
    $form_state->expects($this->once())
      ->method('get')
      ->with('variants')
      ->willReturn([
        [
          'uuid' => 'variant-uuid-1',
          'label' => 'Variant A',
          'value' => '{"enabled": true}',
        ],
        [
          'uuid' => 'variant-uuid-2',
          'label' => 'Variant B',
          'value' => '{"enabled": false}',
        ],
      ]);

    $built_form = $this->plugin->buildConfigurationForm($form, $form_state);

    $this->assertIsArray($built_form);
    $this->assertArrayHasKey('percentages', $built_form);
    $this->assertEquals('fieldset', $built_form['percentages']['#type']);
    $this->assertArrayHasKey('variant-uuid-1', $built_form['percentages']);
    $this->assertArrayHasKey('variant-uuid-2', $built_form['percentages']);
  }

  /**
   * Tests form validation with invalid percentage sum.
   *
   * @covers ::validateConfigurationForm
   */
  public function testValidateConfigurationFormInvalidSum(): void {
    $form = [
      'percentages' => [],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('percentages')
      ->willReturn([
        'variant-uuid-1' => 60,
        'variant-uuid-2' => 30,
      ]);

    $form_state->expects($this->once())
      ->method('setError')
      ->with(
        $form['percentages'],
        $this->callback(function ($message) {
          return str_contains((string) $message, 'must equal exactly 100%');
        })
      );

    $this->plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * Tests form validation with valid percentage sum.
   *
   * @covers ::validateConfigurationForm
   */
  public function testValidateConfigurationFormValidSum(): void {
    $form = [
      'percentages' => [],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('percentages')
      ->willReturn([
        'variant-uuid-1' => 60,
        'variant-uuid-2' => 40,
      ]);

    // Should not call setError for valid sum.
    $form_state->expects($this->never())
      ->method('setError');

    $this->plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * Tests form submission.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationForm(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('percentages')
      ->willReturn([
        'variant-uuid-1' => 70,
        'variant-uuid-2' => 30,
      ]);

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(70, $config['percentages']['variant-uuid-1']);
    $this->assertEquals(30, $config['percentages']['variant-uuid-2']);
  }

}
