<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \dir
 * \brief Function test the pkgagent
 * \file ft_cliPkgagentTest.php
 * \brief function test the pkgagent cli
 *
 * Test cli parameter i and v and rpm file and no parameters.
 */

require_once (__DIR__ . "/../../../testing/db/createEmptyTestEnvironment.php");

/**
 * @class ft_cliPkgagentTest
 * @brief Test cli parameter i and v and rpm file and no parameters.
 */
class ft_cliPkgagentTest extends \PHPUnit\Framework\TestCase {

  public $agentDir;
  public $pkgagent;
  protected $testfile = '../testdata/fossology-1.2.0-1.el5.i386.rpm';
  private $db_conf;

  /**
   * @brief Set up test environment
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  function setUp() : void {
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

    $cwd = getcwd();
    list($test_name, $this->db_conf, $DB_NAME, $PG_CONN) = setupTestEnv($cwd, "pkgagent");

    $this->agentDir = '../../agent';
    $this->pkgagent = $this->agentDir .'/pkgagent -c ' . $this->db_conf;
    return;
  } // setUP

  /**
   * @brief Test help message
   * @test
   * -# Call \c -h on pkgagent CLI
   * -# Check if help message was printed properly
   */
  function testHelp() {
    // pkgagent -h
    $rtn = NULL;
    $last = exec("$this->pkgagent -h 2>&1", $usageOut, $rtn);
    //print "testHelp: last is:$last\nusageout is:\n";
    //print_r($usageOut) . "\n";
    // Check a couple of options for sanity
    $usage = "Usage: $this->agentDir/pkgagent [options] [file [file [...]]";
    $dashI = '-i   :: initialize the database, then exit.';
    $this->assertEquals($usage, $usageOut[0]);
    $this->assertEquals($dashI, trim($usageOut[1]));
    return;
  }

  /**
   * @brief Test DB init flag
   * @test
   * -# Call \c -i on pkgagent CLI
   * -# Check if agent ran properly
   */
  function testI() {
    // pkgagent -i
    $rtn = NULL;
    $last = exec("$this->pkgagent -i 2>&1", $got, $rtn);

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

  /**
   * @brief Test CLI with single RPM
   * @test
   * -# Call CLI with single RPM file path
   * -# Check if agent parse the RPM file properly
   */
  function testOneRPM()
  {
    // pkgagent rpmfile

    $expected = array('Name:fossology',
                      'Arch:i386',
                      'License:GPLv2',
                      'Summary:FOSSology is a licenses exploration tool',
                      'OK'
                     );
    $rtn = NULL;
    $last = exec("$this->pkgagent -C $this->testfile 2>&1", $got, $rtn);
    //print "testOneRpm: last is:$last\ngot is:\n";
    //print_r($got) . "\n";
    //$this->assertEquals($expected[0],$got[0]);
    if(empty($got)){
      $this->fail("pkgagent FAILED!, no output for test, stopping test");
      exit(1);
    }
    $size = count($got);
    foreach($expected as $match) {
      if(FALSE === in_array($match, $got)){
        $this->fail("pkgagent FAILED! did not fine $match in output\n");
      }
    }
    $this->assertEquals('OK',$got[$size-1]);
    return;
  }

  /**
   * @brief Test CLI in verbose with one RPM
   * @test
   * -# Pass one RPM to CLI with \c -vv flag
   * -# Test if extra information is loaded
   */
  function testOneRPMV()
  {
    // pkgagent -v rpmfile
    $rtn = NULL;
    $last = exec("$this->pkgagent -C -vv $this->testfile 2>&1", $got, $rtn);
    //print "testOneRpm: last is:$last\ngot is:\n";
    //print_r($got) . "\n";
    // check the output
    if(empty($got)){
      $this->fail("pkgagent FAILED!, no output for -vv test, stopping test");
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
