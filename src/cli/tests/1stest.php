#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * 1stest - play with simpletest
*/

class TestCLInputCP2foss extends UnitTestCase
{

  function Testhelp()
  {
    $help = exec('/usr/local/bin/cp2foss -h', $output, $retval);
    //print_r($output);
    $this->assertPattern('/Usage: cp2foss \[-h\]/', $output[1]);
  }
}



