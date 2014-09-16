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
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Db\Driver\SqliteE;
use Fossology\Lib\Test\TestLiteDb;
use Mockery as M;
use Mockery\MockInterface;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use SQLite3;

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

  public function setUp()
  {
    $this->licenseSelector = M::mock(NewestEditedLicenseSelector::classname());
    $this->uploadDao = M::mock(UploadDao::classname());

    $logger = new Logger('default');
    $logger->pushHandler(new ErrorLogHandler());

    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();

    $this->clearingDao = new ClearingDao($this->dbManager, $this->licenseSelector, $this->uploadDao);
    
    $this->testDb->createPlainTables(
            array(
                'clearing_decision',
                'clearing_decision_scopes',
                'clearing_decision_types',
                'clearing_licenses',
                'license_ref',
                'users',
                'group_user_member'
        ));
    
    $this->testDb->insertData(
            array(
                'clearing_decision_scopes',
                'clearing_decision_types'
            ));
    
    $this->dbManager->prepare($stmt='insert.users',
                "INSERT INTO users (user_name, root_folder_fk) VALUES ($1,$2)");
    $userArray = array( array('myself', 1), array('in_same_group', 2), array('in_trusted_group', 3), array('not_in_trusted_group', 4));
    foreach ($userArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }

    $this->dbManager->prepare($stmt='insert.gum',
                "INSERT INTO group_user_member (group_fk, user_fk, group_perm) VALUES ($1,$2,$3)");
    $gumArray = array( array(1, 1, 0), array(1, 2, 0), array(2, 3, 0), array(3, 4, 0));
    foreach ($gumArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }
    
    $this->dbManager->prepare($stmt='insert.ref',
                "INSERT INTO license_ref (rf_shortname, rf_text) VALUES ($1,$2)");
    $refArray = array( array('FOO', 'foo text'), array('BAR', 'bar text'), array('BAZ', 'baz text'), array('QUX', 'qux text'));
    foreach ($refArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }
    
    $this->dbManager->prepare($stmt='insert.cd',
                "INSERT INTO clearing_decision (pfile_fk, uploadtree_fk, user_fk, type_fk, scope_fk, date_added) VALUES ($1,$2,$3,$4,$5,$6)");
    $cdArray = array( array(100, 1000, 1, 1, 1, '2014-08-15T12:12:12'),
      array(100, 1000, 2, 1, 1, '2014-08-15T10:43:58'),
      array(100, 1000, 3, 1, 1, '2014-08-14T14:33:45'),
      array(100, 1000, 4, 1, 1, '2014-08-14T11:14:22'),
      array(100, 1200, 1, 1, 1, '2014-08-15T12:12:12'));
    foreach ($cdArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }

    $this->dbManager->prepare($stmt='insert.c_lic',
                "INSERT INTO clearing_licenses (clearing_fk, rf_fk, removed) VALUES ($1,$2,$3)");
    $clicArray = array( array(100, 1000, 1, 1, 1, '2014-08-15T12:12:12'),
      array(1, 1, 0),
      array(1, 2, 0),
      array(2, 4, 1),
      array(3, 4, 0),
      array(5, 3, 0));
    foreach ($clicArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }
  }

  public function testDBStart()
  {
    $result = $this->clearingDao->getRelevantLicenseDecisionEvents(1000);
    foreach ($result as $row)
    {
      print(implode(" ", $row) . "\n");
    }
    $this->markTestSkipped("not yet implemented");
  }

}
 