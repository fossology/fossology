<?php
/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \brief test the delagent agent thru command line.
 */
//require_once '/usr/share/php/PHPUnit/Framework.php';
require_once (__DIR__ . "/../../../testing/db/createEmptyTestEnvironment.php");

/**
 * \class ft_cliDelagentTest
 * \brief Functional test delagent agent from cli
 */
class ft_cliDelagentTest extends \PHPUnit\Framework\TestCase {

  public $EXE_PATH = "";
  public $PG_CONN;
  public $DB_COMMAND = "";
  public $DB_NAME = "";
  public $DB_CONF = "";

  /* initialization */
  protected function setUp() : void {
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
    $EXE_PATH .= " -c $DB_CONF";
    $last = exec("$EXE_PATH -h 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[0]); // check if executable file delagent is exited
    $this->assertEquals($usageL, $out[6]); // check if the option -L removed
  }
  /**
   * @brief test delagent -u
   * @test
   * -# Prepare testdb.
   * -# Get the Upload id and filename for a upload.
   * -# Call delagent cli with `-u` flag
   * -# Check if the upload id and filename matches.
   */
  function testDelagentu(){
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_NAME;
    global $DB_CONF;

    $expected = "";

    $db_array = parse_ini_file("$DB_CONF/Db.conf");
    $db_user = $db_array["user"];

    exec("gunzip -c ../testdata/testdb_all.gz | psql -U $db_user -d $DB_NAME >/dev/null");

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
   * @brief test delagent -f
   * @test
   * -# Load test db
   * -# Get a folder name
   * -# Call delagent cli with `-f` flag
   * -# Check if folder name matches
   */
  function testDelagentf(){
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_NAME;
    global $DB_CONF;

    $expected = "";

    $db_array = parse_ini_file("$DB_CONF/Db.conf");
    $db_user = $db_array["user"];

    exec("gunzip -c ../testdata/testdb_all.gz | psql -U $db_user -d $DB_NAME >/dev/null");

    $sql = "SELECT folder_pk,parent,name,description,upload_pk FROM folderlist ORDER BY name,parent,folder_pk;";
    $result = pg_query($PG_CONN, $sql);
    if (pg_num_rows($result) > 0){
      $row = pg_fetch_assoc($result);
      $expected = "     -- :: Contains: " . $row["name"];
    }
    pg_free_result($result);
    $command = "$EXE_PATH -f -n fossy -p fossy";
    exec($command, $out, $rtn);
    #print $expected . "\n";
    #print $out[1] . "\n";
    $this->assertStringStartsWith($expected, $out[2]);
  }

  /**
   * @brief test delagent -U 2
   * @test
   * -# Setup test db
   * -# Call delagent cli with `-U` flag to delete an upload
   * -# Check if the upload got deleted
   */
  function testDelagentUpload(){
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_NAME;
    global $DB_CONF;

    $expected = "The upload '2' is deleted by the user 'fossy'.";

    $db_array = parse_ini_file("$DB_CONF/Db.conf");
    $db_user = $db_array["user"];

    exec("gunzip -c ../testdata/testdb_all.gz | psql -U $db_user -d $DB_NAME >/dev/null");
    $sql = "UPDATE upload SET user_fk = 3;";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);

    $command = "$EXE_PATH -U 2 -n fossy -p fossy";
    exec($command, $out, $rtn);
    #print $expected . "\n";
    #print $out[1] . "\n";
    $sql = "SELECT upload_fk, uploadtree_pk FROM bucket_container, uploadtree WHERE uploadtree_fk = uploadtree_pk AND upload_fk = 2;";
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
  protected function tearDown() : void {
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;
    global $DB_CONF;

    pg_close($PG_CONN);
    exec("$DB_COMMAND -d $DB_NAME");
    exec("rm -rf $DB_CONF");
  }
}


