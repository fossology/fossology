<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\BusinessRules\NewestEditedLicenseSelector;
use Fossology\Lib\Data\LicenseDecision;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Mockery\MockInterface;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class ClearingDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var NewestEditedLicenseSelector|MockInterface */
  private $licenseSelector;
  /** @var UploadDao|MockInterface */
  private $uploadDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var int */
  private $now;
  

  public function setUp()
  {
    $this->licenseSelector = M::mock(NewestEditedLicenseSelector::classname());
    $this->uploadDao = M::mock(UploadDao::classname());

    $logger = new Logger('default');
    $logger->pushHandler(new ErrorLogHandler());

    $this->testDb = new TestPgDb(); // TestLiteDb("/tmp/fossology.sqlite");
    $this->dbManager = $this->testDb->getDbManager();

    $this->clearingDao = new ClearingDao($this->dbManager, $this->licenseSelector, $this->uploadDao);

    $this->testDb->createPlainTables(
        array(
            'clearing_decision',
            'clearing_decision_events',
            'clearing_decision_type',
            'license_decision_event',
            'license_decision_type',
            'clearing_licenses',
            'license_ref',
            'users',
            'group_user_member',
            'uploadtree'
        ));

    $this->testDb->insertData(
        array(
            'clearing_decision_type',
            'license_decision_type'
        ));

    $this->dbManager->prepare($stmt = 'insert.users',
        "INSERT INTO users (user_name, root_folder_fk) VALUES ($1,$2)");
    $userArray = array(
        array('myself', 1),
        array('in_same_group', 2),
        array('in_trusted_group', 3),
        array('not_in_trusted_group', 4));
    foreach ($userArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }

    $gumArray = array(
        array(1, 1, 0),
        array(1, 2, 0),
        array(2, 3, 0),
        array(3, 4, 0)
    );
    foreach ($gumArray as $params)
    {
      $this->dbManager->insertInto('group_user_member', $keys='group_fk, user_fk, group_perm', $params, $logStmt = 'insert.gum');
    }

    $refArray = array(
        array(1, 'FOO', 'foo text'),
        array(2, 'BAR', 'bar text'),
        array(3, 'BAZ', 'baz text'),
        array(4, 'QUX', 'qux text')
    );
    foreach ($refArray as $params)
    {
      $this->dbManager->insertInto('license_ref', 'rf_pk, rf_shortname, rf_text',$params,$logStmt = 'insert.ref');
    }

    $utArray = array(
        array( 100, 1000),
        array( 100, 1200)
    );
    foreach ($utArray as $params)
    {
      $this->dbManager->insertInto('uploadtree','pfile_fk, uploadtree_pk',$params,$logStmt = 'insert.uploadtree');
    }
    
    $this->now = time();
    $cdArray = array(
        array(1, 100, 1000, 1, 1, false, false, 1, date('c',$this->now-888)),
        array(2, 100, 1000, 1, 2, false, false, 1, date('c',$this->now-888)),
        array(3, 100, 1000, 3, 4, false, false, 1, date('c',$this->now-1234)),
        array(4, 100, 1000, 2, 3, false, true, 2, date('c',$this->now-900)),
        array(5, 100, 1000, 2, 4, true, false, 1, date('c',$this->now-999)),
        array(6, 100, 1200, 1, 3, true, true, 1, date('c',$this->now-654)),
        array(7, 100, 1200, 1, 2, false, false, 1, date('c',$this->now-543))
    );
    foreach ($cdArray as $params)
    {
      $this->dbManager->insertInto('license_decision_event',
           'license_decision_event_pk, pfile_fk, uploadtree_fk, user_fk, rf_fk, is_removed, is_global, type_fk, date_added',
           $params,  $logStmt = 'insert.cd');
    }
  }

  private function fixResult($result) {
    foreach($result as &$row) {
      $row = array_values($row);
    }
    return $result;
  }
  public function testLicenseDecisionEventsViaGroupMembership()
  {
    $result = $this->fixResult($this->clearingDao->getRelevantLicenseDecisionEvents(1, 1000));
    assertThat($result, contains(
        array(5, 100, 1000, $this->now-999, 2, null, 1, LicenseDecision::USER_DECISION, 4, "QUX", 0, 1, null, null),
        array(4, 100, 1000, $this->now-900, 2, null, 1, LicenseDecision::BULK_RECOGNITION, 3, "BAZ", 1, 0, null, null),
        array(1, 100, 1000, $this->now-888, 1, null, 1, LicenseDecision::USER_DECISION, 1, "FOO", 0, 0, null, null),
        array(2, 100, 1000, $this->now-888, 1, null, 1, LicenseDecision::USER_DECISION, 2, "BAR", 0, 0, null, null),
        array(6, 100, 1200, $this->now-654, 1, null, 1, LicenseDecision::USER_DECISION, 3, "BAZ", 1, 1, null, null)
    ));
  }

  public function testLicenseDecisionEventsViaGroupMembershipShouldBeSymmetric()
  {
    $result = $this->fixResult($this->clearingDao->getRelevantLicenseDecisionEvents(2, 1000));
    assertThat($result, contains(
        array(5, 100, 1000, $this->now-999, 2, null, 1, LicenseDecision::USER_DECISION, 4, "QUX", 0, 1, null, null),
        array(4, 100, 1000, $this->now-900, 2, null, 1, LicenseDecision::BULK_RECOGNITION, 3, "BAZ", 1, 0, null, null),
        array(1, 100, 1000, $this->now-888, 1, null, 1, LicenseDecision::USER_DECISION, 1, "FOO", 0, 0, null, null),
        array(2, 100, 1000, $this->now-888, 1, null, 1, LicenseDecision::USER_DECISION, 2, "BAR", 0, 0, null, null),
        array(6, 100, 1200, $this->now-654, 1, null, 1, LicenseDecision::USER_DECISION, 3, "BAZ", 1, 1, null, null)
    ));
  }

  public function testLicenseDecisionEventsUploadScope()
  {
    $result = $this->fixResult($this->clearingDao->getRelevantLicenseDecisionEvents(1, 1200));
    assertThat($result, contains(
        array(4, 100, 1000, $this->now-900, 2, null, 1, LicenseDecision::BULK_RECOGNITION, 3, "BAZ", 1, 0, null, null),
        array(6, 100, 1200, $this->now-654, 1, null, 1, LicenseDecision::USER_DECISION, 3, "BAZ", 1, 1, null, null),
        array(7, 100, 1200, $this->now-543, 1, null, 1, LicenseDecision::USER_DECISION, 2, "BAR", 0, 0, null, null)
    ));
  }

  public function testLicenseDecisionEventsWithoutGroupOverlap()
  {
    $result = $this->fixResult($this->clearingDao->getRelevantLicenseDecisionEvents(3, 1000));
    assertThat(count($result), is(1));
    assertThat($result[0], is(
        array(3, 100, 1000, $this->now-1234, 3, null, 2, LicenseDecision::USER_DECISION, 4, "QUX", 0, 0, null, null)
    ));
  }

  public function testLicenseDecisionEventsWithoutMatch()
  {
    $result = $this->clearingDao->getRelevantLicenseDecisionEvents(3, 1200);
    assertThat($result, is(array()));
  }

  public function testCurrentLicenseDecisionViaGroupMembership()
  {
    list($added, $removed) = $this->clearingDao->getCurrentLicenseDecisions(1, 1000);
    assertThat(array_keys($added), is(array("FOO", "BAR")));
    assertThat(array_keys($removed), is(array("QUX", "BAZ")));
  }

  public function testCurrentLicenseDecisionViaGroupMembershipShouldBeSymmetric()
  {
    list($added, $removed) = $this->clearingDao->getCurrentLicenseDecisions(2, 1000);
    assertThat(array_keys($added), is(array("FOO", "BAR")));
    assertThat(array_keys($removed), is(array("QUX", "BAZ")));
  }

  public function testCurrentLicenseDecisionWithUploadScope()
  {
    list($added, $removed) = $this->clearingDao->getCurrentLicenseDecisions(2, 1200);
    assertThat(array_keys($added), is(array("BAR")));
    assertThat(array_keys($removed), is(array("BAZ")));
  }

  public function testCurrentLicenseDecisionWithoutGroupOverlap()
  {
    list($added, $removed) = $this->clearingDao->getCurrentLicenseDecisions(3, 1000);
    assertThat(array_keys($added), is(array("QUX")));
    assertThat(array_keys($removed), is(array()));
  }

  public function testCurrentLicenseDecisionWithoutMatch()
  {
    list($added, $removed) = $this->clearingDao->getCurrentLicenseDecisions(3, 1200);
    assertThat($added, is(array()));
    assertThat($removed, is(array()));
  }
}
 