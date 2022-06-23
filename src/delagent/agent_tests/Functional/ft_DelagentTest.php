<?php
/*
 SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/cli/tests/test_common.php');
require_once (__DIR__ . "/../../../testing/db/createEmptyTestEnvironment.php");

/**
 * @class ft_DelagentTest
 * @brief test delagent cli
 */

class ft_DelagentTest extends \PHPUnit\Framework\TestCase {

  public $SYSCONF_DIR;
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
    } else { 
      $this->assertFalse(true);
    }
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
  protected function setUp() : void {
    global $SYSCONF_DIR;
    global $DB_COMMAND;
    global $DB_NAME;
    global $REPO_NAME;

    $cwd = getcwd();
    list($test_name, $SYSCONF_DIR, $DB_NAME, $PG_CONN) = setupTestEnv($cwd, "delagent", false);

    $REPO_NAME = "testDbRepo".$test_name;
    add_user();
    replace_repo();
    scheduler_operation();
    $this->upload_testdata();
  }

  /**
   * @brief test delagent -u
   * @test
   * -# Get the Upload id and filename for a upload.
   * -# Call delagent cli with `-u` flag
   * -# Check if the upload id and filename matches.
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
    /* the file is one executable file */
    $command = "$EXE_PATH -u -n fossy -p fossy";
    exec($command, $out, $rtn);
    //print_r($out);
    $this->assertStringStartsWith($expected, $out[1]);
  }

  /**
   * @brief test delagent -u with wrong user
   * @test
   * -# Get the Upload id and filename for a upload.
   * -# Call delagent cli with `-u` flag but wrong user
   * -# Check that upload id and filename should not match.
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
    /* the file is one executable file */
    $command = "$EXE_PATH -u -n testuser -p testuser";
    exec($command, $out, $rtn);
    //print_r($out);
    $this->assertStringStartsWith($expected, $out[1]);
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() : void {
    rollback_repo(); // rollback the repo dir in ununpack.conf and wget_agent.conf to the default
    drop_db();
    print "End up functional test for cp2foss \n";
  }

}


