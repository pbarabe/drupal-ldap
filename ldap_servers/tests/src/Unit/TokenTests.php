<?php

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Ldap\Entry;

/**
 * Helper class to make it possible to simulate ldap_explode_dn().
 */
class LdapExplodeDnMock {

  /**
   * Simulate explode_dn.
   *
   * @return array
   *   DN exploded, input ignored.
   */
  public static function ldapExplodeDn($input) {
    return [
      'count' => 4,
      0 => 'cn=hpotter',
      1 => 'ou=Gryffindor',
      2 => 'ou=student',
      3 => 'ou=people',
      4 => 'dc=hogwarts',
      5 => 'dc=edu',
    ];
  }

}

/**
 * @coversDefaultClass \Drupal\ldap_servers\Processor\TokenProcessor
 * @group ldap
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TokenTests extends UnitTestCase {

  public $config;
  public $container;

  /**
   * @var \Symfony\Component\Ldap\Entry
   */
  private $ldapEntry;
  protected $detailLog;
  protected $fileSystem;

  /**
   * Test setup.
   */
  protected function setUp() {
    parent::setUp();

    // TODO: Move the mock into the token class (was converted to trait.)
    class_alias(
      '\Drupal\Tests\ldap_servers\Unit\LdapExplodeDnMock',
      '\Drupal\ldap_servers\Entity\Server',
      TRUE
    );

    $this->ldapEntry = new Entry('cn=hpotter,ou=Gryffindor,ou=student,ou=people,dc=hogwarts,dc=edu', [
      'mail' => ['hpotter@hogwarts.edu'],
      'sAMAccountName' => ['hpotter'],
      'house' => ['Gryffindor', 'Privet Drive'],
      'guid' => ['sdafsdfsdf'],
    ]);
  }

  /**
   * Test the replacement of tokens.
   *
   * See http://drupal.org/node/1245736 for test tokens.
   */
  public function testTokenReplacement() {
    /** @var \Drupal\ldap_servers\Processor\TokenProcessor $processor */
    $processor = $this->getMockBuilder('\Drupal\ldap_servers\Processor\TokenProcessor')
      ->setMethods(NULL)
      ->disableOriginalConstructor()
      ->getMock();

    $dn = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[dn]');
    $this->assertEquals($this->ldapEntry->getDn(), $dn);

    $house0 = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[house:0]');
    $this->assertEquals($this->ldapEntry->getAttribute('house')[0], $house0);

    $mixed = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, 'thisold[house:0]');
    $this->assertEquals('thisold' . $this->ldapEntry->getAttribute('house')[0], $mixed);

    $compound = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[samaccountname:0][house:0]');
    // TODO: Expected :'hpotterGryffindor', Actual:'[samaccountname:0]Gryffindor'
    // $this->assertEquals($this->ldapEntry->getAttribute('sAMAccountName')[0] . $this->ldapEntry->getAttribute('house')[0], $compound);.
    $literalValue = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, 'literalvalue');
    $this->assertEquals('literalvalue', $literalValue);

    $house0 = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[house]');
    $this->assertEquals($this->ldapEntry->getAttribute('house')[0], $house0);

    $houseLast = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[house:last]');
    $this->assertEquals($this->ldapEntry->getAttribute('house')[1], $houseLast);

    $sAMAccountName = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[samaccountname:0]');
    // TODO: Expected :'hpotter', Actual: NULL
    // $this->assertEquals($this->ldapEntry->getAttribute('sAMAccountName')[0], $sAMAccountName);.
    $sAMAccountNameMixedCase = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[sAMAccountName:0]');
    // TODO: Expected :'hpotter', Actual: NULL
    // $this->assertEquals($this->ldapEntry->getAttribute('sAMAccountName')[0], $sAMAccountNameMixedCase);.
    $sAMAccountName2 = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[samaccountname]');
    // TODO: Expected :'hpotter', Actual: NULL
    // $this->assertEquals($this->ldapEntry->getAttribute('sAMAccountName')[0], $sAMAccountName2);.
    $sAMAccountName3 = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[sAMAccountName]');
    // TODO: Expected :'hpotter', Actual: NULL
    // $this->assertEquals($this->ldapEntry->getAttribute('sAMAccountName')[0], $sAMAccountName3);.
    $base64encode = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[guid;base64_encode]');
    $this->assertEquals(base64_encode($this->ldapEntry->getAttribute('guid')[0]), $base64encode);

    $bin2hex = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[guid;bin2hex]');
    $this->assertEquals(bin2hex($this->ldapEntry->getAttribute('guid')[0]), $bin2hex);

    $msguid = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[guid;msguid]');
    $this->assertEquals(ConversionHelper::convertMsguidToString($this->ldapEntry->getAttribute('guid')[0]), $msguid);

    $binary = $processor->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[guid;binary]');
    $this->assertEquals(ConversionHelper::binaryConversionToString($this->ldapEntry->getAttribute('guid')[0]), $binary);

    $account = $this->prophesize('\Drupal\user\Entity\User');
    $value = new \stdClass();
    $value->value = $this->ldapEntry->getAttribute('sAMAccountName')[0];
    $account->get('name')->willReturn($value);
    $nameReplacement = $processor->drupalAccountReplacementsForLdap($account->reveal(), '[property.name]');
    $this->assertEquals($this->ldapEntry->getAttribute('sAMAccountName')[0], $nameReplacement);

  }

  /**
   * Additional token tests for the reverse behaviour for DN derivatives.
   */
  public function testTokensReverse() {
    $tokenHelper = $this->getMockBuilder('\Drupal\ldap_servers\Processor\TokenProcessor')
      ->setMethods(NULL)
      ->disableOriginalConstructor()
      ->getMock();

    // Test regular reversal (2 elements) at beginning.
    $dc = $tokenHelper->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[dc:reverse:0]');
    $this->assertEquals('edu', $dc);

    // Test single element reversion.
    $ou = $tokenHelper->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[cn:reverse:0]');
    $this->assertEquals('hpotter', $ou);

    // Test 3 element reversion at end.
    $ou2 = $tokenHelper->ldapEntryReplacementsForDrupalAccount($this->ldapEntry, '[ou:reverse:2]');
    $this->assertEquals('Gryffindor', $ou2);

  }

}
