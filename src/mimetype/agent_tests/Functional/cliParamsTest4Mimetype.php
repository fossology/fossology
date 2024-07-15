<?php
/*
 SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2018 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @dir
 * @brief Functional tests for mimetype
 * @file
 * @brief Test the mimetype agent through command line.
 * @group mimetype agent
 */

use Fossology\Lib\Db\ModernDbManager;
use Fossology\Lib\Test\TestPgDb;
use Fossology\Lib\Test\TestInstaller;

/**
 * @class cliParamsTest4Mimetype
 * @brief Test mimetype agent from cli
 */
class cliParamsTest4Mimetype extends \PHPUnit\Framework\TestCase {

  public $EXE_PATH = "";
  public $cwd;
  public $DB_CONF = "";

  /** @var TestPgDb */
  private $testDb;

  /** @var ModernDbManager */
  private $dbManager;

  /** @var TestInstaller */
  private $testInstaller;

  /**
   * @biref Initialization
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void {
    global $EXE_PATH;
    global $cwd;
    global $DB_CONF;

    $cwd = dirname(__DIR__, 4).'/build/src/mimetype/agent_tests';
    $this->testDb = new TestPgDb("fossmimetypetest");
    $tables = array('mimetype', 'agent');
    $this->testDb->createPlainTables($tables);
    $this->testDb->createSequences(['mimetype_mimetype_pk_seq', 'agent_agent_pk_seq']);
    $this->testDb->createConstraints(['mimetype_pk', 'dirmodemask','agent_pkey']);
    $this->testDb->alterTables($tables);
    $this->dbManager = $this->testDb->getDbManager();
    $DB_CONF = $this->testDb->getFossSysConf();
    $this->testInstaller = new TestInstaller($DB_CONF);
    $this->testInstaller->init();
    $this->testInstaller->cpRepo();
    $this->testInstaller->install($cwd.'/..');

    $sql = "INSERT INTO public.mimetype (mimetype_pk, mimetype_name) VALUES (2, 'application/gzip'),"
         . " (3, 'application/x-gzip'), (4, 'application/x-compress'), (5, 'application/x-bzip'), (6, 'application/x-bzip2'),"
         . " (7, 'application/x-upx'), (8, 'application/pdf'), (9, 'application/x-pdf'), (10, 'application/x-zip'),"
         . " (11, 'application/zip'), (12, 'application/x-tar'), (13, 'application/x-gtar'), (14, 'application/x-cpio'),"
         . " (15, 'application/x-rar'), (16, 'application/x-cab'), (17, 'application/x-7z-compressed'),"
         . " (18, 'application/x-7z-w-compressed'), (19, 'application/x-rpm'), (20, 'application/x-archive'),"
         . " (21, 'application/x-debian-package'), (22, 'application/x-iso'), (23, 'application/x-iso9660-image'),"
         . " (24, 'application/x-fat'), (25, 'application/x-ntfs'), (26, 'application/x-ext2'), (27, 'application/x-ext3'),"
         . " (28, 'application/x-x86_boot'), (29, 'application/x-debian-source'), (30, 'application/x-xz'),"
         . " (31, 'application/jar'), (32, 'application/java-archive'), (33, 'application/x-dosexec'),"
         . " (34, 'text/plain');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "insert.mimetype");

    $EXE_PATH = $cwd . '/../agent/mimetype';
    $usage= "";
    if(file_exists($EXE_PATH))
    {
      $usage = 'Usage: '.$EXE_PATH.' [options] [file [file [...]]';
    }
    else
    {
      $this->assertFileExists($EXE_PATH,
      $message = 'FATAL: cannot find executable file, stop testing\n');
    }
    // run it
    $EXE_PATH .= " -C -c $DB_CONF";
    $last = exec("$EXE_PATH -h 2>&1", $out, $rtn);
    $this->assertEquals($usage, $out[0]); // check if executable file mimetype is exited
  }

  /**
   * @brief Test mimetype name is not in table mimetype
   * @test
   * -# Pass a file with mimetype \b not in database to the agent
   * -# Check if agent identifies correct mimetype
   * -# Pass a file with mimetype in database to the agent
   * -# Check if agent identifies correct mimetype
   */
  function testMimetypeNotInDB(){
    global $EXE_PATH;

    $mimeType1 = "application/x-msi";
    /* delete test data pre testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType1');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.mimetype");

    /* the file is one executable file */
    // HACK: Hot fix to use different binary
    $filePath = dirname(__DIR__, 4).'/build/src/ununpack/agent_tests/testdata/test.msi';
    $command = "$EXE_PATH $filePath";
    exec($command, $out, $rtn);
    $this->assertStringStartsWith($mimeType1, $out[0]);

    /* the file is one text file */
    $filePath = dirname(__DIR__, 2)."/mimetype.conf";
    $command = "$EXE_PATH $filePath";
    $out = "";
    exec($command, $out, $rtn);
    $mimeType2 = "text/plain";
    $this->assertStringStartsWith($mimeType2, $out[0]);
    /* delete test data post testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType1');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.mimetype");
  }


  /**
   * @brief The mimetype name is in table mimetype
   * @test
   * -# Pass a file with known mimetype to the agent
   * -# Check if agent return correct mimetype and id
   */
  function testMimetypeInDB(){
    global $EXE_PATH;

    $mimeType = "text/plain";
    /* delete test data pre testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.mimetype");
    /* insert on record */
    $sql = "INSERT INTO mimetype(mimetype_pk, mimetype_name) VALUES(10000, '$mimeType');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "insert.mimetype");
    /* the file is one c source file */
    $filePath = dirname(__DIR__)."/CMakeLists.txt";
    $command = "$EXE_PATH $filePath";
    exec($command, $out, $rtn);
    $expected_string = "text/plain : mimetype_pk=10000";
    $this->assertStringStartsWith($expected_string, $out[0]);

    /* delete test data post testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.mimetype");
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() : void {
    global $cwd;
    if (!is_callable('cwdect')) {
      return;
    }
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
    $this->testInstaller->uninstall($cwd.'/..');
  }
}


