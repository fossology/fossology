<?php
/*
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 * @file libschema.php
 * @brief Functions to bring database schema to a known state.
 *
 **/

/**
 * @brief Make schema match $Filename.  This is a single transaction.
 * @param $Filename Schema file written by schema-export.php
 * @param $Debug Turn on debugging (echo sql as it is being executed)
 * @param $Catalog Optional database name
 * @return false=success, on error return string with error message.
 **/
function ApplySchema($Filename = NULL, $Debug=false, $Catalog='fossology')
{
  global $PG_CONN;

  if (!file_exists($Filename))
  {
    $ErrMsg = $Filename . " does not exist.";
    return $ErrMsg;
  }
  require_once ($Filename); /* this will DIE if the file does not exist. */

  /* Very basic sanity check (so we don't delete everything!) */
  if ((count($Schema['TABLE']) < 5) || (count($Schema['SEQUENCE']) < 5) || (count($Schema['INDEX']) < 5) || (count($Schema['CONSTRAINT']) < 5))
  {
    $ErrMsg = "Schema from '$Filename' appears invalid.";
    return $ErrMsg;
  }

  /* get the current statement timeout */
  $SQL = "show statement_timeout";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__,__LINE__);
  $Results = pg_fetch_all($result);
  $Statement_Timeout = $Results[0]['statement_timeout'];
  pg_free_result($result);

  pg_query($PG_CONN, "SET statement_timeout = 0;"); /* turn off DB timeouts */
  pg_query($PG_CONN, "BEGIN;");
  $Curr = GetSchema();
  /* The gameplan: Make $Curr look like $Schema. */
  // print "<pre>"; print_r($Schema); print "</pre>";
  /* turn off E_NOTICE so this stops reporting undefined index */
  $errlev = error_reporting(E_ERROR | E_WARNING | E_PARSE);
  /************************************/
  /* Add sequences */
  /************************************/
  if (!empty($Schema['SEQUENCE'])) foreach ($Schema['SEQUENCE'] as $Name => $SQL)
  {
    if (empty($Name))
    {
      echo "warning empty sequence in .dat\n";
      continue;
    }
    if ($Curr['SEQUENCE'][$Name] == $SQL)
    {
      continue;
    }
    if ($Debug)
    {
      print "$SQL\n";
    }
    else
    {
      $result = pg_query($PG_CONN, $SQL);
      DBCheckResult($result, $SQL, __FILE__,__LINE__);
    }
  }
  /************************************/
  /* Add tables/columns (dependent on sequences for default values) */
  /************************************/
  if (!empty($Schema['TABLE'])) foreach ($Schema['TABLE'] as $Table => $Columns)
  {
    if (empty($Table))
    {
      continue;
    }
    if (!DB_TableExists($Table))
    {
      $SQL = "CREATE TABLE \"$Table\" ();";
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__,__LINE__);
      }
    }
    foreach ($Columns as $Column => $Val)
    {
      if ($Curr['TABLE'][$Table][$Column]['ADD'] != $Val['ADD'])
      {
        $Rename = "";
        if (DB_ColExists($Table, $Column))
        {
          /* The column exists, but it looks different!
           Solution: Delete the column! */
          $Rename = $Column . "_old";
          $SQL = "ALTER TABLE \"$Table\" RENAME COLUMN \"$Column\" TO \"$Rename\";";
          if ($Debug)
          {
            print "$SQL\n";
          }
          else
          {
            $result = pg_query($PG_CONN, $SQL);
            DBCheckResult($result, $SQL, __FILE__,__LINE__);
          }
        }

        $SQL = $Val['ADD'];
        if ($Debug)
        {
          print "$SQL\n";
        }
        else
        {
          // Add the new column, then set the default value with update
          $result = pg_query($PG_CONN, $SQL);
          DBCheckResult($result, $SQL, __FILE__,__LINE__);
          if (!empty($Val['UPDATE']))
          {
            $SQL = $Val['UPDATE'];
            $result = pg_query($PG_CONN, $SQL);
            DBCheckResult($result, $SQL, __FILE__,__LINE__);
          }
        }
        if (!empty($Rename))
        {
          /* copy over the old data */
          $SQL = "UPDATE \"$Table\" SET \"$Column\" = \"$Rename\";";
          if ($Debug)
          {
            print "$SQL\n";
          }
          else
          {
            $result = pg_query($PG_CONN, $SQL);
            DBCheckResult($result, $SQL, __FILE__,__LINE__);
          }
          $SQL = "ALTER TABLE \"$Table\" DROP COLUMN \"$Rename\";";
          if ($Debug)
          {
            print "$SQL\n";
          }
          else
          {
            $result = pg_query($PG_CONN, $SQL);
            DBCheckResult($result, $SQL, __FILE__,__LINE__);
          }
        }
      } // if
      if ($Curr['TABLE'][$Table][$Column]['ALTER'] != $Val['ALTER'])
      {
        if ($Debug)
        {
          print $Val['ALTER'] . "\n";
        }
        else
        {
          $SQL = $Val['ALTER'];
          $result = pg_query($PG_CONN, $SQL);
          DBCheckResult($result, $SQL, __FILE__,__LINE__);
          $SQL = $Val['UPDATE'];
          if (!empty($Val['UPDATE']))
          {
            $SQL = $Val['UPDATE'];
            $result = pg_query($PG_CONN, $SQL);
            DBCheckResult($result, $SQL, __FILE__,__LINE__);
          }
        }
      }
      if ($Curr['TABLE'][$Table][$Column]['DESC'] != $Val['DESC'])
      {
        if (empty($Val['DESC']))
        {
          $SQL = "COMMENT ON COLUMN \"$Table\".\"$Column\" IS '';";
        }
        else
        {
          $SQL = $Val['DESC'];
        }
        if ($Debug)
        {
          print "$SQL\n";
        }
        else
        {
          $result = pg_query($PG_CONN, $SQL);
          DBCheckResult($result, $SQL, __FILE__,__LINE__);
        }
      }
    }
  }
  /************************************/
  /* Add views (dependent on columns) */
  /************************************/
  if (!empty($Schema['VIEW'])) foreach ($Schema['VIEW'] as $Name => $SQL)
  {
    if (empty($Name))
    {
      continue;
    }
    if ($Curr['VIEW'][$Name] == $SQL)
    {
      continue;
    }
    if (!empty($Curr['VIEW'][$Name]))
    {
      /* Delete it if it exists and looks different */
      $SQL1 = "DROP VIEW $Name;";
      if ($Debug)
      {
        print "$SQL1\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL1);
        DBCheckResult($result, $SQL1, __FILE__,__LINE__);
      }
    }
    /* Create the view */
    if ($Debug)
    {
      print "$SQL\n";
    }
    else
    {
      $result = pg_query($PG_CONN, $SQL);
      DBCheckResult($result, $SQL, __FILE__,__LINE__);
    }
  }
  /************************************/
  /* Delete constraints */
  /* Delete now, so they won't interfere with migrations. */
  /************************************/
  if (!empty($Curr['CONSTRAINT'])) foreach ($Curr['CONSTRAINT'] as $Name => $SQL)
  {
    if (empty($Name))
    {
      continue;
    }
    /* Only process tables that I know about */
    $Table = preg_replace("/^ALTER TABLE \"(.*)\" ADD CONSTRAINT.*/", '${1}', $SQL);
    $TableFk = preg_replace("/^.*FOREIGN KEY .* REFERENCES \"(.*)\" \(.*/", '${1}', $SQL);
    if ($TableFk == $SQL)
    {
      $TableFk = $Table;
    }
    /* If I don't know the primary or foreign table... */
    if (empty($Schema['TABLE'][$Table]) && empty($Schema['TABLE'][$TableFk]))
    {
      continue;
    }
    /* If it is already set correctly, then skip it. */
    if ($Schema['CONSTRAINT'][$Name] == $SQL)
    {
      continue;
    }
    $SQL = "ALTER TABLE \"$Table\" DROP CONSTRAINT \"$Name\" CASCADE;";
    if ($Debug)
    {
      print "$SQL\n";
    }
    else
    {
      $result = pg_query($PG_CONN, $SQL);
      DBCheckResult($result, $SQL, __FILE__,__LINE__);
    }
  }
  /* Reload current since the CASCADE may have changed things */
  /************************************/
  /* Delete indexes */
  /************************************/
  $Curr = GetSchema(); /* constraints and indexes are linked, recheck */
  if (!empty($Curr['INDEX'])) foreach ($Curr['INDEX'] as $Table => $IndexInfo)
  {
    if (empty($Table))
    {
      continue;
    }
    /* Only delete indexes on known tables */
    if (empty($Schema['TABLE'][$Table]))
    {
      continue;
    }
    foreach ($IndexInfo as $Name => $SQL)
    {
      if (empty($Name))
      {
        continue;
      }
      /* Only delete indexes that are different */
      if ($Schema['INDEX'][$Table][$Name] == $SQL)
      {
        continue;
      }
      $SQL = "DROP INDEX \"$Name\";";
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__,__LINE__);
      }
    }
  }
  /************************************/
  /* Add indexes (dependent on columns) */
  /************************************/
  if (!empty($Schema['INDEX'])) foreach ($Schema['INDEX'] as $Table => $IndexInfo)
  {
    if (empty($Table))
    {
      continue;
    }
    // bobg
    if (!array_key_exists($Table, $Schema["TABLE"]))
    {
      echo "skipping orphan table: $Table\n";
      continue;
    }
    foreach ($IndexInfo as $Name => $SQL)
    {
      if (empty($Name))
      {
        continue;
      }
      if ($Curr['INDEX'][$Table][$Name] == $SQL)
      {
        continue;
      }
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__,__LINE__);
      }
      $SQL = "REINDEX INDEX \"$Name\";";
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__,__LINE__);
      }
    }
  }
  /************************************/
  /* Add constraints (dependent on columns, views, and indexes) */
  /************************************/
  $Curr = GetSchema(); /* constraints and indexes are linked, recheck */
  if (!empty($Schema['CONSTRAINT']))
  {
    /* Constraints must be added in the correct order! */
    /* CONSTRAINT: PRIMARY KEY */
    foreach ($Schema['CONSTRAINT'] as $Name => $SQL)
    {
      if (empty($Name))
      {
        continue;
      }
      if ($Curr['CONSTRAINT'][$Name] == $SQL)
      {
        continue;
      }
      if (!preg_match("/PRIMARY KEY/", $SQL))
      {
        continue;
      }
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__,__LINE__);
      }
    }
    /* CONSTRAINT: UNIQUE */
    foreach ($Schema['CONSTRAINT'] as $Name => $SQL)
    {
      if (empty($Name))
      {
        continue;
      }
      if ($Curr['CONSTRAINT'][$Name] == $SQL)
      {
        continue;
      }
      if (!preg_match("/UNIQUE/", $SQL))
      {
        continue;
      }
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__,__LINE__);
      }
    }
    /* CONSTRAINT: FOREIGN KEY */
    foreach ($Schema['CONSTRAINT'] as $Name => $SQL)
    {
      if (empty($Name))
      {
        continue;
      }
      if ($Curr['CONSTRAINT'][$Name] == $SQL)
      {
        continue;
      }
      if (!preg_match("/FOREIGN KEY/", $SQL))
      {
        continue;
      }
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__,__LINE__);
      }
    }
    /* All other constraints */
    foreach ($Schema['CONSTRAINT'] as $Name => $SQL)
    {
      if (empty($Name))
      {
        continue;
      }
      if ($Curr['CONSTRAINT'][$Name] == $SQL)
      {
        continue;
      }
      if (preg_match("/PRIMARY KEY/", $SQL))
      {
        continue;
      }
      if (preg_match("/UNIQUE/", $SQL))
      {
        continue;
      }
      if (preg_match("/FOREIGN KEY/", $SQL))
      {
        continue;
      }
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__,__LINE__);
      }
    }
  } /* Add constraints */
  error_reporting($errlev); /* return to previous error reporting level */
  /************************************/
  /* CREATE FUNCTIONS */
  /************************************/
  MakeFunctions($Debug);
  /* Reload current since CASCADE during migration may have changed things */
  $Curr = GetSchema();
  /************************************/
  /* Delete views */
  /************************************/
  print "  Removing obsolete views\n";
  flush();
  /* Get current tables and columns used by all views */
  /* Delete if: uses table I know and column I do not know. */
  /* Without this delete, we won't be able to drop columns. */
  $SQL = "SELECT view_name,table_name,column_name
  FROM information_schema.view_column_usage
  WHERE table_catalog='$Catalog'
  ORDER BY view_name,table_name,column_name;";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__,__LINE__);
  $Results = pg_fetch_all($result);
  for ($i = 0;!empty($Results[$i]['view_name']);$i++)
  {
    $View = $Results[$i]['view_name'];
    $Table = $Results[$i]['table_name'];
    if (empty($Schema['TABLE'][$Table]))
    {
      continue;
    }
    $Column = $Results[$i]['column_name'];
    if (empty($Schema['TABLE'][$Table][$Column]))
    {
      $SQL = "DROP VIEW \"$View\";";
      if ($Debug)
      {
        print "$SQL\n";
      }
      else
      {
        $results = pg_query($PG_CONN, $SQL);
        DBCheckResult($results, $SQL, __FILE__,__LINE__);
      }
    }
  }
  /************************************/
  /* Delete columns/tables */
  /************************************/
  print "  Removing obsolete columns\n";
  flush();
  if (!empty($Curr['TABLE'])) foreach ($Curr['TABLE'] as $Table => $Columns)
  {
    if (empty($Table))
    {
      continue;
    }
    /* only delete from tables I know */
    if (empty($Schema['TABLE'][$Table]))
    {
      continue;
    }
    foreach ($Columns as $Column => $Val)
    {
      if (empty($Column))
      {
        continue;
      }
      if (empty($Schema['TABLE'][$Table][$Column]))
      {
        $SQL = "ALTER TABLE \"$Table\" DROP COLUMN \"$Column\";";
        if ($Debug)
        {
          print "$SQL\n";
        }
        else
        {
          $results = pg_query($PG_CONN, $SQL);
          DBCheckResult($results, $SQL, __FILE__,__LINE__);
        }
      }
    }
  }

  /************************************/
  /* Commit changes */
  /************************************/
  $results = pg_query($PG_CONN, "COMMIT;");
  DBCheckResult($results, $SQL, __FILE__,__LINE__);

  /************************************/
  /* Flush any cached data. */
  /************************************/
  ReportCachePurgeAll();

  /* restore DB timeouts */
  pg_query($PG_CONN, "SET statement_timeout = $Statement_Timeout;");
  return false;
} // ApplySchema()

/**
 * \brief Load the schema from the db into an array.
 **/
function GetSchema()
{
  global $PG_CONN;
  global $SysConf;

  $Schema = array();
  /***************************/
  /* Get the tables */
  /***************************/
  $SQL = "SELECT class.relname AS table,
  attr.attnum AS ordinal,
  attr.attname AS column_name,
  type.typname AS type,
  attr.atttypmod-4 AS modifier,
  attr.attnotnull AS notnull,
  attrdef.adsrc AS default,
  col_description(attr.attrelid, attr.attnum) AS description
  FROM pg_class AS class
  INNER JOIN pg_attribute AS attr ON attr.attrelid = class.oid
  AND attr.attnum > 0
  INNER JOIN pg_type AS type ON attr.atttypid = type.oid
  INNER JOIN information_schema.tables AS tab ON class.relname = tab.table_name
  AND tab.table_type = 'BASE TABLE'
  AND tab.table_schema = 'public'
  LEFT OUTER JOIN pg_attrdef AS attrdef ON adrelid = attrelid
  AND adnum = attnum
  ORDER BY class.relname,attr.attnum;
  ";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__,__LINE__);
  $Results = pg_fetch_all($result);
  //echo "DB: GetSchema, after query, results are:"; print_r($Results[0]['table']) . "\n";
  for ($i = 0;!empty($Results[$i]['table']);$i++)
  {
    $R = & $Results[$i];
    $Table = $R['table'];
    //echo "processing tabel $Table\n";
    if (preg_match('/[0-9]/', $Table))
    {
      continue;
    }
    $Column = $R['column_name'];
    $Type = $R['type'];
    if ($Type == 'bpchar')
    {
      $Type = "char";
    }
    if ($R['modifier'] > 0)
    {
      $Type.= '(' . $R['modifier'] . ')';
    }
    $Desc = str_replace("'", "''", $R['description']);
    $Schema['TABLEID'][$Table][$R['ordinal']] = $Column;
    if (!empty($Desc))
    {
      $Schema['TABLE'][$Table][$Column]['DESC'] = "COMMENT ON COLUMN \"$Table\".\"$Column\" IS '$Desc';";
    }
    else
    {
      $Schema['TABLE'][$Table][$Column]['DESC'] = "";
    }
    $Schema['TABLE'][$Table][$Column]['ADD'] = "ALTER TABLE \"$Table\" ADD COLUMN \"$Column\" $Type;";
    $Schema['TABLE'][$Table][$Column]['ALTER'] = "ALTER TABLE \"$Table\"";
    $Alter = "ALTER COLUMN \"$Column\"";
    // create the index UPDATE to get rid of php notice
    $Schema['TABLE'][$Table][$Column]['UPDATE'] = "";
    if ($R['notnull'] == 't')
    {
      $Schema['TABLE'][$Table][$Column]['ALTER'].= " $Alter SET NOT NULL";
    }
    else
    {
      $Schema['TABLE'][$Table][$Column]['ALTER'].= " $Alter DROP NOT NULL";
    }
    if ($R['default'] != '')
    {
      $R['default'] = preg_replace("/::bpchar/", "::char", $R['default']);
      $Schema['TABLE'][$Table][$Column]['ALTER'].= ", $Alter SET DEFAULT " . $R['default'];
      $Schema['TABLE'][$Table][$Column]['UPDATE'].= "UPDATE $Table SET $Column=" . $R['default'];
    }
    $Schema['TABLE'][$Table][$Column]['ALTER'].= ";";
  }
  /***************************/
  /* Get Views */
  /***************************/
  $user = $SysConf['DBCONF']['user'];
  $SQL = "SELECT viewname,definition FROM pg_views WHERE viewowner = '$user'";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__,__LINE__);
  $Results = pg_fetch_all($result);
  for ($i = 0;!empty($Results[$i]['viewname']);$i++)
  {
    $SQL = "CREATE VIEW \"" . $Results[$i]['viewname'] . "\" AS " . $Results[$i]['definition'];
    $Schema['VIEW'][$Results[$i]['viewname']] = $SQL;
  }
  /***************************/
  /* Get Sequence */
  /***************************/
  $SQL = "SELECT relname
  FROM pg_class
  WHERE relkind = 'S'
  AND relnamespace IN (
  SELECT oid
  FROM pg_namespace
  WHERE nspname NOT LIKE 'pg_%'
  AND nspname != 'information_schema'
  );";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__,__LINE__);
  $Results = pg_fetch_all($result);
  for ($i = 0;!empty($Results[$i]['relname']);$i++)
  {
    $SQL = "CREATE SEQUENCE \"" . $Results[$i]['relname'] . "\" START 1;";
    $Schema['SEQUENCE'][$Results[$i]['relname']] = $SQL;
  }
  /***************************/
  /* Get Constraints */
  /***************************/
  $SQL = "SELECT c.conname AS constraint_name,
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
    WHEN 'd' THEN 'SET DEFAULT' END AS on_delete, CASE confmatchtype
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
  ORDER BY constraint_name,table_name;
  ";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__,__LINE__);
  $Results = pg_fetch_all($result);
  /* Constraints use indexes into columns.  Covert those to column names. */
  for ($i = 0;!empty($Results[$i]['constraint_name']);$i++)
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
        $Key.= ",";
      }
      $Key.= '"' . $Schema['TABLEID'][$Results[$i]['table_name']][$K] . '"';
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
        $Key.= ",";
      }
      $Key.= '"' . $Schema['TABLEID'][$Results[$i]['references_table']][$K] . '"';
    }
    $Results[$i]['fk_constraint_key'] = $Key;
  }
  /* Save the constraint */
  /** There are different types of constraints that must be stored in order **/
  /** CONSTRAINT: PRIMARY KEY **/
  for ($i = 0;!empty($Results[$i]['constraint_name']);$i++)
  {
    if ($Results[$i]['type'] != 'PRIMARY KEY')
    {
      continue;
    }
    $SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
    $SQL.= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
    $SQL.= " " . $Results[$i]['type'];
    $SQL.= " (" . $Results[$i]['constraint_key'] . ")";
    if (!empty($Results[$i]['references_table']))
    {
      $SQL.= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
      $SQL.= " (" . $Results[$i]['fk_constraint_key'] . ")";
    }
    $SQL.= ";";
    $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
    $Results[$i]['processed'] = 1;
  }
  /** CONSTRAINT: UNIQUE **/
  for ($i = 0;!empty($Results[$i]['constraint_name']);$i++)
  {
    if ($Results[$i]['type'] != 'UNIQUE')
    {
      continue;
    }
    $SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
    $SQL.= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
    $SQL.= " " . $Results[$i]['type'];
    $SQL.= " (" . $Results[$i]['constraint_key'] . ")";
    if (!empty($Results[$i]['references_table']))
    {
      $SQL.= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
      $SQL.= " (" . $Results[$i]['fk_constraint_key'] . ")";
    }
    $SQL.= ";";
    $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
    $Results[$i]['processed'] = 1;
  }

  /** CONSTRAINT: FOREIGN KEY **/
  for ($i = 0;!empty($Results[$i]['constraint_name']);$i++)
  {
    if ($Results[$i]['type'] != 'FOREIGN KEY')
    {
      continue;
    }
    $SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
    $SQL.= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
    $SQL.= " " . $Results[$i]['type'];
    $SQL.= " (" . $Results[$i]['constraint_key'] . ")";
    if (!empty($Results[$i]['references_table']))
    {
      $SQL.= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
      $SQL.= " (" . $Results[$i]['fk_constraint_key'] . ")";
    }

    if (!empty($Results[$i]['on_update']))
      $SQL.= " ON UPDATE " . $Results[$i]['on_update'];
    if (!empty($Results[$i]['on_delete']))
      $SQL.= " ON DELETE " . $Results[$i]['on_delete'];

    $SQL.= ";";
    $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
    $Results[$i]['processed'] = 1;
  }

  /** CONSTRAINT: ALL OTHERS **/
  for ($i = 0;!empty($Results[$i]['constraint_name']);$i++)
  {
    if ($Results[$i]['processed'] == 1) continue;

    $SQL = "ALTER TABLE \"" . $Results[$i]['table_name'] . "\"";
    $SQL.= " ADD CONSTRAINT \"" . $Results[$i]['constraint_name'] . '"';
    $SQL.= " " . $Results[$i]['type'];
    $SQL.= " (" . $Results[$i]['constraint_key'] . ")";
    if (!empty($Results[$i]['references_table']))
    {
      $SQL.= " REFERENCES \"" . $Results[$i]['references_table'] . "\"";
      $SQL.= " (" . $Results[$i]['fk_constraint_key'] . ")";
    }
    $SQL.= ";";
    $Schema['CONSTRAINT'][$Results[$i]['constraint_name']] = $SQL;
    $Results[$i]['processed'] = 1;
  }
  /***************************/
  /* Get Index */
  /***************************/
  $SQL = "SELECT tablename AS table, indexname AS index, indexdef AS define
  FROM pg_indexes
  INNER JOIN information_schema.tables ON table_name = tablename
  AND table_type = 'BASE TABLE'
  AND table_schema = 'public'
  AND schemaname = 'public'
  ORDER BY tablename,indexname;
  ";
  $result = pg_query($PG_CONN, $SQL);
  DBCheckResult($result, $SQL, __FILE__,__LINE__);
  $Results = pg_fetch_all($result);
  for ($i = 0;!empty($Results[$i]['table']);$i++)
  {
    /* UNIQUE constraints also include indexes. */
    if (empty($Schema['CONSTRAINT'][$Results[$i]['index']]))
    {
      $Schema['INDEX'][$Results[$i]['table']][$Results[$i]['index']] = $Results[$i]['define'] . ";";
    }
  }
  if (0)
  {
    /***************************/
    /* Get Functions */
    /***************************/
    // prosrc
    // proretset == setof
    $SQL = "SELECT proname AS name,
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
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__,__LINE__);
    $Results = pg_fetch_all($result);
    for ($i = 0;!empty($Results[$i]['proname']);$i++)
    {
      $SQL = "CREATE or REPLACE function " . $Results[$i]['proname'] . "()";
      $SQL.= ' RETURNS ' . "TBD" . ' AS $$';
      $SQL.= " " . $Results[$i]['prosrc'];
      $SQL.= ";";
      $Schema['FUNCTION'][$Results[$i]['proname']] = $SQL;
    }
  }
  unset($Schema['TABLEID']);
  return ($Schema);
} // GetSchema()


/**
 * \brief Export the schema of the connected ($PG_CONN) database to a
 *        file in the format readable by GetSchema().
 *
 * @param string $Filename path to the file to store the schema in.
 *
 * @return false=success, on error return string with error message.
**/
function ExportSchema($Filename = NULL)
{
	global $Name;
	
	if (empty($Filename)) $Filename = stdout;
	$Schema = GetSchema();
	$Fout = fopen($Filename, "w");
	if (!$Fout) return ("Failed to write to $Filename\n");

	fwrite($Fout, "<?php\n");
	fwrite($Fout, "/* This file is generated by " . $Name . " */\n");
	fwrite($Fout, "/* Do not manually edit this file */\n\n");
	fwrite($Fout, '  $Schema=array();' . "\n\n");
	foreach($Schema as $K1 => $V1) {
		$K1 = str_replace('"', '\"', $K1);
		$A1 = '  $Schema["' . $K1 . "\"]";
		if (!is_array($V1)) {
			$V1 = str_replace('"', '\"', $V1);
			fwrite($Fout, "$A1 = \"$V1\";\n");
		}
		else {
			foreach($V1 as $K2 => $V2) {
				$K2 = str_replace('"', '\"', $K2);
				$A2 = $A1 . '["' . $K2 . '"]';
				if (!is_array($V2)) {
					$V2 = str_replace('"', '\"', $V2);
					fwrite($Fout, "$A2 = \"$V2\";\n");
				}
				else {
					foreach($V2 as $K3 => $V3) {
						$K3 = str_replace('"', '\"', $K3);
						$A3 = $A2 . '["' . $K3 . '"]';
						if (!is_array($V3)) {
							$V3 = str_replace('"', '\"', $V3);
							fwrite($Fout, "$A3 = \"$V3\";\n");
						}
						else {
							foreach($V3 as $K4 => $V4) {
								$V4 = str_replace('"', '\"', $V4);
								$A4 = $A3 . '["' . $K4 . '"]';
								fwrite($Fout, "$A4 = \"$V4\";\n");
							} /* K4 */
							fwrite($Fout, "\n");
						}
					} /* K3 */
					fwrite($Fout, "\n");
				}
			} /* K2 */
			fwrite($Fout, "\n");
		}
	} /* K1 */
	fwrite($Fout, "?>\n");
	fclose($Fout);
	return false;
} // ExportSchema()


/**
 * MakeFunctions
 * \brief Create any required DB functions.
 */
function MakeFunctions($Debug)
{
  global $PG_CONN;
  print "  Applying database functions\n";
  flush();
  /********************************************
   GetRunnable() is a DB function for listing the runnable items
   in the jobqueue. This is used by the scheduler.
   ********************************************/
  $SQL = '
CREATE or REPLACE function getrunnable() returns setof jobqueue as $$
DECLARE
  jqrec jobqueue;
  jqrec_test jobqueue;
  jqcurse CURSOR FOR SELECT *
    FROM jobqueue
    INNER JOIN job
      ON jq_starttime IS NULL
      AND jq_end_bits < 2
      AND job_pk = jq_job_fk
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
  if ($Debug)
  {
    print "$SQL;\n";
  }
  else
  {
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__,__LINE__);
  }
  /********************************************
   * uploadtree2path(uploadtree_pk integer) is a DB function that returns
   * the non-artifact parents of an uploadtree_pk
   ********************************************/
  $SQL = '
CREATE or REPLACE function uploadtree2path(uploadtree_pk_in int) returns setof uploadtree as $$
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
  if ($Debug)
  {
    print "$SQL;\n";
  }
  else
  {
    $result = pg_query($PG_CONN, $SQL);
    DBCheckResult($result, $SQL, __FILE__,__LINE__);
  }
  return;
} // MakeFunctions()

?>
