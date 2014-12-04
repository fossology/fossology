<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Decider\Test;

use Fossology\Lib\Test\TestPgDb;

include_once(__DIR__.'/../../../lib/php/Test/Agent/AgentTestMockHelper.php');
include_once(__DIR__.'/SchedulerTestRunner.php');

/**
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
    if ($success) 
    {
      while (($buffer = fgets($pipeFd, 4096)) !== false) {
        $output .= $buffer;
      }
      $retCode = pclose($pipeFd);
    }
    else 
    {
      print "failed opening pipe to $cmd";
    }

    unlink("$sysConf/mods-enabled/$agentName/VERSION");
    rmdir("$sysConf/mods-enabled/$agentName");
    rmdir("$sysConf/mods-enabled");

    return array($success, $output, $retCode);
  }
}
