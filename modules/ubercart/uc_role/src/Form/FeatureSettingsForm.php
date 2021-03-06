<?php

namespace Drupal\uc_role\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Grants roles upon accepted payment of products.
 *
 * The uc_role module will grant specified roles upon purchase of specified
 * products. Granted roles can be set to have a expiration date. Users can also
 * be notified of the roles they are granted and when the roles will
 * expire/need to be renewed/etc.
 */
class FeatureSettingsForm extends ConfigFormBase {

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Form constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uc_role_feature_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'uc_role.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_role_choices = user_role_names(TRUE);
    unset($default_role_choices[RoleInterface::AUTHENTICATED_ID]);
    $roles_config = $this->config('uc_role.settings');

    if (!count($default_role_choices)) {
      $form['no_roles'] = [
        '#markup' => $this->t('You need to <a href=":url">create new roles</a> before any can be added as product features.', [':url' => Url::fromRoute('user.role_add', [], ['query' => ['destination' => 'admin/store/config/products']])->toString()]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];

      return $form;
    }

    $form['default_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Default role'),
      '#default_value' => $roles_config->get('default_role'),
      '#description' => $this->t('The default role Ubercart grants on specified products.'),
      '#options' => _uc_role_get_choices(),
    ];
    $form['default_role_choices'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Product roles'),
      '#default_value' => $roles_config->get('default_role_choices'),
      '#multiple' => TRUE,
      '#description' => $this->t('These are roles that Ubercart can grant to customers who purchase specified products. If you leave all roles unchecked, they will all be eligible for adding to a product.'),
      '#options' => $default_role_choices,
    ];

    $form['role_lifetime'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default role expiration'),
    ];

    $form['role_lifetime']['default_end_expiration'] = [
      '#type' => 'select',
      '#title' => $this->t('Expiration type'),
      '#options' => [
        'rel' => $this->t('Relative to purchase date'),
        'abs' => $this->t('Fixed date'),
      ],
      '#default_value' => $roles_config->get('default_end_expiration'),
    ];
    $form['role_lifetime']['default_length'] = [
      '#type' => 'textfield',
      '#default_value' => ($roles_config->get('default_granularity') == 'never') ? NULL : $roles_config->get('default_length'),
      '#size' => 4,
      '#maxlength' => 4,
      '#prefix' => '<div class="expiration">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => ['select[name="default_end_expiration"]' => ['value' => 'rel']],
        'invisible' => ['select[name="default_granularity"]' => ['value' => 'never']],
      ],
    ];
    $form['role_lifetime']['default_granularity'] = [
      '#type' => 'select',
      '#default_value' => $roles_config->get('default_granularity'),
      '#options' => [
        'never' => $this->t('never'),
        'day' => $this->t('day(s)'),
        'week' => $this->t('week(s)'),
        'month' => $this->t('month(s)'),
        'year' => $this->t('year(s)'),
      ],
      '#description' => $this->t('From the time the role was purchased.'),
      '#prefix' => '<div class="expiration">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => ['select[name="default_end_expiration"]' => ['value' => 'rel']],
      ],
    ];
    $form['role_lifetime']['absolute'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => ['select[name="default_end_expiration"]' => ['value' => 'abs']],
      ],
    ];
    $date = (int) $roles_config->get('default_end_time');
    $date = !empty($date) ? DrupalDateTime::createFromTimestamp($date) : DrupalDateTime::createFromTimestamp($this->time->getRequestTime());
    $form['role_lifetime']['absolute']['default_end_time'] = [
      '#type' => 'datetime',
      '#description' => $this->t('Expire the role at the beginning of this day.'),
      //'#description' => $this->t('Enter a datetime.'),
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#default_value' => $date,
    ];
    $form['role_lifetime']['default_by_quantity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Multiply by quantity'),
      '#description' => $this->t('Check if the role duration should be multiplied by the quantity purchased.'),
      '#default_value' => $roles_config->get('default_by_quantity'),
    ];
    $form['reminder']['reminder_length'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Time before reminder'),
      '#default_value' => ($roles_config->get('reminder_granularity') == 'never') ? NULL : $roles_config->get('reminder_length'),
      '#size' => 4,
      '#maxlength' => 4,
      '#prefix' => '<div class="expiration">',
      '#suffix' => '</div>',
      '#states' => [
        'disabled' => ['select[name="reminder_granularity"]' => ['value' => 'never']],
      ],
    ];
    $form['reminder']['reminder_granularity'] = [
      '#type' => 'select',
      '#default_value' => $roles_config->get('reminder_granularity'),
      '#options' => [
        'never' => $this->t('never'),
        'day' => $this->t('day(s)'),
        'week' => $this->t('week(s)'),
        'month' => $this->t('month(s)'),
        'year' => $this->t('year(s)'),
      ],
      '#description' => $this->t('The amount of time before a role expiration takes place that a customer is notified of its expiration.'),
      '#prefix' => '<div class="expiration">',
      '#suffix' => '</div>',
    ];
    $form['default_show_expiration'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show expirations on user page'),
      '#default_value' => $roles_config->get('default_show_expiration'),
      '#description' => $this->t('If users have any role expirations they will be displayed on their account page.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // If the user selected a granularity, let's make sure they
    // also selected a duration.
    if ($form_state->getValue('reminder_granularity') != 'never' &&
        $form_state->getValue('reminder_length') < 1) {
      $form_state->setErrorByName('reminder_length', $this->t('You set the granularity (%gran), but you did not set how many. Please enter a positive non-zero integer.', ['%gran' => $form_state->getValue('reminder_granularity') . '(s)']));
    }

    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $roles_config = $this->config('uc_role.settings');
    $roles_config
      ->set('default_role', $form_state->getValue('default_role'))
      ->set('default_role_choices', $form_state->getValue('default_role_choices'))
      ->set('default_end_expiration', $form_state->getValue('default_end_expiration'))
      ->set('default_length', $form_state->getValue('default_length'))
      ->set('default_granularity', $form_state->getValue('default_granularity'))
      ->set('default_end_time', $form_state->getValue('default_end_time')->getTimestamp())
      ->set('default_by_quantity', $form_state->getValue('default_by_quantity'))
      ->set('reminder_length', $form_state->getValue('reminder_length'))
      ->set('reminder_granularity', $form_state->getValue('reminder_granularity'))
      ->set('default_show_expiration', $form_state->getValue('default_show_expiration'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
