<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\feature_flags\Entity\FeatureFlag;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Feature Flag add and edit forms.
 */
class FeatureFlagForm extends EntityForm {

  /**
   * The decision algorithm plugin manager.
   *
   * @var \Drupal\Core\Plugin\DefaultPluginManager
   */
  protected $algorithmPluginManager;

  /**
   * The algorithm condition plugin manager.
   *
   * @var \Drupal\Core\Plugin\DefaultPluginManager
   */
  protected $conditionPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->algorithmPluginManager = $container->get('plugin.manager.feature_flags.decision_algorithm');
    $instance->conditionPluginManager = $container->get('plugin.manager.feature_flags.algorithm_condition');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\feature_flags\Entity\FeatureFlag $feature_flag */
    $feature_flag = $this->entity;

    // Attach libraries.
    $form['#attached']['library'][] = 'feature_flags/admin_form';
    $form['#attached']['library'][] = 'feature_flags/json_editor';

    // Use vertical tabs for better organization.
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 99,
    ];

    // Basic Information tab.
    $form['basic'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic Information'),
      '#group' => 'tabs',
      '#weight' => 0,
    ];

    $form['basic']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $feature_flag->label(),
      '#description' => $this->t('Human-readable name for this feature flag.'),
      '#required' => TRUE,
    ];

    $form['basic']['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $feature_flag->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
        'source' => ['basic', 'label'],
      ],
      '#disabled' => !$feature_flag->isNew(),
      '#maxlength' => 64,
    ];

    $form['basic']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $feature_flag->getDescription(),
      '#description' => $this->t('Internal notes about this feature flag.'),
      '#rows' => 3,
    ];

    $form['basic']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $feature_flag->status(),
      '#description' => $this->t('Whether this feature flag is active.'),
    ];

    // Variants tab.
    $this->buildVariantsForm($form, $form_state, $feature_flag);

    // Algorithms tab.
    $this->buildAlgorithmsForm($form, $form_state, $feature_flag);

    return $form;
  }

  /**
   * Builds the variants form section.
   */
  protected function buildVariantsForm(array &$form, FormStateInterface $form_state, FeatureFlag $feature_flag): void {
    $form['variants_tab'] = [
      '#type' => 'details',
      '#title' => $this->t('Variants'),
      '#group' => 'tabs',
      '#weight' => 1,
      '#description' => $this->t('Define the possible values this feature flag can resolve to. Minimum 2 variants required.'),
    ];

    // Get variants from form state or entity.
    $variants = $form_state->get('variants');
    if ($variants === NULL) {
      $variants = $feature_flag->getVariants();
      // Ensure we have at least 2 empty variants for new entities.
      if (empty($variants)) {
        $variants = [
          ['uuid' => \Drupal::service('uuid')->generate(), 'label' => '', 'value' => '{}'],
          ['uuid' => \Drupal::service('uuid')->generate(), 'label' => '', 'value' => '{}'],
        ];
      }
      $form_state->set('variants', $variants);
    }

    $form['variants_tab']['variants_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="variants-wrapper">',
      '#suffix' => '</div>',
      '#tree' => FALSE,
    ];

    $form['variants_tab']['variants_wrapper']['variants'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($variants as $delta => $variant) {
      $form['variants_tab']['variants_wrapper']['variants'][$delta] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['variant-item']],
      ];

      $form['variants_tab']['variants_wrapper']['variants'][$delta]['uuid'] = [
        '#type' => 'value',
        '#value' => $variant['uuid'],
      ];

      $form['variants_tab']['variants_wrapper']['variants'][$delta]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Variant label'),
        '#default_value' => $variant['label'] ?? '',
        '#required' => TRUE,
        '#size' => 30,
      ];

      $form['variants_tab']['variants_wrapper']['variants'][$delta]['value'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Value (JSON)'),
        '#default_value' => $variant['value'] ?? '{}',
        '#required' => TRUE,
        '#rows' => 3,
        '#attributes' => [
          'class' => ['json-editor-textarea'],
          'data-json-editor' => 'true',
        ],
        '#description' => $this->t('Enter a valid JSON value for this variant.'),
      ];

      // Only allow removal if more than 2 variants.
      if (count($variants) > 2) {
        $form['variants_tab']['variants_wrapper']['variants'][$delta]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_variant_' . $delta,
          '#submit' => ['::removeVariantSubmit'],
          '#ajax' => [
            'callback' => '::variantsAjaxCallback',
            'wrapper' => 'variants-wrapper',
          ],
          '#limit_validation_errors' => [],
          '#variant_delta' => $delta,
        ];
      }
    }

    $form['variants_tab']['variants_wrapper']['add_variant'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add variant'),
      '#submit' => ['::addVariantSubmit'],
      '#ajax' => [
        'callback' => '::variantsAjaxCallback',
        'wrapper' => 'variants-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * Submit handler for adding a variant.
   */
  public function addVariantSubmit(array &$form, FormStateInterface $form_state): void {
    $variants = $form_state->get('variants');
    $variants[] = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'label' => '',
      'value' => '{}',
    ];
    $form_state->set('variants', $variants);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a variant.
   */
  public function removeVariantSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $triggering_element['#variant_delta'];

    $variants = $form_state->get('variants');
    unset($variants[$delta]);
    $variants = array_values($variants);
    $form_state->set('variants', $variants);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for variants operations.
   */
  public function variantsAjaxCallback(array &$form, FormStateInterface $form_state): array {
    return $form['variants_tab']['variants_wrapper'];
  }

  /**
   * Builds the algorithms form section.
   */
  protected function buildAlgorithmsForm(array &$form, FormStateInterface $form_state, FeatureFlag $feature_flag): void {
    $form['algorithms_tab'] = [
      '#type' => 'details',
      '#title' => $this->t('Decision Algorithms'),
      '#group' => 'tabs',
      '#weight' => 2,
      '#description' => $this->t('Configure algorithms that determine which variant a user receives. Algorithms are evaluated in order; the first one whose conditions are met will be used. At least one algorithm without conditions is required as a catch-all.'),
    ];

    // Get algorithms from form state or entity.
    $algorithms = $form_state->get('algorithms');
    if ($algorithms === NULL) {
      $algorithms = $feature_flag->getAlgorithms();
      if (empty($algorithms)) {
        $algorithms = [];
      }
      $form_state->set('algorithms', $algorithms);
    }

    $form['algorithms_tab']['algorithms_wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="algorithms-wrapper">',
      '#suffix' => '</div>',
      '#tree' => FALSE,
    ];

    $form['algorithms_tab']['algorithms_wrapper']['algorithms'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($algorithms as $delta => $algorithm) {
      $plugin_id = $algorithm['plugin_id'] ?? NULL;
      if (!$plugin_id) {
        continue;
      }

      try {
        $plugin_definition = $this->algorithmPluginManager->getDefinition($plugin_id);
        $plugin_label = (string) $plugin_definition['label'];
      }
      catch (\Exception $e) {
        continue;
      }

      $form['algorithms_tab']['algorithms_wrapper']['algorithms'][$delta] = [
        '#type' => 'details',
        '#title' => $this->t('Algorithm: @label', ['@label' => $plugin_label]),
        '#open' => TRUE,
        '#attributes' => ['class' => ['algorithm-item']],
      ];

      $form['algorithms_tab']['algorithms_wrapper']['algorithms'][$delta]['uuid'] = [
        '#type' => 'value',
        '#value' => $algorithm['uuid'],
      ];

      $form['algorithms_tab']['algorithms_wrapper']['algorithms'][$delta]['plugin_id'] = [
        '#type' => 'value',
        '#value' => $plugin_id,
      ];

      $form['algorithms_tab']['algorithms_wrapper']['algorithms'][$delta]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#default_value' => $algorithm['weight'] ?? 0,
        '#delta' => 50,
      ];

      // Plugin configuration subform.
      $this->buildAlgorithmConfigurationForm(
        $form['algorithms_tab']['algorithms_wrapper']['algorithms'][$delta],
        $form_state,
        $plugin_id,
        $algorithm['configuration'] ?? [],
        $delta
      );

      // Conditions section.
      $this->buildConditionsForm(
        $form['algorithms_tab']['algorithms_wrapper']['algorithms'][$delta],
        $form_state,
        $algorithm['conditions'] ?? [],
        $delta
      );

      $form['algorithms_tab']['algorithms_wrapper']['algorithms'][$delta]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove algorithm'),
        '#name' => 'remove_algorithm_' . $delta,
        '#submit' => ['::removeAlgorithmSubmit'],
        '#ajax' => [
          'callback' => '::algorithmsAjaxCallback',
          'wrapper' => 'algorithms-wrapper',
        ],
        '#limit_validation_errors' => [],
        '#algorithm_delta' => $delta,
      ];
    }

    // Add algorithm section.
    $form['algorithms_tab']['algorithms_wrapper']['add_algorithm'] = [
      '#type' => 'details',
      '#title' => $this->t('Add Algorithm'),
      '#open' => empty($algorithms),
    ];

    $algorithm_options = [];
    foreach ($this->algorithmPluginManager->getDefinitions() as $plugin_id => $definition) {
      $algorithm_options[$plugin_id] = $definition['label'] . ' - ' . $definition['description'];
    }

    $form['algorithms_tab']['algorithms_wrapper']['add_algorithm']['algorithm_plugin_select'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select algorithm type'),
      '#options' => $algorithm_options,
      '#default_value' => $form_state->getValue('algorithm_plugin_select') ?? array_key_first($algorithm_options),
    ];

    $form['algorithms_tab']['algorithms_wrapper']['add_algorithm']['add_algorithm_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add algorithm'),
      '#submit' => ['::addAlgorithmSubmit'],
      '#ajax' => [
        'callback' => '::algorithmsAjaxCallback',
        'wrapper' => 'algorithms-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * Builds the algorithm configuration form.
   */
  protected function buildAlgorithmConfigurationForm(array &$form, FormStateInterface $form_state, string $plugin_id, array $configuration, int $delta): void {
    try {
      $plugin = $this->algorithmPluginManager->createInstance($plugin_id, $configuration);

      $form['configuration'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];

      // Get variants from form state for percentage rollout.
      $variants = $form_state->get('variants') ?? [];

      // Create subform state.
      $subform_state = SubformState::createForSubform($form['configuration'], $form, $form_state);
      $subform_state->set('variants', $variants);

      $form['configuration'] = $plugin->buildConfigurationForm($form['configuration'], $subform_state);
    }
    catch (\Exception $e) {
      $form['configuration'] = [
        '#markup' => $this->t('Error loading plugin configuration: @message', ['@message' => $e->getMessage()]),
      ];
    }
  }

  /**
   * Builds the conditions form for an algorithm.
   */
  protected function buildConditionsForm(array &$form, FormStateInterface $form_state, array $conditions, int $algorithm_delta): void {
    $form['conditions_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Conditions'),
      '#open' => !empty($conditions),
      '#description' => $this->t('If no conditions are specified, this algorithm will always apply (catch-all).'),
    ];

    $form['conditions_section']['conditions'] = [
      '#type' => 'container',
      '#prefix' => '<div id="conditions-wrapper-' . $algorithm_delta . '">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    foreach ($conditions as $condition_delta => $condition) {
      $condition_plugin_id = $condition['plugin_id'] ?? NULL;
      if (!$condition_plugin_id) {
        continue;
      }

      $form['conditions_section']['conditions'][$condition_delta] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['condition-item']],
      ];

      $form['conditions_section']['conditions'][$condition_delta]['uuid'] = [
        '#type' => 'value',
        '#value' => $condition['uuid'],
      ];

      // Condition plugin selection.
      $condition_options = [];
      foreach ($this->conditionPluginManager->getDefinitions() as $cond_plugin_id => $definition) {
        $condition_options[$cond_plugin_id] = $definition['label'];
      }

      $form['conditions_section']['conditions'][$condition_delta]['plugin_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Condition type'),
        '#options' => $condition_options,
        '#default_value' => $condition_plugin_id,
        '#required' => TRUE,
      ];

      $form['conditions_section']['conditions'][$condition_delta]['operator'] = [
        '#type' => 'select',
        '#title' => $this->t('Operator'),
        '#options' => [
          'AND' => $this->t('AND (all values must match)'),
          'OR' => $this->t('OR (any value must match)'),
          'NOT' => $this->t('NOT (none of the values must match)'),
        ],
        '#default_value' => $condition['operator'] ?? 'OR',
        '#required' => TRUE,
      ];

      // Condition configuration.
      try {
        $condition_plugin = $this->conditionPluginManager->createInstance(
          $condition_plugin_id,
          $condition['configuration'] ?? []
        );

        $form['conditions_section']['conditions'][$condition_delta]['configuration'] = [
          '#type' => 'container',
          '#tree' => TRUE,
        ];

        $subform_state = SubformState::createForSubform(
          $form['conditions_section']['conditions'][$condition_delta]['configuration'],
          $form,
          $form_state
        );

        $form['conditions_section']['conditions'][$condition_delta]['configuration'] =
          $condition_plugin->buildConfigurationForm(
            $form['conditions_section']['conditions'][$condition_delta]['configuration'],
            $subform_state
          );
      }
      catch (\Exception $e) {
        $form['conditions_section']['conditions'][$condition_delta]['configuration'] = [
          '#markup' => $this->t('Error loading condition configuration.'),
        ];
      }

      $form['conditions_section']['conditions'][$condition_delta]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove condition'),
        '#name' => 'remove_condition_' . $algorithm_delta . '_' . $condition_delta,
        '#submit' => ['::removeConditionSubmit'],
        '#ajax' => [
          'callback' => '::algorithmsAjaxCallback',
          'wrapper' => 'algorithms-wrapper',
        ],
        '#limit_validation_errors' => [],
        '#algorithm_delta' => $algorithm_delta,
        '#condition_delta' => $condition_delta,
      ];
    }

    // Add condition section.
    $form['conditions_section']['add_condition'] = [
      '#type' => 'container',
    ];

    $condition_options = [];
    foreach ($this->conditionPluginManager->getDefinitions() as $plugin_id => $definition) {
      $condition_options[$plugin_id] = $definition['label'];
    }

    $form['conditions_section']['add_condition']['condition_plugin_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Condition type'),
      '#options' => $condition_options,
      '#default_value' => array_key_first($condition_options),
    ];

    $form['conditions_section']['add_condition']['add_condition_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add condition'),
      '#name' => 'add_condition_' . $algorithm_delta,
      '#submit' => ['::addConditionSubmit'],
      '#ajax' => [
        'callback' => '::algorithmsAjaxCallback',
        'wrapper' => 'algorithms-wrapper',
      ],
      '#limit_validation_errors' => [],
      '#algorithm_delta' => $algorithm_delta,
    ];
  }

  /**
   * Submit handler for adding an algorithm.
   */
  public function addAlgorithmSubmit(array &$form, FormStateInterface $form_state): void {
    $plugin_id = $form_state->getValue('algorithm_plugin_select');

    $algorithms = $form_state->get('algorithms');
    $algorithms[] = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'plugin_id' => $plugin_id,
      'configuration' => [],
      'conditions' => [],
      'weight' => count($algorithms),
    ];
    $form_state->set('algorithms', $algorithms);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing an algorithm.
   */
  public function removeAlgorithmSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $triggering_element['#algorithm_delta'];

    $algorithms = $form_state->get('algorithms');
    unset($algorithms[$delta]);
    $algorithms = array_values($algorithms);
    $form_state->set('algorithms', $algorithms);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for algorithms operations.
   */
  public function algorithmsAjaxCallback(array &$form, FormStateInterface $form_state): array {
    return $form['algorithms_tab']['algorithms_wrapper'];
  }

  /**
   * Submit handler for adding a condition.
   */
  public function addConditionSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $algorithm_delta = $triggering_element['#algorithm_delta'];

    $algorithms = $form_state->get('algorithms');

    // Get the selected condition plugin from the parent form path.
    $condition_plugin_id = $form_state->getValue([
      'algorithms',
      $algorithm_delta,
      'conditions_section',
      'add_condition',
      'condition_plugin_select'
    ]);

    $algorithms[$algorithm_delta]['conditions'][] = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'plugin_id' => $condition_plugin_id,
      'operator' => 'OR',
      'configuration' => [],
    ];

    $form_state->set('algorithms', $algorithms);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a condition.
   */
  public function removeConditionSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $algorithm_delta = $triggering_element['#algorithm_delta'];
    $condition_delta = $triggering_element['#condition_delta'];

    $algorithms = $form_state->get('algorithms');
    unset($algorithms[$algorithm_delta]['conditions'][$condition_delta]);
    $algorithms[$algorithm_delta]['conditions'] = array_values($algorithms[$algorithm_delta]['conditions']);
    $form_state->set('algorithms', $algorithms);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Validate variants.
    $variants = $form_state->getValue('variants', []);
    if (count($variants) < 2) {
      $form_state->setErrorByName('variants', $this->t('At least 2 variants are required.'));
    }

    // Validate JSON values.
    foreach ($variants as $delta => $variant) {
      if (!empty($variant['value'])) {
        json_decode($variant['value']);
        if (json_last_error() !== JSON_ERROR_NONE) {
          $form_state->setErrorByName(
            "variants][$delta][value",
            $this->t('Variant @delta has invalid JSON: @error', [
              '@delta' => $delta + 1,
              '@error' => json_last_error_msg(),
            ])
          );
        }
      }
    }

    // Validate algorithms.
    $algorithms = $form_state->getValue('algorithms', []);
    if (empty($algorithms)) {
      $form_state->setErrorByName('algorithms', $this->t('At least one algorithm is required.'));
      return;
    }

    // Validate catch-all algorithm (one with no conditions).
    $has_catch_all = FALSE;
    foreach ($algorithms as $algorithm) {
      if (empty($algorithm['conditions_section']['conditions'])) {
        $has_catch_all = TRUE;
        break;
      }
    }
    if (!$has_catch_all) {
      $form_state->setErrorByName('algorithms', $this->t('At least one algorithm without conditions (catch-all) is required.'));
    }

    // Validate algorithm plugin configurations.
    foreach ($algorithms as $delta => $algorithm) {
      $plugin_id = $algorithm['plugin_id'] ?? NULL;
      if (!$plugin_id) {
        continue;
      }

      try {
        $plugin = $this->algorithmPluginManager->createInstance($plugin_id, $algorithm['configuration'] ?? []);

        $subform = $form['algorithms_tab']['algorithms_wrapper']['algorithms'][$delta]['configuration'] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $subform_state->set('variants', $form_state->get('variants'));

        $plugin->validateConfigurationForm($subform, $subform_state);
      }
      catch (\Exception $e) {
        $form_state->setErrorByName(
          "algorithms][$delta",
          $this->t('Error validating algorithm: @message', ['@message' => $e->getMessage()])
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\feature_flags\Entity\FeatureFlag $feature_flag */
    $feature_flag = $this->entity;

    // Process variants from form state.
    $variants = $form_state->getValue('variants', []);
    $feature_flag->setVariants($variants);

    // Process algorithms from form state.
    $algorithms = $form_state->getValue('algorithms', []);

    // Clean up algorithms structure and process plugin configurations.
    $processed_algorithms = [];
    foreach ($algorithms as $delta => $algorithm) {
      $plugin_id = $algorithm['plugin_id'] ?? NULL;
      if (!$plugin_id) {
        continue;
      }

      $processed_algorithm = [
        'uuid' => $algorithm['uuid'],
        'plugin_id' => $plugin_id,
        'weight' => $algorithm['weight'] ?? 0,
        'configuration' => $algorithm['configuration'] ?? [],
        'conditions' => [],
      ];

      // Process conditions.
      $conditions = $algorithm['conditions_section']['conditions'] ?? [];
      foreach ($conditions as $condition) {
        if (empty($condition['plugin_id'])) {
          continue;
        }

        $processed_algorithm['conditions'][] = [
          'uuid' => $condition['uuid'],
          'plugin_id' => $condition['plugin_id'],
          'operator' => $condition['operator'] ?? 'OR',
          'configuration' => $condition['configuration'] ?? [],
        ];
      }

      $processed_algorithms[] = $processed_algorithm;
    }

    $feature_flag->setAlgorithms($processed_algorithms);

    $status = $feature_flag->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Created the %label feature flag.', [
        '%label' => $feature_flag->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated the %label feature flag.', [
        '%label' => $feature_flag->label(),
      ]));
    }

    $form_state->setRedirectUrl($feature_flag->toUrl('collection'));

    return $status;
  }

  /**
   * Helper function to check whether a Feature Flag configuration entity exists.
   */
  public function exist(string $id): bool {
    $entity = $this->entityTypeManager
      ->getStorage('feature_flag')
      ->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
    return (bool) $entity;
  }

}
