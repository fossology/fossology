<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Daniele Fognini, Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;
use Fossology\Lib\Data\AgentRef;

if (!function_exists('Traceback_uri')) {
  function Traceback_uri()
  {
    return 'Traceback_uri_if_desired';
  }
}

class CopyrightDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = $this->testDb->getDbManager();
  }

  protected function tearDown() : void
  {
    $this->testDb = null;
    $this->dbManager = null;

    M::close();
  }

  public function testGetCopyrightHighlights()
  {
    $this->testDb->createPlainTables(array(),true);
    $this->testDb->createInheritedTables();
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $uploadDao->shouldReceive('getUploadEntry')->with(1)->andReturn(array('pfile_fk'=>8));
    $uploadDao->shouldReceive('getUploadEntry')->with(2)->andReturn(array('pfile_fk'=>9));
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);
    $noHighlights = $copyrightDao->getHighlights($uploadTreeId=1);
    assertThat($noHighlights,emptyArray());

    $this->testDb->insertData(array('copyright'));
    $highlights = $copyrightDao->getHighlights($uploadTreeId = 1);
    assertThat($highlights,arrayWithSize(1));
    $highlight0 = $highlights[0];
    assertThat($highlight0,anInstanceOf(Highlight::class));
    $this->assertInstanceOf('Fossology\Lib\Data\Highlight', $highlight0);
    assertThat($highlight0->getEnd(),equalTo(201));

    $hilights = $copyrightDao->getHighlights($uploadTreeId=2);
    assertThat($hilights,arrayWithSize(1));
    $hilight0 = $hilights[0];
    assertThat($hilight0->getStart(),equalTo(0));
  }

  private function runCopyright()
  {
    $sysConf = $this->testDb->getFossSysConf().time();
    mkdir($sysConf);
    copy($this->testDb->getFossSysConf()."/Db.conf","$sysConf/Db.conf");
    system("touch ".$sysConf."/fossology.conf");
    $copyDir = dirname(dirname(dirname(dirname(__DIR__))))."/copyright/";
    system("install -D $copyDir/VERSION-copyright $sysConf/mods-enabled/copyright/VERSION");

    $retCode = 0;
    system("echo | ".$copyDir."agent/copyright -c ".$sysConf." 2>/dev/null", $retCode);
    $this->assertEquals(0, $retCode, 'this test requires a working copyright agent which creates the necessary tables');

    unlink("$sysConf/mods-enabled/copyright/VERSION");
    rmdir("$sysConf/mods-enabled/copyright");
    rmdir("$sysConf/mods-enabled");
    unlink($sysConf."/fossology.conf");
    unlink($sysConf."/Db.conf");
    rmdir($sysConf);
  }

  private function setUpClearingTables()
  {
    $this->testDb->createPlainTables(array('copyright','uploadtree','copyright_decision','copyright_event'));
    $this->testDb->createInheritedTables(array('uploadtree_a'));
    $this->testDb->insertData(array('copyright','uploadtree_a'));

    $this->testDb->createSequences(array('copyright_pk_seq','copyright_decision_pk_seq'));
    $this->testDb->alterTables(array('copyright','copyright_decision'));
  }

  private function searchContent($array, $content, $key='content')
  {
    foreach ($array as $entry) {
      if (array_key_exists($key, $entry)) {
        if ($entry[$key] === $content) {
          return true;
        }
      }
    }
    return false;
  }

  public function testGetAllEntries()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a");
    $this->assertEquals(14, count($entries));
    $this->assertTrue($this->searchContent($entries,"info@3dfx.com"));
  }

  public function testGetAllEntriesReport()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $entries = $copyrightDao->getAllEntriesReport("copyright", 1, "uploadtree_a");
    $this->assertEquals(15, count($entries));
    $this->assertTrue($this->searchContent($entries,"copyright 3dfx interactive, inc. 1999, all rights reserved this \n"));
  }

  public function testGetAllEntriesOnlyStatementsAndIndentifyedIfCleared()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", false, DecisionTypes::IDENTIFIED);
    $this->assertEquals(13, count($entries));
    $this->assertFalse($this->searchContent($entries,"info@3dfx.com"));
  }

  public function testGetAllEntriesOnlyStatementsWithFilterAndIndentifyedIfCleared()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", false, DecisionTypes::IDENTIFIED, "C.content LIKE '%permission of 3dfx interactiv%'");
    $this->assertEquals(1, count($entries));
    $this->assertTrue($this->searchContent($entries, "written permission of 3dfx interactive, \ninc. see the 3dfx glide general public license for a full text of the \n"));
  }

  public function testGetAllEntriesOnlyStatementsAndOnlyClearedIndentifyed()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED);
    $this->assertEquals(0, count($entries));
  }

  public function testGetAllEntriesForReport_afterADecision()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IDENTIFIED,"desc","text","comment"); // pfile_fk=4 => uploadtree_pk=7
    $entries = $copyrightDao->getAllEntriesReport("copyright", 1, "uploadtree_a", "statement", false, DecisionTypes::IDENTIFIED);
    $this->assertTrue($this->searchContent($entries, "desc", 'description'));
    $this->assertTrue($this->searchContent($entries, "text", 'textfinding'));
    $this->assertTrue($this->searchContent($entries, "comment", 'comments'));
  }

  public function testGetAllEntriesOnlyStatementsAndOnlyClearedIndentifyed_afterADecision()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IDENTIFIED,"desc","text","comment"); // pfile_fk=4 => uploadtree_pk=7
    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED);
    $this->assertEquals(3, count($entries));

    $expected = array(
      array(
        "description"=> "desc",
        "textfinding" => "text",
        "comments" => "comment",
        "uploadtree_pk" => "7",
        "clearing_decision_type_fk" => "5",
        "content" => "copyright 3dfx interactive, inc. 1999, all rights reserved this \n"),
      array(
        "description"=> "desc",
        "textfinding" => "text",
        "comments" => "comment",
        "uploadtree_pk" => "7",
        "clearing_decision_type_fk" => "5",
        "content" => "copyright laws of \nthe united states. \n\ncopyright 3dfx interactive, inc. 1999, all rights reserved\" \n"),
      array(
        "description" => "desc",
        "textfinding" => "text",
        "comments" => "comment",
        "uploadtree_pk" => "7",
        "clearing_decision_type_fk"=> "5",
        "content" => "written permission of 3dfx interactive, \ninc. see the 3dfx glide general public license for a full text of the \n"
      )
    );
    $this->assertEquals($expected, $entries);
  }

  public function testGetAllEntriesOnlyStatementsWithFilterAndOnlyClearedIndentifyed_afterIdentifiedDecision()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IDENTIFIED,"desc","text","comment");
    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED, "C.content LIKE 'written%'");
    $this->assertEquals(1, count($entries));
    $this->assertTrue($this->searchContent($entries, "written permission of 3dfx interactive, \ninc. see the 3dfx glide general public license for a full text of the \n"));
  }

  public function testGetAllEntriesOnlyStatementsOnlyClearedIndentifyed_irrelevantDecisionIsIrrelevant()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IRRELEVANT,"desc1","text1","comment1");
    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED);
    $this->assertEquals(0, count($entries));
  }

  public function testGetAllEntriesOnlyStatementsWithFilterAndOnlyClearedIndentifyed_afterTwoDecisions()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $decisionId = $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IDENTIFIED,"desc","text","comment");
    $copyrightDao->removeDecision("copyright_decision", 4, $decisionId);
    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IRRELEVANT,"desc1","text1","comment1");
    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED, "C.content LIKE 'written%'");
    $this->assertEquals(0, count($entries));
  }

  public function testGetAllEntriesOnlyStatementsWithFilterAndOnlyClearedIndentifyed_afterTwoDecisionsWinsSecond()
  {
    $this->setUpClearingTables();

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);

    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IDENTIFIED,"desc","text","comment");
    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IRRELEVANT,"desc1","text1","comment1");
    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IDENTIFIED,"desc2","text","comment");
    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED, "C.content LIKE 'written%'");
    $this->assertEquals(1, count($entries));
    $this->assertTrue($this->searchContent($entries, "written permission of 3dfx interactive, \ninc. see the 3dfx glide general public license for a full text of the \n"));
    $this->assertEquals("desc2", $entries[0]['description']);
  }

  public function testUpdateTable()
  {
    $this->setUpClearingTables();

    $container = M::mock('ContainerBuilder');
    $agentDao = M::mock('Fossology\Lib\Dao\AgentDao');
    $agentDao->shouldReceive('arsTableExists')->withArgs(['copyright'])
      ->andReturn(true);
    $agentDao->shouldReceive('getSuccessfulAgentEntries')
      ->withArgs(['copyright', 1])->andReturn([['agent_id' => '8',
      'agent_rev' => 'trunk.271e3e', 'agent_name' => 'copyright']]);
    $agentDao->shouldReceive('getCurrentAgentRef')->withArgs(['copyright'])
      ->andReturn(new AgentRef(8, 'copyright', 'trunk.271e3e'));

    $container->shouldReceive('get')->withArgs(['dao.agent'])
      ->andReturn($agentDao);
    $GLOBALS['container'] = $container;

    $item = new ItemTreeBounds(6,'uploadtree_a',1,17,18);
    $hash2 = '0x3a910990f114f12f';
    $ctPk = 2;
    $content = 'foo';

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);
    $copyrightDao->updateTable($item, $hash2, $content, '55', 'copyright', 'update', '1');

    $updatedCp = $this->dbManager->getSingleRow('SELECT * FROM copyright_event WHERE copyright_fk=$1',array($ctPk),__METHOD__.'.cp');
    assertThat($updatedCp['content'],is(equalTo($content)));
  }

  public function testDeleteCopyright()
  {
    $this->setUpClearingTables();

    $container = M::mock('ContainerBuilder');
    $agentDao = M::mock('Fossology\Lib\Dao\AgentDao');
    $agentDao->shouldReceive('arsTableExists')->withArgs(['copyright'])
      ->andReturn(true);
    $agentDao->shouldReceive('getSuccessfulAgentEntries')
      ->withArgs(['copyright', 1])->andReturn([['agent_id' => '8',
      'agent_rev' => 'trunk.271e3e', 'agent_name' => 'copyright']]);
    $agentDao->shouldReceive('getCurrentAgentRef')->withArgs(['copyright'])
      ->andReturn(new AgentRef(8, 'copyright', 'trunk.271e3e'));

    $container->shouldReceive('get')->withArgs(['dao.agent'])
      ->andReturn($agentDao);
    $GLOBALS['container'] = $container;

    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);
    $initialEntries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a");
    $initialCount = count($initialEntries);

    $item = new ItemTreeBounds(6,'uploadtree_a',1,17,18);
    $copyrightDao->updateTable($item, '0x3a910990f114f12f', '', '55', 'copyright', 'delete', '1');
    $updatedCp = $this->dbManager->getSingleRow('SELECT * FROM copyright_event WHERE copyright_fk=$1',array(2),__METHOD__.'.cpDel');
    $deletedIdCheck = array_search($updatedCp['uploadtree_fk'], array_column($initialEntries, 'uploadtree_pk'));
    unset($initialEntries[$deletedIdCheck]);
    $remainingCount = count($initialEntries);
    assertThat($remainingCount,is(equalTo($initialCount-1)));
  }
}
