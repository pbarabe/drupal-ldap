<?php

namespace Drupal\ldap_authorization\Plugin\authorization\Provider;

use Drupal\authorization\AuthorizationSkipAuthorization;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\authorization\Provider\ProviderPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\LdapTransformationTraits;
use Drupal\ldap_user\Processor\DrupalUserProcessor;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The LDAP authorization provider for authorization module.
 *
 * @AuthorizationProvider(
 *   id = "ldap_provider",
 *   label = @Translation("LDAP Authorization")
 * )
 */
class LDAPAuthorizationProvider extends ProviderPluginBase implements ContainerFactoryPluginInterface {

  use LdapTransformationTraits;

  /**
   * {@inheritdoc}
   */
  protected $handlers = ['ldap', 'ldap_authentication'];

  /**
   * {@inheritdoc}
   */
  protected $syncOnLogonSupported = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $revocationSupported = TRUE;

  protected $entityTypeManager;

  protected $drupalUserProcessor;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\ldap_user\Processor\DrupalUserProcessor $drupal_user_processor
   *   Drupal user processor.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, DrupalUserProcessor $drupal_user_processor) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->drupalUserProcessor = $drupal_user_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('ldap.drupal_user_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->configuration['profile'];
    $tokens = $this->getTokens();
    $tokens += $profile->getTokens();
    if ($profile->hasValidConsumer() && method_exists($profile->getConsumer(), 'getTokens')) {
      $tokens += $profile->getConsumer()->getTokens();
    }

    $storage = $this->entityTypeManager->getStorage('ldap_server');
    $query_results = $storage->getQuery()->execute();
    /** @var \Drupal\ldap_servers\Entity\Server[] $servers */
    $servers = $storage->loadMultiple($query_results);

    $form['status'] = [
      '#type' => 'fieldset',
      '#title' => t('Base configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    if (count($servers) == 0) {
      $form['status']['server'] = [
        '#type' => 'markup',
        '#markup' => t('<strong>Warning</strong>: You must create an LDAP Server first.'),
      ];
      $this->messenger()->addWarning($this->t('You must create an LDAP Server first.'));
    }
    else {
      $server_options = [];
      foreach ($servers as $id => $server) {
        /** @var \Drupal\ldap_servers\Entity\Server $server */
        $server_options[$id] = $server->label() . ' (' . $server->get('address') . ')';
      }
    }

    $provider_config = $profile->getProviderConfig();

    if (!empty($server_options)) {
      if (isset($provider_config['status'])) {
        $default_server = $provider_config['status']['server'];
      }
      elseif (count($server_options) == 1) {
        $default_server = key($server_options);
      }
      else {
        $default_server = '';
      }
      $form['status']['server'] = [
        '#type' => 'radios',
        '#title' => t('LDAP Server used in @profile_name configuration.', $tokens),
        '#required' => 1,
        '#default_value' => $default_server,
        '#options' => $server_options,
      ];
    }

    $form['status']['only_ldap_authenticated'] = [
      '#type' => 'checkbox',
      '#title' => t('Only apply the following <strong>LDAP</strong> to <strong>@consumer_name</strong> configuration to users authenticated via LDAP', $tokens),
      '#description' => t('One uncommon reason for disabling this is when you are using Drupal authentication, but want to leverage LDAP for authorization; for this to work the Drupal username still has to map to an LDAP entry.'),
      '#default_value' => isset($provider_config['status'], $provider_config['status']['only_ldap_authenticated']) ? $provider_config['status']['only_ldap_authenticated'] : '',
    ];

    $form['filter_and_mappings'] = [
      '#type' => 'fieldset',
      '#title' => t('LDAP to @consumer_name mapping and filtering', $tokens),
      '#description' => t('Representations of groups derived from LDAP might initially look like:
        <ul>
        <li><code>cn=students,ou=groups,dc=hogwarts,dc=edu</code></li>
        <li><code>cn=gryffindor,ou=groups,dc=hogwarts,dc=edu</code></li>
        <li><code>cn=faculty,ou=groups,dc=hogwarts,dc=edu</code></li>
        </ul>
        <strong>Warning: If you enable "Create <em>@consumer_name</em> targets if they do not exist" under conditions, all LDAP groups will be synced!</strong>', $tokens),
      '#collapsible' => TRUE,
    ];

    $form['filter_and_mappings']['use_first_attr_as_groupid'] = [
      '#type' => 'checkbox',
      '#title' => t('Convert full DN to value of first attribute before mapping'),
      '#description' => t('Example: <code>cn=students,ou=groups,dc=hogwarts,dc=edu</code> would be converted to <code>students</code>'),
      '#default_value' => isset($provider_config['filter_and_mappings'], $provider_config['filter_and_mappings']['use_first_attr_as_groupid']) ? $provider_config['filter_and_mappings']['use_first_attr_as_groupid'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRowForm(array $form, FormStateInterface $form_state, $index = 0) {
    $row = [];
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->configuration['profile'];
    $mappings = $profile->getProviderMappings();
    $row['query'] = [
      '#type' => 'textfield',
      '#title' => t('LDAP query'),
      '#default_value' => isset($mappings[$index]['query']) ? $mappings[$index]['query'] : NULL,
    ];
    $row['is_regex'] = [
      '#type' => 'checkbox',
      '#title' => t('Is this query a regular expression?'),
      '#default_value' => isset($mappings[$index]['is_regex']) ? $mappings[$index]['is_regex'] : NULL,
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getProposals(UserInterface $user) {

    // Do not continue if user should be excluded from LDAP authentication.
    if ($this->drupalUserProcessor->excludeUser($user)) {
      throw new AuthorizationSkipAuthorization();
    }
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->configuration['profile'];
    $config = $profile->getProviderConfig();

    // Load the correct server.
    $server_id = $config['status']['server'];
    /** @var \Drupal\ldap_servers\Entity\Server $server */
    $server = \Drupal::service('entity_type.manager')
      ->getStorage('ldap_server')
      ->load($server_id);
    if (!$server->status()) {
      return [];
    }

    /** @var \Drupal\ldap_servers\LdapUserManager $ldap_user_manager */
    $ldap_user_manager = \Drupal::service('ldap.user_manager');
    $ldap_user_manager->setServer($server);

    $ldap_user_data = $ldap_user_manager->getUserDataByAccount($user);

    if (!$ldap_user_data && $user->isNew()) {
      // If we don't have a real user yet, fall back to the account name.
      $ldap_user_data = $ldap_user_manager->getUserDataByIdentifier($user->getAccountName());
    }

    if (!$ldap_user_data && $this->configuration['status']['only_ldap_authenticated'] == TRUE) {
      throw new AuthorizationSkipAuthorization();
    }

    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = \Drupal::service('ldap.group_manager');
    $group_manager->setServerById($server_id);

    // Get user groups from DN.
    $derive_from_dn_authorizations = $group_manager->groupUserMembershipsFromDn($user->getAccountName());
    if (!$derive_from_dn_authorizations) {
      $derive_from_dn_authorizations = [];
    }

    // Get user groups from membership.
    $group_dns = $group_manager->groupMembershipsFromUser($user->getAccountName());
    if (!$group_dns) {
      $group_dns = [];
    }

    $proposed_ldap_authorizations = array_merge($derive_from_dn_authorizations, $group_dns);
    $proposed_ldap_authorizations = array_unique($proposed_ldap_authorizations);
    \Drupal::service('ldap.detail_log')->log(
        'Available authorizations to test: @authorizations',
        ['@authorizations' => implode("\n", $proposed_ldap_authorizations)],
        'ldap_authorization'
      );

    return (count($proposed_ldap_authorizations)) ? array_combine($proposed_ldap_authorizations, $proposed_ldap_authorizations) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function filterProposals(array $proposedLdapAuthorizations, array $providerMapping) {
    $filtered_proposals = [];
    foreach ($proposedLdapAuthorizations as $key => $value) {
      if ($providerMapping['is_regex']) {
        $pattern = $providerMapping['query'];
        try {
          if (preg_match($pattern, $value, $matches)) {
            // If there is a sub-pattern then return the first one.
            // @TODO support named sub-patterns.
            if (count($matches) > 1) {
              $filtered_proposals[$key] = $matches[1];
            }
            else {
              $filtered_proposals[$key] = $value;
            }
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('ldap')
            ->error('Error in matching regular expression @regex',
              ['@regex' => $pattern]
            );
        }
      }
      elseif ($value == $providerMapping['query']) {
        $filtered_proposals[$key] = $value;
      }
    }
    return $filtered_proposals;
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeProposals(array $proposals) {
    // Configure this provider.
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->configuration['profile'];
    $config = $profile->getProviderConfig();
    foreach ($proposals as $key => $authorization_id) {
      if ($config['filter_and_mappings']['use_first_attr_as_groupid']) {
        $attr_parts = $this->splitDnWithAttributes($authorization_id);
        if (is_array($attr_parts) && count($attr_parts) > 0) {
          $first_part = explode('=', $attr_parts[0]);
          if (count($first_part) > 1) {
            // @FIXME: Potential bug on trim.
            $authorization_id = ConversionHelper::unescapeDnValue(trim($first_part[1]));
          }
        }
        $new_key = mb_strtolower($authorization_id);
      }
      else {
        $new_key = mb_strtolower($key);
      }
      $proposals[$new_key] = $authorization_id;
      if ($key != $new_key) {
        unset($proposals[$key]);
      }
    }
    return $proposals;
  }

  /**
   * {@inheritdoc}
   */
  public function validateRowForm(array &$form, FormStateInterface $form_state) {
    parent::validateRowForm($form, $form_state);

    foreach ($form_state->getValues() as $value) {
      if (isset($value['provider_mappings'])) {
        if ($value['provider_mappings']['is_regex'] == 1) {
          if (@preg_match($value['provider_mappings']['query'], NULL) === FALSE) {
            $form_state->setErrorByName('mapping', t('Invalid regular expression'));
          }
        }
      }
    }
  }

}
