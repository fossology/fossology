<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * db class, my experiment in trying to replace db-core or parts of it
 * using a standard object model.  The tests need to access the db as
 * well.  For now will use the testenvironment file, but should change
 * to use program VARS instead.....
 *
 * @param string $options e.g. db parameters user=foobar; password=ha;
 *
 * @version "$Id$"
 *
 * Created on Jan 15, 2009
 */

require_once (dirname(__FILE__) . '/../TestEnvironment.php');
require_once (dirname(__FILE__) . '/../../ui/common/common-ui.php');

global $URL;
global $USER;
global $PASSWORD;

class db {
  private $_pg_conn;
  private $pg_rows;
  protected $pg_Error;
  private $dbName;
  private $dbUser;
  private $dbPassword;
  private $dbHost;
  public  $Debug;

  function __construct($options = NULL) {
    global $URL;
    global $USER;
    global $PASSWORD;

    if (is_null($options)) {
      //$this->dbHost = parse_url($URL, PHP_URL_HOST);
      $this->dbHost = 'localhost';
      //$this->dbName   = $DBNAME;
      $this->dbUser = $USER;
      $this->dbPassword = $PASSWORD;

      $this->_docon();
    }
    else {
      $this->_docon($options);
    }
    if(is_resource($this->_pg_conn)) {
      $this->pg_Error = 0;
      return (TRUE);
    }
    else {
      $this->pg_ERROR = 1;
      return (FALSE);
    }
  } // __construct

  public function get_pg_ERROR() {
    return($this->pg_ERROR);
  }

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
  public function connect($options = NULL) {
    if (is_resource($this->_pg_conn)) {
      return ($this->_pg_conn);
    }
    else {
      $this->_docon($options);
      return ($this->_pg_conn);
    }
  } // connect

  /**
   * _docon
   *
   * private function that creates a persistent connection to a data base.
   * Uses class properties for the connect parameters or accepts them as a
   * set of key value pairs terminated with a ;.
   *
   * Sets _pg_conn and pg_Error
   */

  private function _docon($options = NULL) {
    // fix the hardcode below, enhance create test env...
    $dbname = 'fossology';

    if (is_null($options)) {
      $this->_pg_conn = pg_pconnect("host=$this->dbHost dbname=$dbname " .
                        "user=$this->dbUser password=$this->dbPassword");
    }
    else {
      $this->_pg_conn = pg_pconnect(str_replace(";", " ", $options));
    }
    $res = pg_last_error($this->_pg_conn);

    if(is_null($this->_pg_conn)) {
      $this->pg_Error = TRUE;
      print "DB: could not connect to the db, connection is NULL\n";
      return(FALSE);
    }

    if($this->_pg_conn === FALSE) {
      $this->pg_Error = TRUE;
      print "DB: could not connect to the db, connect is FALSE\n";
      return(FALSE);
    }
    if (!isset ($this->_pg_conn)) {
      $this->pg_Error = 1;
      return (0);
    }
    $this->pg_Error = 0;
    return (1);
  }
  /**
   * dbQuery
   *
   * perform a query, return results
   *
   * @param string $Sql the SQL Query to perform
   * @return array $rows can be empty array.
   */

  public function dbQuery($Sql) {
    /*
     * sql query's can return False on error or NULL (no error, no
     * results)
     *
     * This code is pretty much copied from Action in db-core.  Look for
     * areas where better error checking can be done (e.g. where the @
     * is used)
     */
    $uid = posix_getuid();
    $uidInfo = posix_getpwuid($uid);
    $this->pg_rows = array ();
    if (!$this->_pg_conn) {
      return ($this->pg_rows); // think about this, is false better?
    }
    if (empty ($Sql)) {
      return ($this->pg_rows); // same as above
    }

    @ $result = pg_query($this->_pg_conn, $Sql);
    DBCheckResult($result, $Sql, __FILE__, __LINE__);

    $this->Error = 0;
    $this->pg_rows = pg_affected_rows($result);

    /* if the query returned nothing then just return*/
    if (!isset ($result)) {
      print "DB-Query: result not set!\n";
      return;
    }
    @ $rows = pg_fetch_all($result);

    if (!is_array($rows)) {
      $rows = array ();
    }
    //print "DB-QU: rows is\n"; print_r($rows) . "\n";
    @ pg_free_result($result);
    return $rows;
  } // dbQuery
} // class db
