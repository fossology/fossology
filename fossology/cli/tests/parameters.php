<?php

/**
 * Parameters, test -h, -p, -n, -a input parameters to cp2foss.
 * 
 * NOTE: test version of cp2foss should be installed to /usr/local/bin as
 * cp2foss.test.
 * 
 * @version "$Id: $"
*/

class TestCLInputCP2foss extends UnitTestCase {
  
  function Testhnpa(){
    $help = exec('/usr/local/bin/cp2foss.test -h', $output, $retval);
    //print_r($output);
    $this->assertPattern('/Usage: cp2foss/', $output[1]);
    $output = array();
    $error = exec('/usr/local/bin/cp2foss.test -n foo -a /bar/baz -d "a comment"', $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -p /', $output[1]);
    $output = array();
    $error = exec('/usr/local/bin/cp2foss.test -p foo -a /bar/baz -d "a comment"', $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -n /', $output[1]);
    $output = array();
    $error = exec('/usr/local/bin/cp2foss.test -p baz -n foo -d "a comment"', $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -a /', $output[1]);
  }
  
  function MissingDashP(){
    // Note you must have a valid archive 
    $error = exec('/usr/local/bin/cp2foss.test -n foo -a /bar/baz -d "a comment"', $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -p /', $output[1]);
  }
  
  function MissingDashN(){
    // Note you must have a valid archive 
    $error = exec('/usr/local/bin/cp2foss.test -p foo -a /bar/baz -d "a comment"', $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -n /', $output[1]);
  }
  
  function MissingDasha(){
    // Note you must have a valid archive 
    $error = exec('/usr/local/bin/cp2foss.test -p baz -n foo -d "a comment"', $output, $retval);
    print_r($output);
    $this->assertPattern('/ERROR, -a /', $output[1]);
  }
}


?>