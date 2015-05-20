<?php
/*
Copyright (C) 2015, Siemens AG

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

namespace Fossology\SpdxTwo\Test;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunnerCli.php');

class SchedulerTest extends \PHPUnit_Framework_TestCase
{
  /** @var int */
  private $userId = 2;
  /** @var int */
  private $groupId = 2;
  
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var TestInstaller */
  private $testInstaller;
  /** @var SchedulerTestRunnerCli */
  private $runnerCli;

  public function setUp()
  {
    $this->testDb = new TestPgDb("spdx2test");
    $this->dbManager = $this->testDb->getDbManager();

    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    
    $this->agentDir = dirname(dirname(__DIR__));
  }

  public function tearDown()
  {
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
    $this->testInstaller->install($this->agentDir);
  }

  private function rmRepo()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  private function setUpTables()
  {
    $this->testDb->createPlainTables(array(),true);
    $this->testDb->createInheritedTables();
    $this->dbManager->queryOnce("CREATE TABLE copyright_ars () INHERITS (ars_master)");
    $this->dbManager->queryOnce("CREATE TABLE monk_ars () INHERITS (ars_master)");
    $this->dbManager->queryOnce("CREATE TABLE nomos_ars () INHERITS (ars_master)");
    
    $this->testDb->createSequences(array('agent_agent_pk_seq','pfile_pfile_pk_seq','upload_upload_pk_seq','nomos_ars_ars_pk_seq','license_file_fl_pk_seq','license_ref_rf_pk_seq','license_ref_bulk_lrb_pk_seq','clearing_decision_clearing_decision_pk_seq','clearing_event_clearing_event_pk_seq'));
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'));
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','clearing_decision','license_file','highlight'));
    
    $this->testDb->insertData(array('mimetype_ars','pkgagent_ars','ununpack_ars','decider_ars'),true,__DIR__.'/fo_report.sql');
    // $this->testDb->insertData_license_ref();
    $this->testDb->resetSequenceAsMaxOf('agent_agent_pk_seq', 'agent', 'agent_pk');
  }

  private function getHeartCount($output)
  {
    $matches = array();
    if (preg_match("/.*HEART: ([0-9]*).*/", $output, $matches))
    {
      return intval($matches[1]);
    } else
    {
      return -1;
    }
  }

  /** @group Functional */
  public function testReport()
  {
    $this->setUpTables();
    $this->setUpRepo();

    list($success,$output,$retCode) = $this->runnerCli->run($uploadId=1, $this->userId, $this->groupId, $jobId=7);

    assertThat('cannot run runner', $success, equalTo(true));
    assertThat( 'report failed: "'.$output.'"', $retCode, equalTo(0));
    assertThat($this->getHeartCount($output), greaterThan(0));
    
    $row = $this->dbManager->getSingleRow("SELECT upload_fk,job_fk,filepath FROM reportgen WHERE job_fk = $1", array($jobId), "reportFileName");
    assertThat($row, hasKeyValuePair('upload_fk', $uploadId));
    assertThat($row, hasKeyValuePair('job_fk', $jobId));
    $filepath = $row['filepath'];
    assertThat($filepath, endsWith('.rdf'));
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
        
    $this->verifyRdf($filepath);
    unlink($filepath);
    $this->rmRepo();
  }
  
  protected function verifyRdf($filepath)
  {
    exec('which java', $lines, $returnVar);
    $this->assertEquals(0,$returnVar,'java required for this test');
    
    $toolDir = __DIR__.'/test-tool/';

    if(!is_dir($toolDir))
    {
      $this->pullSpdxTools($toolDir);
    }

    $toolJarFile = $toolDir.'/SPDXTools-v2.0.0/spdx-tools-2.0.0-jar-with-dependencies.jar';
    $tagFile = __DIR__."/out.tag";
    exec("java -jar $toolJarFile RdfToTag $filepath $tagFile");
    
    $this->assertFileExists($tagFile, 'SPDXTools failed');
    assertThat(filesize($tagFile),is(greaterThan(42)));
    unlink($tagFile);
  }
  
  protected function pullSpdxTools($toolDir)
  {        
    $toolZipFile = __DIR__."/SPDXTools-v2.0.0.zip";    
    if(!file_exists($toolZipFile))
    {
      file_put_contents($toolZipFile, fopen("http://spdx.org/sites/spdx/files/SPDXTools-v2.0.0.zip", 'r'));
    }
    $this->assertFileExists($toolZipFile, 'could not find SPDXTools');
    
    $zip = new \ZipArchive();
    $zip->open($toolZipFile);
    $this->assertTrue( $zip->extractTo($toolDir), 'could not unzip SPDXTools');
    $zip->close();
  }

}
