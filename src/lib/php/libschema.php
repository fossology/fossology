<?php
/*
 SPDX-FileCopyrightText: © 2008-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015, 2018 Siemens AG

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * @file
 * @brief Functions to bring database schema to a known state.
 *
 **/

require_once(__DIR__ . '/../../vendor/autoload.php');

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Db\Driver;
use Fossology\Lib\Db\Driver\Postgres;
use Fossology\Lib\Db\ModernDbManager;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

/**
 * @class fo_libschema
 * @brief Class to handle database schema
 */
class fo_libschema
{
  /**
   * @var bool $debug
   * Set true to enable debugging
   */
  public $debug = false;

  /**
   * @var DbManager $dbman
   * DB manager to use
   */
  private $dbman;

  /**
   * @var array $schema
   * Schema to be applied
   */
  private $schema = array();

  /**
   * @var array $currSchema
   * Current schema of the DB
   */
  private $currSchema = array();

  /**
   * Constructor for fo_libschema
   * @param DbManager $dbManager
   */
  function __construct(DbManager &$dbManager)
  {
    $this->dbman = $dbManager;
  }

  /**
   * Set the Driver for the DbManager
   * @param Driver $dbDriver
   */
  function setDriver(Driver &$dbDriver)
  {
    $this->dbman->setDriver($dbDriver);
  }


  /**
   * Apply or echo the SQL statement based on the debugging status.
   * @param string $sql  Statement to be applied
   * @param string $stmt Name of the statement (for caching)
   */
  function applyOrEchoOnce($sql, $stmt = '')
  {
    if ($this->debug) {
      print ("$sql\n");
    } else {
      $this->dbman->queryOnce($sql, $stmt);
    }
  }


  /**
   * @brief Make schema match $Filename. This is a single transaction.
   * @param string $filename Schema file written by schema-export.php
   * @param bool $debug Turn on debugging (echo sql as it is being executed)
   * @param string $catalog Optional database name
   * @param array[] $migrateColumns array('tablename'=>array('col1','col2'),...) of columns which should not be deleted
   * @return false=success, on error return string with error message.
   **/
  function applySchema($filename = NULL, $debug = false, $catalog = 'fossology', $migrateColumns = array())
  {
    global $PG_CONN;

    // first check to make sure we don't already have the plpgsql language installed
    $sql_statement = "select lanname from pg_language where lanname = 'plpgsql'";

    $result = pg_query($PG_CONN, $sql_statement);
    if (!$result) {
      throw new Exception("Could not check the database for plpgsql language");
    }

    $plpgsql_already_installed = false;
    if ( pg_fetch_row($result) ) {
      $plpgsql_already_installed = true;
    }

    // then create language plpgsql if not already created
    if ($plpgsql_already_installed == false) {
      $sql_statement = "CREATE LANGUAGE plpgsql";
      $result = pg_query($PG_CONN, $sql_statement);
      if (!$result) {
        throw new Exception("Could not create plpgsql language in the database");
      }
    }

    $sql_statement = "select extname from pg_extension where extname = 'uuid-ossp'";

    $result = pg_query($PG_CONN, $sql_statement);
    if (!$result) {
      throw new Exception("Could not check the database for uuid-ossp extension");
    }

    $uuid_already_installed = false;
    if ( pg_fetch_row($result) ) {
      $uuid_already_installed = true;
    }

    // then create extension uuid-ossp if not already created
    if ( $uuid_already_installed == false ) {
      $sql_statement = 'CREATE EXTENSION "uuid-ossp";';
      $result = pg_query($PG_CONN, $sql_statement);
      if (!$result) {
        throw new Exception("Could not create uuid-ossp extension in the database");
      }
    }

    $this->debug = $debug;
    if (!file_exists($filename)) {
      return "$filename does not exist.";
    }
    $Schema = array(); /* will be filled in next line */
    require($filename); /* this cause Fatal Error if the file does not exist. */
    $this->schema = $Schema;

    /* Very basic sanity check (so we don't delete everything!) */
    if ((count($this->schema['TABLE']) < 5) || (count($this->schema['SEQUENCE']) < 5)
        || (count($this->schema['INDEX']) < 5) || (count($this->schema['CONSTRAINT']) < 5)
    ) {
      return "Schema from '$filename' appears invalid.";
    }

    if (!$debug) {
      $result = $this->dbman->getSingleRow("show statement_timeout", array(), $stmt = __METHOD__ . '.getTimeout');
      $statementTimeout = $result['statement_timeout'];
      $this->dbman->queryOnce("SET statement_timeout = 0", $stmt = __METHOD__ . '.setTimeout');
    }

    $this->applyOrEchoOnce('BEGIN');
    $this->getCurrSchema();
    $errlev = error_reporting(E_ERROR | E_WARNING | E_PARSE);
    $this->dropViews($catalog);
    $this->dropConstraints();
    $this->applySequences();
    $this->applyTables();
    $this->applyInheritedRelations();
    $this->getCurrSchema(); /* New tables created, recheck */
    $this->applyTables(true);
    $this->updateSequences();
    $this->applyViews();
    $this->dropConstraints();
    /* Reload current since the CASCADE may have changed things */
    $this->getCurrSchema(); /* constraints and indexes are linked, recheck */
    $this->dropIndexes();
    $this->applyIndexes();
    $this->applyConstraints();
    error_reporting($errlev); /* return to previous error reporting level */
    $this->makeFunctions();
    $this->applyClusters();
    /* Reload current since CASCADE during migration may have changed things */
    $this->getCurrSchema();
    $this->dropViews($catalog);
    foreach ($this->currSchema['TABLE'] as $table => $columns) {
      $skipColumns = array_key_exists($table, $migrateColumns) ? $migrateColumns[$table] : array();
      $dropColumns = array_diff(array_keys($columns), $skipColumns);
      $this->dropColumnsFromTable($dropColumns, $table);
    }
    $this->applyOrEchoOnce('COMMIT');
    flush();
    ReportCachePurgeAll();
    if (!$debug) {
      $this->dbman->getSingleRow("SET statement_timeout = $statementTimeout", array(), $stmt = __METHOD__ . '.resetTimeout');
      print "DB schema has been updated for $catalog.\n";
    } else {
      print "These queries could update DB schema for $catalog.\n";
    }
    return false;
  }

  /**
   * @brief Add sequences to the database
   *
   * The function first checks if the sequence already exists or not.
   * The sequence is only created only if it does not exists.
   */
  function applySequences()
  {
    if (empty($this->schema['SEQUENCE'])) {
      return;
    }
    foreach ($this->schema['SEQUENCE'] as $name => $import) {
      if (empty($name)) {
        continue;
      }

      if (!array_key_exists('SEQUENCE', $this->currSchema)
        || !array_key_exists($name, $this->currSchema['SEQUENCE'])) {
        $createSql = is_string($import) ? $import : $import['CREATE'];
        $this->applyOrEchoOnce($createSql, $stmt = __METHOD__ . "." . $name . ".CREATE");
      }
    }
  }
  /**
   * @brief Add clusters
   *
   * The function first checks if the cluster already exists or not.
   * The cluster is only created only if it does not exists.
   */
  function applyClusters()
  {
    if (empty($this->schema['CLUSTER'])) {
      return;
    }
    foreach ($this->schema['CLUSTER'] as $name => $sql) {
      if (empty($name)) {
        continue;
      }

      if (!array_key_exists('CLUSTER', $this->currSchema)
        || !array_key_exists($name, $this->currSchema['CLUSTER'])) {
        $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . "." . $name . ".CREATE");
      }
    }
  }

  /**
   * @brief Add sequences
   *
   * The function executes the UPDATE statement of the sequence.
   *
   * @see applySequences()
   */
  function updateSequences()
  {
    if (empty($this->schema['SEQUENCE']) ||
        !(array_key_exists('SEQUENCE', $this->currSchema))) {
      return;
    }
    foreach ($this->schema['SEQUENCE'] as $name => $import) {
      if (empty($name)) {
        continue;
      }

      if (is_array($import) && array_key_exists('UPDATE', $import)) {
        $this->applyOrEchoOnce($import['UPDATE'], $stmt = __METHOD__ . "." . $name);
      }
    }
  }

  /**
   * @brief Add tables/columns (dependent on sequences for default values)
   *
   * The function creates new tables in the database. The function also drops
   * columns which are missing from schema and add new columns as well.
   */
  function applyTables($inherits=false)
  {
    if (empty($this->schema['TABLE'])) {
      return;
    }
    foreach ($this->schema['TABLE'] as $table => $columns) {
      if (empty($table) || $inherits^array_key_exists($table,$this->schema['INHERITS']) ) {
        continue;
      }
      $newTable = false;
      if (!DB_TableExists($table)) {
        $sql = "CREATE TABLE IF NOT EXISTS \"$table\" ()";
        $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . $table);
        $newTable = true;
      } elseif (!array_key_exists($table, $this->currSchema['TABLE'])) {
        $newTable = true;
      }
      foreach ($columns as $column => $modification) {
        if (!$newTable && !array_key_exists($column, $this->currSchema['TABLE'][$table])) {
          $colNewTable = true;
        } else {
          $colNewTable = $newTable;
        }
        if ($colNewTable ||
            $this->currSchema['TABLE'][$table][$column]['ADD'] != $modification['ADD']) {
          $rename = "";
          if (DB_ColExists($table, $column)) {
            /* The column exists, but it looks different!
             Solution: Delete the column! */
            $rename = $column . '_old';
            $sql = "ALTER TABLE \"$table\" RENAME COLUMN \"$column\" TO \"$rename\"";
            $this->applyOrEchoOnce($sql);
          }

          $sql = $modification['ADD'];
          if ($this->debug) {
            print "$sql\n";
          } else {
            // Add the new column which sets the default value
            $this->dbman->queryOnce($sql);
          }
          if (!empty($rename)) {
            /* copy over the old data */
            $this->applyOrEchoOnce($sql = "UPDATE \"$table\" SET \"$column\" = \"$rename\"");
            $this->applyOrEchoOnce($sql = "ALTER TABLE \"$table\" DROP COLUMN \"$rename\"");
          }
        }
        if ($colNewTable ||
            $this->currSchema['TABLE'][$table][$column]['ALTER'] != $modification['ALTER'] && isset($modification['ALTER'])) {
          $sql = $modification['ALTER'];
          if ($this->debug) {
            print "$sql\n";
          } else if (!empty ($sql)) {
            $this->dbman->queryOnce($sql);
          }
        }
        if ($colNewTable ||
            $this->currSchema['TABLE'][$table][$column]['DESC'] != $modification['DESC']) {
          $sql = empty($modification['DESC']) ? "COMMENT ON COLUMN \"$table\".\"$column\" IS ''" : $modification['DESC'];
          $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . "$table.$column.comment");
        }
      }
    }
  }

  /**
   * Add views (dependent on columns)
   */
  function applyViews()
  {
    if (empty($this->schema['VIEW'])) {
      return;
    }
    $newViews = !array_key_exists('VIEW', $this->currSchema);
    foreach ($this->schema['VIEW'] as $name => $sql) {
      if (empty($name) || (!$newViews &&
          $this->currSchema['VIEW'][$name] == $sql)) {
        continue;
      }
      if (!$newViews && !empty($this->currSchema['VIEW'][$name])) {
        $sqlDropView = "DROP VIEW IF EXISTS $name";
        $this->applyOrEchoOnce($sqlDropView);
      }
      $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . $name);
    }
  }

  /**
   * @brief Delete constraints
   *
   * Delete now, so they won't interfere with migrations.
   */
  function dropConstraints()
  {
    if (!array_key_exists('CONSTRAINT', $this->currSchema) || // Empty DB
        empty($this->currSchema['CONSTRAINT'])) {
      return;
    }
    foreach ($this->currSchema['CONSTRAINT'] as $name => $sql) {
      // skip if constraint name is empty or does not exist
      if (empty($name) || !array_key_exists($name, $this->schema['CONSTRAINT'])
          || ($this->schema['CONSTRAINT'][$name] == $sql)
          || !DB_ConstraintExists($name)) {
        continue;
      }

      /* Only process tables that I know about */
      $table = preg_replace("/^ALTER TABLE \"(.*)\" ADD CONSTRAINT.*/", '${1}', $sql);
      $TableFk = preg_replace("/^.*FOREIGN KEY .* REFERENCES \"(.*)\" \(.*/", '${1}', $sql);
      if ($TableFk == $sql) {
        $TableFk = $table;
      }
      /* If I don't know the primary or foreign table... */
      if (empty($this->schema['TABLE'][$table]) && empty($this->schema['TABLE'][$TableFk])) {
        continue;
      }
      $sql = "ALTER TABLE \"$table\" DROP CONSTRAINT \"$name\" CASCADE";
      $this->applyOrEchoOnce($sql);
    }
  }

  /**
   * Delete indexes
   */
  function dropIndexes()
  {
    if (!array_key_exists('INDEX', $this->currSchema) ||
        empty($this->currSchema['INDEX'])) {
      return;
    }
    foreach ($this->currSchema['INDEX'] as $table => $IndexInfo) {
      if (empty($table) || (empty($this->schema['TABLE'][$table]) && empty($this->schema['INHERITS'][$table]))) {
        continue;
      }
      foreach ($IndexInfo as $name => $sql) {
        if (empty($name) || $this->schema['INDEX'][$table][$name] == $sql) {
          continue;
        }
        $sql = "DROP INDEX \"$name\"";
        $this->applyOrEchoOnce($sql);
      }
    }
  }

  /**
   * Add indexes (dependent on columns)
   */
  function applyIndexes()
  {
    if (empty($this->schema['INDEX'])) {
      return;
    }
    foreach ($this->schema['INDEX'] as $table => $indexInfo) {
      if (empty($table)) {
        continue;
      }
      if (!array_key_exists($table, $this->schema["TABLE"]) && !array_key_exists($table, $this->schema['INHERITS'])) {
        echo "skipping orphan table: $table\n";
        continue;
      }
      $newIndexes = false;
      if (!array_key_exists('INDEX', $this->currSchema) ||
          !array_key_exists($table, $this->currSchema['INDEX'])) {
        $newIndexes = true;
      }
      foreach ($indexInfo as $name => $sql) {
        if (empty($name) || (!$newIndexes &&
            array_key_exists($name, $this->currSchema['INDEX'][$table]) &&
            $this->currSchema['INDEX'][$table][$name] == $sql)) {
          continue;
        }
        $this->applyOrEchoOnce($sql);
        $sql = "REINDEX INDEX \"$name\"";
        $this->applyOrEchoOnce($sql);
      }
    }
  }

  /**
   * Add constraints (dependent on columns, views, and indexes)
   */
  function applyConstraints()
  {
    $this->currSchema = $this->getCurrSchema(); /* constraints and indexes are linked, recheck */
    if (empty($this->schema['CONSTRAINT'])) {
      return;
    }
    /* Constraints must be added in the correct order! */
    $orderedConstraints = array('primary' => array(), 'unique' => array(), 'foreign' => array(), 'other' => array());
    foreach ($this->schema['CONSTRAINT'] as $Name => $sql) {
      $newConstraint = false;
      if (!array_key_exists('CONSTRAINT', $this->currSchema) ||
          !array_key_exists($Name, $this->currSchema['CONSTRAINT'])) {
        $newConstraint = true;
      }
      if (empty($Name) || (!$newConstraint &&
            $this->currSchema['CONSTRAINT'][$Name] == $sql)) {
        continue;
      }
      if (preg_match("/PRIMARY KEY/", $sql)) {
        $orderedConstraints['primary'][] = $sql;
      } elseif (preg_match("/UNIQUE/", $sql)) {
        $orderedConstraints['unique'][] = $sql;
      } elseif (preg_match("/FOREIGN KEY/", $sql)) {
        $orderedConstraints['foreign'][] = $sql;
      } else {
        $orderedConstraints['other'][] = $sql;
      }
    }
    foreach ($orderedConstraints as $type => $constraints) {
      foreach ($constraints as $sql) {
        $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . ".constraint.$type");
      }
    }
  }

  /**
   * @brief Delete views
   *
   * Get current tables and columns used by all views.
   * Delete if: uses table I know and column I do not know.
   * Without this delete, we won't be able to drop columns.
   *
   * @param string $catalog Name of the catalog
   */
  function dropViews($catalog)
  {
    $sql = "SELECT view_name,table_name,column_name
        FROM information_schema.view_column_usage
        WHERE table_catalog='$catalog'
        ORDER BY view_name,table_name,column_name";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt);
    while ($row = $this->dbman->fetchArray($result)) {
      $View = $row['view_name'];
      $table = $row['table_name'];
      if (empty($this->schema['TABLE'][$table])) {
        continue;
      }
      $column = $row['column_name'];
      if (empty($this->schema['TABLE'][$table][$column])) {
        $sql = "DROP VIEW \"$View\";";
        $this->applyOrEchoOnce($sql);
      }
    }
    $result = $this->dbman->freeResult($result);
  }

  /**
   * Delete columns from tables
   * @param array  $columns Name of columns to be dropped
   * @param string $table   Name of the table
   */
  function dropColumnsFromTable($columns, $table)
  {
    if (empty($table) || empty($this->schema['TABLE'][$table])) {
      return;
    }
    foreach ($columns as $column) {
      if (empty($column)) {
        continue;
      }
      if (empty($this->schema['TABLE'][$table][$column])) {
        $sql = "ALTER TABLE \"$table\" DROP COLUMN \"$column\";";
        $this->applyOrEchoOnce($sql);
      }
    }
  }


  /**
   * \brief Load the schema from the db into an array.
   **/
  function getCurrSchema()
  {
    global $SysConf;
    $this->currSchema = array();
    $this->addInheritedRelations();
    $referencedSequencesInTableColumns = $this->addTables();
    $this->addViews($viewowner = $SysConf['DBCONF']['user']);
    $this->addSequences($referencedSequencesInTableColumns);
    $this->addConstraints();
    $this->addIndexes();
    unset($this->currSchema['TABLEID']);
    return $this->currSchema;
  }

  /**
   * Add inherited relations to the current schema.
   */
  function addInheritedRelations()
  {
    $sql = "SELECT class.relname AS \"table\", daddy.relname AS inherits_from
      FROM pg_class AS class
      INNER JOIN pg_catalog.pg_inherits ON pg_inherits.inhrelid = class.oid
      INNER JOIN pg_class daddy ON pg_inherits.inhparent = daddy.oid";
    $this->dbman->prepare($stmt=__METHOD__, $sql);
    $res = $this->dbman->execute($stmt);
    $relations = array();
    while ($row=$this->dbman->fetchArray($res)) {
      $relations[$row['table']] = $row['inherits_from'];
    }
    $this->dbman->freeResult($res);
    $this->currSchema['INHERITS'] = $relations;
  }

  /**
   * Add tables to the current schema
   */
  function addTables()
  {
    $referencedSequencesInTableColumns = array();

    $sql = "SELECT
    table_name AS \"table\", ordinal_position AS ordinal, column_name,
    udt_name AS type, character_maximum_length AS modifier,
    CASE is_nullable WHEN 'YES' THEN false WHEN 'NO' THEN true END AS \"notnull\",
    column_default AS \"default\",
    col_description(table_name::regclass, ordinal_position) AS description
  FROM information_schema.columns
  WHERE table_schema = 'public'
  ORDER BY table_name, ordinal_position;";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt);
    while ($R = $this->dbman->fetchArray($result)) {
      $Table = $R['table'];
      $Column = $R['column_name'];
      if (array_key_exists($Table, $this->currSchema['INHERITS'])) {
        $this->currSchema['TABLEID'][$Table][$R['ordinal']] = $Column;
        continue;
      }
      $Type = $R['type'];
      if ($Type == 'bpchar') {
        $Type = "char";
      }
      if ($R['modifier'] > 0) {
        $Type .= '(' . $R['modifier'] . ')';
      }
      if (!empty($R['description'])) {
        $Desc = str_replace("'", "''", $R['description']);
      } else {
        $Desc = "";
      }
      $this->currSchema['TABLEID'][$Table][$R['ordinal']] = $Column;
      if (!empty($Desc)) {
        $this->currSchema['TABLE'][$Table][$Column]['DESC'] = "COMMENT ON COLUMN \"$Table\".\"$Column\" IS '$Desc'";
      } else {
        $this->currSchema['TABLE'][$Table][$Column]['DESC'] = "";
      }
      $this->currSchema['TABLE'][$Table][$Column]['ADD'] = "ALTER TABLE \"$Table\" ADD COLUMN \"$Column\" $Type";
      $this->currSchema['TABLE'][$Table][$Column]['ALTER'] = "ALTER TABLE \"$Table\"";
      $Alter = "ALTER COLUMN \"$Column\"";
      if ($R['notnull'] == 't') {
        $this->currSchema['TABLE'][$Table][$Column]['ALTER'] .= " $Alter SET NOT NULL";
      } else {
        $this->currSchema['TABLE'][$Table][$Column]['ALTER'] .= " $Alter DROP NOT NULL";
      }
      if ($R['default'] != '') {
        $R['default'] = preg_replace("/::bpchar/", "::char", $R['default']);
        $R['default'] = str_replace("public.", "", $R['default']);
        $this->currSchema['TABLE'][$Table][$Column]['ALTER'] .= ", $Alter SET DEFAULT " . $R['default'];
        $this->currSchema['TABLE'][$Table][$Column]['ADD'] .= " DEFAULT " . $R['default'];

        $rgx = "/nextval\('([a-z_]*)'.*\)/";
        $matches = array();
        if (preg_match($rgx, $R['default'], $matches)) {
           $sequence = $matches[1];
           $referencedSequencesInTableColumns[$sequence] = array("table" => $Table, "column" => $Column);
        }
      }
    }
    $this->dbman->freeResult($result);

    return $referencedSequencesInTableColumns;
  }

  /**
   * Add views to the current schema
   * @param string $viewowner Owner of the view
   */
  function addViews($viewowner)
  {
    $sql = "SELECT viewname,definition FROM pg_views WHERE viewowner = $1";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt, array($viewowner));
    while ($row = $this->dbman->fetchArray($result)) {
      $sql = "CREATE VIEW \"" . $row['viewname'] . "\" AS " . $row['definition'];
      $this->currSchema['VIEW'][$row['viewname']] = $sql;
    }
    $this->dbman->freeResult($result);
  }

  /**
   * Add sequences to the current schema
   * @param array $referencedSequencesInTableColumns Array from addTables()
   */
  function addSequences($referencedSequencesInTableColumns)
  {
    $sql = "SELECT relname
      FROM pg_class
      WHERE relkind = 'S'
        AND relnamespace IN (
             SELECT oid FROM pg_namespace WHERE nspname NOT LIKE 'pg_%' AND nspname != 'information_schema'
            )";

    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt);

    while ($row = $this->dbman->fetchArray($result)) {
      $sequence = $row['relname'];
      if (empty($sequence)) {
         continue;
      }

      $sqlCreate = "CREATE SEQUENCE \"" . $sequence . "\"";
      $this->currSchema['SEQUENCE'][$sequence]['CREATE'] = $sqlCreate;

      if (array_key_exists($sequence, $referencedSequencesInTableColumns)) {
        $table = $referencedSequencesInTableColumns[$sequence]['table'];
        $column = $referencedSequencesInTableColumns[$sequence]['column'];

        $sqlUpdate = "SELECT setval('$sequence',(SELECT greatest(1,max($column)) val FROM $table))";
        $this->currSchema['SEQUENCE'][$sequence]['UPDATE'] = $sqlUpdate;
      }
    }

    $this->dbman->freeResult($result);
  }

  /**
   * Add constraints to the current schema
   */
  function addConstraints()
  {
    $sql = "SELECT c.conname AS constraint_name,
        CASE c.contype
          WHEN 'c' THEN 'CHECK'
          WHEN 'f' THEN 'FOREIGN KEY'
          WHEN 'p' THEN 'PRIMARY KEY'
          WHEN 'u' THEN 'UNIQUE'
        END AS type,
        CASE WHEN c.condeferrable = 'f' THEN 0 ELSE 1 END AS is_deferrable,
        CASE WHEN c.condeferred = 'f' THEN 0 ELSE 1 END AS is_deferred,
        t.relname AS table_name, array_to_string(c.conkey, ' ') AS constraint_key,
        CASE confupdtype
          WHEN 'a' THEN 'NO ACTION'
          WHEN 'r' THEN 'RESTRICT'
          WHEN 'c' THEN 'CASCADE'
          WHEN 'n' THEN 'SET NULL'
          WHEN 'd' THEN 'SET DEFAULT'
        END AS on_update,
        CASE confdeltype
          WHEN 'a' THEN 'NO ACTION'
          WHEN 'r' THEN 'RESTRICT'
          WHEN 'c' THEN 'CASCADE'
          WHEN 'n' THEN 'SET NULL'
          WHEN 'd' THEN 'SET DEFAULT' END AS on_delete,
        CASE confmatchtype
          WHEN 'u' THEN 'UNSPECIFIED'
          WHEN 'f' THEN 'FULL'
          WHEN 'p' THEN 'PARTIAL'
        END AS match_type,
        t2.relname AS references_table,
        array_to_string(c.confkey, ' ') AS fk_constraint_key
      FROM pg_constraint AS c
      LEFT JOIN pg_class AS t ON c.conrelid = t.oid
      INNER JOIN information_schema.tables AS tab ON t.relname = tab.table_name
      LEFT JOIN pg_class AS t2 ON c.confrelid = t2.oid
      ORDER BY constraint_name,table_name
    ";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt);
    $Results = $this->dbman->fetchAll($result);
    $this->dbman->freeResult($result);
    /* Constraints use indexes into columns.  Covert those to column names. */
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++) {
      $Key = "";
      $Keys = explode(" ", $Results[$i]['constraint_key']);
      foreach ($Keys as $K) {
        if (empty($K)) {
          continue;
        }
        if (!empty($Key)) {
          $Key .= ",";
        }
        if (!empty($this->currSchema['TABLEID'][$Results[$i]['table_name']][$K])) {
          $Key .= '"' . $this->currSchema['TABLEID'][$Results[$i]['table_name']][$K] . '"';
        }
      }
      $Results[$i]['constraint_key'] = $Key;
      $Key = "";
      if (!empty($Results[$i]['fk_constraint_key'])) {
        $Keys = explode(" ", $Results[$i]['fk_constraint_key']);
      } else {
        $Keys = [];
      }
      foreach ($Keys as $K) {
        if (empty($K)) {
          continue;
        }
        if (!empty($Key)) {
          $Key .= ",";
        }
        $Key .= '"' . $this->currSchema['TABLEID'][$Results[$i]['references_table']][$K] . '"';
      }
      $Results[$i]['fk_constraint_key'] = $Key;
    }
    /* Save the constraint */
    /* There are different types of constraints that must be stored in order */
    /* CONSTRAINT: PRIMARY KEY */
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++) {
      if ($Results[$i]['type'] != 'PRIMARY KEY') {
        continue;
      }
      $sql = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $sql .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $sql .= " " . $Results[$i]['type'];
      $sql .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table'])) {
        $sql .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
        $sql .= " (" . $Results[$i]['fk_constraint_key'] . ")";
      }
      $sql .= ";";
      $this->currSchema['CONSTRAINT'][$Results[$i]['constraint_name']] = $sql;
      $Results[$i]['processed'] = 1;
    }
    /* CONSTRAINT: UNIQUE */
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++) {
      if ($Results[$i]['type'] != 'UNIQUE') {
        continue;
      }
      $sql = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $sql .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $sql .= " " . $Results[$i]['type'];
      $sql .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table'])) {
        $sql .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
        $sql .= " (" . $Results[$i]['fk_constraint_key'] . ")";
      }
      $sql .= ";";
      $this->currSchema['CONSTRAINT'][$Results[$i]['constraint_name']] = $sql;
      $Results[$i]['processed'] = 1;
    }

    /* CONSTRAINT: FOREIGN KEY */
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++) {
      if ($Results[$i]['type'] != 'FOREIGN KEY') {
        continue;
      }
      $sql = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $sql .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $sql .= " " . $Results[$i]['type'];
      $sql .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table'])) {
        $sql .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
        $sql .= " (" . $Results[$i]['fk_constraint_key'] . ")";
      }

      if (!empty($Results[$i]['on_update'])) {
        $sql .= " ON UPDATE " . $Results[$i]['on_update'];
      }
      if (!empty($Results[$i]['on_delete'])) {
        $sql .= " ON DELETE " . $Results[$i]['on_delete'];
      }

      $sql .= ";";
      $this->currSchema['CONSTRAINT'][$Results[$i]['constraint_name']] = $sql;
      $Results[$i]['processed'] = 1;
    }

    /* CONSTRAINT: ALL OTHERS */
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++) {
      if (!empty($Results[$i]['processed']) && $Results[$i]['processed'] == 1) {
        continue;
      }

      $sql = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $sql .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $sql .= " " . $Results[$i]['type'];
      $sql .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table'])) {
        $sql .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
        $sql .= " (" . $Results[$i]['fk_constraint_key'] . ")";
      }
      $sql .= ";";
      $this->currSchema['CONSTRAINT'][$Results[$i]['constraint_name']] = $sql;
      $Results[$i]['processed'] = 1;
    }
  }

  /**
   * Add indexes to the current schema
   */
  function addIndexes()
  {
    $sql = "SELECT tablename AS \"table\", indexname AS index, indexdef AS define
      FROM pg_indexes
      INNER JOIN information_schema.tables ON table_name = tablename
        AND table_type = 'BASE TABLE'
        AND table_schema = 'public'
        AND schemaname = 'public'
      ORDER BY tablename,indexname;
    ";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt);
    while ($row = $this->dbman->fetchArray($result)) {
      /* UNIQUE constraints also include indexes. */
      if (empty($this->currSchema['CONSTRAINT'][$row['index']])) {
        $this->currSchema['INDEX'][$row['table']][$row['index']] = str_replace("public.", "", $row['define']) . ";";
      }
    }
    $this->dbman->freeResult($result);
  }

  /**
   * Add functions to the given schema
   * @param array $schema Schema in which the functions are to be added
   * @return array Schema with functions under `FUNCTION` key
   */
  function addFunctions($schema)
  {
    // prosrc
    // proretset == setof
    $sql = "SELECT proname AS name,
        pronargs AS input_num,
        proargnames AS input_names,
        proargtypes AS input_type,
        proargmodes AS input_modes,
        proretset AS setof,
        prorettype AS output_type
      FROM pg_proc AS proc
      INNER JOIN pg_language AS lang ON proc.prolang = lang.oid
      WHERE lang.lanname = 'plpgsql'
      ORDER BY proname;";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt);
    while ($row = $this->dbman->fetchArray($result)) {
      $sql = "CREATE or REPLACE function " . $row['proname'] . "()";
      $sql .= ' RETURNS ' . "TBD" . ' AS $$';
      $sql .= " " . $row['prosrc'];
      $schema['FUNCTION'][$row['proname']] = $sql;
    }
    $this->dbman->freeResult($result);
    return $schema;
  }

  /**
   * Write array entries to $fout as string representation
   * @param resource $fout
   * @param string   $key
   * @param array    $value
   * @param string   $varname
   */
  function writeArrayEntries($fout, $key, $value, $varname)
  {
    $varname .= '["' . str_replace('"', '\"', $key) . '"]';
    if (!is_array($value)) {
      $value = str_replace('"', '\"', $value);
      fwrite($fout, "$varname = \"$value\";\n");
      return;
    }
    foreach ($value as $k => $v) {
      $this->writeArrayEntries($fout, $k, $v, $varname);
    }
    fwrite($fout, "\n");
  }

  /**
   * \brief Export the schema of the connected ($PG_CONN) database to a
   *        file in the format readable by GetSchema().
   *
   * @param string $filename Path to the file to store the schema in.
   *
   * @return false=success, on error return string with error message.
   **/
  function exportSchema($filename = NULL)
  {
    global $PG_CONN;

    /* set driver */
    $dbDriver = $this->dbman->getDriver();
    if (empty($dbDriver)) {
      $pgDrive = new Postgres($PG_CONN);
      $this->dbman->setDriver($pgDrive);
    }

    if (empty($filename)) {
      $filename = 'php://stdout';
    }
    $Schema = $this->getCurrSchema();
    $fout = fopen($filename, "w");
    if (!$fout) {
      return ("Failed to write to $filename\n");
    }
    global $Name;
    fwrite($fout, "<?php\n");
    fwrite($fout, "/* This file is generated by " . $Name . " */\n");
    fwrite($fout, "/* Do not manually edit this file */\n\n");
    fwrite($fout, '  $Schema=array();' . "\n\n");
    foreach ($Schema as $K1 => $V1) {
      $this->writeArrayEntries($fout, $K1, $V1, '  $Schema');
    }
    fclose($fout);
    return false;
  }


  /**
   * \brief Create any required DB functions.
   */
  function makeFunctions()
  {
    print "  Applying database functions\n";
    flush();
    /* *******************************************
     * uploadtree2path is a DB function that returns the non-artifact parents of an uploadtree_pk.
     * drop and recreate to change the return type.
     */
    $sql = 'drop function if exists uploadtree2path(integer);';
    $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . '.uploadtree2path.drop');

    $sql = '
    CREATE function uploadtree2path(uploadtree_pk_in int) returns setof uploadtree as $$
    DECLARE
      UTrec   uploadtree;
      UTpk    integer;
      sql     varchar;
    BEGIN
      UTpk := uploadtree_pk_in;
      WHILE UTpk > 0 LOOP
        sql := ' . "'" . 'select * from uploadtree where uploadtree_pk=' . "'" . ' || UTpk;
        execute sql into UTrec;
        IF ((UTrec.ufile_mode & (1<<28)) = 0) THEN RETURN NEXT UTrec; END IF;
        UTpk := UTrec.parent;
      END LOOP;
      RETURN;
    END;
    $$
    LANGUAGE plpgsql;
      ';
    $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . '.uploadtree2path.create');

    /*
     * getItemParent is a DB function that returns the non-artifact parent of an uploadtree_pk.
     * drop and recreate to change the return type.
     */
    $sql = 'drop function if exists getItemParent(integer);';
    $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . '.getItemParent.drop');

    $sql = '
    CREATE OR REPLACE FUNCTION getItemParent(itemId Integer) RETURNS Integer AS $$
    WITH RECURSIVE file_tree(uploadtree_pk, parent, jump, path, cycle) AS (
        SELECT ut.uploadtree_pk, ut.parent,
          true,
          ARRAY[ut.uploadtree_pk],
          false
        FROM uploadtree ut
        WHERE ut.uploadtree_pk = $1
      UNION ALL
        SELECT ut.uploadtree_pk, ut.parent,
          ut.ufile_mode & (1<<28) != 0,
          path || ut.uploadtree_pk,
        ut.uploadtree_pk = ANY(path)
        FROM uploadtree ut, file_tree ft
        WHERE ut.uploadtree_pk = ft.parent AND jump AND NOT cycle
      )
   SELECT uploadtree_pk from file_tree ft WHERE NOT jump
   $$
   LANGUAGE SQL
   STABLE
   RETURNS NULL ON NULL INPUT
      ';
    $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . '.getItemParent.create');
    return;
  }

  /**
   * Apply inherits relations from schema on DB
   */
  function applyInheritedRelations()
  {
    if (empty($this->schema['INHERITS'])) {
      return;
    }
    foreach ($this->schema['INHERITS'] as $table => $fromTable) {
      if (empty($table)) {
        continue;
      }
      if (!$this->dbman->existsTable($table) && $this->dbman->existsTable($fromTable)) {
        $sql = "CREATE TABLE \"$table\" () INHERITS (\"$fromTable\")";
        $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . $table);
      }
    }
  }

  // MakeFunctions()
}

if (empty($dbManager) || !($dbManager instanceof DbManager)) {
  $logLevel = Logger::INFO;
  $logger = new Logger(__FILE__);
  $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $logLevel));
  $dbManager = new ModernDbManager($logger);
  $pgDriver = new Postgres($GLOBALS['PG_CONN']);
  $dbManager->setDriver($pgDriver);
}
/* simulate the old functions*/
$libschema = new fo_libschema($dbManager);
/**
 * @brief Make schema match $Filename.  This is a single transaction.
 * @param string $Filename Schema file written by schema-export.php
 * @param bool   $Debug Turn on debugging (echo sql as it is being executed)
 * @param string $Catalog Optional database name
 * @return false=success, on error return string with error message.
 **/
function ApplySchema($Filename = NULL, $Debug = false, $Catalog = 'fossology')
{
  global $libschema;
  return $libschema->applySchema($Filename, $Debug, $Catalog);
}

/**
 * \brief Load the schema from the db into an array.
 **/
function GetSchema()
{
  global $libschema;
  return $libschema->getCurrSchema();
}

/**
 * \brief Export the schema of the connected ($PG_CONN) database to a
 *        file in the format readable by GetSchema().
 * @param string $filename path to the file to store the schema in.
 * @return false=success, on error return string with error message.
 **/
function ExportSchema($filename = NULL)
{
  global $libschema;
  return $libschema->exportSchema($filename);
}

/**
 * \brief Create any required DB functions.
 */
function MakeFunctions($Debug)
{
  global $libschema;
  $libschema->makeFunctions($Debug);
}
