<?php

namespace Drupal\ldap_servers;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\externalauth\Authmap;
use Drupal\user\UserInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * LDAP User Manager.
 */
class LdapUserManager extends LdapBaseManager {


  protected $cache;

  protected $externalAuth;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\ldap_servers\LdapBridge $ldap_bridge
   *   LDAP bridge.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache.
   * @param \Drupal\externalauth\Authmap $external_auth
   *   External auth.
   */
  public function __construct(
    LoggerChannelInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    LdapBridge $ldap_bridge,
    ModuleHandler $module_handler,
    CacheBackendInterface $cache,
    Authmap $external_auth) {
    parent::__construct($logger, $entity_type_manager, $ldap_bridge, $module_handler);
    $this->cache = $cache;
    $this->externalAuth = $external_auth;
  }

  /**
   * Create LDAP User entry.
   *
   * Adds AD-specific password handling.
   *
   * @param \Symfony\Component\Ldap\Entry $entry
   *
   * @return bool
   *   Result of action.
   */
  public function createLdapEntry(Entry $entry) {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    if ($entry->hasAttribute('unicodePwd') && $this->server->get('type') == 'ad') {
      $entry->setAttribute('unicodePwd', [$this->convertPasswordForActiveDirectoryUnicodePwd($entry->getAttribute('unicodePwd')[0])]);
    }

    try {
      $this->ldap->getEntryManager()->add($entry);
    }
    catch (LdapException $e) {
      $this->logger->error("LDAP server %id exception: %ldap_error", [
        '%id' => $this->server->id(),
        '%ldap_error' => $e->getMessage(),
      ]
          );
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @TODO / @FIXME: This is not called.
   */
  protected function applyModificationsToEntry(Entry $entry, Entry $current) {
    if ($entry->hasAttribute('unicodePwd') && $this->server->get('type') == 'ad') {
      $entry->setAttribute('unicodePwd', [$this->convertPasswordForActiveDirectoryUnicodePwd($entry->getAttribute('unicodePwd')[0])]);
    }

    parent::applyModificationsToEntry($entry, $current);
  }

  /**
   * Convert password to format required by Active Directory.
   *
   * For the purpose of changing or setting the password. Note that AD needs the
   * field to be called unicodePwd (as opposed to userPassword).
   *
   * @param string $password
   *   The password that is being formatted for Active Directory unicodePwd
   *   field.
   *
   * @return string|array
   *   $password surrounded with quotes and in UTF-16LE encoding
   */
  protected function convertPasswordForActiveDirectoryUnicodePwd($password) {
    // This function can be called with $attributes['unicodePwd'] as an array.
    if (!is_array($password)) {
      return mb_convert_encoding("\"{$password}\"", "UTF-16LE");
    }
    else {
      // Presumably there is no use case for there being more than one password
      // in the $attributes array, hence it will be at index 0 and we return in
      // kind.
      return [mb_convert_encoding("\"{$password[0]}\"", "UTF-16LE")];
    }
  }

  /**
   * Fetches the user account based on the persistent UID.
   *
   * @param string $puid
   *   As returned from ldap_read or other LDAP function (can be binary).
   *
   * @return false|UserInterface|EntityInterface
   *   The updated user or error.
   */
  public function getUserAccountFromPuid($puid) {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    $query = $this->entityTypeManager->getStorage('user')->getQuery();
    $query->condition('ldap_user_puid_sid', $this->server->id(), '=')
      ->condition('ldap_user_puid', $puid, '=')
      ->condition('ldap_user_puid_property', $this->server->get('unique_persistent_attr'), '=')
      ->accessCheck(FALSE);
    $result = $query->execute();

    if (!empty($result)) {
      if (count($result) == 1) {
        return $this->entityTypeManager->getStorage('user')
          ->load(array_values($result)[0]);
      }
      else {
        $uids = implode(',', $result);
        $this->logger->error('Multiple users (uids: %uids) with same puid (puid=%puid, sid=%sid, ldap_user_puid_property=%ldap_user_puid_property)', [
          '%uids' => $uids,
          '%puid' => $puid,
          '%id' => $this->server->id(),
          '%ldap_user_puid_property' => $this->server->get('unique_persistent_attr'),
        ]
        );
      }
    }
    return FALSE;
  }

  /**
   * Fetch user data from server by Identifier.
   *
   * @param string $identifier
   *   User identifier.
   *
   * @return \Symfony\Component\Ldap\Entry|false
   *
   *   This should go into LdapUserProcessor or LdapUserManager, leaning toward
   *   the former.
   */
  public function getUserDataByIdentifier($identifier) {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    // Try to retrieve the user from the cache.
    $cache = $this->cache->get('ldap_servers:user_data:' . $identifier);
    if ($cache && $cache->data) {
      return $cache->data;
    }

    $ldap_entry = $this->queryAllBaseDnLdapForUsername($identifier);
    if ($ldap_entry) {
      $ldap_entry = $this->sanitizeUserDataResponse($ldap_entry, $identifier);
      $cache_expiry = 5 * 60 + time();
      $cache_tags = ['ldap', 'ldap_servers', 'ldap_servers.user_data'];
      $this->cache->set('ldap_servers:user_data:' . $identifier, $ldap_entry, $cache_expiry, $cache_tags);
    }

    return $ldap_entry;
  }

  /**
   * Fetch user data from server by user account.
   *
   * @param \Drupal\user\UserInterface $account
   *   Drupal user account.
   *
   * @return array|bool
   *   Returns data or FALSE.
   *
   *   This should go into LdapUserProcessor or LdapUserManager, leaning toward
   *   the former.
   */
  public function getUserDataByAccount(UserInterface $account) {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    $identifier = $this->externalAuth->get($account->id(), 'ldap_user');
    if ($identifier) {
      return $this->getUserDataByIdentifier($identifier);
    }
    else {
      return FALSE;
    }
  }

}