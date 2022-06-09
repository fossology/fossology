<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Decider\Test;

use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunner.php');

/**
 * @file SchedulerTestRunnerCli
 * @todo move to lib/test
 */
class SchedulerTestRunnerCli implements SchedulerTestRunner
{
  /** @var TestPgDb */
  private $testDb;

  public function __construct(TestPgDb $testDb)
  {
    $this->testDb = $testDb;
  }

  /**
   * @copydoc SchedulerTestRunner::run()
   * @see SchedulerTestRunner::run()
   */
  public function run($uploadId, $userId=2, $groupId=2, $jobId=1, $args="")
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "decider";

    $agentDir = dirname(dirname(__DIR__));
    $execDir = "$agentDir/agent";
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/$agentName/VERSION");

    $pipeFd = popen($cmd = "echo $uploadId | $execDir/$agentName --userID=$userId --groupID=$groupId --jobId=$jobId --scheduler_start -c $sysConf $args", "r");
    $success = $pipeFd !== false;

    $output = "";
    $retCode = -1;
    if ($success) {
      while (($buffer = fgets($pipeFd, 4096)) !== false) {
        $output .= $buffer;
      }
      $retCode = pclose($pipeFd);
    } else {
      print "failed opening pipe to $cmd";
    }

    unlink("$sysConf/mods-enabled/$agentName/VERSION");
    rmdir("$sysConf/mods-enabled/$agentName");
    rmdir("$sysConf/mods-enabled");

    return array($success, $output, $retCode);
  }
}
