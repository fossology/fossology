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
 * \brief test cli cp2foss 
 */

/**
 * @outputBuffering enabled
 */
class test_cp2foss extends PHPUnit_Framework_TestCase {

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

  /* initialization */

  // this method is run once for the entire test class, before any of the 
  // test methods are executed.
  public static function setUpBeforeClass() {

    global $fossology_testconfig;
    global $scheduler_path;
    global $fo_cli_path;
    global $cp2foss_path;

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
    print "\nStarting functional test for cp2foss. \n";

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
   * \brief upload from server
   * 1. upload a file to Software Repository
   * 2. upload a dir to Software Repository
   * 3. upload a dir to one specified path
   *    schedule all agents, set the description for this upload.
   * 4. Loads every file under the corrent directory, except files in the Subversion directories.  The files are
          placed in the UI under the folder "test/exclude/s-u" 
   */
  function test_upload_from_server() {
    //global $SYSCONF_DIR;
    global $fossology_testconfig;
    global $fo_cli_path;
    global $cp2foss_path;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");

    $test_dbh = connect_to_DB($fossology_testconfig);

    //$auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    $auth = "--user fossy --password fossy -c $fossology_testconfig";
    /** upload a file to Software Repository */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth ./test_cp2foss.php";
    fwrite(STDOUT, "DEBUG: test_upload_from_server executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    #print "DEBUG: output is:\n";
    #print_r($out);
    fwrite(STDOUT, "DEBUG: Sleeping for 10 seconds (why?)\n");
    sleep(10);
    //DEBUG
    $repo_string = "Uploading to folder: 'Software Repository'";
    $repo_pos = strpos($out[1], $repo_string);
    $output_msg_count = count($out);
    print "DEBUG: \$this->assertGreaterThan(0, $repo_pos);\n";
    $this->assertGreaterThan(0, $repo_pos);
    print "DEBUG: \$this->assertEquals(4, $output_msg_count);\n";
    $this->assertEquals(4, $output_msg_count);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[3]) {
      $upload_id = get_upload_id($out[3]);
      print "DEBUG: Upload_id is $upload_id\n";
    } 
    else {
        print "DEBUG:  Did not get an upload_id!\n";
        $this->assertFalse(TRUE);
    }
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);

    /** upload a dir to Software Repository */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth ./";
    fwrite(STDOUT, "DEBUG: test_upload_from_server executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    print "DEBUG: output is:\n";
    print_r($out);
    print "DEBUG: Sleeping for 10 seconds (why?)\n";
    sleep(10);
    // print_r($out);
    $repo_string = "Uploading to folder: 'Software Repository'";
    $repo_pos = strpos($out[1], $repo_string);
    $output_msg_count = count($out);
    $this->assertGreaterThan(0, $repo_pos);
    $this->assertEquals(4, $output_msg_count);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[3]) {
      $upload_id = get_upload_id($out[3]);
    } else $this->assertFalse(TRUE);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh, "ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);

    /**  upload a dir to one specified path */
    $out = "";
    $pos = 0;
    $upload_path = "upload_path";
    $command = "$cp2foss_path $auth ./ -f $upload_path -d upload_des -q all -v";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    sleep(10);
    // print_r($out);
    $repo_string = "Uploading to folder: '/$upload_path'";
    $repo_pos = strpos($out[7], $repo_string);
    $output_msg_count = count($out);
    print "DEBUG: \$this->assertGreaterThan(0, $repo_pos)\n";
    $this->assertGreaterThan(0, $repo_pos);
    $scheduled_agent_info_1 = "agent_pkgagent is queued to run on";
    $scheduled_agent_info_2 = "agent_nomos is queued to run on";
    $scheduled_agent_info_3 = "agent_mimetype is queued to run on";
    $scheduled_agent_info_4 = "agent_copyright is queued to run on";
    $pos = false;
    $pos = strpos($out[$output_msg_count - 1], $scheduled_agent_info_1);
    $this->assertEquals(0, $pos);
    $pos = false;
    $pos = strpos($out[$output_msg_count - 2], $scheduled_agent_info_2);
    $this->assertEquals(0, $pos);
    $pos = false;
    $pos = strpos($out[$output_msg_count - 3], $scheduled_agent_info_3);
    $this->assertEquals(0, $pos);
    $pos = false;
    $pos = strpos($out[$output_msg_count - 4], $scheduled_agent_info_4);
    $this->assertEquals(0, $pos);
    $upload_id = 0;

    /** get upload id that you just upload for testing */
    if ($out && $out[11]) {
      $upload_id = get_upload_id($out[11]);
    } else $this->assertFalse(TRUE);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh, "ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh, "copyright", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh, "nomos", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh, "mimetype", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh, "pkgagent", $upload_id);
    $this->assertEquals(1, $agent_status);

    /** cp2foss --user USER --password PASSWORD -q all -A -f test/exclude -n 'test exclue dir'  \ 
      -d 'test des exclude dir' -X .svn -X ./ -v */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth -q all -A -f test/exclude -n 'test exclue dir'  -d 'test des exclude dir' -X .svn ./ -v";
    fwrite(STDOUT, "DEBUG: Running $command\n");
    $last = exec("$command 2>&1", $out, $rtn);
    sleep(10);
    // print_r($out);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[23]) {
      $upload_id = get_upload_id($out[23]);
    } else $this->assertFalse(TRUE);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh, "ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);

    pg_close($test_dbh);

    fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");
  }

  /**
   * \brief upload from url
   */
  function test_upload_from_url(){
    //global $SYSCONF_DIR;
    global $fossology_testconfig;
    global $fo_cli_path;
    global $cp2foss_path;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");
    $test_dbh = connect_to_DB($fossology_testconfig);

    //$auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    $auth = "--user fossy --password fossy -c $fossology_testconfig";
    /** upload a file to Software Repository */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth http://www.fossology.org/rpms/fedora/10/SRPMS/fossology-1.1.0-1.fc10.src.rpm -d 'fossology des' -f 'fossology path' -n 'test package'";
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

    fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");

  }

  /**
   * \brief list agents and help msg, etc
   */
  function test_list_agent_and_others() {
    global $fossology_testconfig;
    global $fo_cli_path;
    global $cp2foss_path;
    //global $SYSCONF_DIR;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");
    $auth = "--user fossy --password fossy -c $fossology_testconfig";
    //$auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    /** help */
    $command = "$cp2foss_path $auth -h";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    //$this->assertEquals(54, $output_msg_count);
    $this->assertCount(54, $out, "Test that the number of output lines from '$command' is 54");
    // print_r($out);
    /** list agents */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth -Q";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    $this->assertEquals(8, $output_msg_count);
    /** uplaod NULL */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth ";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    // print_r($out);
    $output_msg = "FATAL: you want to upload ''.";
    $this->assertEquals($output_msg, $out[0]);

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

    print "End of functional tests for cp2foss \n";

  }

}

?>
