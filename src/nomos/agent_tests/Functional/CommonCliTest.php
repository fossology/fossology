<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 *
 * @file
 * @brief Functional test cases for Nomos
 * @dir
 * @brief Functional test cases for Nomos
 */
use Fossology\Lib\Test\TestInstaller;
use Fossology\Lib\Test\TestPgDb;

/**
 *
 * @class CommonCliTest
 * @brief Tests for common CLI operations
 */
class CommonCliTest extends \PHPUnit\Framework\TestCase
{

  /**
   *
   * @var TestPgDb $testDb Test Database
   */
  protected $testDb;

  /**
   *
   * @var TestInstaller $testInstaller Test installer to setup test env
   */
  protected $testInstaller;

  /**
   *
   * @var string $agentDir Path to agent
   */
  protected $agentDir;

  /**
   * @brief Setup the test cases and initialize the objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("nomosfun" . time());
    $this->agentDir = dirname(dirname(__DIR__));
    $this->testdir = dirname(dirname(__DIR__)) .
      "/agent_tests/testdata/NomosTestfiles/";

    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->install($this->agentDir);

    $this->testDb->createSequences(array(
      'license_ref_rf_pk_seq'
    ), false);
    $this->testDb->createPlainTables(array(
      'agent',
      'license_ref'
    ), false);
    $this->testDb->alterTables(array(
      'license_ref'
    ), false);
  }

  /**
   * @brief Destruct the objects initialized during setUp()
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;
  }

  /**
   * @brief Run nomos using the arguments passed
   *
   * -# The function setups the test environment required for the agent to run.
   * -# Run the agent with the arguments passed and pass the files to the agent
   * @param string $args CLI arguments for the nomos agent
   * @param array $files File paths to scan by nomos
   * @return string[] Output and return code
   */
  protected function runNomos($args = "", $files = array())
  {
    $sysConf = $this->testDb->getFossSysConf();

    $confFile = $sysConf . "/fossology.conf";
    system("touch " . $confFile);
    $config = "[FOSSOLOGY]\ndepth = 0\npath = $sysConf/repo\n";
    file_put_contents($confFile, $config);

    $execDir = $this->agentDir . '/agent';
    system(
      "install -D $this->agentDir/VERSION $sysConf/mods-enabled/nomos/VERSION");

    foreach ($files as $file) {
      $args .= " " . escapeshellarg($file);
    }

    $pipeFd = popen("$execDir/nomos -c $sysConf $args", "r");
    $this->assertTrue($pipeFd !== false, 'running nomos failed');

    $output = "";
    while (($buffer = fgets($pipeFd, 4096)) !== false) {
      $output .= $buffer;
    }
    $retCode = pclose($pipeFd);

    unlink("$sysConf/mods-enabled/nomos/VERSION");
    // unlink("$sysConf/mods-enabled/nomos");
    // rmdir("$sysConf/mods-enabled");
    unlink($confFile);

    return array(
      $output,
      $retCode
    );
  }

  /**
   * @brief Test for nomos help message
   * @test
   * -# Call runNomos() with `-h` to get help message
   * -# Check the output for help message
   */
  public function testHelp()
  {
    $nomos = dirname(dirname(__DIR__)) . '/agent/nomos';
    list ($output,) = $this->runNomos($args = "-h"); // exec("$nomos -h 2>&1",
                                                     // $out, $rtn);
    $out = explode("\n", $output);
    $usage = "Usage: $nomos [options] [file [file [...]]";
    $this->assertEquals($usage, $out[0]);
  }
}
