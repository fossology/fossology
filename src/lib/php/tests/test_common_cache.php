<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_cache.php
 * \brief unit tests for common-cache.php
 */
use Fossology\Lib\Db\ModernDbManager;
use Fossology\Lib\Test\TestPgDb;

require_once(dirname(__FILE__, 2) . '/common-cache.php');
require_once(dirname(__FILE__, 2) . '/common-db.php');

/**
 * \class test_common_cache
 */
class test_common_cached extends \PHPUnit\Framework\TestCase
{
   /** @var TestPgDb */
   private $testDb;

    /** @var ModernDbManager */
  private $dbManager;

  public $upload_pk = 0;
  public $uploadtree_pk = 0;
  public $UserCacheStat = 0;

  /**
   * \brief initialization
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("fosslibtest");
    $tables = array('upload', 'uploadtree', 'report_cache');
    $this->testDb->createPlainTables($tables);
    $sequences = array('upload_upload_pk_seq', 'uploadtree_uploadtree_pk_seq', 'report_cache_report_cache_pk_seq');
    $this->testDb->createSequences($sequences);
    $this->testDb->createConstraints(['upload_pkey_idx', 'ufile_rel_pkey', 'report_cache_pkey', 'report_cache_report_cache_key_key']);
    $this->testDb->alterTables($tables);

    global $upload_pk;
    global $uploadtree_pk;
    global $UserCacheStat;

    $this->dbManager = $this->testDb->getDbManager();
  }

  /**
   * \brief preparation for testing ReportCachePut
   */
  function preparation4ReportCachePut()
  {
    global $upload_pk;
    global $uploadtree_pk;

    /** preparation, add uploadtree, upload, pfile record */
    /** add an upload record */
    $sql = "INSERT INTO upload (upload_filename,upload_mode,upload_ts) VALUES ('cache_test',40,now());";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "insert.upload");

    /** get upload id */
    $sql = "SELECT upload_pk from upload where upload_filename = 'cache_test';";
    $row = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "upload.select");
    $upload_pk= $row['upload_pk'];

    /** add an uploadtree record */
    $sql= "INSERT INTO uploadtree (upload_fk) VALUES($upload_pk)";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "insert.uploadtree");

    /** get uploadtree id */
    $sql = "SELECT uploadtree_pk from uploadtree where upload_fk = $upload_pk;";
    $row = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "uploadtree.select");
    $uploadtree_pk= $row['uploadtree_pk'];
  }

  /**
   * \brief test for ReportCachePut upload id is in $CacheKey
   */
  function testReportCachePut_upload_id_not_null()
  {
    print "Start unit test for common-cache.php\n";
    print "test function ReportCachePut()\n";
    global $upload_pk;
    global $uploadtree_pk;

    $this->preparation4ReportCachePut();

    $CacheKey = "?mod=nomoslicense&upload=$upload_pk&item=$uploadtree_pk&show=detail";
    $CacheValue = "no data";
    ReportCachePut($CacheKey, $CacheValue);
    /** get report_cache_value to check */
    $sql = "SELECT report_cache_value from report_cache where report_cache_uploadfk = $upload_pk;";
    $row = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "select.report_cache");
    $value = $row['report_cache_value'];
    $this->assertEquals($CacheValue, $value);
    $this->resetEnv4ReportCachePut();
  }

  /**
   * \brief test for ReportCachePut upload id is not in $CacheKey
   */
  function testReportCachePut_upload_id_null()
  {
    print "test function ReportCachePut()\n";
    global $upload_pk;
    global $uploadtree_pk;

    $this->preparation4ReportCachePut();

    $CacheKey = "?mod=nomoslicense&item=$uploadtree_pk&show=detail";
    $CacheValue = "no data";
    ReportCachePut($CacheKey, $CacheValue);
    /** get report_cache_value to check */
    $sql = "SELECT report_cache_value from report_cache where report_cache_uploadfk = $upload_pk;";
    $row = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "select.report_cache");
    $value = $row['report_cache_value'];
    $this->assertEquals($CacheValue, $value);
    $this->resetEnv4ReportCachePut();
  }

  /**
   * \brief test for ReportCacheGet
   */
  function testReportCacheGet()
  {
    print "test function ReportCacheGet()\n";
    global $upload_pk;
    global $uploadtree_pk;
    global $UserCacheStat;

    $this->preparation4ReportCachePut();

    /** put an Cache record */
    $CacheKey = "?mod=nomoslicense&upload=$upload_pk&item=$uploadtree_pk&show=detail";
    $CacheValue = "no data";
    ReportCachePut($CacheKey, $CacheValue);
    /** get the cache value thru CacheKey */
    $UserCacheStat = 2; /**<  Cache is off for this user */
    $value = ReportCacheGet($CacheKey);
    $this->assertEquals($CacheValue, $value);
    $this->resetEnv4ReportCachePut();
    print "unit test for common-cache.php end\n";
  }

  /**
   * \brief reset enviroment after testing ReportCachePut
   */
  function resetEnv4ReportCachePut()
  {
    global $upload_pk;
    /** delete the test data */
    /** delete the report_cache record */
    $sql = "DELETE from report_cache where report_cache_uploadfk = $upload_pk;";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.report_cache");
    /** delete the uploadtree record */
    $sql= "DELETE from uploadtree where upload_fk = $upload_pk;";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.uploadtree");
    /** delete the upload record */
    $sql = "DELETE from upload where upload_pk = $upload_pk;";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.upload");
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() : void
  {
    if (!is_callable('pg_connect')) {
      return;
    }
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
  }
}
