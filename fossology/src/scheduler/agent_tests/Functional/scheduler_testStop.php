<?php
/***************************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/

/**
 * @file scheduler_testStop.php
 * @brief Tests how the scheduler responds to stop requests
 * 
 * This will start a scheduler using the test agents and then use the command
 * line interface to attempt to stop the scheduler. It will check that the
 * scheduler is still running. It will then forcebly stop the scheduler and
 * check that it is no longer running.
 */

class scheduler_testStop extends PHPUnit_Framework_TestCase {
  
  /** The original directory that the tests were called from */
  public $originalDir;
  
  /** The directory that the test is running from */
  public $mainDir;
  
  /** The location of the agent */
  public $agentDir;
  
  /** The scheduler executable */
  public $scheduler;
  
  /** The command line interface executable */
  public $schedulerCli;
  
  /** The location of the test agents */
  public $fakesAgents;
  
  /** args that are passed to the scheduler upon startup */
  public $cmdArgs;
  
  /** The pid of the scheduler process */
  public $schedPid;
  
  /** The configuration information */
  public $configuration;
  
  /**
   * @brief Setup the tests
   *
   * This sets the test strings, starts the scheduler and waits for it to
   * finished running the statup tests.
   */
  public function setUp()
  {
    $this->originalDir  = getcwd();
  
    chdir('../..');
  
    $this->mainDir      = getcwd();
    $this->agentDir     = $this->mainDir . '/agent';
    $this->scheduler    = $this->agentDir . '/fo_scheduler';
    $this->schedulerCli = $this->agentDir . '/fo_cli';
    $this->fakeAgents   = $this->mainDir . '/agent_tests/agents';
    $this->configuration = parse_ini_file($this->fakeAgents . '/fossology.conf',
            true);
  
    $this->cmdArgs = array(
        '--config=' . $this->fakeAgents,
        '--log=' . $this->fakeAgents . '/fossology.log',
        '--verbose=952');
  
    $this->schedPid = pcntl_fork();
  
    if(!$this->schedPid)
    {
      exec("$this->scheduler " . implode(" ", $this->cmdArgs));
      exit(0);
    }
  
    sleep(1);
  }
  
  /**
   * @brief Stops the scheduler running
   *
   * This reaps the child process created when the scheduler was started.
   */
  public function tearDown()
  {
    pcntl_waitpid($this->schedPid, $status, WUNTRACED);
    chdir($this->originalDir);
  }
  
  /**
   * @brief Tests the gracefull and non-gracefull scheduler stops
   * 
   * When a scheduler is started with the test agents, there are several test
   * agents that take a while to finished running. This will use the cli to
   * send a stop and then check that the scheduler is still running since there
   * are test agents that haven't finished running yet. It will then send a die
   * and check that the scheduler is not running anymore.
   * 
   * This test was written to fix issue 681:
   *   http://www.fossology.org/issues/681
   */
  public function testStop()
  {
    $retval = system("$this->schedulerCli -s " . $this->cmdArgs[0]);
    sleep(5);
    $retval = system("$this->schedulerCli -S " . $this->cmdArgs[0]);
    
    $delimited = explode(':', $retval);
    $this->assertEquals($delimited[0], 'job');
    
    $retval = system("$this->schedulerCli -D " . $this->cmdArgs[0]);
    sleep(5);
    $retval = system("$this->schedulerCli -S " . $this->cmdArgs[0]);
    
    $delimited = explode(':', $retval);
    $this->assertEquals($delimited[0], '');
  }
}

?>
