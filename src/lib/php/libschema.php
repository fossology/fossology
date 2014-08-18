<?php
/*
 Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * @file libschema.php
 * @brief Functions to bring database schema to a known state.
 *
 **/

require_once(__DIR__ . '/../../vendor/autoload.php');

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Db\Driver\Postgres;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class fo_libschema
{
  public $debug = false;

  /**
   * @var DbManager
   */
  private $dbman;

  private $schema = array();

  private $currSchema = array();

  /**
   *
   * @param Fossology\Lib\Db\DbManager $dbManager
   */
  function __construct(DbManager &$dbManager)
  {
    $this->dbman = $dbManager;
  }


  /**
   * apply or echo
   */
  function applyOrEchoOnce($sql, $stmt = '')
  {
    if ($this->debug)
    {
      print ("$sql\n");
    } else
    {
      return $this->dbman->queryOnce($sql, $stmt);
    }
  }


  /**
   * @brief Make schema match $Filename.  This is a single transaction.
   * @param $filename Schema file written by schema-export.php
   * @param $debug Turn on debugging (echo sql as it is being executed)
   * @param $catalog Optional database name
   * @return false=success, on error return string with error message.
   **/
  function applySchema($filename = NULL, $debug = false, $catalog = 'fossology')
  {
    global $PG_CONN;
    $this->dbman->setDriver(new Postgres($PG_CONN));

    // first check to make sure we don't already have the plpgsql language installed
    $sql_statement = "select lanname from pg_language where lanname = 'plpgsql'";

    $result = pg_query($PG_CONN, $sql_statement)
      or die("Could not check the database for plpgsql language\n");

    $plpgsql_already_installed = FALSE;
    if ( $row = pg_fetch_row($result) ) {
      $plpgsql_already_installed = TRUE;
    }

    // then create language plpgsql if not already created
    if ( $plpgsql_already_installed == FALSE ) {
      $sql_statement = "CREATE LANGUAGE plpgsql";
      $result = pg_query($PG_CONN, $sql_statement)
        or die("Could not create plpgsql language in the database\n");
    }


    $this->debug = $debug;
    if (!file_exists($filename))
    {
      $errMsg = "$filename does not exist.";
      return $errMsg;
    }
    $Schema = array(); /* will be filled in next line */
    require_once($filename); /* this will DIE if the file does not exist. */
    $this->schema = $Schema;

    /* Very basic sanity check (so we don't delete everything!) */
    if ((count($this->schema['TABLE']) < 5) || (count($this->schema['SEQUENCE']) < 5)
        || (count($this->schema['INDEX']) < 5) || (count($this->schema['CONSTRAINT']) < 5)
    )
    {
      $errMsg = "Schema from '$filename' appears invalid.";
      return $errMsg;
    }

    if (!$debug)
    {
      $result = $this->dbman->getSingleRow("show statement_timeout", array(), $stmt = __METHOD__ . '.getTimeout');
      $statementTimeout = $result['statement_timeout'];
      $this->dbman->queryOnce("SET statement_timeout = 0", $stmt = __METHOD__ . '.setTimeout');
    }

    $this->applyOrEchoOnce('BEGIN');
    $this->getCurrSchema();
    $errlev = error_reporting(E_ERROR | E_WARNING | E_PARSE);
    $this->applySequences();
    $this->applyTables();
    $this->applyViews();
    $this->dropConstraints();
    /* Reload current since the CASCADE may have changed things */
    $this->getCurrSchema(); /* constraints and indexes are linked, recheck */
    $this->dropIndexes();
    $this->applyIndexes();
    $this->applyConstraints();
    error_reporting($errlev); /* return to previous error reporting level */
    $this->makeFunctions();
    /* Reload current since CASCADE during migration may have changed things */
    $this->getCurrSchema();
    $this->dropViews($catalog);
    foreach ($this->currSchema['TABLE'] as $table => $columns)
    {
      $this->dropColumnsFromTable($columns, $table);
    }
    $this->applyOrEchoOnce('COMMIT');
    flush();
    ReportCachePurgeAll();
    if (!$debug)
    {
      $this->dbman->getSingleRow("SET statement_timeout = $statementTimeout", array(), $stmt = __METHOD__ . '.resetTimeout');
      print "DB schema has been updated for $catalog.\n";
    } else
    {
      print "These queries could update DB schema for $catalog.\n";
    }
    return false;
  }

  /************************************/
  /* Add sequences */
  /************************************/
  function applySequences()
  {
    if (empty($this->schema['SEQUENCE']))
    {
      return;
    }
    foreach ($this->schema['SEQUENCE'] as $name => $sql)
    {
      if (empty($name) || $this->currSchema['SEQUENCE'][$name] == $sql)
      {
        continue;
      }
      $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . $name);
    }
  }

  /************************************/
  /* Add tables/columns (dependent on sequences for default values) */
  /************************************/
  function applyTables()
  {
    if (empty($this->schema['TABLE']))
    {
      return;
    }
    foreach ($this->schema['TABLE'] as $table => $columns)
    {
      if (empty($table))
      {
        continue;
      }
      if (!DB_TableExists($table))
      {
        $sql = "CREATE TABLE \"$table\" ()";
        $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . $table);
      }
      foreach ($columns as $column => $modification)
      {
        if ($this->currSchema['TABLE'][$table][$column]['ADD'] != $modification['ADD'])
        {
          $rename = "";
          if (DB_ColExists($table, $column))
          {
            /* The column exists, but it looks different!
             Solution: Delete the column! */
            $rename = $column . '_old';
            $sql = "ALTER TABLE \"$table\" RENAME COLUMN \"$column\" TO \"$rename\"";
            $this->applyOrEchoOnce($sql);
          }

          $sql = $modification['ADD'];
          if ($debug)
          {
            print "$sql\n";
          } else
          {
            // Add the new column, then set the default value with update
            $this->dbman->queryOnce($sql);
            if (!empty($modification['UPDATE']))
            {
              $this->dbman->queryOnce($sql = $modification['UPDATE']);
            }
          }
          if (!empty($rename))
          {
            /* copy over the old data */
            $this->applyOrEchoOnce($sql = "UPDATE \"$table\" SET \"$column\" = \"$rename");
            $this->applyOrEchoOnce($sql = "ALTER TABLE \"$table\" DROP COLUMN \"$rename");
          }
        }
        if ($this->currSchema['TABLE'][$table][$column]['ALTER'] != $modification['ALTER'])
        {
          $sql = $modification['ALTER'];
          if ($debug)
          {
            print "$sql\n";
          } else
          {
            $this->dbman->queryOnce($sql);
            if (!empty($modification['UPDATE']))
            {
              $this->dbman->queryOnce($sql = $modification['UPDATE']);
            }
          }
        }
        if ($this->currSchema['TABLE'][$table][$column]['DESC'] != $modification['DESC'])
        {
          $sql = empty($modification['DESC']) ? "COMMENT ON COLUMN \"$table\".\"$column\" IS ''" : $modification['DESC'];
          $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . "$table.$column.comment");
        }
      }
    }
  }

  /************************************/
  /* Add views (dependent on columns) */
  /************************************/
  function applyViews()
  {
    if (empty($this->schema['VIEW']))
    {
      return;
    }
    foreach ($this->schema['VIEW'] as $name => $sql)
    {
      if (empty($name) || $this->currSchema['VIEW'][$name] == $sql)
      {
        continue;
      }
      if (!empty($this->currSchema['VIEW'][$name]))
      {
        $sqlDropView = "DROP VIEW IF EXISTS $name";
        $this->applyOrEchoOnce($sqlDropView);
      }
      $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . $name);
    }
  }

  /************************************/
  /* Delete constraints */
  /* Delete now, so they won't interfere with migrations. */
  /************************************/
  function dropConstraints()
  {
    if (empty($this->currSchema['CONSTRAINT']))
    {
      return;
    }
    foreach ($this->currSchema['CONSTRAINT'] as $name => $sql)
    {
      if (empty($name) || $this->schema['CONSTRAINT'][$name] == $sql)
      {
        continue;
      }
      /* Only process tables that I know about */
      $table = preg_replace("/^ALTER TABLE \"(.*)\" ADD CONSTRAINT.*/", '${1}', $sql);
      $TableFk = preg_replace("/^.*FOREIGN KEY .* REFERENCES \"(.*)\" \(.*/", '${1}', $sql);
      if ($TableFk == $sql)
      {
        $TableFk = $table;
      }
      /* If I don't know the primary or foreign table... */
      if (empty($this->schema['TABLE'][$table]) && empty($this->schema['TABLE'][$TableFk]))
      {
        continue;
      }
      $sql = "ALTER TABLE \"$table\" DROP CONSTRAINT \"$name\" CASCADE";
      $this->applyOrEchoOnce($sql);
    }
  }

  /************************************/
  /* Delete indexes */
  /************************************/
  function dropIndexes()
  {
    if (empty($this->currSchema['INDEX']))
    {
      return;
    }
    foreach ($this->currSchema['INDEX'] as $table => $IndexInfo)
    {
      if (empty($table) || empty($this->schema['TABLE'][$table]))
      {
        continue;
      }
      foreach ($IndexInfo as $name => $sql)
      {
        if (empty($name) || $this->schema['INDEX'][$table][$name] == $sql)
        {
          continue;
        }
        $sql = "DROP INDEX \"$name\"";
        $this->applyOrEchoOnce($sql);
      }
    }
  }

  /************************************/
  /* Add indexes (dependent on columns) */
  /************************************/
  function applyIndexes()
  {
    if (empty($this->schema['INDEX']))
    {
      return;
    }
    foreach ($this->schema['INDEX'] as $table => $IndexInfo)
    {
      if (empty($table))
      {
        continue;
      }
      if (!array_key_exists($table, $this->schema["TABLE"]))
      {
        echo "skipping orphan table: $table\n";
        continue;
      }
      foreach ($IndexInfo as $name => $sql)
      {
        if (empty($name) || $this->currSchema['INDEX'][$table][$name] == $sql)
        {
          continue;
        }
        $this->applyOrEchoOnce($sql);
        $sql = "REINDEX INDEX \"$name\"";
        $this->applyOrEchoOnce($sql);
      }
    }
  }

  /************************************/
  /* Add constraints (dependent on columns, views, and indexes) */
  /************************************/
  function applyConstraints()
  {
    $this->currSchema = $this->getCurrSchema(); /* constraints and indexes are linked, recheck */
    if (empty($this->schema['CONSTRAINT']))
    {
      return;
    }
    /* Constraints must be added in the correct order! */
    $orderedConstraints = array('primary' => array(), 'unique' => array(), 'foreign' => array(), 'other' => array());
    foreach ($this->schema['CONSTRAINT'] as $Name => $sql)
    {
      if (empty($Name) || $this->currSchema['CONSTRAINT'][$Name] == $sql)
      {
        continue;
      }
      if (preg_match("/PRIMARY KEY/", $sql))
      {
        $orderedConstraints['primary'][] = $sql;
      } elseif (preg_match("/UNIQUE/", $sql))
      {
        $orderedConstraints['unique'][] = $sql;
      } elseif (preg_match("/FOREIGN KEY/", $sql))
      {
        $orderedConstraints['foreign'][] = $sql;
      } else
      {
        $orderedConstraints['other'][] = $sql;
      }
    }
    foreach ($orderedConstraints as $type => $constraints)
    {
      foreach ($constraints as $sql)
      {
        $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . ".constraint.$type");
      }
    }
  }

  /************************************/
  /* Delete views */
  /************************************/
  /* Get current tables and columns used by all views */
  /* Delete if: uses table I know and column I do not know. */
  /* Without this delete, we won't be able to drop columns. */
  function dropViews($catalog)
  {
    $sql = "SELECT view_name,table_name,column_name
        FROM information_schema.view_column_usage
        WHERE table_catalog='$catalog'
        ORDER BY view_name,table_name,column_name";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt);
    $Results = pg_fetch_all($result);
    for ($i = 0; !empty($Results[$i]['view_name']); $i++)
    {
      $View = $Results[$i]['view_name'];
      $table = $Results[$i]['table_name'];
      if (empty($this->schema['TABLE'][$table]))
      {
        continue;
      }
      $column = $Results[$i]['column_name'];
      if (empty($this->schema['TABLE'][$table][$column]))
      {
        $sql = "DROP VIEW \"$View\";";
        $this->applyOrEchoOnce($sql);
      }
    }
  }

  /************************************/
  /* Delete columns/tables */
  /************************************/
  function dropColumnsFromTable($columns, $table)
  {
    if (empty($table) || empty($this->schema['TABLE'][$table]))
    {
      return;
    }
    foreach ($columns as $column => $modification)
    {
      if (empty($column))
      {
        continue;
      }
      if (empty($this->schema['TABLE'][$table][$column]))
      {
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
    $this->addTables();
    $this->addViews($viewowner = $SysConf['DBCONF']['user']);
    $this->addSequences();
    $this->addConstraints();
    $this->addIndexes();
    unset($this->currSchema['TABLEID']);
    return $this->currSchema;
  }

  /***************************/
  /* Get the tables */
  /***************************/
  function addTables()
  {
    $sql = "SELECT class.relname AS table,
        attr.attnum AS ordinal,
        attr.attname AS column_name,
        type.typname AS type,
        attr.atttypmod-4 AS modifier,
        attr.attnotnull AS notnull,
        attrdef.adsrc AS default,
        col_description(attr.attrelid, attr.attnum) AS description
      FROM pg_class AS class
      INNER JOIN pg_attribute AS attr ON attr.attrelid = class.oid AND attr.attnum > 0
      INNER JOIN pg_type AS type ON attr.atttypid = type.oid
      INNER JOIN information_schema.tables AS tab ON class.relname = tab.table_name
        AND tab.table_type = 'BASE TABLE'
        AND tab.table_schema = 'public'
      LEFT OUTER JOIN pg_attrdef AS attrdef ON adrelid = attrelid AND adnum = attnum
      ORDER BY class.relname,attr.attnum";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt);
    $Results = pg_fetch_all($result);
    for ($i = 0; !empty($Results[$i]['table']); $i++)
    {
      $R = & $Results[$i];
      $Table = $R['table'];
      $Column = $R['column_name'];
      $Type = $R['type'];
      if ($Type == 'bpchar')
      {
        $Type = "char";
      }
      if ($R['modifier'] > 0)
      {
        $Type .= '(' . $R['modifier'] . ')';
      }
      $Desc = str_replace("'", "''", $R['description']);
      $this->currSchema['TABLEID'][$Table][$R['ordinal']] = $Column;
      if (!empty($Desc))
      {
        $this->currSchema['TABLE'][$Table][$Column]['DESC'] = "COMMENT ON COLUMN \"$Table\".\"$Column\" IS '$Desc';";
      } else
      {
        $this->currSchema['TABLE'][$Table][$Column]['DESC'] = "";
      }
      $this->currSchema['TABLE'][$Table][$Column]['ADD'] = "ALTER TABLE \"$Table\" ADD COLUMN \"$Column\" $Type;";
      $this->currSchema['TABLE'][$Table][$Column]['ALTER'] = "ALTER TABLE \"$Table\"";
      $Alter = "ALTER COLUMN \"$Column\"";
      // create the index UPDATE to get rid of php notice
      $this->currSchema['TABLE'][$Table][$Column]['UPDATE'] = "";
      if ($R['notnull'] == 't')
      {
        $this->currSchema['TABLE'][$Table][$Column]['ALTER'] .= " $Alter SET NOT NULL";
      } else
      {
        $this->currSchema['TABLE'][$Table][$Column]['ALTER'] .= " $Alter DROP NOT NULL";
      }
      if ($R['default'] != '')
      {
        $R['default'] = preg_replace("/::bpchar/", "::char", $R['default']);
        $this->currSchema['TABLE'][$Table][$Column]['ALTER'] .= ", $Alter SET DEFAULT " . $R['default'];
        $this->currSchema['TABLE'][$Table][$Column]['UPDATE'] .= "UPDATE $Table SET $Column=" . $R['default'];
      }
      $this->currSchema['TABLE'][$Table][$Column]['ALTER'] .= ";";
    }
  }

  /***************************/
  /* Get Views */
  /***************************/
  function addViews($viewowner)
  {
    $sql = "SELECT viewname,definition FROM pg_views WHERE viewowner = $1";
    $stmt = __METHOD__;
    $this->dbman->prepare($stmt, $sql);
    $result = $this->dbman->execute($stmt, array($viewowner));
    $Results = pg_fetch_all($result);
    for ($i = 0; !empty($Results[$i]['viewname']); $i++)
    {
      $sql = "CREATE VIEW \"" . $Results[$i]['viewname'] . "\" AS " . $Results[$i]['definition'];
      $this->currSchema['VIEW'][$Results[$i]['viewname']] = $sql;
    }
  }

  /***************************/
  /* Get Sequence */
  /***************************/
  function addSequences()
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
    $Results = pg_fetch_all($result);
    for ($i = 0; !empty($Results[$i]['relname']); $i++)
    {
      $sql = "CREATE SEQUENCE \"" . $Results[$i]['relname'] . "\" START 1;";
      $this->currSchema['SEQUENCE'][$Results[$i]['relname']] = $sql;
    }
  }

  /***************************/
  /* Get Constraints */
  /***************************/
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
    $Results = pg_fetch_all($result);
    /* Constraints use indexes into columns.  Covert those to column names. */
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++)
    {
      $Key = "";
      $Keys = explode(" ", $Results[$i]['constraint_key']);
      foreach ($Keys as $K)
      {
        if (empty($K))
        {
          continue;
        }
        if (!empty($Key))
        {
          $Key .= ",";
        }
        $Key .= '"' . $this->currSchema['TABLEID'][$Results[$i]['table_name']][$K] . '"';
      }
      $Results[$i]['constraint_key'] = $Key;
      $Key = "";
      $Keys = explode(" ", $Results[$i]['fk_constraint_key']);
      foreach ($Keys as $K)
      {
        if (empty($K))
        {
          continue;
        }
        if (!empty($Key))
        {
          $Key .= ",";
        }
        $Key .= '"' . $this->currSchema['TABLEID'][$Results[$i]['references_table']][$K] . '"';
      }
      $Results[$i]['fk_constraint_key'] = $Key;
    }
    /* Save the constraint */
    /** There are different types of constraints that must be stored in order **/
    /** CONSTRAINT: PRIMARY KEY **/
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++)
    {
      if ($Results[$i]['type'] != 'PRIMARY KEY')
      {
        continue;
      }
      $sql = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $sql .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $sql .= " " . $Results[$i]['type'];
      $sql .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
      {
        $sql .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
        $sql .= " (" . $Results[$i]['fk_constraint_key'] . ")";
      }
      $sql .= ";";
      $this->currSchema['CONSTRAINT'][$Results[$i]['constraint_name']] = $sql;
      $Results[$i]['processed'] = 1;
    }
    /** CONSTRAINT: UNIQUE **/
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++)
    {
      if ($Results[$i]['type'] != 'UNIQUE')
      {
        continue;
      }
      $sql = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $sql .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $sql .= " " . $Results[$i]['type'];
      $sql .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
      {
        $sql .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
        $sql .= " (" . $Results[$i]['fk_constraint_key'] . ")";
      }
      $sql .= ";";
      $this->currSchema['CONSTRAINT'][$Results[$i]['constraint_name']] = $sql;
      $Results[$i]['processed'] = 1;
    }

    /** CONSTRAINT: FOREIGN KEY **/
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++)
    {
      if ($Results[$i]['type'] != 'FOREIGN KEY')
      {
        continue;
      }
      $sql = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $sql .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $sql .= " " . $Results[$i]['type'];
      $sql .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
      {
        $sql .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
        $sql .= " (" . $Results[$i]['fk_constraint_key'] . ")";
      }

      if (!empty($Results[$i]['on_update']))
        $sql .= " ON UPDATE " . $Results[$i]['on_update'];
      if (!empty($Results[$i]['on_delete']))
        $sql .= " ON DELETE " . $Results[$i]['on_delete'];

      $sql .= ";";
      $this->currSchema['CONSTRAINT'][$Results[$i]['constraint_name']] = $sql;
      $Results[$i]['processed'] = 1;
    }

    /** CONSTRAINT: ALL OTHERS **/
    for ($i = 0; !empty($Results[$i]['constraint_name']); $i++)
    {
      if (!empty($Results[$i]['processed']) && $Results[$i]['processed'] == 1) continue;

      $sql = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
      $sql .= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
      $sql .= " " . $Results[$i]['type'];
      $sql .= " (" . $Results[$i]['constraint_key'] . ")";
      if (!empty($Results[$i]['references_table']))
      {
        $sql .= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
        $sql .= " (" . $Results[$i]['fk_constraint_key'] . ")";
      }
      $sql .= ";";
      $this->currSchema['CONSTRAINT'][$Results[$i]['constraint_name']] = $sql;
      $Results[$i]['processed'] = 1;
    }
  }

  /***************************/
  /* Get Index */
  /***************************/
  function addIndexes()
  {
    $sql = "SELECT tablename AS table, indexname AS index, indexdef AS define
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
    $Results = pg_fetch_all($result);
    for ($i = 0; !empty($Results[$i]['table']); $i++)
    {
      /* UNIQUE constraints also include indexes. */
      if (empty($this->currSchema['CONSTRAINT'][$Results[$i]['index']]))
      {
        $this->currSchema['INDEX'][$Results[$i]['table']][$Results[$i]['index']] = $Results[$i]['define'] . ";";
      }
    }
  }


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
    $Results = pg_fetch_all($result);
    for ($i = 0; !empty($Results[$i]['proname']); $i++)
    {
      $sql = "CREATE or REPLACE function " . $Results[$i]['proname'] . "()";
      $sql .= ' RETURNS ' . "TBD" . ' AS $$';
      $sql .= " " . $Results[$i]['prosrc'];
      $schema['FUNCTION'][$Results[$i]['proname']] = $sql;
    }
    return $schema;
  }


  function writeArrayEntries($fout, $key, $value, $varname)
  {
    $varname .= '["' . str_replace('"', '\"', $key) . '"]';
    if (!is_array($value))
    {
      $value = str_replace('"', '\"', $value);
      fwrite($fout, "$varname = \"$value\";\n");
      return;
    }
    foreach ($value as $k => $v)
    {
      $this->writeArrayEntries($fout, $k, $v, $varname);
    }
    fwrite($fout, "\n");
  }

  /**
   * \brief Export the schema of the connected ($PG_CONN) database to a
   *        file in the format readable by GetSchema().
   *
   * @param string $filename path to the file to store the schema in.
   *
   * @return false=success, on error return string with error message.
   **/
  function exportSchema($filename = NULL)
  {
    if (empty($filename))
    {
      $filename = stdout;
    }
    $Schema = $this->getCurrSchema();
    $fout = fopen($filename, "w");
    if (!$fout)
    {
      return ("Failed to write to $filename\n");
    }
    global $Name;
    fwrite($fout, "<?php\n");
    fwrite($fout, "/* This file is generated by " . $Name . " */\n");
    fwrite($fout, "/* Do not manually edit this file */\n\n");
    fwrite($fout, '  $Schema=array();' . "\n\n");
    foreach ($Schema as $K1 => $V1)
    {
      $this->writeArrayEntries($fout, $K1, $V1, '  $Schema');
    }
    fclose($fout);
    return false;
  }


  /**
   * MakeFunctions
   * \brief Create any required DB functions.
   */
  function makeFunctions()
  {
    print "  Applying database functions\n";
    flush();
    /********************************************
     * GetRunnable() is a DB function for listing the runnable items
     * in the jobqueue. This is used by the scheduler.
     ********************************************/
    $sql = '
  CREATE or REPLACE function getrunnable() returns setof jobqueue as $$
  DECLARE
    jqrec jobqueue;
    jqrec_test jobqueue;
    jqcurse CURSOR FOR SELECT *
      FROM jobqueue
      INNER JOIN job ON jq_starttime IS NULL AND jq_end_bits < 2 AND job_pk = jq_job_fk
      ORDER BY job_priority DESC
      ;
    jdep_row jobdepends;
    success integer;
  BEGIN
    open jqcurse;
  <<MYLABEL>>
    LOOP
      FETCH jqcurse INTO jqrec;
      IF FOUND
      THEN -- check all dependencies
        success := 1;
        <<DEPLOOP>>
        FOR jdep_row IN SELECT *  FROM jobdepends WHERE jdep_jq_fk=jqrec.jq_pk LOOP
    -- has the dependency been satisfied?
    SELECT INTO jqrec_test * FROM jobqueue WHERE jdep_row.jdep_jq_depends_fk=jq_pk AND jq_endtime IS NOT NULL AND jq_end_bits < 2;
    IF NOT FOUND
    THEN
      success := 0;
      EXIT DEPLOOP;
    END IF;
        END LOOP DEPLOOP;

        IF success=1 THEN RETURN NEXT jqrec; END IF;
      ELSE EXIT;
      END IF;
    END LOOP MYLABEL;
  RETURN;
  END;
  $$
  LANGUAGE plpgsql;
      ';
    $this->applyOrEchoOnce($sql, $stmt = __METHOD__ . '.getrunnable');
    /********************************************
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
    return;
  } // MakeFunctions()
}

if (empty($dbManager) || !($dbManager instanceof DbManager))
{
  $logLevel = Logger::INFO;
  $logger = new Logger(__FILE__);
  $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $logLevel));
  $dbManager = new DbManager($logger);
}
/* simulate the old functions*/
$libschema = new fo_libschema($dbManager);
/**
 * @brief Make schema match $Filename.  This is a single transaction.
 * @param $Filename Schema file written by schema-export.php
 * @param $Debug Turn on debugging (echo sql as it is being executed)
 * @param $Catalog Optional database name
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
 * MakeFunctions
 * \brief Create any required DB functions.
 */
function MakeFunctions($Debug)
{
  global $libschema;
  return $libschema->makeFunctions($Debug);
}
