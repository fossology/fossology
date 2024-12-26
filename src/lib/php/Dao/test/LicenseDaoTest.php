<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestLiteDb;
use Fossology\Lib\Test\TestPgDb;

class LicenseDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestLiteDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var LicenseDao */
  private $licenseDao;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->testDb->createPlainTables(array('obligation_ref','obligation_map','obligation_candidate_map'));
    $this->dbManager = $this->testDb->getDbManager();
    $this->licenseDao = new LicenseDao($this->dbManager);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * Setup license_ref table and rf_pk sequence
   */
  private function setUpLicenseRefTable()
  {
    $this->testDb->createPlainTables(array('license_ref'));
    $this->testDb->createSequences(array('license_ref_rf_pk_seq'));
    $this->testDb->alterTables(array('license_ref'));
  }

  public function testGetFileLicenseMatches()
  {
    $this->testDb->createPlainTables(array('uploadtree','license_file','agent'));
    $this->setUpLicenseRefTable();
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

    $licenseRef = new LicenseRef($licenseRefNumber, $lic0['rf_shortname'], $lic0['rf_fullname'], $lic0['rf_spdx_id']);
    $agentRef = new AgentRef($agentId, $agentName, $agentRev);
    $expected = array( new LicenseMatch($pfileId, $licenseRef, $agentRef, $licenseFileId, $matchPercent) );

    assertThat($matches, equalTo($expected));
    assertThat($matches[0], is(anInstanceOf(LicenseMatch::class)) );
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testGetLicenseByShortName()
  {
    $this->setUpLicenseRefTable();
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
    $this->setUpLicenseRefTable();
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
    $this->setUpLicenseRefTable();
    $this->testDb->insertData_license_ref();
    $licDao = new LicenseDao($this->dbManager);
    $licAll = $licDao->getLicenseRefs();
    $cntA = $this->dbManager->getSingleRow("Select count(*) cnt from license_ref limit 1");
    $this->assertEquals($cntA['cnt'], count($licAll));
    $this->assertInstanceOf('Fossology\Lib\Data\LicenseRef', $licAll[0]);
  }

  public function testGetLicenseShortnamesContained()
  {
    $this->testDb->createPlainTables(array('license_file','uploadtree'));
    $this->setUpLicenseRefTable();
    $this->dbManager->queryOnce("CREATE TABLE \"uploadtree_a\" AS SELECT * FROM uploadtree");
    $this->testDb->createViews(array('license_file_ref'));
    $this->testDb->insertData(array('license_file','uploadtree_a'));
    $this->testDb->insertData_license_ref($limit=3);
    $stmt = __METHOD__.'.select.license_ref';
    $this->dbManager->prepare($stmt,"SELECT rf_pk,rf_shortname FROM license_ref");
    $licRes = $this->dbManager->execute($stmt);
    $licAll = array();
    while ($erg=$this->dbManager->fetchArray($licRes)) {
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
    foreach ($licAll as $licenseRefNumber=>$shortname) {
      $this->dbManager->queryOnce("INSERT INTO license_file (rf_fk, agent_fk, rf_match_pct, rf_timestamp, pfile_fk)
            VALUES ($licenseRefNumber, $agentId, $matchPercent, $mydate, $pfileId)");
    }
    $this->dbManager->queryOnce("INSERT INTO uploadtree (uploadtree_pk, upload_fk, pfile_fk, lft, rgt)
            VALUES ($uploadtreeId, $uploadId, $pfileId, $left, $right)");

    $licDao = new LicenseDao($this->dbManager);
    $itemTreeBounds = new ItemTreeBounds($uploadtreeId,"uploadtree",$uploadId,$left,$right);
    $licenses = $licDao->getLicenseShortnamesContained($itemTreeBounds);

    assertThat($licenses, is(arrayContainingInAnyOrder(array_values($licAll))));

    $licensesForBadAgent = $licDao->getLicenseShortnamesContained($itemTreeBounds,array(2*$agentId));
    assertThat($licensesForBadAgent, is(emptyArray()));

    $licensesForNoAgent = $licDao->getLicenseShortnamesContained($itemTreeBounds,array());
    assertThat($licensesForNoAgent, is(emptyArray()));

    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }


  public function testGetLicenseIdPerPfileForAgentId()
  {
    $this->testDb->createPlainTables(array('license_file','uploadtree','agent'));
    $this->setUpLicenseRefTable();
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
    $nonArtifactChildId = $uploadtreeId+2;
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

    $row = array('pfile_id'=>$pfileId,'license_id'=>$rf_pk,'match_percentage'=>$matchPercent,'agent_id'=>$agentId,'uploadtree_pk'=>$nonArtifactChildId);
    $expected = array($pfileId=>array($rf_pk=>$row));
    $itemRestriction = array($nonArtifactChildId, $nonArtifactChildId+7);

    $licensesForGoodAgent = $licDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $selectedAgentId = $agentId, $itemRestriction);
    assertThat($licensesForGoodAgent, is(equalTo($expected)));

    $licensesForBadAgent = $licDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $selectedAgentId = 1+$agentId, $itemRestriction);
    assertThat($licensesForBadAgent, is(equalTo(array())));

    $licensesOutside = $licDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $selectedAgentId = $agentId, array());
    assertThat($licensesOutside, is(equalTo(array())));

    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testGetLicensesPerFileNameForAgentId()
  {
    $this->testDb->createPlainTables(array('license_file','uploadtree','agent'));
    $this->setUpLicenseRefTable();
    $this->testDb->insertData(array('agent'));
    $this->testDb->createViews(array('license_file_ref'));
    $this->testDb->insertData_license_ref($limit=3);
    $licAll = $this->dbManager->createMap('license_ref', 'rf_pk','rf_shortname');
    $rf_pk_all = array_keys($licAll);

    $uploadtreetable_name = 'uploadtree';
    //  uploadtree_pk | parent | realparent | upload_fk | pfile_fk | ufile_mode | lft | rgt |    ufile_name
    // ---------------+--------+------------+-----------+----------+------------+-----+-----+------------------
    //          80895 |        |            |        16 |    70585 |  536904704 |   1 |  36 | project.tar.gz
    //          80896 |  80895 |      80895 |        16 |        0 |  805323776 |   2 |  35 | artifact.dir
    //          80897 |  80896 |      80895 |        16 |    70586 |  536903680 |   3 |  34 | project.tar
    //          80898 |  80897 |      80897 |        16 |        0 |  805323776 |   4 |  33 | artifact.dir
    //          80899 |  80898 |      80897 |        16 |        0 |  536888320 |   5 |  32 | project
    //          80900 |  80899 |      80899 |        16 |        0 |  536888320 |   6 |   7 | folderA
    //          80905 |  80899 |      80899 |        16 |        0 |  536888320 |   8 |  23 | folderB
    //          80907 |  80905 |      80905 |        16 |        0 |  536888320 |   9 |  10 | subBfolderA
    //          80908 |  80905 |      80905 |        16 |        0 |  536888320 |  11 |  20 | subBfolderB
    //          80909 |  80908 |      80908 |        16 |        0 |  536888320 |  12 |  19 | subBBsubBfolderA
    //          80912 |  80909 |      80909 |        16 |    70592 |      33152 |  13 |  14 | BBBfileA
    //          80911 |  80909 |      80909 |        16 |    70591 |      33152 |  15 |  16 | BBBfileB
    //          80910 |  80909 |      80909 |        16 |    70590 |      33152 |  17 |  18 | BBBfileC
    //          80906 |  80905 |      80905 |        16 |        0 |  536888320 |  21 |  22 | subBfolderC
    //          80901 |  80899 |      80899 |        16 |        0 |  536888320 |  24 |  31 | folderC
    //          80903 |  80901 |      80901 |        16 |    70588 |      33152 |  25 |  26 | CfileA
    //          80904 |  80901 |      80901 |        16 |    70589 |      33152 |  27 |  28 | CfileB
    //          80902 |  80901 |      80901 |        16 |    70587 |      33152 |  29 |  30 | CfileC
    $mainUploadtreeId = 80895;
    $uploadtreeId = $mainUploadtreeId;
    $uploadId = 16;
    /* 80895 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'pfile_fk'=>70585, 'lft'=>1, 'rgt'=>36, 'ufile_mode'=>536904704, 'ufile_name'=>'project.tar.gz'));
    /* 80896 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80895, 'realparent'=>80895, 'pfile_fk'=>0, 'lft'=>2, 'rgt'=>35, 'ufile_mode'=>805323776, 'ufile_name'=>'artifact.dir'));
    /* 80897 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80896, 'realparent'=>80895, 'pfile_fk'=>70586, 'lft'=>3, 'rgt'=>34, 'ufile_mode'=>536903680, 'ufile_name'=>'project.tar'));
    /* 80898 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80897, 'realparent'=>80897, 'pfile_fk'=>0, 'lft'=>4, 'rgt'=>33, 'ufile_mode'=>805323776, 'ufile_name'=>'artifact.dir'));
    /* 80899 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80898, 'realparent'=>80897, 'pfile_fk'=>0, 'lft'=>5, 'rgt'=>32, 'ufile_mode'=>536888320, 'ufile_name'=>'project'));
    /* 80900 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80899, 'realparent'=>80899, 'pfile_fk'=>0, 'lft'=>6, 'rgt'=>7, 'ufile_mode'=>536888320, 'ufile_name'=>'folderA'));
    /* 80901 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80899, 'realparent'=>80899, 'pfile_fk'=>0, 'lft'=>24, 'rgt'=>31, 'ufile_mode'=>536888320, 'ufile_name'=>'folderC'));
    /* 80902 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80901, 'realparent'=>80901, 'pfile_fk'=>70587, 'lft'=>29, 'rgt'=>30, 'ufile_mode'=>33152, 'ufile_name'=>'CfileC'));
    /* 80903 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80901, 'realparent'=>80901, 'pfile_fk'=>70588, 'lft'=>25, 'rgt'=>26, 'ufile_mode'=>33152, 'ufile_name'=>'CfileA'));
    /* 80904 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80901, 'realparent'=>80901, 'pfile_fk'=>70589, 'lft'=>27, 'rgt'=>28, 'ufile_mode'=>33152, 'ufile_name'=>'CfileB'));
    /* 80905 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80899, 'realparent'=>80899, 'pfile_fk'=>0, 'lft'=>8, 'rgt'=>23, 'ufile_mode'=>536888320, 'ufile_name'=>'folderB'));
    /* 80906 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80905, 'realparent'=>80905, 'pfile_fk'=>0, 'lft'=>21, 'rgt'=>22, 'ufile_mode'=>536888320, 'ufile_name'=>'subBfolderC'));
    /* 80907 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80905, 'realparent'=>80905, 'pfile_fk'=>0, 'lft'=>9, 'rgt'=>10, 'ufile_mode'=>536888320, 'ufile_name'=>'subBfolderA'));
    /* 80908 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80905, 'realparent'=>80905, 'pfile_fk'=>0, 'lft'=>11, 'rgt'=>20, 'ufile_mode'=>536888320, 'ufile_name'=>'subBfolderB'));
    /* 80909 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80908, 'realparent'=>80908, 'pfile_fk'=>0, 'lft'=>12, 'rgt'=>19, 'ufile_mode'=>536888320, 'ufile_name'=>'subBBsubBfolderA'));
    /* 80910 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80909, 'realparent'=>80909, 'pfile_fk'=>70590, 'lft'=>17, 'rgt'=>18, 'ufile_mode'=>33152, 'ufile_name'=>'BBBfileC'));
    /* 80911 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80909, 'realparent'=>80909, 'pfile_fk'=>70591, 'lft'=>15, 'rgt'=>16, 'ufile_mode'=>33152, 'ufile_name'=>'BBBfileB'));
    /* 80912 */ $this->dbManager->insertTableRow($uploadtreetable_name, array('uploadtree_pk'=>$uploadtreeId++, 'upload_fk'=>$uploadId, 'parent'=>80909, 'realparent'=>80909, 'pfile_fk'=>70592, 'lft'=>13, 'rgt'=>14, 'ufile_mode'=>33152, 'ufile_name'=>'BBBfileA'));

    $agentId = 5;
    //  fl_pk  |     rf_fk     |   agent_fk   | rf_match_pct |         rf_timestamp          | pfile_fk | server_fk | fl_ref_start_byte | fl_ref_end_byte | fl_start_byte | fl_end_byte
    // --------+---------------+--------------+--------------+-------------------------------+----------+-----------+-------------------+-----------------+---------------+-------------
    //       1 | $rf_pk_all[0] | $agentId     |              | 2016-02-08 16:08:59.333096+00 |    70592 |         1 |                   |                 |               |
    //       2 | $rf_pk_all[1] | $agentId + 1 |              | 2016-02-08 16:08:59.333096+00 |    70591 |         1 |                   |                 |               |
    //       3 | $rf_pk_all[0] | $agentId     |              | 2016-02-08 16:08:59.333096+00 |    70590 |         1 |                   |                 |               |
    //       4 | $rf_pk_all[1] | $agentId     |              | 2016-02-08 16:08:59.333096+00 |    70590 |         1 |                   |                 |               |
    $someDate = "'2016-02-08 16:08:59.333096+00'";
    /* 1 */ $this->dbManager->insertInto('license_file', 'fl_pk, rf_fk, agent_fk, rf_timestamp, pfile_fk, server_fk', array(1, $rf_pk_all[0], $agentId, $someDate, 70592, 1) );
    /* 2 */ $this->dbManager->insertInto('license_file', 'fl_pk, rf_fk, agent_fk, rf_timestamp, pfile_fk, server_fk', array(2, $rf_pk_all[1], $agentId+1, $someDate, 70591, 1) );
    /* 3 */ $this->dbManager->insertInto('license_file', 'fl_pk, rf_fk, agent_fk, rf_timestamp, pfile_fk, server_fk', array(3, $rf_pk_all[0], $agentId, $someDate, 70590, 1) );
    /* 4 */ $this->dbManager->insertInto('license_file', 'fl_pk, rf_fk, agent_fk, rf_timestamp, pfile_fk, server_fk', array(4, $rf_pk_all[1], $agentId, $someDate, 70590, 1) );

    $licDao = new LicenseDao($this->dbManager);
    $itemTreeBounds = new ItemTreeBounds($mainUploadtreeId,$uploadtreetable_name,$uploadId,1,36);

    //**************************************************************************
    // Test with minimal input
    $result = $licDao->getLicensesPerFileNameForAgentId($itemTreeBounds);

    $key = "project.tar.gz/project.tar/project/folderB/subBfolderB/subBBsubBfolderA/BBBfileA";
    $this->assertArrayHasKey($key, $result);
    $expected = $licAll[$rf_pk_all[0]];
    assertThat($result[$key]['scanResults'][0], is(equalTo($expected)));

    $key = "project.tar.gz/project.tar/project/folderB/subBfolderB/subBBsubBfolderA/BBBfileB";
    $this->assertArrayHasKey($key, $result);

    $key = "project.tar.gz/project.tar/project/folderB/subBfolderB/subBBsubBfolderA/BBBfileC";
    $this->assertArrayHasKey($key, $result);
    $this->assertContains($licAll[$rf_pk_all[0]],$result[$key]['scanResults']);
    $this->assertContains($licAll[$rf_pk_all[1]],$result[$key]['scanResults']);

    $key = "project.tar.gz";
    $this->assertArrayHasKey($key, $result);

    //**************************************************************************
    // Test with empty agent list
    $result = $licDao->getLicensesPerFileNameForAgentId($itemTreeBounds, array(),true,'',true);

    $expected = array();
    assertThat($result, is(equalTo($expected)));

    //**************************************************************************
    // Test with only one agent
    $result = $licDao->getLicensesPerFileNameForAgentId($itemTreeBounds, array($agentId));

    $key = "project.tar.gz/project.tar/project/folderB/subBfolderB/subBBsubBfolderA/BBBfileA";
    $this->assertArrayHasKey($key, $result);

    $key = "project.tar.gz/project.tar/project/folderB/subBfolderB/subBBsubBfolderA/BBBfileB";
    $this->assertArrayNotHasKey($key, $result);

    //**************************************************************************
    // Test with excluding
    $result = $licDao->getLicensesPerFileNameForAgentId($itemTreeBounds, array($agentId),true,"fileC");

    $key = "project.tar.gz/project.tar/project/folderB/subBfolderB/subBBsubBfolderA/BBBfileA";
    $this->assertArrayHasKey($key, $result);

    $key = "project.tar.gz/project.tar/project/folderB/subBfolderB/subBBsubBfolderA/BBBfileB";
    $this->assertArrayNotHasKey($key, $result);

    $key = "project.tar.gz/project.tar/project/folderB/subBfolderB/subBBsubBfolderA/BBBfileC";
    $this->assertArrayNotHasKey($key, $result);

    //**************************************************************************
    // Test with container
    $result = $licDao->getLicensesPerFileNameForAgentId($itemTreeBounds, array($agentId));

    $key = "project.tar.gz";
    $this->assertArrayHasKey($key, $result);

    $key = "project.tar.gz/project.tar";
    $this->assertArrayHasKey($key, $result);

    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testIsNewLicense()
  {
    $groupId = 401;
    $this->setUpLicenseRefTable();
    $this->testDb->insertData_license_ref();
    $this->dbManager->queryOnce("CREATE TABLE license_candidate AS SELECT *,$groupId group_fk FROM license_ref LIMIT 1");
    $licCandi = $this->dbManager->getSingleRow("SELECT * FROM license_candidate",array(),__METHOD__.'.candi');
    $this->dbManager->queryOnce("DELETE FROM license_ref WHERE rf_pk=$licCandi[rf_pk]");
    $licRef = $this->dbManager->getSingleRow("SELECT * FROM license_ref LIMIT 1",array(),__METHOD__.'.ref');
    $licDao = new LicenseDao($this->dbManager);
    /* test the test but do not count assert */
    assertThat($this->dbManager->getSingleRow(
            "SELECT count(*) cnt FROM license_ref WHERE rf_shortname=$1",array($licCandi['rf_shortname']),__METHOD__.'.check'),
        is(equalTo(array('cnt'=>0))));
    $this->assertCountBefore++;
    /* test the DAO */
    assertThat($licDao->isNewLicense($licRef['rf_shortname'],$groupId), equalTo(FALSE));
    assertThat($licDao->isNewLicense($licRef['rf_shortname'],0), equalTo(FALSE));

    assertThat($licDao->isNewLicense($licCandi['rf_shortname'],$groupId), equalTo(FALSE));
    assertThat($licDao->isNewLicense($licCandi['rf_shortname'],$groupId+1), equalTo(TRUE));
    assertThat($licDao->isNewLicense($licCandi['rf_shortname'],0), equalTo(TRUE));

    assertThat($licDao->isNewLicense('(a new shortname)',$groupId), equalTo(TRUE));
    assertThat($licDao->isNewLicense('(a new shortname)',0), equalTo(TRUE));

    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }

  public function testGetAgentFileLicenseMatchesWithLicenseMapping()
  {
    $this->testDb->createPlainTables(array('uploadtree','license_file','agent','license_map'));
    $this->setUpLicenseRefTable();
    $this->testDb->insertData_license_ref();

    $lic0 = $this->dbManager->getSingleRow("Select * from license_ref limit 1",array(),__METHOD__.'.anyLicense');
    $licRefId = $lic0['rf_pk'];
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
    $lic1 = $this->dbManager->getSingleRow("SELECT * FROM license_ref WHERE rf_pk!=$1 LIMIT 1",array($licRefId),__METHOD__.'.anyOtherLicense');
    $licVarId = $lic1['rf_pk'];
    $mydate = "'2014-06-04 14:01:30.551093+02'";
    $this->dbManager->insertTableRow('license_map', array('license_map_pk'=>0,'rf_fk'=>$licVarId,'rf_parent'=>$licRefId,'usage'=>LicenseMap::CONCLUSION));
    $this->dbManager->queryOnce("INSERT INTO license_file (fl_pk, rf_fk, agent_fk, rf_match_pct, rf_timestamp, pfile_fk)
            VALUES ($licenseFileId, $licVarId, $agentId, $matchPercent, $mydate, $pfileId)");
    $this->dbManager->queryOnce("INSERT INTO uploadtree (uploadtree_pk, upload_fk, pfile_fk, lft, rgt)
            VALUES ($uploadtreeId, $uploadID, $pfileId, $left, $right)");
    $stmt = __METHOD__.'.insert.agent';
    $this->dbManager->prepare($stmt,"INSERT INTO agent (agent_pk, agent_name, agent_rev, agent_enabled) VALUES ($1,$2,$3,$4)");
    $this->dbManager->execute($stmt,array($agentId, $agentName, $agentRev, 'true'));

    $licDao = new LicenseDao($this->dbManager);
    $itemTreeBounds = new ItemTreeBounds($uploadtreeId,"uploadtree",$uploadID,$left,$right);
    $matches = $licDao->getAgentFileLicenseMatches($itemTreeBounds,LicenseMap::CONCLUSION);

    $licenseRef = new LicenseRef($licRefId, $lic0['rf_shortname'], $lic0['rf_fullname'], $lic0['rf_spdx_id']);
    $agentRef = new AgentRef($agentId, $agentName, $agentRev);
    $expected = array( new LicenseMatch($pfileId, $licenseRef, $agentRef, $licenseFileId, $matchPercent) );

    assertThat($matches, equalTo($expected));
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
  }
}
