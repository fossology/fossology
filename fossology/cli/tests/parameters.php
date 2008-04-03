<?php

/**
 * Parameters, test -h, -p, -n, -a input parameters to cp2foss.
 *
 * NOTE: test version of cp2foss should be installed to /usr/local/bin as
 * test.cp2foss
 *
 * @version "$Id: $"
 */

class TestCLInputCP2foss extends UnitTestCase {

  function Testhnpa(){

    $command = '/usr/local/bin/test.cp2foss';

    $help = exec("$command -h", $output, $retval);
    //print_r($output);
    $this->assertPattern('/Usage: cp2foss/', $output[0]);
    $output = array();
    $error = exec("$command -n foo -a /bar/baz -d 'a comment'", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -p /', $output[0]);
    $output = array();
    $error = exec("$command -p foo -a /bar/baz -d \"a comment\"", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -n /', $output[0]);
    $output = array();
    $error = exec("$command -p baz -n foo -d 'a comment'", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -a /', $output[0]);
  }

  function MissingDashP(){

    $command = '/usr/local/bin/test.cp2foss';
    // Note you must have a valid archive
    $error = exec("$command -n foo -a /tmp/zlib.tar.bz2 -d 'a comment'", $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -p /', $output[0]);
  }

  function MissingDashN(){

    $command = '/usr/local/bin/test.cp2foss';
    // Note you must have a valid archive
    $error = exec("$command -p foo -a /tmp/zlib.tar.bz2 -d 'a comment'", $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -n /', $output[0]);
  }

  function MissingDasha(){

    $command = '/usr/local/bin/test.cp2foss';
    // Note you must have a valid archive
    $error = exec("$command -p baz -n foo -d 'a comment'", $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -a /', $output[0]);
  }
}


?>