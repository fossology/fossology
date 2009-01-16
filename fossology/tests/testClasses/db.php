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

/**
 * db class, my experiment in trying to replace db-core or parts of it
 * using a standard object model.  The tests need to access the db as
 * well.  For now will use the testenvironment file, but should change
 * to use program VARS instead.....
 *
 * @param string $options e.g. db parameters user=foobar; password=ha;
 *
  * @version "$Id: $"
 *
 * Created on Jan 15, 2009
 */

 require_once(dirname(__FILE__) . '../TestEnvironment.php');

 class db
 {
   private $_pg_conn;
   private $pg_rows;
   private $pg_Error;
   private $dbName;
   private $dbUser;
   private $dbPassword;
   private $dbHost;

   function __construct($options=NULL) {
     if(isnull($options)) {
        $this->dbHost = parse_url($URL, PHP_URL_HOST);
        //$this->dbName   = $DBNAME;
        $this->dbUser     = $USER;
        $this->dbPassword = $PASSWORD;

        _docon();
     }
     else {
        _docon($options);
     }
     if (!isset ($this->_pg_conn)) {
       $this->pg_ERROR = 1;
       return (FALSE);
     }
     $this->pg_Error = 0;
     return (TRUE);
   } // __construct

/**
 * connect
 *
 * public function to connect to the db, uses class properties or passed
 * in options.
 *
 * @param string $options e.g. "user=fonzy; password=thefonz;"
 *
 * @return connection resource
 */
   public function connect($options=NULL) {
    if (is_resource($this->_db_conn)) {
      return($this->_db_conn);
    }
    else {
      _do_con();
      return($this->_db_conn);
    }
   } // connect

/**
 * _docon
 *
 * private function that creates a persistent connection to a data base.
 * Uses class properties for the connect parameters or accepts then as a
 * set of ; seperated strings.
 *
 * Sets _pg_conn and pg_Error
 */

   private function _docon($options=NULL) {
    // fix the hardcode below, enhance create test env...
    $dbname = 'fossology';

    if (isnull($options))
    {
      $this->_pg_conn = pg_pconnect("dbname=$dbname", "$host=$this->dbHost",
       "user=$this->dbUser", "password=$this->dbPassword" );
    } else
    {
      $this->_pg_conn = pg_pconnect(str_replace(";", " ", $options));
    }
    if (!isset ($this->_pg_conn)) {
      $this->pg_Error = 1;
      return (0);
    }
    $this->pg_Error = 0;
    return (1);
  }
  /**
  * query
  *
  * perform a query, return any results.
  */

  public function dbQuery($sql) {
    /*
     * sql query's can return False on error or NULL (no error, no
     * results)
     *
     * This code is pretty much copied from Action in db-core.  Look for
     * areas where better error checking can be done (e.g. where the @
     * is used)
     */
    $this->pg_rows = array();
    if (!$this->_pg_conn) {
      return($rows);  // think about this, is false better?
    }
    if (empty($sql)) {
      return($rows);    // same as above
    }

    @ $result = pg_query($this->_pg_conn, $Command);

    /* Error handling */
    if ($result == FALSE) {
      $this->Error = 1;
      //$PGError = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
      $PGError = pg_last_error($this->_pg_conn);
      if ($this->Debug) {
        print "--------\n";
        print "SQL failed: $Command\n";
        print $PGError;
      }
      $this->pg_rows = 0;
    }
    else {
      $this->Error = 0;
      $this->pg_rows = pg_affected_rows($result);
    }
    /* if the query returned nothing then just return*/
    if (!isset ($result)) {
      return;
    }
    @ $rows = pg_fetch_all($result);

    if (!is_array($rows)) {
      $rows = array ();
    }
    @ pg_free_result($result);
    return $rows;
  }
 } // class db
?>
