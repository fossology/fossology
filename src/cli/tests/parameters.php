<?php
/*
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Parameters, test -h, -p, -n, -a input parameters to cp2foss.
 *
 * NOTE: test version of cp2foss should be installed to /usr/local/bin as
 * test.cp2foss
 *
 * @version "$Id: $"
 */

class TestCLInputCP2foss extends UnitTestCase
{

  public $command = '/usr/local/bin/test.cp2foss';

  function Testhnpa()
  {

    $help = exec("$this->command -h", $output, $retval);
    //print_r($output);
    $this->assertPattern('/Usage: cp2foss/', $output[0]);
    $output = array();
    $error = exec("$this->command -n foo -a /bar/baz -d 'a comment'", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -p /', $output[0]);
    $output = array();
    $error = exec("$this->command -p foo -a /bar/baz -d \"a comment\"", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -n /', $output[0]);
    $output = array();
    $error = exec("$this->command -p baz -n foo -d 'a comment'", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -a /', $output[0]);
  }

  function TestMissingDashP()
  {

    // Note you must have a valid archive
    $error = exec("$this->command -n foo -a /tmp/zlib.tar.bz2 -d 'a comment'", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -p /', $output[0]);
  }

  function TestMissingDashN()
  {

    // Note you must have a valid archive
    $error = exec("$this->command -p foo -a /tmp/zlib.tar.bz2 -d 'a comment'", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -n /', $output[0]);
  }

  function TestMissingDasha()
  {
    // Note you must have a valid archive
    $error = exec("$this->command -p baz -n foo -d 'a comment'", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -a /', $output[0]);
  }
}

