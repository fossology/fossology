<?php
/**
 * Test stub to see if this is how you run multiple tests
 *
 */
class TestCP2foss extends UnitTestCase {
  
  public $command = '/usr/local/bin/test.cp2foss';
  
  function TestDesc(){
    
    global $command;
    
    $output = array();
    $error = exec("$command -p baz -n foo -a /tmp/zip.tar.bz2 -d \"a comment\"", $output, $retval);
    //print_r($output);
    $this->assertPattern('/Working on /', $output[1]);
    $output = array();
    // No description specified
    $error = exec("$command -p foo -n Bar -a /tmp/zip.tar.bz2", $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -d /', $output[1]);
  }
}
?>