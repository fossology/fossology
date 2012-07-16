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

class test_cp2foss extends PHPUnit_Framework_TestCase {

  public $SYSCONF_DIR = "/usr/local/etc/fossology/";
  public $DB_NAME;
  public $PG_CONN;
  public $DB_COMMAND;

  /* initialization */
  protected function setUp() {
    global $SYSCONF_DIR;
    global $DB_COMMAND;
    global $DB_NAME;

    $SYSCONF_DIR = "/usr/local/etc/fossology/";
    $DB_NAME = "fossology";
    $DB_COMMAND = "../../testing/db/createTestDB.php";
    print "Starting functional test for cp2foss. \n";
    create_db();
    add_user();
    preparations();
    scheduler_operation();
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
  function test_upload_from_server(){
    global $SYSCONF_DIR;
    $auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    /** upload a file to Software Repository */
    $out = "";
    $pos = 0;
    $command = "cp2foss $auth ./test_cp2foss.php";
    $last = exec("$command 2>&1", $out, $rtn);
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
    $agent_status = check_agent_status("ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);

    /** upload a dir to Software Repository */
    $out = "";
    $pos = 0;
    $command = "cp2foss $auth ./";
    $last = exec("$command 2>&1", $out, $rtn);
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
    $agent_status = check_agent_status("ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);

    /**  upload a dir to one specified path */
    $out = "";
    $pos = 0;
    $upload_path = "upload_path";
    $command = "cp2foss $auth ./ -f $upload_path -d upload_des -q all -v";
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

    /** cp2foss --user USER --password PASSWORD -q all -A -f test/exclude -n 'test exclue dir'  \ 
      -d 'test des exclude dir' -X .svn -X ./ -v */
    $out = "";
    $pos = 0;
    $command = "cp2foss $auth -q all -A -f test/exclude -n 'test exclue dir'  -d 'test des exclude dir' -X .svn ./ -v";
    $last = exec("$command 2>&1", $out, $rtn);
    sleep(10);
    // print_r($out);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[23]) {
      $upload_id = get_upload_id($out[23]);
    } else $this->assertFalse(TRUE);
    $agent_status = 0;
    $agent_status = check_agent_status("ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);
  }

  /**
   * \brief upload from url
   */
  function test_upload_from_url(){
    global $SYSCONF_DIR;
    $auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    /** upload a file to Software Repository */
    $out = "";
    $pos = 0;
    $command = "cp2foss $auth http://www.fossology.org/rpms/fedora/10/SRPMS/fossology-1.1.0-1.fc10.src.rpm -d 'fossology des' -f 'fossology path' -n 'test package'";
    $last = exec("$command 2>&1", $out, $rtn);
    // print_r($out);
    sleep(110);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[5]) {
      $upload_id = get_upload_id($out[5]);
    } else $this->assertFalse(TRUE);
    $agent_status = 0;
    $agent_status = check_agent_status("ununpack", $upload_id);
    $this->assertEquals(1, $agent_status);
  }

  /**
   * \brief list agents and help msg, etc
   */
  function test_list_agent_and_others(){
    global $SYSCONF_DIR;
    $auth = "--user fossy --password fossy -c $SYSCONF_DIR";
    /** help */
    $command = "cp2foss -h";
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    $this->assertEquals(54, $output_msg_count);
    // print_r($out);
    /** list agents */
    $out = "";
    $pos = 0;
    $command = "cp2foss $auth -Q";
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    $this->assertEquals(8, $output_msg_count);
    /** uplaod NULL */
    $out = "";
    $pos = 0;
    $command = "cp2foss $auth ";
    $last = exec("$command 2>&1", $out, $rtn);
    // print_r($out);
    $output_msg = "FATAL: you want to upload ''.";
    $this->assertEquals($output_msg, $out[0]);
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    stop_scheduler(); 
    drop_db();
    print "End up functional test for cp2foss \n";
  }

}

?>
