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
 * \brief test cli fossjobs
 */

class test_fossjobs extends PHPUnit_Framework_TestCase {

  public $SYSCONF_DIR;
  public $DB_NAME;
  public $PG_CONN;
  public $DB_COMMAND;
  public $REPO_NAME;

  /* initialization */
  protected function setUp() {
    global $SYSCONF_DIR;
    global $DB_COMMAND;
    global $DB_NAME;

    $SYSCONF_DIR = "/usr/local/etc/fossology/";
    $DB_NAME = "fossology";
    $DB_COMMAND = "../../testing/db/createTestDB.php";
    print "Starting functional test for fossjobs \n";
    create_db();
    add_user();
    replace_repo();
    scheduler_operation();
  }

  /** 
   * \brief schedule agents
   */
  function test_reschedule_agents(){
    global $SYSCONF_DIR;
    global $PG_CONN;

    $out = "";
    /** 1. upload one dir, no any agents except wget/unpack/adj2nest */
    $auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    $cp2foss_command = "cp2foss $auth ./ -f fossjobs -d 'fossjobs testing'";
    // print "cp2foss_command is:$cp2foss_command\n";
    $last = exec("$cp2foss_command 2>&1", $out, $rtn);
    // print_r($out);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[4]) {
      $upload_id = get_upload_id($out[4]);
    } else $this->assertFalse(TRUE);

    sleep(5); //wait for the agents complete

    $agent_status = 0;
    $agent_status = check_agent_status("ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);

    /** reschedule all rest of agent */
    $command = "fossjobs $auth -U $upload_id -v";
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    sleep(10); //wait for the agents complete
    $agent_status = 0;
    $agent_status = check_agent_status("nomos", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status("copyright", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status("pkgagent", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status("mimetype", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;

    $out = "";
    /** 2. upload one file, schedule copyright except wget/unpack/adj2nest */
    $cp2foss_command = "cp2foss $auth ./test_fossjobs.php -f fossjobs -d 'fossjobs testing copyright' -q agent_copyright";
    $last = exec("$cp2foss_command 2>&1", $out, $rtn);
    //print_r($out);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[4]) {
      $upload_id = get_upload_id($out[4]);
    } else $this->assertFalse(TRUE);

    sleep(5); //wait for the agents complete
    $agent_status = 0;
    $agent_status = check_agent_status("ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status("copyright", $upload_id);
    $this->assertEquals(1, $agent_status);

    /** reschedule just nomos */
    $command = "fossjobs $auth -U $upload_id -v -A agent_nomos";
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    sleep(5); //wait for the agents complete

    $agent_status = 0;
    $agent_status = check_agent_status("nomos", $upload_id);
    $this->assertEquals(1, $agent_status);
  }

  /**
   * \brief list agents, list uploads, help msg
   */
  function test_list_agent_and_others(){
    $this->test_reschedule_agents(); // using the uloads in test case test_reschedule_agents()
    global $SYSCONF_DIR;
    /** help */
    $command = "fossjobs --help";
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    //print_r($out);
    $this->assertEquals(15, $output_msg_count);
    $auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    /** list agents */
    $out = "";
    $pos = 0;
    $command = "fossjobs $auth -a ";
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    $output_msg_count = count($out);
    $this->assertEquals(8, $output_msg_count);

    /** list uploads */
    $out = "";
    $pos = 0;
    $command = "fossjobs $auth -u ";
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    $output_msg_count = count($out);
    $this->assertEquals(3, $output_msg_count); // have 2 = (3 -1_ upploads
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    rollback_repo(); // rollback the repo dir in ununpack.conf and wget_agent.conf to the default
    drop_db();
    print "End up functional test for fossjobs \n";
  }
}

?>
