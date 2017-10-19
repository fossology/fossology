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

namespace Fossology\Report\Test;

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
    $this->testDb = new TestPgDb("report".time());
    $this->dbManager = $this->testDb->getDbManager();

    $this->runnerCli = new SchedulerTestRunnerCli($this->testDb);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    // $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
  }

  private function setUpRepo()
  {
    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
  }

  private function rmRepo()
  {
    $this->testInstaller->rmRepo();
    $this->testInstaller->clear();
  }

  private function setUpTables()
  {
    $this->testDb->createSequences(array(),true);
    $this->testDb->createPlainTables(array(),true);
    $this->testDb->createInheritedTables();
    $this->testDb->createInheritedArsTables(array('copyright','monk','nomos'));
    $this->testDb->createConstraints(array('agent_pkey','pfile_pkey','upload_pkey_idx','FileLicense_pkey','clearing_event_pkey'),false);
    $this->testDb->alterTables(array('agent','pfile','upload','ars_master','license_ref_bulk','clearing_event','clearing_decision','license_file','highlight'),false);
    
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
    // TODO replace file
    // $comparisionFile = __DIR__.'/ReportTestfiles.tar_clearing_report_Mon_May_04_05_2015_11_53_18.docx';
    // assertThat(is_file($comparisionFile),equalTo(true));
    // assertThat(filesize($filepath), closeTo(filesize($comparisionFile),5));

    $this->rmRepo();
  }
}
