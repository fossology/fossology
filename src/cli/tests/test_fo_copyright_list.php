  <?php
  /*
   Copyright (C) 2012-2014 Hewlett-Packard Development Company, L.P.

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
   * \brief test cli fo_copyright_list 
   */

  /**
   * @outputBuffering enabled
   */
  class test_fo_copyright_list extends PHPUnit_Framework_TestCase {

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

    // cp2foss_path is the absolute path to the cp2foss binary
    public $cp2foss_path;
    
    // fo_copyright_listis the absolute path to the fo_copyright_list_path binary
    public $fo_copyright_list_path;
    
    /* initialization */

    // this method is run once for the entire test class, before any of the 
    // test methods are executed.
    public static function setUpBeforeClass() {

      global $fossology_testconfig;
      global $scheduler_path;
      global $cp2foss_path;
      global $fo_copyright_list_path;

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
      $cp2foss_path .= " -s ";

      /* locate fo_copyright_list binary */
      // first get the absolute path to the current fossology src/ directory
      $fo_base_dir = realpath(__DIR__ . '/../..');
      $fo_copyright_list_path = $fo_base_dir . "/cli/fo_copyright_list";
      if (!is_executable($fo_copyright_list_path)) {
          print "Error:  fo_copyright_list path '" . $fo_copyright_list_path. "' is not executable!\n";
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
      print "\nStarting functional test for fo_copyright_list. \n";

    }


    // this method is run once before each test method defined for this test class.
    protected function setUp() {

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
     * \brief first populate test data via upload from url, then get copyright list
     */
    function test_get_copryright_list() 
    {
      global $fossology_testconfig;
      global $fo_copyright_list_path;

      fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");
      $upload = $this->upload_from_url();
      $upload_id = $upload[0];

      $auth = "--user fossy --password fossy -c $fossology_testconfig";
      /** get all */
      $out = "";
      $uploadtree_id = $upload[1]; // uploadtree_id is the 1st uploadtree_id for this upload 
      $command = "$fo_copyright_list_path $auth -u $upload_id -t $uploadtree_id --container 1";
      fwrite(STDOUT, "DEBUG: Executing '$command'\n");
      $last = exec("$command 2>&1", $out, $rtn);
      $output_msg_count = count($out);
      /** for this uload, will get 101 lines for report */
      $this->assertEquals(101, $output_msg_count, "Test that the number of output lines from '$command' is $output_msg_count");
      /** check one line of the report */
      sort($out, SORT_STRING);
      $this->assertEquals("test package/data.tar.gz/data.tar/etc/cron.d/fossology: copyright (c) 2007 hewlett-packard development company, l.p.", $out[83]);


      $out = "";
      /** get email */
      $command = "$fo_copyright_list_path $auth -u $upload_id -t $uploadtree_id --type email --container 1";
      fwrite(STDOUT, "DEBUG: Executing '$command'\n");
      $last = exec("$command 2>&1", $out, $rtn);
      /** check one line of the report */
      sort($out, SORT_STRING);
      $this->assertEquals("test package/control.tar.gz/control.tar: taggart@debian.org", $out[5]);

      $out = "";
      /** get url */
      $command = "$fo_copyright_list_path $auth -u $upload_id -t $uploadtree_id --type url --container 1";
      fwrite(STDOUT, "DEBUG: Executing '$command'\n");
      $last = exec("$command 2>&1", $out, $rtn);
      /** check one line of the report */
      sort($out, SORT_STRING);
      $this->assertEquals("test package/data.tar.gz: http://fossology.org", $out[23]);

      /** do not include container, get url */
      $out = "";
      $command = "$fo_copyright_list_path $auth -u $upload_id -t $uploadtree_id --type url --container 0";
      fwrite(STDOUT, "DEBUG: Executing '$command'\n");
      $last = exec("$command 2>&1", $out, $rtn);
      /** check one line of the report */
      sort($out, SORT_STRING);
      $this->assertEquals("test package/control.tar.gz/control.tar/control: http://fossology.org", $out[1]);

      fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");
      fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");
  }

  /**
   * \brief populate test data via upload from url 
   */
  function upload_from_url(){
    //global $SYSCONF_DIR;
    global $fossology_testconfig;
    global $cp2foss_path;

    $test_dbh = connect_to_DB($fossology_testconfig);

    $auth = "--username fossy --password fossy -c $fossology_testconfig";
    /** upload a file to Software Repository */
    $out = "";
    $pos = 0;
    $command = "$cp2foss_path $auth http://www.fossology.org/testdata/debian/lenny-backports/fossology-db_1.2.0-3~bpo50+1_all.deb -d 'fossology des' -f 'fossology path' -n 'test package' -q 'all'";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[4]) {
      $upload_id = get_upload_id($out[4]);
    } else $this->assertFalse(TRUE);
    $agent_status = 0;
    $agent_status = check_agent_status($test_dbh,"ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);

    $uploadtree_id = get_uploadtree_id($test_dbh, $upload_id); // get uploadtree id

    pg_close($test_dbh);

    fwrite(STDOUT,"DEBUG: upload_id is:$upload_id\n");
    return array($upload_id, $uploadtree_id);
  }

  /**
   * \brief help msg, etc
   */
  function test_others() {
    global $fossology_testconfig;
    global $fo_copyright_list_path;
    //global $SYSCONF_DIR;

    fwrite(STDOUT, " ----> Running " . __METHOD__ . "\n");
    $auth = "--user fossy --password fossy -c $fossology_testconfig";
    //$auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    /** help */
    $command = "$fo_copyright_list_path $auth -h";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    $this->assertEquals(12, $output_msg_count, "Test that the number of output lines from '$command' is $output_msg_count");
    // print_r($out);

    /** help, not authentication */
    $out = "";
    $command = "$fo_copyright_list_path -h";
    fwrite(STDOUT, "DEBUG: Executing '$command'\n");
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    $this->assertEquals(12, $output_msg_count, "Test that the number of output lines from '$command' is $output_msg_count");
    // print_r($out);
    fwrite(STDOUT,"DEBUG: Done running " . __METHOD__ . "\n");
  }

  /**
   * \brief clean the env
   */
  // this method is run once after each test method defined for this test class.
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

    print "End of functional tests for fo_copyright_list\n";

  }

}

?>
