<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_db.php
 * \brief unit tests for common-db.php
 */

use Fossology\Lib\Test\TestPgDb;

require_once(dirname(dirname(__FILE__)) . '/common-container.php');
require_once(dirname(dirname(__FILE__)) . '/common-db.php');

class test_common_db extends \PHPUnit\Framework\TestCase
{
  /**
   * @var TestPgDb $testDb
   */
  private $testDb;

  protected function setUpDb()
  {
    if (!is_callable('pg_connect')) {
      $this->markTestSkipped("php-psql not found");
    }
    global $sys_conf;

    $this->testDb = new TestPgDb("sysconfTest");
    $sys_conf = $this->testDb->getFossSysConf();
    $this->testDb->getDbManager()->getDriver();
  }

  protected function tearDownDb()
  {
    if (!is_callable('pg_connect')) {
      return;
    }
    if ($this->testDb) {
        $this->testDb->fullDestruct();
        $this->testDb = null;
    }
  }

  /**
   * \brief test DB_ColExists respects custom database names
   * This proves that if a system is configured to use a custom database name
   * (e.g. external databases in Kubernetes/Helm), DB_ColExists correctly checks
   * the configured database instead of defaulting to 'fossology'.
   * Regression test for issue #2801.
   */
  public function testDBColExistsCustomDbName()
  {
    $this->setUpDb();
    global $SysConf, $sys_conf;

    // Simulate an external database name by setting DBCONF dbname
    // to the actual test database name created by TestPgDb.
    $SysConf['DBCONF']['dbname'] = $sys_conf['dbname'];

    // Create a dummy table and column to verify
    $this->testDb->getDbManager()->queryOnce("CREATE TABLE dummy_table (dummy_col int)");

    // Since DB_ColExists defaults to using $SysConf['DBCONF']['dbname'],
    // it should successfully find the column in the test database.
    $this->assertEquals(1, DB_ColExists('dummy_table', 'dummy_col'));

    // Verify it returns 0 for non-existent columns
    $this->assertEquals(0, DB_ColExists('dummy_table', 'non_existent_col'));

    // Check constraint existence logic
    $this->testDb->getDbManager()->queryOnce("ALTER TABLE dummy_table ADD CONSTRAINT dummy_const UNIQUE (dummy_col)");
    $this->assertTrue(DB_ConstraintExists('dummy_const'));

    $this->tearDownDb();
  }
}
