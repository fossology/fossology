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

require_once (__DIR__ . "/../../../testing/db/createEmptyTestEnvironment.php");

/**
 * @class cliParamsTest4Mimetype
 * @brief Test mimetype agent from cli
 */
class cliParamsTest4Mimetype extends \PHPUnit\Framework\TestCase {

  public $EXE_PATH = "";
  public $PG_CONN;
  public $DB_COMMAND =  "";
  public $DB_NAME =  "";
  public $DB_CONF = "";

  /**
   * @biref Initialization
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void {
    global $EXE_PATH;
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;
    global $DB_CONF;

    $cwd = getcwd();
    list($test_name, $DB_CONF, $DB_NAME, $PG_CONN) = setupTestEnv($cwd, "mimetype");

    $sql = "CREATE TABLE mimetype (mimetype_pk SERIAL, mimetype_name text);";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
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
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);

    $EXE_PATH = '../../agent/mimetype';
    $usage= "";
    if(file_exists($EXE_PATH))
    {
      $usage = 'Usage: ../../agent/mimetype [options] [file [file [...]]';
    }
    else
    {
      $this->assertFileExists($EXE_PATH,
      $message = 'FATAL: cannot find executable file, stop testing\n');
    }
    // run it
    $EXE_PATH = $EXE_PATH." -C -c $DB_CONF";
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
    global $PG_CONN;

    $mimeType1 = "application/x-sharedlib";
    /* delete test data pre testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType1');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);

    /* the file is one executable file */
    $filePath = "../../agent/mimetype";
    $command = "$EXE_PATH $filePath";
    exec($command, $out, $rtn);
    $this->assertStringStartsWith($mimeType1, $out[0]);

    /* the file is one text file */
    $filePath = "../../mimetype.conf";
    $command = "$EXE_PATH $filePath";
    $out = "";
    exec($command, $out, $rtn);
    $mimeType2 = "text/plain";
    $this->assertStringStartsWith($mimeType2, $out[0]);
    /* delete test data post testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType1');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
  }


  /**
   * @brief The mimetype name is in table mimetype
   * @test
   * -# Pass a file with known mimetype to the agent
   * -# Check if agent return correct mimetype and id
   */
  function testMimetypeInDB(){
    global $EXE_PATH;
    global $PG_CONN;

    $mimeType = "text/x-makefile";
    /* delete test data pre testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
    /* insert on record */
    $sql = "INSERT INTO mimetype(mimetype_pk, mimetype_name) VALUES(10000, '$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
    /* the file is one c source file */
    $filePath = "./Makefile";
    $command = "$EXE_PATH $filePath";
    exec($command, $out, $rtn);
    $expected_string = "text/x-makefile : mimetype_pk=10000";
    $this->assertStringStartsWith($expected_string, $out[0]);

    /* delete test data post testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = pg_query($PG_CONN, $sql);
    pg_free_result($result);
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() : void {
    global $PG_CONN;
    global $DB_COMMAND;
    global $DB_NAME;
    global $DB_CONF;

    pg_close($PG_CONN);
    exec("$DB_COMMAND -d $DB_NAME");
    exec("rm -rf $DB_CONF");
  }
}


