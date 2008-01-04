#!/usr/bin/php

<?php
/**
 * Test stub to see if this is how you run multiple tests
 *
 */
class TestCP2foss extends UnitTestCase {
  
  function TestDesc(){
    $output = array();
    $error = exec('/usr/local/bin/cp2foss -p baz -n foo -a ~/fedora/Fedora8-pkgs/zsh.tar.bz2 -d "a comment"', $output, $retval);
    //print_r($output);
    $this->assertPattern('/Working on /', $output[1]);
    $output = array();
    // No description specified
    $error = exec('/usr/local/bin/cp2foss -p foo -n Bar -a ~/fedora/Fedora8-pkgs/zsh.tar.bz2', $output, $retval);
    //print_r($output);
    $this->assertPattern('/ERROR, -d /', $output[1]);
  }
}

?>