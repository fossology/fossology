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
use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Data\DecisionTypes;
use Mockery as M;

if (!function_exists('Traceback_uri'))
{
  function Traceback_uri(){
    return 'Traceback_uri_if_desired';
  }
}

class CopyrightDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;

  public function setUp()
  {
    $this->testDb = new TestPgDb("copyrightDao".time());
    $this->dbManager = $this->testDb->getDbManager();
  }
  
  public function tearDown()
  {
    $this->testDb = null;
    $this->dbManager = null;

    M::close();
  }

  public function testGetCopyrightHighlights()
  {
    $this->testDb->createPlainTables(array(),TRUE); //array('copyright'));
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $uploadDao->shouldReceive('getUploadEntry')->andReturn(array('pfile_fk'=>8));
    $copyrightDao = new CopyrightDao($this->dbManager,$uploadDao);
    $highlights = $copyrightDao->getHighlights($uploadTreeId=1);
    $this->assertSame(array(), $highlights);
    
    $this->testDb->insertData(array('copyright'));
/*    $this->dbManager->queryOnce("INSERT INTO copyright (ct_pk, agent_fk, pfile_fk, content, hash, type, copy_startbyte, copy_endbyte) VALUES (15, 8, 8, 'written permission.

you agree to indemnify, hold harmless and defend adobe systems incorporated from and against any loss, damage, claims or lawsuits, including attorney''''s fees that arise or result ', '0x32c91329da4f38ae', 'statement', 698, 899)
",
            __METHOD__.'.insert.data');*/
    $highlights = $copyrightDao->getHighlights($uploadTreeId = 1);
    $highlight0 = reset($highlights);
    $this->assertInstanceOf('Fossology\Lib\Data\Highlight', $highlight0);
    $this->assertEquals($expected=899, $highlight0->getEnd());
  }

  private function runCopyright()
  {
    $sysConf = $this->testDb->getFossSysConf();
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
  }

  private function setUpClearingTables()
  {
    $this->testDb->createPlainTables(array('uploadtree','uploadtree_a','agent','pfile','users','bucketpool','mimetype','clearing_decision_type'),false);
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','users_user_pk_seq','clearing_decision_type_type_seq'),false);
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','user_pkey','clearing_decision_type_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','users'),false);

    $this->testDb->insertData(array('agent'), false);
    $this->runCopyright();

    $this->testDb->insertData(array('mimetype','pfile','uploadtree_a','clearing_decision_type','bucketpool','users','copyright'), false);
  }

  private function searchContent($array, $content)
  {
    foreach($array as $entry) {
      if ($entry['content'] === $content)
        return true;
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

    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", false, DecisionTypes::IDENTIFIED, "content LIKE '%permission of 3dfx interactiv%'");
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
        "uploadtree_pk" => "7",
        "clearing_decision_type_fk" => "5",
        "content" => "copyright 3dfx interactive, inc. 1999, all rights reserved this \n"),
      array(
        "description"=> "desc",
        "textfinding" => "text",
        "uploadtree_pk" => "7",
        "clearing_decision_type_fk" => "5",
        "content" => "copyright laws of \nthe united states. \n\ncopyright 3dfx interactive, inc. 1999, all rights reserved\" \n"),
      array(
        "description" => "desc",
        "textfinding" => "text",
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
    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED, "content LIKE 'written%'");
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

    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IDENTIFIED,"desc","text","comment");
    $copyrightDao->saveDecision("copyright_decision", 4, 2, DecisionTypes::IRRELEVANT,"desc1","text1","comment1");
    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED, "content LIKE 'written%'");
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
    $entries = $copyrightDao->getAllEntries("copyright", 1, "uploadtree_a", "statement", true, DecisionTypes::IDENTIFIED, "content LIKE 'written%'");
    $this->assertEquals(1, count($entries));
    $this->assertTrue($this->searchContent($entries, "written permission of 3dfx interactive, \ninc. see the 3dfx glide general public license for a full text of the \n"));
    $this->assertEquals("desc2", $entries[0]['description']);
  }


}