<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Test\TestPgDb;

require_once(__DIR__ . '/../../../vendor/autoload.php');
require_once(__DIR__ . '/../common-container.php');
require_once(__DIR__ . '/../common-db.php');

class test_libschema extends \PHPUnit\Framework\TestCase
{
  private $testDb;
  private $dbManager;
  private $libschema;
  private $schema;
  private $previousSysConf;
  private $previousPgConn;

  protected function setUp(): void
  {
    $this->previousSysConf = $GLOBALS['SysConf'] ?? null;
    $this->previousPgConn = $GLOBALS['PG_CONN'] ?? null;
    if (!is_callable('pg_connect')) {
      $this->markTestSkipped('php-pgsql is required');
    }

    // TestPgDb drops tables in an existing database of the requested name.
    // Refuse a collision before handing it a disposable name.
    $dbName = 'fossschema' . bin2hex(random_bytes(8));
    $admin = pg_connect(
      'host=localhost dbname=fossology user=fossy password=fossy',
      PGSQL_CONNECT_FORCE_NEW
    );
    $this->assertNotFalse($admin);
    $result = pg_query_params(
      $admin,
      'SELECT 1 FROM pg_database WHERE datname = $1',
      array($dbName)
    );
    $this->assertNotFalse($result);
    $this->assertSame(0, pg_num_rows($result), 'Refusing to reuse an existing database');
    pg_free_result($result);
    pg_close($admin);

    $GLOBALS['PG_CONN'] = false;
    $this->testDb = new TestPgDb($dbName);
    $this->dbManager = $this->testDb->getDbManager();
    $this->assertSame($dbName, pg_dbname($GLOBALS['PG_CONN']));
    $GLOBALS['SysConf']['DBCONF']['dbname'] = pg_dbname($GLOBALS['PG_CONN']);
    require_once(__DIR__ . '/../libschema.php');
    $this->libschema = new fo_libschema($this->dbManager);

    $Schema = array();
    require(__DIR__ . '/../../../www/ui/core-schema.dat');
    $this->schema = array(
      'INHERITS' => array('license_candidate' => 'license_ref'),
      'TABLE' => array(
        'license_candidate' => $Schema['TABLE']['license_candidate'],
        'schema_plain' => array('payload' => $this->column('schema_plain', 'payload', 'text'))
      )
    );
    $this->setSchema();
  }

  protected function tearDown(): void
  {
    if ($this->testDb !== null) {
      $this->testDb->fullDestruct();
    }
    $GLOBALS['PG_CONN'] = $this->previousPgConn;
    if ($this->previousSysConf === null) {
      unset($GLOBALS['SysConf']);
    } else {
      $GLOBALS['SysConf'] = $this->previousSysConf;
    }
  }

  private function column($table, $column, $type)
  {
    return array(
      'ADD' => "ALTER TABLE \"$table\" ADD COLUMN \"$column\" $type",
      'ALTER' => "ALTER TABLE \"$table\" ALTER COLUMN \"$column\" DROP NOT NULL",
      'DESC' => ''
    );
  }

  private function setSchema()
  {
    $property = new ReflectionProperty(fo_libschema::class, 'schema');
    $property->setAccessible(true);
    $property->setValue($this->libschema, $this->schema);
  }

  private function queryRows($sql)
  {
    $result = pg_query($GLOBALS['PG_CONN'], $sql);
    $this->assertNotFalse($result, pg_last_error($GLOBALS['PG_CONN']));
    $rows = pg_fetch_all($result);
    pg_free_result($result);
    return $rows ?: array();
  }

  private function attributes($table)
  {
    return $this->queryRows("SELECT a.attname, a.attnum, a.atttypid::regtype::text AS type,
        a.attnotnull, a.attinhcount, a.attisdropped,
        pg_get_expr(d.adbin, d.adrelid) AS default_value
      FROM pg_attribute a
      LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
      WHERE a.attrelid = '$table'::regclass AND a.attnum > 0
      ORDER BY a.attnum");
  }

  private function droppedCount($table)
  {
    return count(array_filter($this->attributes($table), function ($row) {
      return $row['attisdropped'] === 't';
    }));
  }

  private function initializeSchema()
  {
    $this->dbManager->queryOnce(
      'CREATE TABLE license_ref (rf_pk bigint, rf_shortname text)'
    );
    $this->libschema->getCurrSchema();
    $this->libschema->applyTables();
    $this->libschema->applyInheritedRelations();
    $this->libschema->getCurrSchema();
    $this->libschema->applyTables(true);
  }

  public function testInheritedLocalColumnsRemainStableAcrossUpdates()
  {
    $this->initializeSchema();
    $this->dbManager->queryOnce(
      'CREATE TABLE schema_merged (rf_shortname text) INHERITS (license_ref)'
    );
    $current = $this->libschema->getCurrSchema();
    $this->assertArrayHasKey('license_candidate', $current['TABLE']);
    $this->assertSame(
      array_keys($this->schema['TABLE']['license_candidate']),
      array_keys($current['TABLE']['license_candidate'])
    );
    foreach ($this->schema['TABLE']['license_candidate'] as $name => $definition) {
      $this->assertSame($definition['ADD'], $current['TABLE']['license_candidate'][$name]['ADD']);
    }
    $this->assertArrayNotHasKey('rf_pk', $current['TABLE']['license_candidate']);
    $this->assertArrayNotHasKey('schema_merged', $current['TABLE']);
    $this->assertSame(
      array(array('attislocal' => 't', 'attinhcount' => '1')),
      $this->queryRows("SELECT attislocal, attinhcount FROM pg_attribute
        WHERE attrelid = 'schema_merged'::regclass AND attname = 'rf_shortname'")
    );
    $this->assertSame(0, $this->droppedCount('license_candidate'));
    $this->assertSame(
      array(array('default_value' => 'now()')),
      $this->queryRows("SELECT pg_get_expr(d.adbin, d.adrelid) AS default_value
        FROM pg_attrdef d JOIN pg_attribute a
          ON a.attrelid = d.adrelid AND a.attnum = d.adnum
        WHERE a.attrelid = 'license_candidate'::regclass
          AND a.attname = 'rf_creationdate'")
    );

    $this->dbManager->queryOnce(
      "INSERT INTO license_candidate (rf_pk, rf_shortname, group_fk, rf_user_fk_created)
       VALUES (10, 'candidate', 4, 7)"
    );
    $this->dbManager->queryOnce("INSERT INTO schema_plain (payload) VALUES ('plain data')");
    $this->dbManager->queryOnce(
      'ALTER TABLE license_candidate ADD CONSTRAINT license_candidate_test_pk PRIMARY KEY (rf_pk)'
    );
    $before = $this->attributes('license_candidate');
    $plainBefore = $this->attributes('schema_plain');

    for ($i = 0; $i < 3; $i++) {
      $this->libschema->getCurrSchema();
      $this->libschema->applyTables();
      $this->libschema->applyInheritedRelations();
      $this->libschema->getCurrSchema();
      $this->libschema->applyTables(true);
      $this->assertSame($before, $this->attributes('license_candidate'));
      $this->assertSame($plainBefore, $this->attributes('schema_plain'));
      $this->assertSame(0, $this->droppedCount('license_candidate'));
    }

    $this->assertSame(
      array(array('rf_pk' => '10', 'rf_shortname' => 'candidate',
        'group_fk' => '4', 'rf_user_fk_created' => '7')),
      $this->queryRows('SELECT rf_pk, rf_shortname, group_fk, rf_user_fk_created
        FROM license_candidate WHERE rf_pk = 10')
    );
    $this->assertCount(1, $this->queryRows("SELECT conname FROM pg_constraint
      WHERE conrelid = 'license_candidate'::regclass
        AND conname = 'license_candidate_test_pk'"));

    $this->dbManager->queryOnce('ALTER TABLE license_candidate ADD COLUMN custom_note text');
    $this->dbManager->queryOnce(
      "UPDATE license_candidate SET custom_note = 'keep me' WHERE rf_pk = 10"
    );
    $current = $this->libschema->getCurrSchema();
    $this->assertArrayHasKey('custom_note', $current['TABLE']['license_candidate']);
    $this->libschema->dropColumnsFromTable(array('custom_note'), 'license_candidate');
    $this->assertSame(
      array(array('custom_note' => 'keep me')),
      $this->queryRows('SELECT custom_note FROM license_candidate WHERE rf_pk = 10')
    );

    $this->schema['TABLE']['license_candidate']['review_number'] =
      $this->column('license_candidate', 'review_number', 'int4');
    $this->schema['TABLE']['schema_plain']['status'] =
      $this->column('schema_plain', 'status', 'text');
    $this->setSchema();
    $this->libschema->getCurrSchema();
    $this->libschema->applyTables();
    $this->libschema->applyTables(true);
    $this->assertSame(0, $this->droppedCount('license_candidate'));
    $this->assertSame(
      'integer',
      $this->queryRows("SELECT atttypid::regtype::text AS type FROM pg_attribute
        WHERE attrelid = 'license_candidate'::regclass AND attname = 'review_number'")[0]['type']
    );
    $this->assertCount(1, $this->queryRows("SELECT attname FROM pg_attribute
      WHERE attrelid = 'schema_plain'::regclass AND attname = 'status'"));
    $this->assertSame(
      array(array('payload' => 'plain data')),
      $this->queryRows('SELECT payload FROM schema_plain')
    );
    $this->assertSame(
      array(array('group_fk' => '4', 'rf_user_fk_created' => '7')),
      $this->queryRows('SELECT group_fk, rf_user_fk_created
        FROM license_candidate WHERE rf_pk = 10')
    );

    $this->schema['TABLE']['license_candidate']['group_fk']['ALTER'] =
      'ALTER TABLE "license_candidate" ALTER COLUMN "group_fk" SET NOT NULL';
    $this->setSchema();
    $this->libschema->getCurrSchema();
    $this->libschema->applyTables(true);
    $this->assertSame('t', $this->queryRows("SELECT attnotnull FROM pg_attribute
      WHERE attrelid = 'license_candidate'::regclass AND attname = 'group_fk'")[0]['attnotnull']);
    $this->assertSame(0, $this->droppedCount('license_candidate'));

    $this->dbManager->queryOnce(
      'UPDATE license_candidate SET review_number = 42 WHERE rf_pk = 10'
    );
    $this->schema['TABLE']['license_candidate']['review_number'] =
      $this->column('license_candidate', 'review_number', 'int8');
    $this->setSchema();
    $this->libschema->getCurrSchema();
    $this->libschema->applyTables(true);
    $this->assertSame(
      array(array('type' => 'bigint', 'review_number' => '42')),
      $this->queryRows("SELECT pg_typeof(review_number)::text AS type, review_number
        FROM license_candidate WHERE rf_pk = 10")
    );
    $this->assertSame(1, $this->droppedCount('license_candidate'));
    $this->libschema->getCurrSchema();
    $this->libschema->applyTables(true);
    $this->assertSame(1, $this->droppedCount('license_candidate'));
    $this->assertSame(
      array(
        array('attname' => 'rf_creationdate', 'default_value' => 'now()'),
        array('attname' => 'rf_user_fk_created', 'default_value' => '3')
      ),
      $this->queryRows("SELECT a.attname, pg_get_expr(d.adbin, d.adrelid) AS default_value
        FROM pg_attribute a JOIN pg_attrdef d
          ON d.adrelid = a.attrelid AND d.adnum = a.attnum
        WHERE a.attrelid = 'license_candidate'::regclass
          AND a.attname IN ('rf_creationdate', 'rf_user_fk_created')
        ORDER BY a.attname")
    );
    $this->assertCount(1, $this->queryRows("SELECT conname FROM pg_constraint
      WHERE conrelid = 'license_candidate'::regclass
        AND conname = 'license_candidate_test_pk'"));

    $this->schema['TABLE']['schema_plain']['payload'] =
      $this->column('schema_plain', 'payload', 'varchar(100)');
    $this->setSchema();
    $this->libschema->getCurrSchema();
    $this->libschema->applyTables();
    $this->assertSame(
      array(array('type' => 'character varying', 'payload' => 'plain data')),
      $this->queryRows('SELECT pg_typeof(payload)::text AS type, payload FROM schema_plain')
    );
    $this->assertSame(1, $this->droppedCount('schema_plain'));
    $this->libschema->getCurrSchema();
    $this->libschema->applyTables();
    $this->assertSame(1, $this->droppedCount('schema_plain'));
  }
}
