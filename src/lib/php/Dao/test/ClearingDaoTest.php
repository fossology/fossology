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
use Fossology\Lib\Data\FileTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
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


  public function setUp()
  {
    $this->licenseSelector = M::mock(NewestEditedLicenseSelector::classname());
    $this->uploadDao = M::mock(UploadDao::classname());

    $logger = new Logger('default');
    $logger->pushHandler(new ErrorLogHandler());

    $this->testDb = new TestLiteDb("/tmp/fossology.sqlite");
    $this->dbManager = $this->testDb->getDbManager();

    $this->clearingDao = new ClearingDao($this->dbManager, $this->licenseSelector, $this->uploadDao);

    $this->testDb->createPlainTables(
        array(
            'clearing_decision',
            'clearing_decision_events',
            'clearing_decision_types',
            'clearing_decision_scopes',
            'clearing_licenses',
            'license_ref',
            'users',
            'group_user_member',
            'uploadtree'
        ));

    $this->testDb->insertData(
        array(
            'clearing_decision_types',
            'clearing_decision_scopes'
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

    $this->dbManager->prepare($stmt = 'insert.gum',
        "INSERT INTO group_user_member (group_fk, user_fk, group_perm) VALUES ($1,$2,$3)");
    $gumArray = array(
        array(1, 1, 0),
        array(1, 2, 0),
        array(2, 3, 0),
        array(3, 4, 0)
    );
    foreach ($gumArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }

    $this->dbManager->prepare($stmt = 'insert.ref',
        "INSERT INTO license_ref (rf_pk, rf_shortname, rf_text) VALUES ($1, $2, $3)");
    $refArray = array(
        array(1, 'FOO', 'foo text'),
        array(2, 'BAR', 'bar text'),
        array(3, 'BAZ', 'baz text'),
        array(4, 'QUX', 'qux text')
    );
    foreach ($refArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }

    $directory = 536888320;
    $file= 33188;

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
        array( 1, 1000, 5, $file,       1,2,"Afile"),
        array( 1, 1200, 6, $file,       3,4,"Bfile"),
        array( 2, 1000, 7, $file,       1,2,"Afile"),
        array( 2,    0, 8, $directory,  3,6,"Adirectory"),
        array( 2, 1000, 9, $file,       4,5,"Afile"),
        array( 2, 1200,10, $file,       7,8,"Bfile"),
    );
    foreach ($utArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }

    $this->dbManager->prepare($stmt = 'insert.cd',
        "INSERT INTO clearing_decision (clearing_pk, pfile_fk, uploadtree_fk, user_fk, scope_fk, type_fk, date_added) VALUES ($1, $2, $3, $4, $5, $6, $7)");
    $cdArray = array(
        array(1, 1000, 5, 1, 1, 1,  '2014-08-15T12:12:12'),
        array(2, 1000, 7, 1, 1, 1,  '2014-08-15T12:12:12'),
        array(3, 1000, 9, 3, 1, 1,  '2014-08-16T14:33:45')
    );
    foreach ($cdArray as $ur)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $ur));
    }
  }

  /**
   * @var ClearingDecision[] $input
   * @return array[]
   */
  private function fixClearingDecArray($input) {
    $output = array();
    foreach($input as $row) {
      $tmp=array();
      $tmp[]=$row->getClearingId();
      $tmp[]=$row->getPfileId();
      $tmp[]=$row->getUploadTreeId();
      $tmp[]=$row->getUserId();
      $tmp[]=$row->getScope();
      $tmp[]=$row->getType();
      $tmp[]=$row->getDateAdded();
      $tmp[]=$row->getSameFolder();
      $tmp[]=$row->getSameUpload();

      $output[] = $tmp;
    }
    return $output;
  }



  public function testGetFileClearingsFolder()
  {
    $fileTreeBounds =  new FileTreeBounds(7, "uploadtree",2,1,2);

    $clearingDec = $this->clearingDao->getFileClearingsFolder( $fileTreeBounds);
    $result = $this->fixClearingDecArray($clearingDec);
    assertThat($result, contains(
        array(3, 1000, 9, 3, 'global', 'User decision',  new DateTime('2014-08-16T14:33:45'), false, true),
        array(2, 1000, 7, 1, 'global', 'User decision',  new DateTime('2014-08-15T12:12:12'), true,  true),
        array(1, 1000, 5, 1, 'global', 'User decision',  new DateTime('2014-08-15T12:12:12'), false, false)
        ));
  }




}

 