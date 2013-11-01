<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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
 * \file demomod.php
 * \brief browse an upload and display the demomod data (first bytes of the file)
 */

define("TITLE_ui_demomod", _("Demomod Browser"));

class ui_demomod extends FO_Plugin
{
  var $Name       = "demomod";
  var $Title      = TITLE_ui_demomod;
  var $Dependency = array("browse","view");
  var $DBaccess   = PLUGIN_DB_READ;
  var $uploadtree_tablename;

  /**
   * \brief  Only used during installation.
   * \return 0 on success, non-zero on failure.
   */
  function Install()
  {
    global $PG_CONN;

    if (!$PG_CONN) {
      return(1);
    }

    return(0);
  } // Install()

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("upload","item"));
    $MenuName = "Demomod Browser";

    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
      if (GetParm("mod",PARM_STRING) == $this->Name)
      {
        menu_insert("Browse::$MenuName",100);
      }
      else
      {
        $text = _("Demomod data");
        menu_insert("Browse::$MenuName",100,$URI,$text);
        menu_insert("View::$MenuName",100,$URI,$text);
      }
    }
  } // RegisterMenus()


  /**
   * \brief This is called before the plugin is used.
   * It should assume that Install() was already run one time
   * (possibly years ago and not during this object's creation).
   *
   * \return true on success, false on failure.
   * A failed initialize is not used by the system.
   *
   * \note This function must NOT assume that other plugins are installed.
   */
  function Initialize()
  {
    if ($this->State != PLUGIN_STATE_INVALID)  return(1);  // don't re-run

    return($this->State == PLUGIN_STATE_VALID);
  } // Initialize()


  /**
   * \brief Display the demomod data
   * 
   * \param $upload_pk
   * \param $uploadtree_pk
   */
  function ShowData($upload_pk, $uploadtree_pk)
  {
    global $PG_CONN;

    /* Check the demomod_ars table to see if we have any data */
    $sql = "select ars_pk from demomod_ars where upload_fk=$upload_pk and ars_success=true";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $rows = pg_num_rows($result);
    pg_free_result($result);

    if ($rows == 0) return _("There is no demomod data for this upload.  Use Jobs > Schedule Agent.");

    /* Get the scan result */
    /* First we need the pfile_pk */
    $sql = "select pfile_fk from $this->uploadtree_tablename where uploadtree_pk=$uploadtree_pk and upload_fk=$upload_pk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $rows = pg_num_rows($result);
    if ($rows == 0) return _("Internal consistency error. Failed: $sql");
    $row = pg_fetch_assoc($result);
    $pfile_fk = $row['pfile_fk'];
    pg_free_result($result);

    /* Now we can get the scan result */
    $sql = "select firstbytes from demomod where pfile_fk=$pfile_fk";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $rows = pg_num_rows($result);
    if ($rows == 0) return _("Internal consistency error. Failed: $sql");
    $row = pg_fetch_assoc($result);
    $firstbytes = $row['firstbytes'];
    pg_free_result($result);

    $text = _("The first bytes of this file are: ");
    return ($text . $firstbytes);
  }
  

  /**
   * \brief This function returns the scheduler status.
   */
  function Output()
  {
    $uTime = microtime(true);
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }
    $V="";

    $Upload = GetParm("upload",PARM_INTEGER);
    $UploadPerm = GetUploadPerm($Upload);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }

    $Item = GetParm("item",PARM_INTEGER);
    $updcache = GetParm("updcache",PARM_INTEGER);

    /* Remove "updcache" from the GET args.
     * This way all the url's based on the input args won't be
     * polluted with updcache
     * Use Traceback_parm_keep to ensure that all parameters are in order */
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload","item","agent"));
    if ($updcache)
    {
      $_SERVER['REQUEST_URI'] = preg_replace("/&updcache=[0-9]*/","",$_SERVER['REQUEST_URI']);
      unset($_GET['updcache']);
      $V = ReportCachePurgeByKey($CacheKey);
    }
    else
    {
      $V = ReportCacheGet($CacheKey);
    }

    $this->uploadtree_tablename = GetUploadtreeTableName($Upload);

    if (empty($V) )  // no cache exists
    {
      switch($this->OutputType)
      {
        case "XML":
          break;
        case "HTML":
          $V .= "<font class='text'>\n";

          /************************/
          /* Show the folder path */
          /************************/
          $V .= Dir2Browse($this->Name,$Item,NULL,1,"Browse", -1, '', '', $this->uploadtree_tablename) . "<P />\n";

          if (!empty($Upload))
          {
            $Uri = preg_replace("/&item=([0-9]*)/","",Traceback());
            $V .= js_url();
            $V .= $this->ShowData($Upload, $Item);
          }
          $V .= "</font>\n";
          $V .= "<p>\n";
          break;
        case "Text":
          break;
        default:
      }

      $Cached = false;
    }
    else
    $Cached = true;

    if (!$this->OutputToStdout) {
      return($V);
    }
    print "$V";
    $Time = microtime(true) - $uTime;  // convert usecs to secs
    $text = _("Elapsed time: %.2f seconds");
    printf( "<small>$text</small>", $Time);

    if ($Cached)
    {
      $text = _("cached");
      $text1 = _("Update");
      echo " <i>$text</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> $text1 </a>";
    }
    else
    {
      /*  Cache Report if this took longer than 1/2 second*/
      if ($Time > 0.5) ReportCachePut($CacheKey, $V);
    }
    return;
  }

};

$NewPlugin = new ui_demomod;
$NewPlugin->Initialize();

?>
