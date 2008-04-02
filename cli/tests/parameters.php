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

  public $command = '/usr/local/bin/test.cp2foss';
  
  function Testhnpa(){
    
    global $command;
    $help = exec('$command -h', $output, $retval);
    //print_r($output);
    $this->assertPattern('/Usage: cp2foss/', $output[1]);
    $output = array();
    $error = exec('$command -n foo -a /bar/baz -d "a comment"', $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -p /', $output[1]);
    $output = array();
    $error = exec('$command -p foo -a /bar/baz -d "a comment"', $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -n /', $output[1]);
    $output = array();
    $error = exec('$command -p baz -n foo -d "a comment"', $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -a /', $output[1]);
  }
  
  function MissingDashP(){
    
    global $command;
    // Note you must have a valid archive 
    $error = exec('$command -n foo -a /tmp/zlib.tar.bz2 -d "a comment"', $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -p /', $output[1]);
  }
  
  function MissingDashN(){
    
    global $command;
    // Note you must have a valid archive 
    $error = exec('$command -p foo -a /tmp/zlib.tar.bz2 -d "a comment"', $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -n /', $output[1]);
  }
  
  function MissingDasha(){
    
    global $command;
    // Note you must have a valid archive 
    $error = exec('$command -p baz -n foo -d "a comment"', $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -a /', $output[1]);
  }
}


?>