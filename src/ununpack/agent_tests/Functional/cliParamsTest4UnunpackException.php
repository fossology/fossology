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
 * \brief test the ununpack agent cli parameters.
 *
 * @group ununpack
 */
require_once './utility.php';

use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;

class cliParamsTest4UnunpackExcption extends PHPUnit_Framework_TestCase
{
  private $agentDir;

  /** @var TestPgDb */
  private $testDb;
  /** @var TestInstaller */
  private $testInstaller;

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
    $this->testDb->alterTables(array(), true);
  }

  public function tearDown()
  {
    $this->testInstaller->uninstall($this->agentDir);
    $this->testInstaller->clear();
    $this->testInstaller->rmRepo();
    $this->testDb = null;
  }

  /* command is */
  public function testValidParam(){
    global $UNUNPACK_CMD;
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $fossology_testconfig = $this->testDb->getFossSysConf();

    $UNUNPACK_CMD = $this->agentDir . "/agent/ununpack";
    if (!empty($TEST_RESULT_PATH))
      exec("/bin/rm -rf $TEST_RESULT_PATH");
    $command = "$UNUNPACK_CMD -qCRs $TEST_DATA_PATH/523.iso -d $TEST_RESULT_PATH -c $fossology_testconfig > /dev/null 2>&1";
    $last = exec($command, $usageOut, $rtn);
    $this->assertNotEquals($rtn, 0);
    $this->assertFileNotExists("$TEST_RESULT_PATH/523.iso.dir/523SFP/QMFGOEM.TXT");
  }

  /* test null-file */
  public function testNullFile(){
    global $TEST_DATA_PATH;
    global $TEST_RESULT_PATH;
    $fossology_testconfig = $this->testDb->getFossSysConf();

    $UNUNPACK_CMD = $this->agentDir . "/agent/ununpack";
    if (!empty($TEST_RESULT_PATH))
      exec("/bin/rm -rf $TEST_RESULT_PATH");
    $command = "$UNUNPACK_CMD -qCR $TEST_DATA_PATH/null-file -d $TEST_RESULT_PATH -c $fossology_testconfig > /dev/null 2>&1";
    $last = exec($command, $usageOut, $rtn);
    $this->assertNotEquals($rtn, 0);
    $this->assertFileNotExists("$TEST_RESULT_PATH/null-file.dir/");
  }
}
