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
 * dbConnect
 *
 * General class to connect to the fossology Data-Base
 *
 */
/**
 * dbConnect($host, $dbName, $dbUser, $dbPasswd)
 *
 * @param string $host hostname to connect to
 * @param string $dbName Data-Base name
 * @param string $dbUser Data-Base user
 * @param string $dbPasswd Data-Base password
 *
 * @return db Resource or FALSE
 *
 * @version "$Id: $"
 *
 * Created on Sep 12, 2008
 */
 class dbConnect
 {
   public $DB;
   public $host;
   public $dbName;
   public $dbUser;
   private $dbPasswd;

   function __construct($host, $dbName, $dbUser, $dbPasswd)
   {
     /* if no host, use localhost */
     if(empty($host))
     {
      $this->$host = 'localhost';
     }
     else
     {
       $this->$host = $host;
     }
     if(empty($dbName) || empty($dbUser) || empty($dbPasswd))
     {
       return(FALSE);
     }
     else
     {
      $this->$dbName = $dbName;
      $this->$dbUser = $dbUser;
      $this->$dbPasswd = $dbPasswd;
     }
   }
   function connectUI()
   {
    require_once('pathinclude.h.php');
    global $DATADIR, $PROJECT;
    $path="$DATADIR/dbconnect/$PROJECT";
    $myConnect = pg_pconnect(str_replace(";", " ", file_get_contents($path)));
    if(is_resource($myConnect))
    {
      return($myConnect);
    }
    else
    {
      return(FALSE);
    }
   }
   function connect4Tests()
   {
     require_once(dirname(__FILE__) . "../../tests/TestEnvironment.php");
     // need to get the host name from the path
     $myConnect = pg_pconnect("host=$host", "user=xxx", "password=xxxxx", "dbname=xxxxx" );
     if(is_resource($myConnect)) { return($myConnect); }
     else { return(FALSE); }
   }
   public function fossologyConnect($type)
   {
    /* $type is either ui or test only need 1 routine then */
    return(TRUE);
   }
 }
?>
