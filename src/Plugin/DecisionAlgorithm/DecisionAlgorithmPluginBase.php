<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Plugin\DecisionAlgorithm;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Decision Algorithm plugins.
 *
 * Provides common functionality for algorithm plugins including configuration
 * management and form handling.
 */
abstract class DecisionAlgorithmPluginBase extends PluginBase implements DecisionAlgorithmInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Constructs a DecisionAlgorithmPluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Default implementation - no validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Default implementation - no submission processing.
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function getJavaScriptSettings(): array;

  /**
   * Helper method to get variants from the complete form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state, which may be a subform state.
   *
   * @return array
   *   The variants array.
   */
  protected function getVariantsFromFormState(FormStateInterface $form_state): array {
    // Get the complete form state (in case we're in a subform).
    $complete_form_state = $form_state instanceof SubformState
      ? $form_state->getCompleteFormState()
      : $form_state;

    return $complete_form_state->get('variants') ?? [];
  }

}
