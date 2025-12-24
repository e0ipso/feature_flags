<?php

namespace Drupal\Tests\feature_flags\Unit\Plugin\AlgorithmCondition;

use Drupal\Tests\UnitTestCase;
use Drupal\feature_flags\Plugin\AlgorithmCondition\UserId;
use Drupal\Core\Form\FormStateInterface;

/**
 * Tests the UserId algorithm condition plugin.
 *
 * @coversDefaultClass \Drupal\feature_flags\Plugin\AlgorithmCondition\UserId
 * @group feature_flags
 */
class UserIdTest extends UnitTestCase {

  /**
   * The plugin instance under test.
   *
   * @var \Drupal\feature_flags\Plugin\AlgorithmCondition\UserId
   */
  protected UserId $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $configuration = [];
    $plugin_id = 'user_id';
    $plugin_definition = [
      'id' => 'user_id',
      'label' => 'User ID',
      'description' => 'Match against specific user IDs',
      'context_key' => 'user_id',
      'js_library' => 'feature_flags/condition.user_id',
    ];

    $this->plugin = new UserId(
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

    $this->assertArrayHasKey('values', $default_config);
    $this->assertIsArray($default_config['values']);
    $this->assertEmpty($default_config['values']);
  }

  /**
   * Tests getting and setting configuration.
   *
   * @covers ::getConfiguration
   * @covers ::setConfiguration
   */
  public function testGetSetConfiguration(): void {
    $config = [
      'values' => ['1', '5', '10'],
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
      'values' => ['1', '5', '10'],
    ];

    $this->plugin->setConfiguration($config);
    $js_settings = $this->plugin->getJavaScriptSettings();

    $this->assertArrayHasKey('values', $js_settings);
    $this->assertEquals($config['values'], $js_settings['values']);
  }

  /**
   * Tests that context key matches plugin definition.
   *
   * @covers ::getContextKey
   */
  public function testGetContextKey(): void {
    $context_key = $this->plugin->getContextKey();

    $this->assertEquals('user_id', $context_key);
  }

  /**
   * Tests form building creates textfield element.
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationForm(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $built_form = $this->plugin->buildConfigurationForm($form, $form_state);

    $this->assertArrayHasKey('values', $built_form);
    $this->assertEquals('textfield', $built_form['values']['#type']);
    $this->assertTrue($built_form['values']['#required']);
  }

  /**
   * Tests form building with existing values.
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationFormWithExistingValues(): void {
    $config = [
      'values' => ['1', '5', '10'],
    ];
    $this->plugin->setConfiguration($config);

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $built_form = $this->plugin->buildConfigurationForm($form, $form_state);

    $this->assertEquals('1, 5, 10', $built_form['values']['#default_value']);
  }

  /**
   * Tests form validation with empty input.
   *
   * @covers ::validateConfigurationForm
   */
  public function testValidateConfigurationFormEmptyInput(): void {
    $form = [
      'values' => [],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('values', '')
      ->willReturn('');

    $form_state->expects($this->once())
      ->method('setError')
      ->with(
        $form['values'],
        $this->callback(function ($message) {
          return str_contains((string) $message, 'Please enter at least one user ID');
        })
      );

    $this->plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * Tests form validation with whitespace-only input.
   *
   * @covers ::validateConfigurationForm
   */
  public function testValidateConfigurationFormWhitespaceOnly(): void {
    $form = [
      'values' => [],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('values', '')
      ->willReturn('   ');

    $form_state->expects($this->once())
      ->method('setError')
      ->with(
        $form['values'],
        $this->callback(function ($message) {
          return str_contains((string) $message, 'Please enter at least one user ID');
        })
      );

    $this->plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * Tests form validation with valid input.
   *
   * @covers ::validateConfigurationForm
   */
  public function testValidateConfigurationFormValidInput(): void {
    $form = [
      'values' => [],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('values', '')
      ->willReturn('1, 5, 10');

    // Should not call setError for valid input.
    $form_state->expects($this->never())
      ->method('setError');

    $this->plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * Tests form submission parses comma-separated values.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationForm(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('values', '')
      ->willReturn('1, 5, 10');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(['1', '5', '10'], $config['values']);
  }

  /**
   * Tests form submission trims whitespace from values.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationFormTrimsWhitespace(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('values', '')
      ->willReturn('  1  ,  5  ,  10  ');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(['1', '5', '10'], $config['values']);
  }

  /**
   * Tests form submission filters out empty values.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationFormFiltersEmptyValues(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('values', '')
      ->willReturn('1, , 5, , 10');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(['1', '5', '10'], $config['values']);
  }

  /**
   * Tests form submission with single value.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationFormSingleValue(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('values', '')
      ->willReturn('42');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(['42'], $config['values']);
  }

}
