<?php
/**
 * Test stub to see if this is how you run multiple tests
 *
 */
class TestCP2foss extends UnitTestCase {
  

  function TestDesc(){
    
    $command = '/usr/local/bin/test.cp2foss';
    
    $output = array();
    $error = exec("$command -p baz -n foo -a /tmp/zlib.tar.bz2 -d \"a comment\"", $output, $retval);
    //print_r($output);
    $this->assertPattern('/Working on /', $output[0]);
    $output = array();
    // No description specified
    $error = exec("$command -p foo -n Bar -a /tmp/zlib.tar.bz2", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -d /', $output[0]);
  }
}
?>