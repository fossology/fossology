<?php

/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * \brief test the mimetype agent thu command line.
 * @group mimetype agent 
 */

/**
 * \class cliParamsTest4Mimetype - test mimetype agent from cli
 */
class cliParamsTest4Mimetype extends PHPUnit_Framework_TestCase {
   
  public $EXE_PATH = "";
  public $PG_CONN;
  public $DB_COMMAND =  "";
  public $DB_NAME =  "";
 
  /* initialization */
  protected function setUp() {
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;

    $db_conf = "";

    $DB_COMMAND  = "../../../testing/db/createTestDB.php";

    exec($DB_COMMAND, $dbout, $rc);
    preg_match("/(\d+)/", $dbout[0], $matches);
    $test_name = $matches[1];
    $db_conf = $dbout[0];
    $DB_NAME = "fosstest".$test_name;
    $PG_CONN = pg_connect("host=localhost port=5432 dbname=$DB_NAME user=fossy password=fossy")
               or die("Could not connect");
    $EXE_PATH = '../../agent/mimetype';
    $usage= "";
    if(file_exists($EXE_PATH))
    {
      $usage = 'Usage: ../../agent/mimetype [options] [file [file [...]]';
    }
    else
    {
      $this->assertFileExists($EXE_PATH,
      $message = 'FATAL: cannot find executable file, stop testing\n');
    }
    // run it
    $last = exec("$EXE_PATH -h 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[1]); // check if executable file mimetype is exited
    $EXE_PATH = $EXE_PATH." -C -c $db_conf";
  }

  /**
   * \brief test mimetype name is not in table mimetype 
   */
  function testMimetypeNotInDB(){
    print "Starting test functional mimetype agent \n";
    global $EXE_PATH;
    global $PG_CONN;

    $mimeType1 = "application/x-executable";
    /** delete test data pre testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType1');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);

    /** the file is one executable file */
    $filePath = "../../agent/mimetype"; 
    $command = "$EXE_PATH $filePath";
    exec($command, $out, $rtn);
    $this->assertStringStartsWith($mimeType1, $out[0]);

    /** the file is one text file */
    $filePath = "../../mimetype.conf";
    $command = "$EXE_PATH $filePath";
    $out = "";
    exec($command, $out, $rtn);
    $mimeType2 = "text/plain";
    $this->assertStringStartsWith($mimeType2, $out[0]);
    /** delete test data post testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType1');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
  }


  /**
   * \brief the mimetype name is in table mimetype
   */
  function testMimetypeInDB(){
    global $EXE_PATH;
    global $PG_CONN;

    $mimeType = "text/x-pascal";
    /** delete test data pre testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
    /** insert on record */
    $sql = "INSERT INTO mimetype(mimetype_pk, mimetype_name) VALUES(10000, '$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
    /** the file is one c source file */
    $filePath = "../../agent/mimetype.c";
    $command = "$EXE_PATH $filePath";
    exec($command, $out, $rtn);
    $expected_string = "text/x-pascal : mimetype_pk=10000";
    $this->assertStringStartsWith($expected_string, $out[0]);
    
    /** delete test data post testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);

    print "ending test functional mimetype agent \n";
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;

    pg_close($PG_CONN);
    exec("$DB_COMMAND -d $DB_NAME");
  }
}

?>
