<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl, Johannes Najjar

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

use DateTime;
use Fossology\Lib\BusinessRules\NewestEditedLicenseSelector;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\LicenseDecision\LicenseDecision;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionEvent;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Mockery\MockInterface;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class ClearingDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var  TestPgDb */
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

    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

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
      $this->dbManager->insertInto('group_user_member', $keys = 'group_fk, user_fk, group_perm', $params, $logStmt = 'insert.gum');
    }

    $refArray = array(
        array(1, 'FOO', 'foo text'),
        array(2, 'BAR', 'bar text'),
        array(3, 'BAZ', 'baz text'),
        array(4, 'QUX', 'qux text')
    );
    foreach ($refArray as $params)
    {
      $this->dbManager->insertInto('license_ref', 'rf_pk, rf_shortname, rf_text', $params, $logStmt = 'insert.ref');
    }

    $directory = 536888320;
    $file = 33188;

    /*                                (pfile, uploadtreeID, left, right)
      upload1:     Afile              (1000,  5,  1,  2)
                   Bfile              (1200,  6,  3,  4)

      upload2:     Afile              (1000,  7,  1,  2)
                   Adirectory/        (   0,  8,  3,  6)
                   Adirectory/Afile   (1000,  9,  4,  5)
                   Bfile              (1200, 10,  7,  8)
    */
    $this->dbManager->prepare($stmt = 'insert.uploadtree',
        "INSERT INTO uploadtree (upload_fk, pfile_fk, uploadtree_pk, ufile_mode,lft,rgt,ufile_name) VALUES ($1, $2,$3,$4,$5,$6,$7)");
    $utArray = array(
        array(100, 1000, 5, $file, 1, 2, "Afile"),
        array(100, 1200, 6, $file, 3, 4, "Bfile"),
        array(2, 1000, 7, $file, 1, 2, "Afile"),
        array(2, 0, 8, $directory, 3, 6, "Adirectory"),
        array(2, 1000, 9, $file, 4, 5, "Afile"),
        array(2, 1200, 10, $file, 7, 8, "Bfile"),
    );
    foreach ($utArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }

    $this->now = time();
    $ldArray = array(
        array(1, 100, 1, 1, false, 1, $this->getMyDate($this->now - 888)),
        array(2, 100, 1, 2, false, 1, $this->getMyDate($this->now - 888)),
        array(3, 100, 3, 4, false, 1, $this->getMyDate($this->now - 1234)),
        array(4, 100, 2, 3, false, 2, $this->getMyDate($this->now - 900)),
        array(5, 100, 2, 4, true, 1, $this->getMyDate($this->now - 999)),
        array(6, 100, 1, 3, true, 1, $this->getMyDate($this->now - 654)),
        array(7, 100, 1, 2, false, 1, $this->getMyDate($this->now - 543))
    );
    foreach ($ldArray as $params)  
    {
      $this->dbManager->insertInto('license_decision_event',
          'license_decision_event_pk, uploadtree_fk, user_fk, rf_fk, is_removed, type_fk, date_added',
          $params, $logStmt = 'insert.lde');
    }

    $this->dbManager->prepare($stmt = 'insert.cd',
        "INSERT INTO clearing_decision (clearing_decision_pk, pfile_fk, uploadtree_fk, user_fk, decision_type, date_added, scope)"
            . " VALUES ($1, $2, $3, $4, $5, $6, $7)");
    $cdArray = array(
        array(1, 1000, 5, 1, 5, $this->getMyDate($this->now - 888), DecisionScopes::ITEM),
        array(2, 1000, 7, 1, 5, $this->getMyDate($this->now - 888), DecisionScopes::ITEM),
        array(3, 1000, 9, 3, 5, $this->getMyDate($this->now - 1234), DecisionScopes::ITEM)
    );
    foreach ($cdArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }

  }

  function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function getMyDate($in)
  {
    $date = new DateTime();
    return $date->setTimestamp($in)->format('Y-m-d H:i:s T');
  }

  private function getMyDate2($in)
  {
    $date = new DateTime();
    return $date->setTimestamp($in);
  }

  public function testCurrentLicenseDecisionViaGroupMembershipShouldBeSymmetric()
  {
    list($added1, $removed1) = $this->clearingDao->getCurrentLicenseDecisions(1, 7);
    list($added2, $removed2) = $this->clearingDao->getCurrentLicenseDecisions(2, 7);
    assertThat(array_keys($added1), is($added2));
    assertThat(array_keys($removed1), is($removed2));
  }
  
}
