<?php
/*
 Copyright (C) 2013-2014 Hewlett-Packard Development Company, L.P.

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

require_once("./test_common.php");

/**
 * \brief test cli fossjobs
 */

/**
 * @outputBuffering enabled
 */
class test_fossjobs extends PHPUnit_Framework_TestCase {

  // fossology_testconfig is the temporary system configuration directory
  // created by the src/testing/db/create_test_database.php script.
  // It is initialized via the Makefile and passed in via the 
  // FOSSOLOGY_TESTCONFIG environment variable.
  public $fossology_testconfig;

  // scheduler_path is the absolute path to the scheduler binary
  public $scheduler_path;

  // cp2foss_path is the absolute path to the cp2foss binary
  public $cp2foss_path;

  // fossjobs_path is the absolute path to the fossjobs binary
  public $fossjobs_path;

  // this method is run once for the entire test class, before any of the 
  // test methods are executed.
  public static function setUpBeforeClass() {

    global $fossology_testconfig;
    global $scheduler_path;
    global $cp2foss_path;
    global $fossjobs_path;

    fwrite(STDOUT, "--> Running " . __METHOD__ . " method.\n");

    /**
       get the value of the FOSSOLOGY_TESTCONFIG environment variable,
       which will be initialized by the Makefile by running the 
       create_test_database.pl script
    */
    $fossology_testconfig = getenv('FOSSOLOGY_TESTCONFIG');
    fwrite(STDOUT, __METHOD__ . " got fossology_testconfig = '$fossology_testconfig'\n");

    /* locate cp2foss binary */
    // first get the absolute path to the current fossology src/ directory
    $fo_base_dir = realpath(__DIR__ . '/../..');
    $cp2foss_path = $fo_base_dir . "/cli/cp2foss";
    if (!is_executable($cp2foss_path)) {
        print "Error:  cp2foss path '" . $cp2foss_path . "' is not executable!\n";
        exit(1);
    }

    /* locate fossjobs binary */
    $fossjobs_path = $fo_base_dir . "/cli/fossjobs";
    if (!is_executable($fossjobs_path)) {
        print "Error:  fossjobs path '" . $cp2foss_path . "' is not executable!\n";
        exit(1);
    }

    /* locate the scheduler binary */
    $scheduler_path = $fossology_testconfig . "/mods-enabled/scheduler/agent/fo_scheduler";
    if (!is_executable($scheduler_path)) {
        print "Error:  Scheduler path '$scheduler_path' is not executable!\n";
        exit(1);
    }

    /* invoke the scheduler */
    $scheduler_cmd = "$scheduler_path --daemon --reset --verbose=952 -c $fossology_testconfig";
    print "DEBUG: Starting scheduler with '$scheduler_cmd'\n";
    exec($scheduler_cmd, $output, $return_var);
    //print_r($output);
    if ( $return_var != 0 ) {
        print "Error: Could not start scheduler '$scheduler_path'\n";
        print "$output\n";
        exit(1);
    }
    sleep(10);
    print "\nStarting functional test for fossjobs. \n";

  }

  /* initialization */
  protected function setUp() {

    fwrite(STDOUT, "--> Running " . __METHOD__ . " method.\n");

  }

  /** 
   * \brief schedule agents
   */
  function test_reschedule_agents(){
    global $fossology_testconfig;
    global $scheduler_path;
    global $cp2foss_path;
    global $fossjobs_path;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");

    $test_dbh = connect_to_DB($fossology_testconfig);

    $out = "";
    /** 1. upload one dir, no any agents except wget/unpack/adj2nest */
    $auth = "--username fossy --password fossy -c $fossology_testconfig";
    $cp2foss_command = "$cp2foss_path -s $auth ./ -f fossjobs -d 'fossjobs testing'";
    // print "cp2foss_command is:$cp2foss_command\n";
    fwrite(STDOUT, "DEBUG: " . __METHOD__ . " Line: " . __LINE__ . "  executing '$cp2foss_command'\n");
    $last = exec("$cp2foss_command 2>&1", $out, $rtn);
    // print_r($out);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[4]) {
      $upload_id = get_upload_id($out[4]);
    } else $this->assertFalse(TRUE);

    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh, "ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);

    /** reschedule all rest of agent */
    $command = "$fossjobs_path $auth -U $upload_id -A agent_copyright,agent_mimetype,agent_nomos,agent_pkgagent -v";
    fwrite(STDOUT, "DEBUG: " . __METHOD__ . " Line: " . __LINE__ . "  executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    fwrite(STDOUT, "DEBUG: " . __METHOD__ . " Line: " . __LINE__ . "  Waiting 300 seconds for the agents to complete\n");
    sleep(300); //wait for the agents complete $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"nomos", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"copyright", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"pkgagent", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"mimetype", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;

    $out = "";

    /** 2. upload one file, schedule copyright except wget/unpack/adj2nest */
    $cp2foss_command = "$cp2foss_path -s $auth ./test_fossjobs.php -f fossjobs -d 'fossjobs testing copyright' -q agent_copyright";
    fwrite(STDOUT, "DEBUG: " . __METHOD__ . " Line: " . __LINE__ . "  executing '$cp2foss_command'\n");
    $last = exec("$cp2foss_command 2>&1", $out, $rtn);
    //print_r($out);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[4]) {
      $upload_id = get_upload_id($out[4]);
    } else $this->assertFalse(TRUE);

    sleep(5); //wait for the agents complete
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"copyright", $upload_id);
    $this->assertEquals(1, $agent_status);

    /** reschedule just nomos */
    $command = "$fossjobs_path $auth -U $upload_id -v -A agent_nomos";
    fwrite(STDOUT, "DEBUG: " . __METHOD__ . " Line: " . __LINE__ . "  executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    sleep(5); //wait for the agents complete

    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"nomos", $upload_id);
    $this->assertEquals(1, $agent_status);

    fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");

  }

  /**
   * \brief list agents, list uploads, help msg
   */
  function test_list_agent_and_others(){

    global $fossology_testconfig;
    global $scheduler_path;
    global $cp2foss_path;
    global $fossjobs_path;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");

    //$this->test_reschedule_agents(); // using the uloads in test case test_reschedule_agents()
    /** help */
    $command = "$fossjobs_path -h -c $fossology_testconfig";
    fwrite(STDOUT, "DEBUG: " . __METHOD__ . " Line: " . __LINE__ . "  executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    //print_r($out);
    $this->assertEquals(17, $output_msg_count);
    $auth = "--username fossy --password fossy -c $fossology_testconfig";
    /** list agents */
    $out = "";
    $pos = 0;
    $command = "$fossjobs_path $auth -a ";
    fwrite(STDOUT, "DEBUG: " . __METHOD__ . " Line: " . __LINE__ . "  executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    $output_msg_count = count($out);
    $this->assertEquals(9, $output_msg_count);

    /** list uploads */
    $out = "";
    $pos = 0;
    $command = "$fossjobs_path $auth -u ";
    fwrite(STDOUT, "DEBUG: " . __METHOD__ . " Line: " . __LINE__ . "  executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    fwrite(STDOUT, "DEBUG: output was:\n");
    print_r($out);
    $output_msg_count = count($out);
    // TODO: / Note:  This is *Highly* dependent on the execution of 
    // test_reschedule_agents() - i.e. these two test cases are 
    // tightly coupled, and they should _not_ be so.
    // at the end of test_reschedule and this method, the number of
    $this->assertEquals(3, $output_msg_count, $command); // have 2 = (3 -1_ uploads

    fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    global $fossology_testconfig;

    fwrite(STDOUT, "--> Running " . __METHOD__ . " method.\n");

    // TODO:  Drop the test database

    //stop_scheduler(); 
    //drop_db();
  }

  // this method is run once for the entire test class, after all of the 
  // test methods are executed.
  public static function tearDownAfterClass() {

    global $fossology_testconfig;
    global $scheduler_path;
    fwrite(STDOUT, "--> Running " . __METHOD__ . " method.\n");

    // stop the scheduler
    print "Stopping the scheduler\n";
    $scheduler_cmd = "$scheduler_path -k -c $fossology_testconfig";
    print "DEBUG: command is $scheduler_cmd \n";
    exec($scheduler_cmd, $output, $return_var);
    if ( $return_var != 0 ) {
        print "Error: Could not stop scheduler via '$scheduler_cmd'\n";
        print "$output\n";
#        exit(1);
    }

    // time to drop the database 
    sleep(10);

    print "End of functional tests for cp2foss \n";
  }

}

?>
