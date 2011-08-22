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
 * \brief test the delagent agent thu command line.
 */
require_once '/usr/share/php/PHPUnit/Framework.php';

/**
 * \class ft_cliDelagentTest - functioin test delagent agent from cli
 */
class ft_cliDelagentTest extends PHPUnit_Framework_TestCase {
   
  public $EXE_PATH = "";
  public $PG_CONN;
 
  /* initialization */
  protected function setUp() {
    print "Starting test functional delagent agent \n";
    global $EXE_PATH;
    global $PG_CONN;
    $EXE_PATH = '../../agent/delagent';
    $usage= ""; 
    
    if(file_exists($EXE_PATH))
    {
      $usage = 'Usage: ../../agent/delagent [options]';
    }
    else
    {
      $this->assertFileExists($EXE_PATH,
      $message = 'FATAL: cannot find executable file, stop testing\n');
    }
    // run it
    $last = exec("$EXE_PATH -h 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[1]); // check if executable file delagent is exited
    $PG_CONN = pg_connect("host=localhost port=5432 dbname=fossology user=fossy password=fossy")
               or die("Could not connect");
  }
  /**
   * \brief test delagent -u 
   */
  function testDelagentu(){
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
    $command = "$EXE_PATH -u";
    exec($command, $out, $rtn);
    $this->assertStringStartsWith($expected, $out[1]);
  }


  /**
   * \brief test delagent -f
   */
  function testDelagentf(){
    global $EXE_PATH;
    global $PG_CONN;
    $expected = "";

    $sql = "SELECT folder_pk,parent,name,description,upload_pk FROM folderlist ORDER BY name,parent,folder_pk;";
    $result = pg_query($PG_CONN, $sql);
    if (pg_num_rows($result) > 0){
      $row = pg_fetch_assoc($result);
      $expected = "      " . $row["folder_pk"] . " :: " . $row["name"] . " (" . $row["description"]. ")";
    }
    pg_free_result($result);
    $command = "$EXE_PATH -f";
    exec($command, $out, $rtn);
    print $expected;
    $this->assertStringStartsWith($expected, $out[1]);
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    global $PG_CONN;
    pg_close($PG_CONN);
    print "Ending test functional delagent agent \n";
  }
}

?>
