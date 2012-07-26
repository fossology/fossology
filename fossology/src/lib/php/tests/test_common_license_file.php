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
 * \file test_common_license_file.php
 * \brief unit tests for common-license-file.php
 */

require_once('../common-license-file.php');
require_once('../common-db.php');
require_once('../common-dir.php');
require_once('../common-ui.php');

/**
 * \class test_common_license_file
 */
class test_common_license_file extends PHPUnit_Framework_TestCase
{
  public $PG_CONN;
  public $upload_pk = 0;
  public $uploadtree_pk_parent = 0;
  public $uploadtree_pk_child = 0;
  public $pfile_parent = 0;
  public $pfile_child = 0;
  public $agent_pk = 0;
  public $uploadtree_tablename = 'uploadtree';

  public $DB_COMMAND =  "";
  public $DB_NAME =  "";
 
  /**
   * \brief initialization
   */
  protected function setUp() 
  {
    global $PG_CONN;
    global $upload_pk;
    global $uploadtree_pk_parent;
    global $uploadtree_pk_child;
    global $pfile_pk_parent;
    global $pfile_pk_child;
    global $agent_pk;

    global $DB_COMMAND;
    global $DB_NAME;
    #$sysconfig = './sysconfigDirTest';

    $DB_COMMAND  = "../../../testing/db/createTestDB.php";
    exec($DB_COMMAND, $dbout, $rc);
    preg_match("/(\d+)/", $dbout[0], $matches);
    $test_name = $matches[1];
    $db_conf = $dbout[0];
    $DB_NAME = "fosstest".$test_name;

    $PG_CONN = DBconnect($db_conf);


    /** preparation, add uploadtree, upload, pfile, license_file record */
    $upload_filename = "license_file_test"; /* upload file name */

    /** add a pfile record */
    $sql = "INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_size) VALUES".
      "('AF1DF2C4B32E4115DB5F272D9EFD0E674CF2A0BC', '2239AA7DAC291B6F8D0A56396B1B8530', '4560'), ".
      "('B1938B14B9A573D59ABCBD3BF0F9200CE6E79FB6', '55EFE7F9B9D106047718F1CE9173B869', '1892');";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
   
    /** add nomos agent record **/
    $sql = "INSERT INTO agent (agent_name) VALUES('nomos');";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** if didn't have license_ref record, add it */
    $sql = "SELECT rf_shortname FROM license_ref where rf_pk = 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) <= 0)
    {
      $sql = "INSERT INTO license_ref (rf_pk, rf_shortname, rf_text, marydone, rf_active, rf_text_updatable, rf_detector_type) VALUES(1, 'test_ref', 'test_ref', false, true, false, 1);";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
    }
    pg_free_result($result);


 
    /** get pfile id */
    $sql = "SELECT pfile_pk from pfile where pfile_sha1 IN ('AF1DF2C4B32E4115DB5F272D9EFD0E674CF2A0BC', 'B1938B14B9A573D59ABCBD3BF0F9200CE6E79FB6');";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result, 0);
    $pfile_pk_parent = $row['pfile_pk'];
    $row = pg_fetch_assoc($result, 1);
    $pfile_pk_child= $row['pfile_pk'];
    pg_free_result($result);

    /** add a license_file record */
    /** at first, get agent_id of 'nomos' */
    $sql = "SELECT agent_pk from agent where agent_name = 'nomos';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $agent_pk = $row['agent_pk'];
    pg_free_result($result);
    /** secondly add license_file record */
    $sql = "INSERT INTO license_file(rf_fk, agent_fk, pfile_fk) VALUES(1, $agent_pk, $pfile_pk_parent), (2, $agent_pk, $pfile_pk_child);";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** add an upload record */
    $sql = "INSERT INTO upload (upload_filename,upload_mode,upload_ts, pfile_fk, uploadtree_tablename) VALUES ('$upload_filename',40,now(), '$pfile_pk_parent', '$this->uploadtree_tablename');";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** get upload id */
    $sql = "SELECT upload_pk from upload where upload_filename = '$upload_filename';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $upload_pk= $row['upload_pk'];
    pg_free_result($result);

    /** add parent uploadtree record */
    $sql= "INSERT INTO uploadtree (parent, upload_fk, pfile_fk, lft, rgt, ufile_name) VALUES(NULL, $upload_pk, $pfile_pk_parent, 1, 2, 'license_test.file.parent');";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** get uploadtree id */
    $sql = "SELECT uploadtree_pk from uploadtree where pfile_fk = $pfile_pk_parent;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $uploadtree_pk_parent = $row['uploadtree_pk'];
    pg_free_result($result);

    /** add child uploadtree record */
    $sql= "INSERT INTO uploadtree (parent, upload_fk, pfile_fk, lft, rgt, ufile_name) VALUES($uploadtree_pk_parent, $upload_pk, $pfile_pk_child, 1, 2, 'license_test.file.child')";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** get uploadtree id */
    $sql = "SELECT uploadtree_pk from uploadtree where pfile_fk = $pfile_pk_child;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $uploadtree_pk_child = $row['uploadtree_pk'];
    pg_free_result($result);

    $this->uploadtree_tablename = GetUploadtreeTableName($upload_pk);
  }

  /**
   * \brief testing from GetFileLicenses
   * in this test case, this pfile have only one license 
   */
  function testGetFileLicenses()
  {
    print "Start unit test for common-license-file.php\n";
    print "test function GetFileLicenses()\n";
    global $PG_CONN;
    global $upload_pk;
    global $uploadtree_pk_parent;
    global $pfile_pk_parent;
    global $agent_pk;

    $license_array = GetFileLicenses($agent_pk, '' , $uploadtree_pk_parent, $this->uploadtree_tablename);
    /** the expected license value */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $license_value_expected = $row['rf_shortname'];
    pg_free_result($result);

    $this->assertEquals($license_value_expected, $license_array[1]);
  }

  /**
   * \brief testing from GetFileLicenses_tring
   * in this test case, this pfile have only one license
   */
  function testGetFileLicenses_string()
  {
    print "test function GetFileLicenses_tring()\n";
    global $PG_CONN;
    global $uploadtree_pk_parent;
    global $pfile_pk_parent;
    global $agent_pk;

    $license_string = GetFileLicenses_string($agent_pk, '', $uploadtree_pk_parent, $this->uploadtree_tablename);
    /** the expected license value */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $license_value_expected = $row['rf_shortname'];
    pg_free_result($result);

    $this->assertEquals($license_value_expected, $license_string);
  }

  /**
   * \brief testing for GetFilesWithLicense
   */
  function testGetFilesWithLicense()
  {
    print "test function GetFilesWithLicense()\n";
    global $PG_CONN;
    global $uploadtree_pk_parent;
    global $pfile_pk_parent;
    global $agent_pk;

    /** get a license short name */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $rf_shortname = $row['rf_shortname'];
    pg_free_result($result);

    $files_result = GetFilesWithLicense($agent_pk, $rf_shortname, $uploadtree_pk_parent, false, 0, "ALL", "", null, $this->uploadtree_tablename);
    $row = pg_fetch_assoc($files_result);
    $pfile_id_actual = $row['pfile_fk'];
    pg_free_result($files_result);
    $this->assertEquals($pfile_pk_parent, $pfile_id_actual);
  }

  /**
   * \brief testing for CountFilesWithLicense
   */
  function testCountFilesWithLicense()
  {
    print "test function CountFilesWithLicense()\n";
    global $PG_CONN;
    global $uploadtree_pk_parent;
    global $agent_pk;

    /** get a license short name */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $rf_shortname = $row['rf_shortname'];
    pg_free_result($result);

    $CountFiles = CountFilesWithLicense($agent_pk, $rf_shortname, $uploadtree_pk_parent, false, false, 0, $this->uploadtree_tablename);
    $this->assertEquals(1, $CountFiles['count']);
    $this->assertEquals(1, $CountFiles['unique']);
  }

  /**
   * \brief testing for Level1WithLicense
   */
  function testLevel1WithLicense()
  {
    print "test function Level1WithLicense()\n";
    global $PG_CONN;
    global $uploadtree_pk_parent;
    global $uploadtree_pk_child;
    global $pfile_pk_parent;
    global $pfile_pk_child;
    global $agent_pk;

    /** get a license short name */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    $rf_shortname = $row['rf_shortname'];
    pg_free_result($result);

    $file_name = Level1WithLicense($agent_pk, $rf_shortname, $uploadtree_pk_parent, false, $this->uploadtree_tablename);
print_r ($file_name);
    $this->assertEquals("license_test.file.child", $file_name[$uploadtree_pk_child]);
    print "unit test for common-license-file.php end\n";
  }


  /**
   * \brief clean the env
   */
  protected function tearDown() {
    global $PG_CONN;
    global $pfile_pk_parent;
    global $pfile_pk_child;
    global $upload_pk;
    global $DB_COMMAND;
    global $DB_NAME;

    /** delte the uploadtree record */
    $sql = "DELETE FROM uploadtree where upload_fk = $upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** delte the license_file record */
    $sql = "DELETE FROM license_file where pfile_fk IN ($pfile_pk_parent, $pfile_pk_child);";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** delte the upload record */
    $sql = "DELETE FROM upload where upload_pk = $upload_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /** delte the pfile record */
    $sql = "DELETE FROM pfile where pfile_pk IN ($pfile_pk_parent, $pfile_pk_child);";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
   
    /** delete the agent record */
    $sql = "DELETE FROM agent where agent_name = 'nomos';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

 
    pg_close($PG_CONN);
    exec("$DB_COMMAND -d $DB_NAME");
    print "Ending unit test for common-license_file.php\n";
  }
}

?>
