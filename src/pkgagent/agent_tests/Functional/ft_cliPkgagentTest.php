<?php
/***************************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/

/**
 * \file ft_cliPkgagentTest.php
 * \brief function test the pkgagent cli
 *
 * Test cli parameter i and v and rpm file and no parameters.
 */

class ft_cliPkgagentTest extends PHPUnit_Framework_TestCase {

  public $agentDir;
  public $pkgagent;
  protected $testfile = '../testdata/fossology-1.2.0-1.el5.i386.rpm';

  function setUp() {
/*
    $AGENTDIR = NULL;
    // determine where the agents are installed
    $upStream = '/usr/local/share/fossology/php/pathinclude.php';
    $pkg = '/usr/share/fossology/php/pathinclude.php';

    if (file_exists($upStream)) {
      require $upStream;
      //print "agentdir is:$AGENTDIR\n";
      $this->agentDir = $AGENTDIR;
      $this->pkgagent = $this->agentDir . '/pkgagent';
    } else
    if (file_exists($pkg)) {
      require $pkg;
      //print "agentdir is:$AGENTDIR\n";
      $this->agentDir = $AGENTDIR;
      $this->pkgagent = $this->agentDir . '/pkgagent';
    } else {
      $this->assertFileExists($upStream, $message = 'FATAL: cannot find pathinclude.php file, stopping test\n');
    }
*/
    //print "agent:$this->agentDir\npkgagent:$this->pkgagent\n";
    $this->agentDir = '../../agent';
    $this->pkgagent = $this->agentDir .'/pkgagent';
    return;
  } // setUP

  function testHelp() {
    // pkgagent -h
    $last = exec("$this->pkgagent -h 2>&1", $usageOut, $rtn=NULL);
    //print "testHelp: last is:$last\nusageout is:\n";
    //print_r($usageOut) . "\n";
    // Check a couple of options for sanity
    $usage = "Usage: $this->pkgagent [options] [file [file [...]]";
    $dashI = '-i   :: initialize the database, then exit.';
    $this->assertEquals($usage, $usageOut[0]);
    $this->assertEquals($dashI, trim($usageOut[1]));
    return;
  }

  function testI() {
    // pkgagent -i
    $last = exec("$this->pkgagent -i 2>&1", $got, $rtn=NULL);

    if($rtn != 0){
      $this->fail("pkgagent FAILED!, return value is:$rtn\n");
    }else{
      $this->assertTrue(true);
    }
    if(!empty($got)) {
      $this->fail("pkgagent FAILED! output in -i test\n");
      print_r($got) . "\n";
    }
    return;
  }

  function testOneRPM()
  {
    // pkgagent rpmfile

    $expected = array(
        'OK');
    $last = exec("$this->pkgagent -C $this->testfile 2>&1", $got, $rtn=NULL);
    //print "testOneRpm: last is:$last\ngot is:\n";
    //print_r($got) . "\n";
    $this->assertEquals($expected[0],$got[0]);
    return;
  }
  
  function testOneRPMV()
  {
    // pkgagent -v rpmfile

    $last = exec("$this->pkgagent -C -v $this->testfile 2>&1", $got, $rtn=NULL);
    //print "testOneRpm: last is:$last\ngot is:\n";
    //print_r($got) . "\n";
    // check the output
    if(empty($got)){
      $this->fail("pkgagent FAILED!, no output for -v test, stopping test");
      exit(1);
    }
    // compare output to the standard
    /*look in the output for items that should be in the header
     * e.g.
     * Name:fossology
     * Arch:i386
     * License:GPLv2
     * Summary:FOSSology is a licenses exploration tool
     * Size:44
     * Name:fossology-1.2.0-1.el5.src.rpm
     * OK
     */
    $expected = array('Name:fossology',
                      'Arch:i386',
                      'License:GPLv2',
                      'Summary:FOSSology is a licenses exploration tool',
                      'Size:44',
                      'Name:fossology-1.2.0-1.el5.src.rpm',
                      'OK'
                     );
    $size = count($got);
    foreach($expected as $match) {
      if(FALSE === in_array($match, $got)){
        $this->fail("pkgagent FAILED! did not fine $match in output\n");
      }
    }
    $this->assertEquals('OK',$got[$size-1]);
    return;
  }
}
?>
