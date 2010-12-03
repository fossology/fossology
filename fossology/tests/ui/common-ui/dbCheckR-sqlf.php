<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 * dbCheckR-sqlf
 * \brief  ensure dbCheckResult produces correct output on sql failure
 *
 */
require_once '/usr/share/php/PHPUnit/Framework.php';
require_once '../../../ui/common/common-ui.php';

global $GlobalReady;
$GlobalReady=TRUE;

global $PG_CONN;

class dbCheckRSqlFTest extends PHPUnit_Framework_TestCase
{
  public function testSqlFail()
  {
    global $PG_CONN;

    print "Starting SQLFail\n";

    $sql = "select * from users where user_name='floozy';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
  }
}
?>
