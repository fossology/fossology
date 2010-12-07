#!/usr/bin/php
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

/*
 * dbcrDriver
 * \brief driver program to run each test case and examine the output
 *
 * @version "$Id$"
 *
 * Created on Dec 6, 2010
 *
 */

require_once '/usr/share/php/PHPUnit/Framework.php';

global $GlobalReady;
$GlobalReady = TRUE;

class driverTest extends PHPUnit_Framework_TestCase
{

  public function testAll()
  {
    $sqlOut = array ();
    $db1Out = array ();
    $fileOut = array ();
    $sqlLast = exec('./dbBadSQL.php', $sqlOut, $srtn);
    $db1Last = exec('./pgConn1.php', $db1Out, $oneRtn);
    $dbFileLast = exec('./pgConnFile.php', $fileOut, $fileRtn);

    $sqlMatch = 0;
    $db1Last = 0;
    $dbFileLast = 0;
    $outList = array (
      $sqlOut,
      $db1Out,
      $fileOut
    );
    $sqlErr = 'ERROR:  column "user_name" does not exist';
    $noConn = 'FATAL: DB connection lost';
    $errList = array (
      $sqlErr,
      $noConn,
      $noConn
    );
    for($i=0; $i<3; $i++)
    {
      $outLines = implode(' ', $outList[$i]);
      $this->assertEquals(preg_match("/$errList[$i]/", $outLines), 1);
    }
  }
}
?>
