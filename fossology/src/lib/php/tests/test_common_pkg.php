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
 * \file test_common_pkg.php
 * \brief unit tests for common-pkg.php
 */

require_once('../common-pkg.php');
require_once('../common-db.php');
require_once '/usr/share/php/PHPUnit/Framework.php';

/**
 * \class test_common_pkg
 */
class test_common_pkg extends PHPUnit_Framework_TestCase
{
  public $PG_CONN;
  public $DB_COMMAND =  "";
  public $DB_NAME =  "";

  /* initialization */
  protected function setUp() 
  {
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;
    print "Starting unit test for common-pkg.php\n";
    
    $DB_COMMAND  = "../../../testing/db/createTestDB.php";
    exec($DB_COMMAND, $dbout, $rc);
    preg_match("/(\d+)/", $dbout[0], $matches);
    $test_name = $matches[1];
    $db_conf = $dbout[0];
    $DB_NAME = "fosstest".$test_name;
    #$sysconfig = './sysconfigDirTest';
    $PG_CONN = DBconnect($db_conf);
  }

  /**
   * \brief test for GetPkgMimetypes()
   */
  function test_GetPkgMimetypes()
  {
    print "test function GetPkgMimetypes()\n";
    global $PG_CONN;
    
    #prepare database testdata
    $mimeType = "application/x-rpm";
    /** delete test data pre testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
    /** insert on record */
    $sql = "INSERT INTO mimetype(mimetype_pk, mimetype_name) VALUES(10000, '$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);  
 
    #begin test GetPkgMimetypes()
    $sql = "select * from mimetype where
             mimetype_name='application/x-rpm'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $expected = $row['mimetype_pk'];
    pg_free_result($result);

    $result = GetPkgMimetypes();
    $this->assertContains($expected, $result);

    /** delete test data post testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
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
    print "Ending unit test for common-pkg.php\n";
  }
}

?>
