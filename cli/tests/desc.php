<?php
/***********************************************************
 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/
/**
 * Test the description parameter
 * Test cases are:
 *   1. with  a description using ""
 *   2. without the -d flag (allowed, should pass)
 *
 * @version "$Id: desc.php 626 2008-05-24 03:04:12Z rrando $"
 *
 */
class TestDashD extends UnitTestCase {

  public $command = '/usr/local/bin/test.cp2foss';

  function TestDesc(){

    $output = array();
    $error = exec("$this->command -p CP2fossTest -n foo -a /tmp/zlib.tar.bz2 -d \"a comment\"", $output, $retval);
    //print_r($output);
    $this->assertPattern('/Working on /', $output[0]);
    $output = array();
    // No description specified
    $error = exec("$this->command -p foo -n Bar -a /tmp/zlib.tar.bz2", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -d /', $output[0]);
  }
}
?>