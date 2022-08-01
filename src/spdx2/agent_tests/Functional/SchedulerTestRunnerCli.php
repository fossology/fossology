<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\SpdxTwo\Test;

use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunner.php');

/**
 * @class SchedulerTestRunnerCli
 * @brief Handles scheduler interaction
 */
class SchedulerTestRunnerCli implements SchedulerTestRunner
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

    $agentName = "spdx2";

    $agentDir = dirname(dirname(__DIR__));
    $execDir = "$agentDir/agent";

    $pipeFd = popen($cmd = "echo $uploadId | $execDir/$agentName --userID=$userId "
            . "--groupID=$groupId --jobId=$jobId --scheduler_start -c $sysConf $args", "r");
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

    return array($success, $output, $retCode);
  }
}
