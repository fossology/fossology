<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_pkg.php
 * \brief unit tests for common-pkg.php
 */

use Fossology\Lib\Db\ModernDbManager;
use Fossology\Lib\Test\TestPgDb;

require_once(dirname(__FILE__) . '/../common-pkg.php');
require_once(dirname(__FILE__) . '/../common-db.php');

/**
 * \class test_common_pkg
 */
class test_common_pkg extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;

  /** @var ModernDbManager */
  private $dbManager;

  /* initialization */
  protected function setUp() : void
  {

    $this->testDb = new TestPgDb("fosslibtest");
    $tables = array('mimetype');
    $this->testDb->createPlainTables($tables);
    $sequences = array('mimetype_mimetype_pk_seq');
    $this->testDb->createSequences($sequences);
    $this->testDb->createConstraints(['dirmodemask', 'mimetype_pk']);
    $this->testDb->alterTables($tables);
    $this->dbManager = $this->testDb->getDbManager();
  }

  /**
   * \brief test for GetPkgMimetypes()
   */
  function test_GetPkgMimetypes()
  {
    print "test function GetPkgMimetypes()\n";

    #prepare database testdata
    $mimeType = "application/x-rpm";
    /** delete test data pre testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.mimetype");
    /** insert on record */
    $sql = "INSERT INTO mimetype(mimetype_pk, mimetype_name) VALUES(10000, '$mimeType');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "insert.mimetype");

    #begin test GetPkgMimetypes()
    $sql = "select * from mimetype where
             mimetype_name='application/x-rpm'";
    $row = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "select.mimetype");
    $expected = $row['mimetype_pk'];

    $result = GetPkgMimetypes();
    $this->assertContains($expected, $result);

    /** delete test data post testing */
    $sql = "DELETE FROM mimetype where mimetype_name in ('$mimeType');";
    $result = $this->dbManager->getSingleRow($sql, [], __METHOD__ . "delete.mimetype");
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
