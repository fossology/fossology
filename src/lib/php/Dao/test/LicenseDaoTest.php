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
use Fossology\Lib\Data\Tree\ItemTreeBounds;
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
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;
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
    $itemTreeBounds = new ItemTreeBounds($uploadtreeId,"uploadtree",$uploadID,$left,$right);
    $matches = $licDao->getAgentFileLicenseMatches($itemTreeBounds);
    
    $licenseRef = new LicenseRef($licenseRefNumber, $lic0['rf_shortname'], $lic0['rf_fullname']);
    $agentRef = new AgentRef($agentId, $agentName, $agentRev);
    $expected = array( new LicenseMatch($pfileId, $licenseRef, $agentRef, $licenseFileId, $matchPercent) );
    
    assertThat($matches, equalTo($expected));
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

    $invalidId = -1;
    $lic = $licDao->getLicenseById($invalidId);
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
    $itemTreeBounds = new ItemTreeBounds($uploadtreeId,"uploadtree",$uploadId,$left,$right);
    $licenses = $licDao->getLicenseShortnamesContained($itemTreeBounds);
    
    asort($licAll);    
    assertThat($licenses, is(array_values($licAll)));
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }
  

  public function testGetLicenseIdPerPfileForAgentId()
  {
    $this->testDb->createPlainTables(array('license_ref','license_file','uploadtree','agent'));
    $this->testDb->insertData(array('agent'));
    $this->testDb->createViews(array('license_file_ref'));
    $this->testDb->insertData_license_ref($limit=3);
    $licAll = $this->dbManager->createMap('license_ref', 'rf_pk','rf_shortname');
    $rf_pk_all = array_keys($licAll);
    $rf_pk =  $rf_pk_all[0];
    $uploadtreetable_name = 'uploadtree';
    $this->dbManager->insertInto('license_file', 
            'fl_pk, rf_fk, agent_fk, rf_match_pct, rf_timestamp, pfile_fk, server_fk',
            array(1, $rf_pk, $agentId = 5, $matchPercent = 50, $mydate = "'2014-06-04 14:01:30.551093+02'", $pfileId=42, 1) );
    $uploadtreeId= 512;
    $uploadId =123;
    $left=2009;
    $containerMode = 1<<29;
    $this->dbManager->insertTableRow('uploadtree',
            array('uploadtree_pk'=>$uploadtreeId, 'upload_fk'=>$uploadId, 'pfile_fk'=>0,
                'lft'=>$left, 'rgt'=>$left+5, 'parent'=>NULL, 'ufile_mode'=>$containerMode));
    $this->dbManager->insertTableRow('uploadtree',
            array('uploadtree_pk'=>$uploadtreeId+1, 'upload_fk'=>$uploadId, 'pfile_fk'=>0,
                'lft'=>$left+1, 'rgt'=>$left+4, 'parent'=>$uploadtreeId, 'ufile_mode'=>$containerMode));
    $this->dbManager->insertTableRow('uploadtree',
            array('uploadtree_pk'=>$uploadtreeId+2, 'upload_fk'=>$uploadId, 'pfile_fk'=>$pfileId,
                'lft'=>$left+2, 'rgt'=>$left+3, 'parent'=>$uploadtreeId+1, 'ufile_mode'=>0));
    
    $licDao = new LicenseDao($this->dbManager);
    $itemTreeBounds = new ItemTreeBounds($uploadtreeId,$uploadtreetable_name,$uploadId,$left,$left+5);

    $row = array('pfile_id'=>$pfileId,'license_shortname'=>$licAll[$rf_pk],'license_id'=>$rf_pk,'match_percentage'=>$matchPercent,'agent_id'=>$agentId);
    $expected = array($pfileId=>array($rf_pk=>$row));
    
    $licensesForGoodAgent = $licDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $selectedAgentId = $agentId);
    assertThat($licensesForGoodAgent, is(equalTo($expected)));

    $licensesForBadAgent = $licDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $selectedAgentId = 1+$agentId);
    assertThat($licensesForBadAgent, is(equalTo(array())));
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

}
