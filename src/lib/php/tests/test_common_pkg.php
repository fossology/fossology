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
  /* initialization */
  protected function setUp() 
  {
    global $PG_CONN;
    print "Starting unit test for common-pkg.php\n";
    $sysconfig = './sysconfigDirTest';
    $PG_CONN = DBconnect($sysconfig);
  }

  /**
   * \brief test for GetPkgMimetypes()
   */
  function test_GetPkgMimetypes()
  {
    print "test function GetPkgMimetypes()\n";
    global $PG_CONN; 
    $sql = "select * from mimetype where
             mimetype_name='application/x-rpm'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $expected = $row['mimetype_pk'];
    pg_free_result($result);

    $result = GetPkgMimetypes();
    $this->assertContains($expected, $result);
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    global $PG_CONN;
    pg_close($PG_CONN);
    print "Ending unit test for common-pkg.php\n";
  }
}

?>
