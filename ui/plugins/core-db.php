<?php
/***********************************************************
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
***********************************************************/

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

$DB = NULL; /* global pointer used by everyone... */
class db_access extends FO_Plugin
  {
  var $Name="db";
  var $Version="1.0";
  var $PluginLevel=100;
  var $LoginFlag=0;

  var $Debug=0; /* 0=none, 1=errors, 2=show all SQL */

  var $_pg_conn = NULL;	/* connection to database */
  var $_pg_rows = 0;	/* number of affected rows */
  var $Error = 0;	/* was the last operating an error? */

  /***********************************************************
   PostInitialize(): This function is called before the plugin
   is used and after all plugins have been initialized.
   If there is any initialization step that is dependent on other
   plugins, put it here.
   Returns true on success, false on failure.
   NOTE: Do not assume that the plugin exists!  Actually check it!
   ***********************************************************/
  function PostInitialize()
    {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_VALID) { return(0); } // don't run
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val)
      {
      $id = plugin_find_id($val);
      if ($id < 0) { $this->Destroy(); return(0); }
      }

    $this->State = PLUGIN_STATE_READY;
    global $DB;
    $DB = $this;
    return($this->State == PLUGIN_STATE_READY);
    } // PostInitialize()

  /*******************************************************
   db_init(): Establish a connection to the database.
   This is only called if the DB is needed.
   NOTE: There is no "db_close" since PHP automatically
   closes connections when everything closes.
   "$Options" = an optional list of attributes for
   connecting to the database. E.g.:
     "dbname=text host=text user=text password=text"
   *******************************************************/
  function db_init($Options="")
    {
    global $DATADIR, $PROJECT;
    if (isset($this->_pg_conn)) { return(1); }
    $path="$DATADIR/dbconnect/$PROJECT";
    if (empty($Options))
      {
      $this->_pg_conn = pg_pconnect(str_replace(";", " ", file_get_contents($path)));
      }
    else
      {
      $this->_pg_conn = pg_pconnect(str_replace(";", " ", $Options));
      }
    if (!isset($this->_pg_conn)) return(0);
    $this->Error = 0;
    return(1);
    }

  /***********************************************************
   GetAffectedRows(): Returns the number of affected rows from
   the last call to Action().
   ***********************************************************/
  function GetAffectedRows	()
    {
    return($this->_pg_rows);
    } // GetAffectedRows()

  /***********************************************************
   Action(): This function performs an SQL command and returns
   a structure containing the results.
   The $Command is the SQL to process.
   If given, $PGError will return the PGSQL_DIAG_SQLSTATE error code
   ***********************************************************/
  function Action($Command, &$PGError=0)
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    if (!$this->db_init()) { return; }
    if ($this->Debug)
	{
	/* When using pg_query(), you need to use pg_set_error_verbosity().
	   Otherwise, pg_last_error() returns nothing. */
	pg_set_error_verbosity($this->_pg_conn,PGSQL_ERRORS_VERBOSE);
	if ($this->Debug > 1) { print "DB.Action('$Command')\n"; }
	}
    @$result = pg_query($this->_pg_conn,$Command);
    
    /* Error handling */
    if ($result == FALSE)
      {
      $this->Error=1;
      //$PGError = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
      $PGError = pg_last_error($this->_pg_conn);
      if ($this->Debug)
	{
	print "--------\n";
	print "SQL failed: $Command\n";
	print $PGError;
	}
      $this->_pg_rows = 0;
      }
    else
      {
      $this->Error=0;
      $this->_pg_rows = pg_affected_rows($result);
      }

    if (!isset($result)) return;
    if ($this->Debug > 2)
      {
      $rows = array();
      while ($r = pg_fetch_array($result))
	{
	print "Row: " . count($rows) . "\n";
	$rows[] = $r;
	}
      }
    else
      {
      @$rows = pg_fetch_all($result);
      }

    if (!is_array($rows)) $rows = array();
    @pg_free_result($result);
    return $rows;

    /*****
     NOTE: Where's the error handling?
     From the UI, there is not much that can cause an error, and even
     less to resolve an error.
     If an error happens, then NULL is returned.
     If no error, then an array is returned (array may be empty).
     *****/
    }

  /***********************************************************
   Execute(): Run a prepared SQL statement from Prepare().
   NOTE: Statements are actually version specific.
   ***********************************************************/
  function Execute($Prep,$Command)
    {
    global $SVN_REV;
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    if (!$this->db_init()) { return; }
    $Prep .= "_$SVN_REV";
    if ($this->Debug)
	{
	/* When using pg_query(), you need to use pg_set_error_verbosity().
	   Otherwise, pg_last_error() returns nothing. */
	pg_set_error_verbosity($this->_pg_conn,PGSQL_ERRORS_VERBOSE);
	if ($this->Debug > 1) { print "DB.Execute('$Prep','$Command')\n"; }
	}
    $result = pg_execute($this->_pg_conn,$Prep,$Command);
    if (!isset($result))
	{
	$this->Error=1;
	return;
	}
    $this->Error=0;
    $rows = pg_fetch_all($result);
    if (!is_array($rows)) $rows = array();
    pg_free_result($result);
    return $rows;
    }

  /***********************************************************
   Prepare(): This prepares an SQL statement for execution.
   $Prep is the name of the prepared statement.
   NOTE: Statements are actually version specific.
   ***********************************************************/
  function Prepare($Prep,$Command)
    {
    global $SVN_REV;
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    if (!$this->db_init()) { return; }
    $Prep .= "_$SVN_REV";
    if ($this->Debug)
	{
	/* When using pg_query(), you need to use pg_set_error_verbosity().
	   Otherwise, pg_last_error() returns nothing. */
	pg_set_error_verbosity($this->_pg_conn,PGSQL_ERRORS_VERBOSE);
	if ($this->Debug > 1) { print "DB.Prepare('$Prep','$Command')\n"; }
	}
    /* Because the DB connection is shared, $Prep may already exist! */
    $result = @pg_prepare($this->_pg_conn,$Prep,$Command);
    if (!isset($result))
	{
	$this->Error=1;
	return;
	}
    $this->Error=0;
    return;
    }

  /***********************************************************
   ColExist(): Check if Column $Col,exists in Table
               Return True if the column exists in Table
               Return False if either the column or the table
               do not exist
   This could also be done via a pg_query > pg_fetch_object.
   ***********************************************************/
  function ColExist($Table,$Col)
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    global $DB;
    if (empty($DB)) { return(0); }
    $Results = $DB->Action("SELECT 'SUCCESS' FROM pg_attribute, pg_type 
              WHERE typrelid=attrelid AND typname = '$Table'
	      AND attname='$Col' LIMIT 1");
    if (count($Results) > 0) { return(1); }
    return(0);
    }

  /***********************************************************
   TblExist(): Check if Table $Col,exists
               Return True if the table exists
               Return False if the table does not exist
   This could also be done via a pg_query > pg_fetch_object.
   ***********************************************************/
  function TblExist($Table)
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    global $DB;
    if (empty($DB)) { return(0); }
    $Results = $DB->Action("SELECT count(*) AS count FROM pg_type
			    WHERE typname = '$Table'");
    if ($Results[0]['count'] > 0) { return(1); }
    return(0);
    }
}

$NewPlugin = new db_access;
$NewPlugin->Initialize();

?>
