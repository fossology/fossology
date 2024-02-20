<?php
/*
 SPDX-FileCopyrightText: © 2012-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief test cli cp2foss  for package testing 
 */

require_once("./test_common.php");

/**
 * @outputBuffering enabled
 */
class test_cp2foss extends \PHPUnit\Framework\TestCase {

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

  // cp2foss_path is the absolute path to the cp2foss binary
  public $cp2foss_path;

  /* initialization */

  // this method is run once for the entire test class, before any of the 
  // test methods are executed.
  public static function setUpBeforeClass() {

    global $fossology_testconfig;
    global $scheduler_path;
    global $cp2foss_path;
    global $PG_CONN;

    $fossology_testconfig = getenv('FOSSOLOGY_TESTCONFIG');
    /** set default config dir as /etc/fossology/ */
    if (empty($fossology_testconfig)) $fossology_testconfig = "/usr/local/etc/fossology/";
    fwrite(STDOUT, __METHOD__ . " got fossology_testconfig = '$fossology_testconfig'\n");

    $cp2foss_path = "cp2foss";

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

    $PG_CONN = connect_to_DB($fossology_testconfig); // connect db
    add_user("fossy", "fossy"); // add account fossy/fossy

    print "\nStarting functional test for cp2foss. \n";

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
    global $fossology_testconfig;
    global $cp2foss_path;
    global $PG_CONN;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");

    $test_dbh = $PG_CONN;

    $auth = "--username fossy --password fossy -c $fossology_testconfig -s ";
    /** upload a file to Software Repository */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth ./test_cp2foss.php";
    fwrite(STDOUT, "DEBUG: test_upload_from_server executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
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
    $repo_string = "Uploading to folder: '/$upload_path'";
    $repo_pos = strpos($out[7], $repo_string);
    $output_msg_count = count($out);
    print "DEBUG: \$this->assertGreaterThan(0, $repo_pos)\n";
    $this->assertGreaterThan(0, $repo_pos);
    $scheduled_agent_info_1 = "agent_pkgagent is queued to run on";
    $scheduled_agent_info_2 = "agent_nomos is queued to run on";
    $scheduled_agent_info_3 = "agent_monk is queued to run on";
    $scheduled_agent_info_4 = "agent_mimetype is queued to run on";
    $scheduled_agent_info_5 = "agent_copyright is queued to run on";
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
    $pos = false;
    $pos = strpos($out[$output_msg_count - 5], $scheduled_agent_info_5);
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

  // this method is run once for the entire test class, after all of the 
  // test methods are executed.
  public static function tearDownAfterClass() {

    global $fossology_testconfig;
    global $scheduler_path;

    // stop the scheduler
    print "Stopping the scheduler\n";
    $scheduler_cmd = "$scheduler_path -k -c $fossology_testconfig";
    exec($scheduler_cmd, $output, $return_var);
    if ( $return_var != 0 ) {
        print "Error: Could not stop scheduler via '$scheduler_cmd'\n";
        print "$output\n";
#        exit(1);
    }

    // time to drop the database 

    print "End of functional tests for cp2foss \n";

  }

}
