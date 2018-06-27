<?php
/*
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
 */

/**
 * cliParams
 * @file
 * @brief Test the ununpack agent cli parameters. (Exceptions)
 *
 * @group ununpack
 */
require_once './utility.php';

use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;

/**
 * @class cliParamsTest4UnunpackExcption
 * @brief Test the ununpack agent cli parameters. (Exceptions)
 */
class cliParamsTest4UnunpackExcption extends \PHPUnit\Framework\TestCase
{
  /** @var string $agentDir
   * Location of agent directory
   */
  private $agentDir;

  /** @var TestPgDb $testDb
   * Test db
   */
  private $testDb;
  /** @var TestInstaller $testInstaller
   * TestInstaller object
   */
  private $testInstaller;

  /**
   * @brief Setup test repo and db
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  function setUp()
  {
    $this->testDb = new TestPgDb('ununpackExceptional');
    $this->agentDir = dirname(dirname(__DIR__))."/";

    $sysConf = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($sysConf);
    $this->testInstaller->init();
    $this->testInstaller->install($this->agentDir);

    $this->testDb->createSequences(array(), true);
    $this->testDb->createPlainTables(array(), true);
    $this->testDb->createInheritedTables(array());
    $this->testDb->alterTables(array(), true);
  }

  /**
   * @brief Teardown test repo and db
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  public function tearDown()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;
  }

  /**
   * @brief Pass an invalid flag to agent which should not run
   * @test
   * -# Call the ununpack agent CLI with an invalid flag
   * -# Check if agent did not return OK
   * -# Check if agent did not extract the package
   */
  public function testValidParam(){
    global $UNUNPACK_CMD;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $fossology_testconfig = $this->testDb->getFossSysConf();

    $UNUNPACK_CMD = $this->agentDir . "/agent/ununpack";
    if (!empty($TEST_RESULT_PATH))
      exec("/bin/rm -rf $TEST_RESULT_PATH");
    $command = "$UNUNPACK_CMD -qCRs $TEST_DATA_PATH/test.iso -d $TEST_RESULT_PATH -c $fossology_testconfig > /dev/null 2>&1";
    $last = exec($command, $usageOut, $rtn);
    $this->assertNotEquals($rtn, 0);
    $this->assertFileNotExists("$TEST_RESULT_PATH/test.iso.dir/test1.zip.tar.dir/test1.zip");
  }

  /**
   * @brief Passing null file to agent
   * @test
   * -# Pass a null file to the agent (0 size)
   * -# Check if agent did not try to extract the file
   */
  public function testNullFile(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $fossology_testconfig = $this->testDb->getFossSysConf();

    $UNUNPACK_CMD = $this->agentDir . "/agent/ununpack";
    if (!empty($TEST_RESULT_PATH))
      exec("/bin/rm -rf $TEST_RESULT_PATH");
    $command = "$UNUNPACK_CMD -qCR $TEST_DATA_PATH/null_file -d $TEST_RESULT_PATH -c $fossology_testconfig > /dev/null 2>&1";
    $last = exec($command, $usageOut, $rtn);
    $this->assertNotEquals($rtn, 0);
    $this->assertFileNotExists("$TEST_RESULT_PATH/null_file.dir/");
  }
}
