<?php

/*
 Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.

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
 * \brief test the delagent agent thu command line.
 */
//require_once '/usr/share/php/PHPUnit/Framework.php';
require_once (__DIR__ . "/../../../testing/db/createEmptyTestEnvironment.php");

/**
 * \class ft_cliDelagentTest - functioin test delagent agent from cli
 */
class ft_cliDelagentTest extends PHPUnit_Framework_TestCase {

  public $EXE_PATH = "";
  public $PG_CONN;
  public $DB_COMMAND = "";
  public $DB_NAME = "";
  public $DB_CONF = "";

  /* initialization */
  protected function setUp() {
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;
    global $DB_CONF;

    $cwd = getcwd();
    list($test_name, $DB_CONF, $DB_NAME, $PG_CONN) = setupTestEnv($cwd, "delagent", false);

    $EXE_PATH = '../../agent/delagent';
    $usage= "";
    $usageL = "";

    if(file_exists($EXE_PATH))
    {
      $usage = 'Usage: ../../agent/delagent [options]';
      $usageL = '  -f   :: List folder IDs.';
    }
    else
    {
      $this->assertFileExists($EXE_PATH,
      $message = 'FATAL: cannot find executable file, stop testing\n');
    }
    // run it
    $EXE_PATH = $EXE_PATH." -c $DB_CONF";
    $last = exec("$EXE_PATH -h 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[0]); // check if executable file delagent is exited
    $this->assertEquals($usageL, $out[6]); // check if the option -L removed
  }
  /**
   * \brief test delagent -u
   */
  function testDelagentu(){
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_NAME;

    $expected = "";

    exec("pg_restore -Ufossy -d $DB_NAME ../testdata/testdb_all.tar");

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
   * \brief test delagent -f
   */
  function testDelagentf(){
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_NAME;
    $expected = "";

    exec("pg_restore -Ufossy -d $DB_NAME ../testdata/testdb_all.tar");

    $sql = "SELECT folder_pk,parent,name,description,upload_pk FROM folderlist ORDER BY name,parent,folder_pk;";
    $result = pg_query($PG_CONN, $sql);
    if (pg_num_rows($result) > 0){
      $row = pg_fetch_assoc($result);
      $expected = "        -- :: Contains: " . $row["name"];
    }
    pg_free_result($result);
    $command = "$EXE_PATH -f -n fossy -p fossy";
    exec($command, $out, $rtn);
    #print $expected . "\n";
    #print $out[1] . "\n";
    $this->assertStringStartsWith($expected, $out[3]);
  }

  /**
   * \brief test delagent -U 85
   */
  function testDelagentUpload(){
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_NAME;
    $expected = "The upload '85' is deleted by the user 'fossy'.";

    exec("pg_restore -Ufossy -d $DB_NAME ../testdata/testdb_all.tar");
    $sql = "UPDATE upload SET user_fk = 2;";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);

    $command = "$EXE_PATH -U 85 -n fossy -p fossy";
    exec($command, $out, $rtn);
    #print $expected . "\n";
    #print $out[1] . "\n";
    $sql = "SELECT upload_fk, uploadtree_pk FROM bucket_container, uploadtree WHERE uploadtree_fk = uploadtree_pk AND upload_fk = 85;";
    $result = pg_query($PG_CONN, $sql);
    if (pg_num_rows($result) > 0){
      $this->assertFalse("bucket_container records not deleted!");
    }
    pg_free_result($result);

    $this->assertStringStartsWith($expected, $out[0]);
  }


  /**
   * \brief clean the env
   */
  protected function tearDown() {
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;
    global $DB_CONF;

    pg_close($PG_CONN);
    exec("$DB_COMMAND -d $DB_NAME");
    exec("rm -rf $DB_CONF");
  }
}

?>
