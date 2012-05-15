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

/**
 * \brief test cli fossjobs
 */

class test_fossjobs extends PHPUnit_Framework_TestCase {

  public $SYSCONF_DIR = "/usr/local/etc/fossology/";
  public $DB_NAME = "";

  /**
   * \brief create DB 
   */
  function create_db() {
    global $SYSCONF_DIR;
    global $DB_NAME;

    $DB_COMMAND  = "../../testing/db/createTestDB.php";
    exec($DB_COMMAND, $dbout, $rc);
    $this->assertEquals(0, $rc);
    preg_match("/(\d+)/", $dbout[0], $matches);
    $test_name = $matches[1];
    $DB_NAME = "fosstest".$test_name;
    $SYSCONF_DIR = $dbout[0];
    //print "DB_NAME is:$DB_NAME, $SYSCONF_DIR\n";
  }

  /**
   * \brief get upload id
   *
   * \param $upload_info - The string to search in.
   *
   * \return upload Id, false on failure.
   */
  function get_upload_id($upload_info) {
    $upload_id = 0;
    preg_match("/UploadPk is: '(\d+)'/", $upload_info, $matches);
    $upload_id = $matches[1];
    if (!$upload_id) return false;
    else return $upload_id;
  }

  /** 
   * \brief reschedule all rest of the agent which are not sheduled
   */
  function test_reschedule_all(){
    global $SYSCONF_DIR;
    //$this->create_db();
    print "Starting functional test for fossjobs \n";
    $out = "";
    /** upload one dir, not any agents except wget/unpack/adj2nest */
    $auth = "--user fossy --password fossy";
    $cp2foss_command = "cp2foss $auth ./ -f fossjobs -d 'fossjobs testing'";
    $last = exec("$cp2foss_command 2>&1", $out, $rtn);
    //print_r($out);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[4]) {
      $upload_id = $this->get_upload_id($out[4]);
    } else $this->assertFalse(TRUE);
    $out = "";
    /** reschedule all rest of agent */
    $command = "fossjobs $auth -U $upload_id -v";
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    $out = "";
    /** upload one file, schedule copyright except wget/unpack/adj2nest */
    $auth = "--user fossy --password fossy";
    $cp2foss_command = "cp2foss $auth ./test_fossjobs.php -f fossjobs -d 'fossjobs testing copyright' -q agent_copyright";
    $last = exec("$cp2foss_command 2>&1", $out, $rtn);
    //print_r($out);
    $upload_id = 0;
    /** get upload id that you just upload for testing */
    if ($out && $out[4]) {
      $upload_id = $this->get_upload_id($out[4]);
    } else $this->assertFalse(TRUE);
    $out = "";
    /** reschedule just nomos */
    $command = "fossjobs $auth -U $upload_id -v -A agent_nomos";
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);

  }

  /**
   * \brief list agents, list uploads, help msg
   */
  function test_list_agent_and_others(){
    /** help */
    $command = "fossjobs --help";
    $last = exec("$command 2>&1", $out, $rtn);
    $output_msg_count = count($out);
    //print_r($out);
    $this->assertEquals(15, $output_msg_count);
    $auth = "--user fossy --password fossy";
    /** list agents */
    $out = "";
    $pos = 0;
    $command = "fossjobs $auth -a ";
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    /** list uploads */
    $out = "";
    $pos = 0;
    $command = "fossjobs $auth -a ";
    $last = exec("$command 2>&1", $out, $rtn);
    //print_r($out);
    print "End up functional test for fossjobs \n";
  }

  /**
   * \brief drop db
   */
  protected function drop_db() {
    global $DB_NAME;
    $DB_COMMAND  = "../../testing/db/createTestDB.php -d $DB_NAME";
    print "DB_COMMAND is:$DB_COMMAND\n";
    exec($DB_COMMAND, $dbout, $rc);
    $this->assertEquals(0, $rc);
  }
}

?>
