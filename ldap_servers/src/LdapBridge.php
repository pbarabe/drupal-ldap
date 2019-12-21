<?php

declare(strict_types=1);

namespace Drupal\ldap_servers;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Ldap;

/**
 * Ldap Bridge to symfony/ldap.
 */
class LdapBridge {

  /**
   * Bind method.
   *
   * @var string
   */
  protected $bindMethod;

  /**
   * Bind DN.
   *
   * @var string
   */
  protected $bindDn;

  /**
   * Bind PW.
   *
   * @var string
   */
  protected $bindPw;

  /**
   * LDAP.
   *
   * @var \Symfony\Component\Ldap\Ldap
   */
  protected $ldap;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Entity Storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityManager;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    $this->logger = $logger;
    $this->entityManager = $entity_type_manager->getStorage('ldap_server');
  }

  /**
   * Set Server by ID.
   *
   * @param string $sid
   *   Server machine name.
   */
  public function setServerById($sid): void {
    $server = $this->entityManager->load($sid);
    /** @var \Drupal\ldap_servers\Entity\Server $server */
    if ($server) {
      $this->setServer($server);
    }
  }

  /**
   * Set Server.
   *
   * @param \Drupal\ldap_servers\Entity\Server $server
   *   Server object.
   */
  public function setServer(Server $server): void {
    $parameters = [
      'host' => $server->get('address'),
      'port' => $server->get('port'),
      'encryption' => 'none',
      'options' => ['timeout' => $server->getTimeout()],
    ];
    if ($server->isUsingStartTls()) {
      $parameters['encryption'] = 'tls';
    }
    $this->bindMethod = $server->get('bind_method');
    $this->bindDn = $server->get('binddn');
    $this->bindPw = $server->get('bindpw');
    $this->ldap = Ldap::create('ext_ldap', $parameters);
  }

  /**
   * Bind (authenticate) against an active LDAP database.
   *
   * @return bool
   *   Binding successful.
   */
  public function bind() {

    if (
      $this->bindMethod === 'anon' ||
      ($this->bindMethod === 'anon_user' && !CredentialsStorage::validateCredentials())
    ) {
      $userDn = NULL;
      $password = NULL;
    }
    else {
      // Default credentials form service account.
      $userDn = $this->bindDn;
      $password = $this->bindPw;

      // Runtime credentials for user binding and password checking.
      if (CredentialsStorage::validateCredentials()) {
        $userDn = CredentialsStorage::getUserDn();
        $password = CredentialsStorage::getPassword();
      }

      if ($password === '' || $userDn === '') {
        $this->logger->notice('LDAP bind failure due to missing credentials for user userdn=%userdn', [
          '%userdn' => $userDn,
        ]);
        return FALSE;
      }
    }

    try {
      $this->ldap->bind($userDn, $password);
    }
    catch (ConnectionException $e) {
      $this->logger->notice('LDAP connection failure: %message.', [
        '%message' => $e->getMessage(),
      ]);
      return FALSE;
    }
    catch (LdapException $e) {
      $this->logger->notice('LDAP bind failure: %message.', [
        '%message' => $e->getMessage(),
      ]);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get LDAP service.
   *
   * @return \Symfony\Component\Ldap\Ldap
   *   LDAP service.
   */
  public function get() {
    return $this->ldap;
  }

}
