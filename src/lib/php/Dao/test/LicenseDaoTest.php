<?php
/*
Copyright (C) 2014, Siemens AG
Author: Steffen Weber

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

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Fossology\Lib\Data\FileTreeBounds;
use Mockery;

class LicenseDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;

  public function setUp()
  {
    $this->testDb = new TestLiteDb();
    $this->dbManager = $this->testDb->getDbManager();
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function dirnameRec($path, $depth = 1)
  {
    for ($i = 0; $i < $depth; $i++)
    {
      $path = dirname($path);
    }
    return $path;
  }

  private function createTable_license_ref()
  {
    $LIBEXECDIR = $this->dirnameRec(__FILE__, 6) . '/install/db';
    $sqlstmts = file_get_contents("$LIBEXECDIR/licenseref.sql");

    $delimiter = "INSERT INTO license_ref";
    $splitted = explode($delimiter, $sqlstmts);

    for ($i = 1; $i < count($splitted); $i++)
    {
      $partial = $splitted[$i];
      $sql = $delimiter . str_replace(' false,', " 'false',", $partial);
      $sql = str_replace(' true,', " 'true',", $sql);
      $this->dbManager->queryOnce($sql);
      if ($i > 140)
      {
        break;
      }
    }
  }

  public function testSimple()
  {
    $licDao = new LicenseDao($this->dbManager);
    $this->assertInstanceOf('Fossology\Lib\Dao\LicenseDao', $licDao);
  }

  public function testGetFileLicenseMatches()
  {
    $this->testDb->createPlainTables(array('license_ref','uploadtree','license_file','agent'));
    $this->createTable_license_ref();

    $lic0 = $this->dbManager->getSingleRow("Select rf_pk from license_ref limit 1");
    $licenseRefNumber = $lic0['rf_pk'];
    $licenseFileId= 1;
    $pfileId= 42;
    $agentId = 23;
    $matchPercent = 50;
    $uploadtreeId= 512;
    $uploadID =123;
    $left=2009;
    $right=2014;
    $agentID=23;
    $agentName="'fake'";
    $agentRev=1;
    $mydate = "'2014-06-04 14:01:30.551093+02'";
    $this->dbManager->queryOnce('CREATE VIEW license_file_ref AS 
            SELECT license_ref.rf_fullname, license_ref.rf_shortname, license_ref.rf_pk,
            license_file.fl_end_byte, license_file.rf_match_pct, license_file.rf_timestamp,
            license_file.fl_pk, license_file.agent_fk, license_file.pfile_fk
            FROM license_file JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk');

    $this->dbManager->queryOnce("INSERT INTO license_file(
            fl_pk, rf_fk, agent_fk, rf_match_pct, rf_timestamp, pfile_fk)
            VALUES ($licenseFileId, $licenseRefNumber, $agentId, $matchPercent, $mydate, $pfileId)");

    $this->dbManager->queryOnce("INSERT INTO uploadtree(uploadtree_pk, upload_fk, pfile_fk, lft, rgt)
            VALUES ($uploadtreeId, $uploadID, $pfileId, $left, $right)");

    $uploadDao = new UploadDao($this->dbManager);
    $fileTreeBounds = $uploadDao->getFileTreeBounds($uploadtreeId);
    assertThat($fileTreeBounds, is(new FileTreeBounds($uploadtreeId,"uploadtree",$uploadID,$left,$right )));

//    $fileTreeBounds = Mockery::mock('Fossology\Lib\Data\FileTreeBounds');
//    $fileTreeBounds->shouldReceive('getUploadTreeTableName')->once()->andReturn('uploadtree');
//    $fileTreeBounds->shouldReceive('getUploadId')->andReturn($uploadID);
//    $fileTreeBounds->shouldReceive('getLeft')->andReturn($left);
//    $fileTreeBounds->shouldReceive('getRight')->andReturn($right);

    $this->dbManager->queryOnce("INSERT INTO agent ( agent_pk, agent_name, agent_rev)
            VALUES ($agentID, $agentName, $agentRev)");


    $licDao = new LicenseDao($this->dbManager);
    $matches = $licDao->getFileLicenseMatches($fileTreeBounds);
    $this->assertInstanceOf('Fossology\Lib\Data\LicenseMatch', $matches[0]);
  }
  

  public function testGetLicenseByShortName()
  {
    $this->testDb->createPlainTables(array('license_ref'));
    $this->createTable_license_ref();
    $licDao = new LicenseDao($this->dbManager);
    $lic0 = $this->dbManager->getSingleRow("Select rf_shortname from license_ref limit 1");
    $sname = $lic0['rf_shortname'];
    $lic = $licDao->getLicenseByShortName($sname);
    $this->assertInstanceOf('Fossology\Lib\Data\License', $lic);
    $this->assertEquals($sname, $lic->getShortName());

    $sname = "Self-destructing license";
    $lic = $licDao->getLicenseByShortName($sname);
    $this->assertNull($lic);
  }

  public function testGetLicenseId()
  {
    $this->testDb->createPlainTables(array('license_ref'));
    $this->createTable_license_ref();
    $licDao = new LicenseDao($this->dbManager);
    $lic0 = $this->dbManager->getSingleRow("Select rf_pk from license_ref limit 1");
    $id = $lic0['rf_pk'];
    $lic = $licDao->getLicenseById($id);
    $this->assertInstanceOf('Fossology\Lib\Data\License', $lic);
    $this->assertEquals($id, $lic->getId());

    $id = -1;
    $lic = $licDao->getLicenseById($id);
    $this->assertNull($lic);
  }

  public function testGetLicenseRefs()
  {
    $this->testDb->createPlainTables(array('license_ref'));
    $this->createTable_license_ref();
    $licDao = new LicenseDao($this->dbManager);
    $licAll = $licDao->getLicenseRefs();
    $cntA = $this->dbManager->getSingleRow("Select count(*) cnt from license_ref limit 1");
    $this->assertEquals($cntA['cnt'], count($licAll));
    $this->assertInstanceOf('Fossology\Lib\Data\LicenseRef', $licAll[0]);
  }
}
 