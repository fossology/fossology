#!/usr/bin/php
<?php
/*
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
 * schema-update
 * \brief apply the schema to the fossology db using the supplied data file
 *
 * @param string $filePath
 * @return 0 for success, 1 for failure
 *
 * @version "$Id$"
 */

/*
 Note: can't use the UI plugins, they may not be initialized.  On install, the are
 initialized AFTER this script is run.  Well we could init them, cheaper to just
 open the db.
 */
global $GlobalReady;
$GlobalReady = 1;

//require_once (dirname(__FILE__)) . '/../share/fossology/php/pathinclude.php';
require_once '/usr/local/share/fossology/php/pathinclude.php';
global $LIBEXECDIR;
//require_once "$LIBEXECDIR/libschema.php";
require_once "./libschema.php";

global $PGCONN;

$usage = "Usage: " . basename($argv[0]) . " [options]
  -f <filepath> pathname to schema data file
  -h this help usage";

$Options = getopt('f:h');
if (empty($Options))
{
	print "$usage\n";
	exit(1);
}

if (array_key_exists('h',$Options))
{
	print "$usage\n";
	exit(0);
}

if (array_key_exists('f', $Options))
{
	$Filename = $Options['f'];
}
if((strlen($Filename)) == 0)
{
	print "Error, no filename supplied\n$usage\n";
	exit(1);
}

$Filename = "./testcore-schema-noserver.dat";

// get db params and open connection to db.

echo "connecting to db randodb\n";
$dbOptions = 'dbname=randodb user=rando password=rando';
$PGCONN = dbConnect($dbOptions);

ApplySchema($Filename, 0);

  /***********************************************************
  ApplySchema(): Apply the current schema from a file.
  NOTE: The order for add/delete is important!
  ***********************************************************/
  function ApplySchema($Filename = NULL, $Debug, $Verbose = 1) 
{
    global $PGCONN;
    print "Applying database schema\n";
    flush();
    /**************************************/
    /** BEGIN: Term list from ExportTerms() **/
    /**************************************/
if (!file_exists($Filename))
{
echo $Filename, " does not exist\n";
}
echo "require $Filename\n";
    require_once ($Filename); /* this will DIE if the file does not exist. */
echo "got  $Filename\n";
    /**************************************/
    /** END: Term list from ExportTerms() **/
    /**************************************/
    /* Very basic sanity check (so we don't delete everything!) */
    if ((count($Schema['TABLE']) < 5) || (count($Schema['VIEW']) < 1) || (count($Schema['SEQUENCE']) < 5) || (count($Schema['INDEX']) < 5) || (count($Schema['CONSTRAINT']) < 5)) {
      print "FATAL: Schema from '$Filename' appears invalid.\n";
      flush();
      exit(1);
    }
    pg_query($PGCONN, "SET statement_timeout = 0;"); /* turn off DB timeouts */
    pg_query($PGCONN, "BEGIN;");
    $Curr = GetSchema();
    //print "SUPDB: Current Schema returned by GetSchema:\n"; print_r($Curr) . "\n";
    /* The gameplan: Make $Curr look like $Schema. */
    // print "<pre>"; print_r($Schema); print "</pre>";
    /************************************/
    /* Add sequences */
    /************************************/
    if (!empty($Schema['SEQUENCE'])) 
      foreach($Schema['SEQUENCE'] as $Name => $SQL) 
      {
        if (empty($Name)) { echo "warning empty sequence in .dat\n"; continue; }
        if ($Curr['SEQUENCE'][$Name] == $SQL) { continue; }
      if ($Debug) { print "$SQL\n"; }
      else {
        $result = pg_query($PGCONN, $SQL);
        checkresult($result, $SQL, __LINE__);
        }
      }

    /************************************/
    /* Add tables/columns (dependent on sequences for default values) */
    /************************************/
    if (!empty($Schema['TABLE'])) foreach($Schema['TABLE'] as $Table => $Columns) {
      if (empty($Table)) {
        continue;
      }
      if (!TblExist($Table)) {
        $SQL = "CREATE TABLE \"$Table\" ();";
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
          checkresult($result, $SQL, __LINE__);
        }
      }
      foreach($Columns as $Column => $Val) {
        if ($Curr['TABLE'][$Table][$Column]['ADD'] != $Val['ADD']) {
          $Rename = "";
          if (ColExist($Table, $Column)) {
            /* The column exists, but it looks different!
            Solution: Delete the column! */
            $Rename = $Column . "_old";
            $SQL = "ALTER TABLE \"$Table\" RENAME COLUMN \"$Column\" TO \"$Rename\";";
            if ($Debug) {
              print "$SQL\n";
            }
            else {
              $result = pg_query($PGCONN, $SQL);
              checkresult($result, $SQL, __LINE__);
            }
          }
          if ($Debug) {
            print $Val['ADD'] . "\n";
          }
          else {
            $SQL = $Val['ADD'];
            $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
          }
          if (!empty($Rename)) {
            /* copy over the old data */
            $SQL = "UPDATE \"$Table\" SET \"$Column\" = \"$Rename\";";
            if ($Debug) {
              print "$SQL\n";
            }
            else {
              $result = pg_query($PGCONN, $SQL);
              checkresult($result, $SQL, __LINE__);
            }
            $SQL = "ALTER TABLE \"$Table\" DROP COLUMN \"$Rename\";";
            if ($Debug) {
              print "$SQL\n";
            }
            else {
              $result = pg_query($PGCONN, $SQL);
              checkresult($result, $SQL, __LINE__);
            }
          }
        }
        if ($Curr['TABLE'][$Table][$Column]['ALTER'] != $Val['ALTER']) {
          if ($Debug) {
            print $Val['ALTER'] . "\n";
          }
          else {
            $SQL = $Val['ALTER'];
            $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
          }
        }
        if ($Curr['TABLE'][$Table][$Column]['DESC'] != $Val['DESC']) {
          if (empty($Val['DESC'])) {
            $SQL = "COMMENT ON COLUMN \"$Table\".\"$Column\" IS '';";
          }
          else {
            $SQL = $Val['DESC'];
          }
          if ($Debug) {
            print "$SQL\n";
          }
          else {
            $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
          }
        }
      }
    }
    /************************************/
    /* Add views (dependent on columns) */
    /************************************/
    if (!empty($Schema['VIEW'])) 
      foreach($Schema['VIEW'] as $Name => $SQL) 
      {
        if (empty($Name)) {
          continue;
        }
        if ($Curr['VIEW'][$Name] == $SQL) {
          continue;
        }
        if (!empty($Curr['VIEW'][$Name])) 
        {
          /* Delete it if it exists and looks different */
          $SQL1 = "DROP VIEW '$Name'";
          if ($Debug) 
          {
            print "$SQL1\n";
          }
          else 
          {
            $result = pg_query($PGCONN, $SQL1);
            checkresult($result, $SQL1, __LINE__);
          }
        }
  
        /* Create the view */
          if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
          checkresult($result, $SQL, __LINE__);
        }
    }
    /************************************/
    /* Delete constraints */
    /* Delete now, so they won't interfere with migrations. */
    /************************************/
    if (!empty($Curr['CONSTRAINT'])) foreach($Curr['CONSTRAINT'] as $Name => $SQL) {
      if (empty($Name)) {
        continue;
      }
      /* Only process tables that I know about */
      $Table = preg_replace("/^ALTER TABLE \"(.*)\" ADD CONSTRAINT.*/", '${1}', $SQL);
      $TableFk = preg_replace("/^.*FOREIGN KEY .* REFERENCES \"(.*)\" \(.*/", '${1}', $SQL);
      if ($TableFk == $SQL) {
        $TableFk = $Table;
      }
      /* If I don't know the primary or foreign table... */
      if (empty($Schema['TABLE'][$Table]) && empty($Schema['TABLE'][$TableFk])) {
        continue;
      }
      /* If it is already set correctly, then skip it. */
      if ($Schema['CONSTRAINT'][$Name] == $SQL) {
        continue;
      }
      $SQL = "ALTER TABLE \"$Table\" DROP CONSTRAINT \"$Name\" CASCADE;";
      if ($Debug) {
        print "$SQL\n";
      }
      else {
        $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
      }
    }
    /* Reload current since the CASCADE may have changed things */
    $Curr = GetSchema();
    /************************************/
    /* Delete indexes */
    /************************************/
    $Curr = GetSchema(); /* constraints and indexes are linked, recheck */
    if (!empty($Curr['INDEX'])) foreach($Curr['INDEX'] as $Table => $IndexInfo) {
      if (empty($Table)) {
        continue;
      }
      /* Only delete indexes on known tables */
      if (empty($Schema['TABLE'][$Table])) {
        continue;
      }
      foreach($IndexInfo as $Name => $SQL) {
        if (empty($Name)) {
          continue;
        }
        /* Only delete indexes that are different */
        if ($Schema['INDEX'][$Table][$Name] == $SQL) {
          continue;
        }
        $SQL = "DROP INDEX \"$Name\";";
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
        }
      }
    }
    /************************************/
    /* Add indexes (dependent on columns) */
    /************************************/
    if (!empty($Schema['INDEX'])) foreach($Schema['INDEX'] as $Table => $IndexInfo) {
      if (empty($Table)) {
        continue;
      }
// bobg
    if (!array_key_exists($Table, $Schema["TABLE"])) 
    {
      echo "skipping orphan table: $Table\n";
      continue;
    }

      foreach($IndexInfo as $Name => $SQL) {
        if (empty($Name)) {
          continue;
        }
        if ($Curr['INDEX'][$Table][$Name] == $SQL) {
          continue;
        }
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
          checkresult($result, $SQL, __LINE__);
        }
        $SQL = "REINDEX INDEX \"$Name\";";
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
        }
      }
    }
    /************************************/
    /* Add constraints (dependent on columns, views, and indexes) */
    /************************************/
    $Curr = GetSchema(); /* constraints and indexes are linked, recheck */
    if (!empty($Schema['CONSTRAINT'])) {
      /* Constraints must be added in the correct order! */
      /* CONSTRAINT: PRIMARY KEY */
      foreach($Schema['CONSTRAINT'] as $Name => $SQL) {
        if (empty($Name)) {
          continue;
        }
        if ($Curr['CONSTRAINT'][$Name] == $SQL) {
          continue;
        }
        if (!preg_match("/PRIMARY KEY/", $SQL)) {
          continue;
        }
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
        }
      }
      /* CONSTRAINT: UNIQUE */
      foreach($Schema['CONSTRAINT'] as $Name => $SQL) {
        if (empty($Name)) {
          continue;
        }
        if ($Curr['CONSTRAINT'][$Name] == $SQL) {
          continue;
        }
        if (!preg_match("/UNIQUE/", $SQL)) {
          continue;
        }
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
        }
      }
      /* CONSTRAINT: FOREIGN KEY */
      foreach($Schema['CONSTRAINT'] as $Name => $SQL) {
        if (empty($Name)) {
          continue;
        }
        if ($Curr['CONSTRAINT'][$Name] == $SQL) {
          continue;
        }
        if (!preg_match("/FOREIGN KEY/", $SQL)) {
          continue;
        }
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
        }
      }
      /* All other constraints */
      foreach($Schema['CONSTRAINT'] as $Name => $SQL) {
        if (empty($Name)) {
          continue;
        }
        if ($Curr['CONSTRAINT'][$Name] == $SQL) {
          continue;
        }
        if (preg_match("/PRIMARY KEY/", $SQL)) {
          continue;
        }
        if (preg_match("/UNIQUE/", $SQL)) {
          continue;
        }
        if (preg_match("/FOREIGN KEY/", $SQL)) {
          continue;
        }
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
        }
      }
    } /* Add constraints */
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
  WHERE table_catalog='fossology'
  ORDER BY view_name,table_name,column_name;";
    $result = pg_query($PGCONN, $SQL);
            checkresult($result, $SQL, __LINE__);
    $Results = pg_fetch_all($result);
    for ($i = 0;!empty($Results[$i]['view_name']);$i++) {
      $View = $Results[$i]['view_name'];
      $Table = $Results[$i]['table_name'];
      if (empty($Schema['TABLE'][$Table])) {
        continue;
      }
      $Column = $Results[$i]['column_name'];
      if (empty($Schema['TABLE'][$Table][$Column])) {
        $SQL = "DROP VIEW \"$View\";";
        if ($Debug) {
          print "$SQL\n";
        }
        else {
          $results = pg_query($PGCONN, $SQL);
            checkresult($results, $SQL, __LINE__);
        }
      }
    }
    /************************************/
    /* Delete columns/tables */
    /************************************/
    print "  Removing obsolete columns\n";
    flush();
    if (!empty($Curr['TABLE'])) foreach($Curr['TABLE'] as $Table => $Columns) {
      if (empty($Table)) {
        continue;
      }
      /* only delete from tables I know */
      if (empty($Schema['TABLE'][$Table])) {
        continue;
      }
      foreach($Columns as $Column => $Val) {
        if (empty($Column)) {
          continue;
        }
        if (empty($Schema['TABLE'][$Table][$Column])) {
          $SQL = "ALTER TABLE \"$Table\" DROP COLUMN \"$Column\";";
          if ($Debug) {
            print "$SQL\n";
          }
          else {
            $results = pg_query($PGCONN, $SQL);
            checkresult($results, $SQL, __LINE__);
          }
        }
      }
    }
    /************************************/
    /* Commit changes */
    /************************************/
    print "  Committing changes...\n";
    flush();
    $results = pg_query($PGCONN, "COMMIT;");
    checkresult($results, $SQL, __LINE__);
echo "Success!\n";
    return;
  } // ApplySchema()

	?>
