<?php
/*
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
 */

/**
 * \file test_common_cache.php
 * \brief unit tests for common-cache.php
 */

require_once(dirname(__FILE__) . '/../common-cache.php');
require_once(dirname(__FILE__) . '/../common-db.php');

/**
 * \class test_common_cache
 */
class test_common_cached extends PHPUnit_Framework_TestCase
{
  public $PG_CONN;
  public $upload_pk = 0;
  public $uploadtree_pk = 0;
  public $UserCacheStat = 0;

  /**
   * \brief initialization
   */
  protected function setUp() 
  {
    global $PG_CONN;
    $sysconfig = './sysconfigDirTest';
    if (!is_callable('pg_connect')) {
      $this->markTestSkipped("php-psql not found");
    }
    $PG_CONN = DBconnect($sysconfig);
  }

  /**
   * \brief preparation for testing ReportCachePut
   */
  function preparation4ReportCachePut()
  {
    global $PG_CONN;
    global $upload_pk;
    global $uploadtree_pk;

    /** preparation, add uploadtree, upload, pfile record */
    /** add an upload record */
    $sql = "INSERT INTO upload (upload_filename,upload_mode,upload_ts) VALUES ('cache_test',40,now());";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** get upload id */
    $sql = "SELECT upload_pk from upload where upload_filename = 'cache_test';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $upload_pk= $row['upload_pk'];
    pg_free_result($result);

    /** add an uploadtree record */
    $sql= "INSERT INTO uploadtree (upload_fk) VALUES($upload_pk)";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** get uploadtree id */
    $sql = "SELECT uploadtree_pk from uploadtree where upload_fk = $upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $uploadtree_pk= $row['uploadtree_pk'];
    pg_free_result($result);
  }

  /**
   * \brief test for ReportCachePut upload id is in $CacheKey
   */
  function testReportCachePut_upload_id_not_null()
  {
    print "Start unit test for common-cache.php\n";
    print "test function ReportCachePut()\n";
    global $PG_CONN;
    global $upload_pk;
    global $uploadtree_pk;

    $this->preparation4ReportCachePut();

    $CacheKey = "?mod=nomoslicense&upload=$upload_pk&item=$uploadtree_pk&show=detail";
    $CacheValue = "no data";
    ReportCachePut($CacheKey, $CacheValue);
    /** get report_cache_value to check */
    $sql = "SELECT report_cache_value from report_cache where report_cache_uploadfk = $upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $value = $row['report_cache_value'];
    pg_free_result($result);
    $this->assertEquals($CacheValue, $value);
    $this->resetEnv4ReportCachePut();
  }
  
  /**
   * \brief test for ReportCachePut upload id is not in $CacheKey
   */
  function testReportCachePut_upload_id_null()
  {
    print "test function ReportCachePut()\n";
    global $PG_CONN;
    global $upload_pk;
    global $uploadtree_pk;

    $this->preparation4ReportCachePut();

    $CacheKey = "?mod=nomoslicense&item=$uploadtree_pk&show=detail";
    $CacheValue = "no data";
    ReportCachePut($CacheKey, $CacheValue);
    /** get report_cache_value to check */
    $sql = "SELECT report_cache_value from report_cache where report_cache_uploadfk = $upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $value = $row['report_cache_value'];
    pg_free_result($result);
    $this->assertEquals($CacheValue, $value);
    $this->resetEnv4ReportCachePut();
  }

  /**
   * \brief test for ReportCacheGet 
   */
  function testReportCacheGet()
  {
    print "test function ReportCacheGet()\n";
    global $PG_CONN;
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
    global $PG_CONN;
    global $upload_pk;
    /** delete the test data */
    /** delete the report_cache record */
    $sql = "DELETE from report_cache where report_cache_uploadfk = $upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    /** delete the uploadtree record */
    $sql= "DELETE from uploadtree where upload_fk = $upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    /** delete the upload record */
    $sql = "DELETE from upload where upload_pk = $upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
  }

  /**
   * \brief clean the env
   */
  protected function tearDown() {
    global $PG_CONN;
    /** db close */
    if (is_callable('pg_close')) {
      pg_close($PG_CONN);
    }
  }

}

?>
