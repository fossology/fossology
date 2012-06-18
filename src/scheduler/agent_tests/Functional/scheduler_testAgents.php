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
 * @file  scheduler_testAgents.php
 * @brief Uses the testAgents to test the scheduler
 * 
 * This will start the scheduler using the test agents. When the scheduler has
 * finished starting, this will make sure the correct test agents passed the
 * startup test and then stop the scheduler. This should test that any failing
 * agents are correctly handled by the scheduler.
 */

class scheduler_testAgents extends PHPUnit_Framework_TestCase {
  
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
    
    return;
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
   * @brief Tests that the correct test agents are running after startup
   * 
   * This waits for the scheduler that was started in the startUp function
   * finishes creating all the agents. It then checks that only the correctly
   * finished agents passed the test. It finished by sending a stop command to
   * the scheduler.
   */
  public function testSchedulerAgents()
  {
    /* Make sure the scheduler has finished all initializations */
    do {
      $retval = system("$this->schedulerCli -S " . $this->cmdArgs[0]);
      $delimited = explode(':', $retval);
      sleep(5);
    } while($delimited[0] != 'scheduler' && $delimited[0] != '');
    
    /*
     * @brief Tests that the correct agents passed the startup test
     *
     * There are only 3 test agents that correctly pass the scheduler's startup
     * tests. These are:
     *   1. multi_connect
     *   2. no_update
     *   3. simple
     *
     * If any new test agents that pass the scheduler startup test are created,
     * they should be added the list $valid_agents.
     * 
     * TODO this should be in its own tests
     */
    $retval = system("$this->schedulerCli -a " . $this->cmdArgs[0]);
    
    $valid_agents = array(
        'multi_connect',
        'no_update',
        'simple');
    
    $returned_agents = explode(' ', $retval);
    
    $this->assertEquals($valid_agents, $returned_agents);

    /*
     * @brief Tests that we get the correct status from the scheduler
     *
     * The scheduler should not have any currently running agents. Therefore,
     * the line returned by running am 'fo_cli -S' should be the simple scheduler
     * line.
     * 
     * TODO this should be in its own tests
     */
    $retval = system("$this->schedulerCli -S " . $this->cmdArgs[0]);
    
    $compare = 'scheduler:' . ($this->schedPid + 2) . ' revision:(null) daemon:0 jobs:0 log:' .
            $this->fakeAgents . '/fossology.log port:' .
            $this->configuration['FOSSOLOGY']['port'] . ' verbose:952';
    
    $this->assertEquals($compare, $retval);

    /*
     * @biref Tests that we gets the correct load information from the scheduler
     *
     * There is only 1 host and that is localhost so there should only be one
     * line and it should be very simple to compare to.
     * 
     * TODO this should be in its own tests
     */
    $retval = system("$this->schedulerCli -l " . $this->cmdArgs[0]);
    
    $compare = 'host:localhost address:' .
            $this->configuration['FOSSOLOGY']['address'] . ' max:10 running:0';
    
    $this->assertEquals($compare, $retval);
    
    /* All tests have finished, tell the scheduler to stop running */
    exec("$this->schedulerCli -s " . $this->cmdArgs[0]);
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
  public function testSchedulerStop()
  {
    /* Test that the scheduler is still after a stop command */
    $retval = system("$this->schedulerCli -s " . $this->cmdArgs[0]);
    sleep(5);
    $retval = system("$this->schedulerCli -S " . $this->cmdArgs[0]);
  
    $delimited = explode(':', $retval);
    $this->assertEquals($delimited[0], 'job');
  
    /* Test that the scheduler is not running after a die command */
    $retval = system("$this->schedulerCli -D " . $this->cmdArgs[0]);
    sleep(5);
    $retval = system("$this->schedulerCli -S " . $this->cmdArgs[0]);
  
    $delimited = explode(':', $retval);
    $this->assertEquals($delimited[0], '');
  }
}

?>
