<?php

namespace Drupal\Tests\feature_flags\Unit\Plugin\AlgorithmCondition;

use Drupal\Tests\UnitTestCase;
use Drupal\feature_flags\Plugin\AlgorithmCondition\UserTier;
use Drupal\Core\Form\FormStateInterface;

/**
 * Tests the UserTier algorithm condition plugin.
 *
 * @coversDefaultClass \Drupal\feature_flags\Plugin\AlgorithmCondition\UserTier
 * @group feature_flags
 */
class UserTierTest extends UnitTestCase {

  /**
   * The plugin instance under test.
   *
   * @var \Drupal\feature_flags\Plugin\AlgorithmCondition\UserTier
   */
  protected UserTier $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $configuration = [];
    $plugin_id = 'user_tier';
    $plugin_definition = [
      'id' => 'user_tier',
      'label' => 'User Tier',
      'description' => 'Match against user tier values provided via page context',
      'context_key' => 'user_tier',
      'js_library' => 'feature_flags/condition.user_tier',
      'js_class' => 'UserTierCondition',
    ];

    $this->plugin = new UserTier(
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

    // @phpstan-ignore-next-line method.alreadyNarrowedType
    $this->assertIsArray($default_config);
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
      'values' => ['free', 'premium', 'enterprise'],
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
      'values' => ['free', 'premium', 'enterprise'],
    ];

    $this->plugin->setConfiguration($config);
    $js_settings = $this->plugin->getJavaScriptSettings();

    // @phpstan-ignore-next-line method.alreadyNarrowedType
    $this->assertIsArray($js_settings);
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

    $this->assertEquals('user_tier', $context_key);
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

    // @phpstan-ignore-next-line method.alreadyNarrowedType
    $this->assertIsArray($built_form);
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
      'values' => ['free', 'premium', 'enterprise'],
    ];
    $this->plugin->setConfiguration($config);

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $built_form = $this->plugin->buildConfigurationForm($form, $form_state);

    $this->assertEquals(
      'free, premium, enterprise',
      $built_form['values']['#default_value']
    );
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
          return str_contains((string) $message, 'Please enter at least one tier value');
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
          return str_contains((string) $message, 'Please enter at least one tier value');
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
      ->willReturn('free, premium');

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
      ->willReturn('free, premium, enterprise');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(
      ['free', 'premium', 'enterprise'],
      $config['values']
    );
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
      ->willReturn('  free  ,  premium  ,  enterprise  ');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(
      ['free', 'premium', 'enterprise'],
      $config['values']
    );
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
      ->willReturn('free, , premium, , enterprise');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(
      ['free', 'premium', 'enterprise'],
      $config['values']
    );
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
      ->willReturn('premium');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(['premium'], $config['values']);
  }

  /**
   * Tests form submission preserves case sensitivity.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationFormPreservesCase(): void {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('values', '')
      ->willReturn('Free, Premium, ENTERPRISE');

    $this->plugin->submitConfigurationForm($form, $form_state);

    $config = $this->plugin->getConfiguration();
    $this->assertEquals(
      ['Free', 'Premium', 'ENTERPRISE'],
      $config['values']
    );
  }

}
