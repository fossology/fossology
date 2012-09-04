<?php
/*
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 * \brief test cli fo_nomos_license_list 
 */

/**
 * @outputBuffering enabled
 */
class test_fo_nomos_license_list extends PHPUnit_Framework_TestCase {

  public $SYSCONF_DIR = "/usr/local/etc/fossology/";
  public $DB_NAME;
  public $PG_CONN;
  public $DB_COMMAND;

  // fossology_testconfig is the temporary system configuration directory
  // created by the src/testing/db/create_test_database.php script.
  // It is initialized via the Makefile and passed in via the 
  // FOSSOLOGY_TESTCONFIG environment variable.
  public $fossology_testconfig;

  // scheduler_path is the absolute path to the scheduler binary
  public $scheduler_path;

  // fo_cli_path is the absolute path to the fo_cli binary
  public $fo_cli_path;

  // cp2foss_path is the absolute path to the cp2foss binary
  public $cp2foss_path;
  
  // fo_nomos_license_list_path is the absolute path to the fo_nomos_license_list_path binary
  public $fo_nomos_license_list_path;
  
  /* initialization */

  // this method is run once for the entire test class, before any of the 
  // test methods are executed.
  public static function setUpBeforeClass() {

    global $fossology_testconfig;
    global $scheduler_path;
    global $fo_cli_path;
    global $cp2foss_path;
    global $fo_nomos_license_list_path;

    fwrite(STDOUT, "--> Running " . __METHOD__ . " method.\n");

    /**
       get the value of the FOSSOLOGY_TESTCONFIG environment variable,
       which will be initialized by the Makefile by running the 
       create_test_database.pl script
    */
    $fossology_testconfig = getenv('FOSSOLOGY_TESTCONFIG');
    fwrite(STDOUT, __METHOD__ . " got fossology_testconfig = '$fossology_testconfig'\n");

    /* locate fo_cli binary */
    $fo_cli_path = $fossology_testconfig . "/mods-enabled/scheduler/agent/fo_cli";
    if (!is_executable($fo_cli_path)) {
        print "Error:  fo_cli path '$fo_cli_path' is not executable!\n";
        exit(1);
    }

    /* locate cp2foss binary */
    // first get the absolute path to the current fossology src/ directory
    $fo_base_dir = realpath(__DIR__ . '/../..');
    $cp2foss_path = $fo_base_dir . "/cli/cp2foss";
    if (!is_executable($cp2foss_path)) {
        print "Error:  cp2foss path '" . $cp2foss_path . "' is not executable!\n";
        exit(1);
    }

    /* locate fo_nomos_license_list binary */
    // first get the absolute path to the current fossology src/ directory
    $fo_base_dir = realpath(__DIR__ . '/../..');
    $fo_nomos_license_list_path = $fo_base_dir . "/cli/fo_nomos_license_list";
    if (!is_executable($fo_nomos_license_list_path)) {
        print "Error:  fo_nomos_license_list path '" . $fo_nomos_license_list_path . "' is not executable!\n";
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
    print "\nStarting functional test for fo_nomos_license_list. \n";

  }


  // this method is run once before each test method defined for this test class.
  protected function setUp() {

    //global $fo_cli_path;
    fwrite(STDOUT, "--> Running " . __METHOD__ . " method.\n");

    //$SYSCONF_DIR = "/usr/local/etc/fossology/";
    //$DB_NAME = "fossology";
    //$DB_COMMAND = "../../testing/db/createTestDB.php";
    
    // these calls are deprecated with the new create_test_database call
    //create_db();
    //add_user();
    //preparations();
    //scheduler_operation();
  }

  /**
   * \brief first populate test data via upload from url, then get nomos license list
   */
  function test_get_nomos_list() 
  {
    global $fossology_testconfig;
    global $fo_nomos_license_list_path;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");
    $upload_id = $this->upload_from_url();

    $auth = "--user fossy --password fossy -c $fossology_testconfig";
    $command = "$fo_nomos_license_list_path $auth -u $upload_id -t $upload_id ";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);

    fwrite(STDOUT,"DEBUG: output_msg_count is:$output_msg_count\n");
    fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");
  }

  /**
   * \brief populate test data via upload from url
   */
  function upload_from_url(){
    //global $SYSCONF_DIR;
    global $fossology_testconfig;
    global $fo_cli_path;
    global $cp2foss_path;

    $test_dbh = connect_to_DB($fossology_testconfig);

    //$auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    $auth = "--user fossy --password fossy -c $fossology_testconfig";
    /** upload a file to Software Repository */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth http://www.fossology.org/rpms/fedora/10/SRPMS/fossology-1.1.0-1.fc10.src.rpm -d 'fossology des' -f 'fossology path' -n 'test package' -q 'all'";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    print "DEBUG: output is:\n";
    print_r($out);
    sleep(110);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[5]) {
      $upload_id = get_upload_id($out[5]);
    } else $this->assertFalse(TRUE);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);
    pg_close($test_dbh);

    return $upload_id;
  }

  /**
   * \brief help msg, etc
   */
  function test_others() {
    global $fossology_testconfig;
    global $fo_nomos_license_list_path;
    //global $SYSCONF_DIR;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");
    $auth = "--user fossy --password fossy -c $fossology_testconfig";
    //$auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    /** help */
    $command = "$fo_nomos_license_list_path $auth -h";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    $this->assertEquals(8, $output_msg_count, "Test that the number of output lines from '$command' is $output_msg_count");
    // print_r($out);

    /** help, not authentication */
    $out = "";
    $command = "$fo_nomos_license_list_path -h";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    $this->assertEquals(8, $output_msg_count, "Test that the number of output lines from '$command' is $output_msg_count");
    // print_r($out);
    fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");
  }

  /**
   * \brief clean the env
   */
  // this method is run once after each test method defined for this test class.
  protected function tearDown() {

    global $fo_cli_path;
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
    global $fo_cli_path;
    fwrite(STDOUT, "--> Running " . __METHOD__ . " method.\n");

    // stop the scheduler
    print "Stopping the scheduler\n";
    $fo_cli_cmd = "$fo_cli_path -s -c $fossology_testconfig";
    print "DEBUG: command is $fo_cli_cmd\n";
    exec($fo_cli_cmd, $output, $return_var);
    if ( $return_var != 0 ) {
        print "Error: Could not stop scheduler via '$fo_cli_cmd'\n";
        print "$output\n";
#        exit(1);
    }

    // time to drop the database 

    print "End of functional tests for fo_nomos_license_list\n";

  }

}

?>
