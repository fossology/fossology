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

require_once("../../../cli/tests/test_common.php");

/**
 * \brief test delagent cli
 */

class ft_DelagentTest extends PHPUnit_Framework_TestCase {

  public $SYSCONF_DIR = "/usr/local/etc/fossology/";
  public $DB_NAME;
  public $PG_CONN;
  public $DB_COMMAND;

  /** 
   * \brief upload testdata
   *    prepare testdata for delagent, upload one tar file and schedule all agents
   */
  function upload_testdata(){
    global $SYSCONF_DIR;
    $auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    /**  upload a tar file to one specified path */
    $out = "";
    $pos = 0;
    $upload_path = "upload_path";
    $command = "cp2foss $auth ../../../pkgagent/agent_tests/testdata/fossology-1.2.0-1.el5.i386.rpm -f $upload_path -d upload_des -q all -v";
    $last = exec("$command 2>&1", $out, $rtn);
    sleep(10);
    // print_r($out);
    $repo_string = "Uploading to folder: '/$upload_path'";
    $repo_pos = strpos($out[7], $repo_string);
    $output_msg_count = count($out);
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
    $agent_status = check_agent_status("ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status("copyright", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status("nomos", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status("mimetype", $upload_id);
    $this->assertEquals(1, $agent_status);
    $agent_status = 0;
    $agent_status = check_agent_status("pkgagent", $upload_id);
    $this->assertEquals(1, $agent_status);
  }

  /* initialization */
  protected function setUp() {
    global $SYSCONF_DIR;
    global $DB_COMMAND;
    global $DB_NAME;

    $SYSCONF_DIR = "/usr/local/etc/fossology/";
    $DB_NAME = "fossology";
    $DB_COMMAND = "../../../testing/db/createTestDB.php";
    print "Starting functional test for delagent. \n";
    create_db();
    add_user();
    replace_repo();
    scheduler_operation();
    $this->upload_testdata();
  }

  /**
   * \brief test delagent -u
   */
  function test_delagentu(){
    global $EXE_PATH;
    global $PG_CONN;

    $expected = "";

    $sql = "SELECT upload_pk, upload_filename FROM upload ORDER BY upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    if (pg_num_rows($result) > 0){
      $row = pg_fetch_assoc($result);
      $expected = $row["upload_pk"] . " :: ". $row["upload_filename"];
    }
    pg_free_result($result);
    /** the file is one executable file */
    $command = "$EXE_PATH -u -n fossy -p fossy";
    exec($command, $out, $rtn);
    //print_r($out);
    $this->assertStringStartsWith($expected, $out[1]);
  }

  /**
   * \brief test delagent -u with wrong user
   */
  function test_delagentu_wronguser(){
    global $EXE_PATH;
    global $PG_CONN;

    $expected = "";

    add_user("testuser", "testuser");
    $sql = "SELECT upload_pk, upload_filename FROM upload ORDER BY upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    if (pg_num_rows($result) > 0){
      $row = pg_fetch_assoc($result);
      $expected = $row["upload_pk"] . " :: ". $row["upload_filename"];
    }
    pg_free_result($result);
    /** the file is one executable file */
    $command = "$EXE_PATH -u -n testuser -p testuser";
    exec($command, $out, $rtn);
    //print_r($out);
    $this->assertStringStartsWith($expected, $out[1]);
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    rollback_repo(); // rollback the repo dir in ununpack.conf and wget_agent.conf to the default
    drop_db();
    print "End up functional test for cp2foss \n";
  }

}

?>
