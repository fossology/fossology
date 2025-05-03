<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Compatibility\Test;

use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunner.php');

/**
 * @class SchedulerTestRunnerScheduler
 * @brief Handles scheduler interaction
 */
class SchedulerTestRunnerScheduler implements SchedulerTestRunner
{
  /** @var TestPgDb $testDb
   * Test DB
   */
  private $testDb;

  public function __construct(TestPgDb $testDb)
  {
    $this->testDb = $testDb;
  }

  public function run($uploadId, $userId=2, $groupId=2, $jobId=1, $args="")
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "compatibility";

    $agentDir = dirname(__DIR__, 4).'/build/src/compatibility';
    $execDir = "$agentDir/agent";
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/$agentName/VERSION");
    system("install -D $agentDir/agent/$agentName $sysConf/mods-enabled/$agentName/agent/$agentName");
    $pCmd = "echo $uploadId | $execDir/$agentName --userID=$userId --groupID=$groupId --jobId=$jobId --scheduler_start -c $sysConf $args";
    $pipeFd = popen($pCmd, "r");
    $success = $pipeFd !== false;

    $output = "";
    $retCode = -1;
    if ($success) {
      while (($buffer = fgets($pipeFd, 4096)) !== false) {
        $output .= $buffer;
      }
      $retCode = pclose($pipeFd);
    } else {
      print "failed opening pipe to $pCmd";
    }

    unlink("$sysConf/mods-enabled/$agentName/VERSION");
    unlink("$sysConf/mods-enabled/$agentName/agent/$agentName");
    rmdir("$sysConf/mods-enabled/$agentName/agent/");
    rmdir("$sysConf/mods-enabled/$agentName");
    rmdir("$sysConf/mods-enabled");
    unlink($sysConf."/fossology.conf");

    return array($success, $output, $retCode);
  }
}
