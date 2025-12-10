<?php

namespace Drupal\Tests\feature_flags\Unit\Plugin\DecisionAlgorithm;

use Drupal\Tests\UnitTestCase;
use Drupal\feature_flags\Plugin\DecisionAlgorithm\PercentageRollout;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;

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
    $form_state = $this->createMock(SubformStateInterface::class);

    // Mock the complete form state with variants.
    $complete_form_state = $this->createMock(FormStateInterface::class);
    $complete_form_state->expects($this->once())
      ->method('getValue')
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

    $form_state->expects($this->once())
      ->method('getCompleteFormState')
      ->willReturn($complete_form_state);

    $built_form = $this->plugin->buildConfigurationForm($form, $form_state);

    $this->assertIsArray($built_form);
    $this->assertArrayHasKey('percentages', $built_form);
    $this->assertEquals('table', $built_form['percentages']['#type']);
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
      'percentages' => [
        'variant-uuid-1' => ['percentage' => ['#parents' => ['percentages', 'variant-uuid-1', 'percentage']]],
        'variant-uuid-2' => ['percentage' => ['#parents' => ['percentages', 'variant-uuid-2', 'percentage']]],
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('percentages')
      ->willReturn([
        'variant-uuid-1' => ['percentage' => 60],
        'variant-uuid-2' => ['percentage' => 30],
      ]);

    $form_state->expects($this->once())
      ->method('setError')
      ->with(
        $this->anything(),
        $this->stringContains('must add up to exactly 100')
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
      'percentages' => [
        'variant-uuid-1' => ['percentage' => ['#parents' => ['percentages', 'variant-uuid-1', 'percentage']]],
        'variant-uuid-2' => ['percentage' => ['#parents' => ['percentages', 'variant-uuid-2', 'percentage']]],
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('percentages')
      ->willReturn([
        'variant-uuid-1' => ['percentage' => 60],
        'variant-uuid-2' => ['percentage' => 40],
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
        'variant-uuid-1' => ['percentage' => 70],
        'variant-uuid-2' => ['percentage' => 30],
      ]);

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(70, $config['percentages']['variant-uuid-1']);
    $this->assertEquals(30, $config['percentages']['variant-uuid-2']);
  }

}
