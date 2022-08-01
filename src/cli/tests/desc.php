<?php
/*
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Test the description parameter
 * Test cases are:
 *   1. with  a description using ""
 *   2. without the -d flag (allowed, should pass)
 *
 * @version "$Id: desc.php 626 2008-05-24 03:04:12Z rrando $"
 *
 */
class TestDashD extends UnitTestCase
{

  public $command = '/usr/local/bin/test.cp2foss';

  function TestDesc()
  {

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

