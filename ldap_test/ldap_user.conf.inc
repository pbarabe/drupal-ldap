<?php
// $Id$

/**
 * @file
 * simpltest ldap user config
 *
 * should only contain saveable properties of ldapUserConf class.
 */

function ldap_test_ldap_user_data() {

$conf['default']  = array(
    'drupalAcctProvisionServer' => 'activedirectory',
    'ldapEntryProvisionServer' => LDAP_USER_NO_SERVER_SID,
    'drupalAcctProvisionTriggers' => array(
      LDAP_USER_DRUPAL_USER_PROV_ON_USER_UPDATE_CREATE,
      LDAP_USER_DRUPAL_USER_PROV_ON_AUTHENTICATE,
      ),
    'ldapEntryProvisionTriggers' => array(
      ),
    'userConflictResolve' => LDAP_USER_CONFLICT_LOG,
    'acctCreation'  => LDAP_USER_ACCT_CREATION_LDAP_BEHAVIOR,
    'wsEnabled'  => 0,
    'wsUserIps' => array('1.2.3.4'),
    'wsKey'  => 'sdafjlksjadsdafjkksdafjsdfsdjfsdf',
    'ldapUserSynchMappings' => array(
      LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER => array(
        'activedirectory' => array(),
      ),
      LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY => array(
        'activedirectory' => array(),
      ),
    )
  );

$conf['ad_authentication'] = array(
    'drupalAcctProvisionServer' => 'ldapauthen1',
    'ldapEntryProvisionServer' => LDAP_USER_NO_SERVER_SID,
    'drupalAcctProvisionTriggers' => array(
      LDAP_USER_DRUPAL_USER_PROV_ON_USER_UPDATE_CREATE,
      LDAP_USER_DRUPAL_USER_PROV_ON_AUTHENTICATE,
      ),
    'ldapEntryProvisionTriggers' => array(
      ),
    'userConflictResolve' => LDAP_USER_CONFLICT_LOG,
    'acctCreation'  => LDAP_USER_ACCT_CREATION_LDAP_BEHAVIOR,
    'wsEnabled'  => 0,
    'wsUserIps' => array('1.2.3.4'),
    'wsKey'  => 'sdafjlksjadsdafjkksdafjsdfsdjfsdf',
    'ldapUserSynchMappings' => array(
      LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER => array(
        'activedirectory' => array(),
      ),
      LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY => array(
        'activedirectory' => array(),
      ),
    )
  );

  $conf['ad_authorization'] = array(
    'drupalAcctProvisionServer' => 'ldapauthor1',
    'ldapEntryProvisionServer' => LDAP_USER_NO_SERVER_SID,
    'drupalAcctProvisionTriggers' => array(
      LDAP_USER_DRUPAL_USER_PROV_ON_USER_UPDATE_CREATE,
      LDAP_USER_DRUPAL_USER_PROV_ON_AUTHENTICATE,
      ),
    'ldapEntryProvisionTriggers' => array(
      ),
    'userConflictResolve' => LDAP_USER_CONFLICT_LOG,
    'acctCreation'  => LDAP_USER_ACCT_CREATION_LDAP_BEHAVIOR,
    'wsEnabled'  => 0,
    'wsUserIps' => array('1.2.3.4'),
    'wsKey'  => 'sdafjlksjadsdafjkksdafjsdfsdjfsdf',
    'ldapUserSynchMappings' => array(
      LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER => array(
        'activedirectory' => array(),
      ),
      LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY => array(
        'activedirectory' => array(),
      ),
    )
  );
  
  $conf['provision0']  = array(
    'drupalAcctProvisionServer' => 'activedirectory',
    'ldapEntryProvisionServer' => LDAP_USER_NO_SERVER_SID,
    'drupalAcctProvisionTriggers' => array(
      LDAP_USER_DRUPAL_USER_PROV_ON_USER_UPDATE_CREATE,
      LDAP_USER_DRUPAL_USER_PROV_ON_AUTHENTICATE,
      ),
    'ldapEntryProvisionTriggers' => array(
      LDAP_USER_LDAP_ENTRY_PROV_ON_USER_UPDATE_CREATE,
    ),
    'userConflictResolve' => LDAP_USER_CONFLICT_LOG,
    'acctCreation'  => LDAP_USER_ACCT_CREATION_LDAP_BEHAVIOR,
    'wsEnabled'  => 0,
    'wsUserIps' => array('1.2.3.4'),
    'wsKey'  => 'sdafjlksjadsdafjkksdafjsdfsdjfsdf',
    'ldapUserSynchMappings' => array(
      LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER => array(
        'activedirectory' => array(),
      ),
      LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY => array(
        'activedirectory' => array(),
      ),
    )
  );

  $conf['provision1']  = array(
    'drupalAcctProvisionServer' => LDAP_USER_NO_SERVER_SID,
    'ldapEntryProvisionServer' => 'activedirectory',
    'drupalAcctProvisionTriggers' => array(),
    'ldapEntryProvisionTriggers' => array(
      LDAP_USER_LDAP_ENTRY_PROV_ON_USER_UPDATE_CREATE,
      LDAP_USER_LDAP_ENTRY_DELETE_ON_USER_DELETE,
      ),
    'userConflictResolve' => LDAP_USER_CONFLICT_LOG,
    'acctCreation'  => LDAP_USER_ACCT_CREATION_LDAP_BEHAVIOR,
    'wsEnabled'  => 0,
    'wsUserIps' => array('1.2.3.4'),
    'wsKey'  => 'sdafjlksjadsdafjkksdafjsdfsdjfsdf',
    'ldapUserSynchMappings' => array(
      LDAP_USER_PROV_DIRECTION_TO_DRUPAL_USER => array(
        'activedirectory' => array(),
      ),
      LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY => array(
        'activedirectory' => array(
            '[dn]' => array(
              'sid' => 'activedirectory',
              'ldap_attr' => '[dn]',
              'user_attr' => 'user_tokens',
              'convert' => 0,
              'direction' => LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY,
              'user_tokens' => 'cn=[property.name],ou=people,dc=activedirectory,dc=ldap,dc=pixotech,dc=com',
              'config_module' => 'ldap_user',
              'synch_module' => 'ldap_user',
              'enabled' => 1,
              'prov_events' => array(LDAP_USER_EVENT_CREATE_LDAP_ENTRY, LDAP_USER_EVENT_SYNCH_TO_LDAP_ENTRY),
            ),
            '[cn]' => array (
              'sid' =>  'activedirectory',
              'ldap_attr' => '[cn]',
              'user_attr' => '[property.name]',
              'convert' => 0,
              'direction' => LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY,
              'user_tokens' => '',
              'config_module' => 'ldap_user',
              'synch_module' => 'ldap_user',
              'enabled' => 1,
              'prov_events' => array(LDAP_USER_EVENT_CREATE_LDAP_ENTRY),
            ),
            '[displayName]' => array(
              'sid' => 'activedirectory',
              'ldap_attr' => '[displayName]',
              'user_attr' => 'user_tokens',
              'convert' => 0,
              'direction' => LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY,
              'user_tokens' => '[field.field_fname] [field.field_lname]',
              'config_module' => 'ldap_user',
              'synch_module' => 'ldap_user',
              'enabled' => 1,
              'prov_events' => array(LDAP_USER_EVENT_CREATE_LDAP_ENTRY, LDAP_USER_EVENT_SYNCH_TO_LDAP_ENTRY),  
            ),
            '[sAMAccountName]' => array(
              'sid' => 'activedirectory',
              'ldap_attr' => '[sAMAccountName]',
              'user_attr' => '[property.name]',
              'convert' => 0,
              'direction' => LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY,
              'user_tokens' => '',
              'config_module' => 'ldap_user',
              'synch_module' => 'ldap_user',
              'prov_events' => array(LDAP_USER_EVENT_CREATE_LDAP_ENTRY),  
            ),
            '[sn]' => array(
              'sid' => 'activedirectory',
              'ldap_attr' => '[sn]',
              'user_attr' => '[field.field_lname]',
              'convert' => 0,
              'direction' => LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY,
              'user_tokens' => '',
              'config_module' => 'ldap_user',
              'synch_module' => 'ldap_user',
              'enabled' => 1,
              'prov_events' => array(LDAP_USER_EVENT_CREATE_LDAP_ENTRY, LDAP_USER_EVENT_SYNCH_TO_LDAP_ENTRY), 
            ),
            '[givenName]' => array(
              'sid' => 'activedirectory',
              'ldap_attr' => '[givenName]',
              'user_attr' => '[field.field_fname]',
              'convert' => 0,
              'direction' => LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY,
              'user_tokens' => '',
              'config_module' => 'ldap_user',
              'synch_module' => 'ldap_user',
              'enabled' => 1,
              'prov_events' => array(LDAP_USER_EVENT_CREATE_LDAP_ENTRY, LDAP_USER_EVENT_SYNCH_TO_LDAP_ENTRY), 
            ), 
            '[guid]' => array(
              'sid' => 'activedirectory',
              'ldap_attr' => '[guid]',
              'user_attr' => 'user_tokens',
              'convert' => 0,
              'direction' => LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY,
              'user_tokens' => 'ldap.pixotech.com.uid.[property.uid]',
              'config_module' => 'ldap_user',
              'synch_module' => 'ldap_user',
              'enabled' => 1,
              'prov_events' => array(LDAP_USER_EVENT_CREATE_LDAP_ENTRY), 
            ),
            '[provisionSource]' => array(
              'sid' => 'activedirectory',
              'ldap_attr' => '[provisionSource]',
              'user_attr' => 'user_tokens',
              'convert' => 0,
              'direction' => LDAP_USER_PROV_DIRECTION_TO_LDAP_ENTRY,
              'user_tokens' => 'drupal.pixotech.com',
              'config_module' => 'ldap_user',
              'synch_module' => 'ldap_user',
              'enabled' => 1,
              'prov_events' => array(LDAP_USER_EVENT_CREATE_LDAP_ENTRY), 
            ),
          ),
        )
      )
    );

  return $conf;

}