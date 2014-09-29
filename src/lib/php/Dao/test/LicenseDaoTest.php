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

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;

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

  public function testSimple()
  {
    $licDao = new LicenseDao($this->dbManager);
    $this->assertInstanceOf('Fossology\Lib\Dao\LicenseDao', $licDao);
  }

  public function testGetFileLicenseMatches()
  {
    $this->testDb->createPlainTables(array('license_ref','uploadtree','license_file','agent'));
    $this->testDb->insertData_license_ref();

    $lic0 = $this->dbManager->getSingleRow("Select * from license_ref limit 1");
    $licenseRefNumber = $lic0['rf_pk'];
    $licenseFileId= 1;
    $pfileId= 42;
    $agentId = 23;
    $matchPercent = 50;
    $uploadtreeId= 512;
    $uploadID =123;
    $left=2009;
    $right=2014;
    $agentName="fake";
    $agentRev=1;
    $mydate = "'2014-06-04 14:01:30.551093+02'";
    $this->testDb->createViews(array('license_file_ref'));
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk, rf_fk, agent_fk, rf_match_pct, rf_timestamp, pfile_fk)
            VALUES ($licenseFileId, $licenseRefNumber, $agentId, $matchPercent, $mydate, $pfileId)");
    $this->dbManager->queryOnce("INSERT INTO uploadtree (uploadtree_pk, upload_fk, pfile_fk, lft, rgt)
            VALUES ($uploadtreeId, $uploadID, $pfileId, $left, $right)");
    $stmt = __METHOD__.'.insert.agent';
    $this->dbManager->prepare($stmt,"INSERT INTO agent (agent_pk, agent_name, agent_rev, agent_enabled) VALUES ($1,$2,$3,$4)");
    $this->dbManager->execute($stmt,array($agentId, $agentName, $agentRev, 'true'));

    $licDao = new LicenseDao($this->dbManager);
    $fileTreeBounds = new FileTreeBounds($uploadtreeId,"uploadtree",$uploadID,$left,$right);
    $matches = $licDao->getAgentFileLicenseMatches($fileTreeBounds);
    
    $licenseRef = new LicenseRef($licenseRefNumber, $lic0['rf_shortname'], $lic0['rf_fullname']);
    $agentRef = new AgentRef($agentId, $agentName, $agentRev);
    $expected = array( new LicenseMatch($pfileId, $licenseRef, $agentRef, $licenseFileId, $matchPercent) );
    
    assertThat($matches, equalTo($expected));
//    $this->assertInstanceOf(LicenseMatch::className(), $matches[0]);
    assertThat($matches[0], is(anInstanceOf(LicenseMatch::classname())) );
  }
  

  public function testGetLicenseByShortName()
  {
    $this->testDb->createPlainTables(array('license_ref'));
    $this->testDb->insertData_license_ref($limit=3);
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
    $this->testDb->insertData_license_ref($limit=3);
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
    $this->testDb->insertData_license_ref();
    $licDao = new LicenseDao($this->dbManager);
    $licAll = $licDao->getLicenseRefs();
    $cntA = $this->dbManager->getSingleRow("Select count(*) cnt from license_ref limit 1");
    $this->assertEquals($cntA['cnt'], count($licAll));
    $this->assertInstanceOf('Fossology\Lib\Data\LicenseRef', $licAll[0]);
  }
  
  public function testGetLicenseShortnamesContained()
  {
    $this->testDb->createPlainTables(array('license_ref','license_file','uploadtree'));
    $this->dbManager->queryOnce("CREATE TABLE \"uploadtree_a\" AS SELECT * FROM uploadtree");
    $this->testDb->createViews(array('license_file_ref'));
    $this->testDb->insertData(array('license_file','uploadtree_a'));
    $this->testDb->insertData_license_ref($limit=3);
    $stmt = __METHOD__.'.select.license_ref';
    $this->dbManager->prepare($stmt,"SELECT rf_pk,rf_shortname FROM license_ref");
    $licRes = $this->dbManager->execute($stmt);
    $licAll = array();
    while ($erg=$this->dbManager->fetchArray($licRes))
    {
      $licAll[$erg['rf_pk']] = $erg['rf_shortname'];
    }
    $this->dbManager->freeResult($licRes);
    $pfileId= 42;
    $agentId = 23;
    $matchPercent = 50;
    $uploadtreeId= 512;
    $uploadId =123;
    $left=2009;
    $right=2014;
    $mydate = "'2014-06-04 14:01:30.551093+02'";
    foreach ($licAll as $licenseRefNumber=>$shortname)
    {
      $this->dbManager->queryOnce("INSERT INTO license_file (rf_fk, agent_fk, rf_match_pct, rf_timestamp, pfile_fk)
            VALUES ($licenseRefNumber, $agentId, $matchPercent, $mydate, $pfileId)");
    }
    $this->dbManager->queryOnce("INSERT INTO uploadtree (uploadtree_pk, upload_fk, pfile_fk, lft, rgt)
            VALUES ($uploadtreeId, $uploadId, $pfileId, $left, $right)");

    $licDao = new LicenseDao($this->dbManager);
    $fileTreeBounds = new FileTreeBounds($uploadtreeId,"uploadtree",$uploadId,$left,$right);
    $licenses = $licDao->getLicenseShortnamesContained($fileTreeBounds);
    
    asort($licAll);    
    assertThat($licenses, is(array_values($licAll)));
  }
}
 