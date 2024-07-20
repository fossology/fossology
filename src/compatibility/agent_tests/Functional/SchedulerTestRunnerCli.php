<?php
/*
 SPDX-FileCopyrightText: © 2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Compatibility\Test;

use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');

/**
 * @class SchedulerTestRunnerCli
 * @brief Handles scheduler interaction
 */
class SchedulerTestRunnerCli
{
  /** @var TestPgDb $testDb
   * Test DB
   */
  private $testDb;

  public function __construct(TestPgDb $testDb)
  {
    $this->testDb = $testDb;
  }

  public function run($args)
  {
    $sysConf = $this->testDb->getFossSysConf();

    $agentName = "compatibility";

    $agentDir = dirname(__DIR__, 4).'/build/src/compatibility';
    $execDir = "$agentDir/agent";
    system("install -D $agentDir/VERSION $sysConf/mods-enabled/$agentName/VERSION");

    $curdir = getcwd();
    chdir(__DIR__);
    $pipeFd = popen($cmd = "$execDir/$agentName $args", "r");
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
    chdir($curdir);

    unlink("$sysConf/mods-enabled/$agentName/VERSION");
    rmdir("$sysConf/mods-enabled/$agentName");
    rmdir("$sysConf/mods-enabled");

    return array($success, $output, $retCode);
  }
}
