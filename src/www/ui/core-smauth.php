<?php
/***********************************************************
 Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_core_smauth", _("SiteMinder_Login"));

class core_smauth extends FO_Plugin {
  var $Name = "smauth";
  var $Title = TITLE_core_smauth;
  var $Version = "1.0";
  var $Dependency = array();
  var $PluginLevel = 1000; /* make this run first! */
  var $LoginFlag = 0;

  /**
   * \brief Only used during installation.
   * This may be called multiple times.
   * Used to ensure the DB has the right default columns.
   * 
   * \return 0 on success, non-zero on failure.
   */
  function Install() {
    global $PG_CONN;
    if (empty($PG_CONN)) {
      return (1);
    } /* No DB */
    return (0);
  } // Install()

  /**
   * \brief This is where the magic for
   * Authentication happens.
   */
  function PostInitialize() {
    global $Plugins;
    global $PG_CONN;
    global $SysConf;

    if (siteminder_check() == -1) {return;}

    $UID = siteminder_check();

    session_name("Login");
    session_start();
    if (array_key_exists('UserId', $_SESSION)) $SysConf['auth']['UserId'] = $_SESSION['UserId'];
    $Now = time();
    if (!empty($_SESSION['time'])) {
      /* Logins older than 60 secs/min * 480 min = 8 hr are auto-logout */
      if (@$_SESSION['time'] + (60 * 480) < $Now) {
        $_SESSION['User'] = NULL;
        $_SESSION['UserId'] = NULL;
        $_SESSION['UserLevel'] = NULL;
        $SysConf['auth']['UserId'] = NULL;
        $_SESSION['UserEmail'] = NULL;
        $_SESSION['Folder'] = NULL;
        $_SESSION['UiPref'] = NULL;
        /* TODO: need to clear SiteMinder session */
      }
    }

    /* check db connection */
    if (!$PG_CONN)
    { 
      DBconnect();
      if (!$PG_CONN) echo "NO DB connection"; 
    }

    /* Enable or disable plugins based on login status */
    $Level = PLUGIN_DB_NONE;
    if (@$_SESSION['User']) {  //TODO: also need to check SiteMinder session
    /* If you are logged in, then the default level is "Download". */
    if ("X" . $_SESSION['UserLevel'] == "X") {
      $Level = PLUGIN_DB_WRITE;
    } else {
      $Level = @$_SESSION['UserLevel'];
    }
    /* Recheck the user in case he is suddenly blocked or changed. */
    if (empty($_SESSION['time_check'])) {
      $_SESSION['time_check'] = time() + (480 * 60);
    }
    if (time() >= @$_SESSION['time_check']) {
      $sql = "SELECT * FROM users WHERE user_pk='" . @$_SESSION['UserId'] . "';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $R = pg_fetch_assoc($result);
      pg_free_result($result);
      $_SESSION['User'] = $R['user_name'];
      $_SESSION['Folder'] = $R['root_folder_fk'];
      $_SESSION['UserLevel'] = $R['user_perm'];
      $_SESSION['UserEmail'] = $R['user_email'];
      $_SESSION['UserEnote'] = $R['email_notify'];
      if(empty($R['ui_preference']))
      {
        $_SESSION['UiPref'] = 'simple';
      }
      else
      {
        $_SESSION['UiPref'] = $R['ui_preference'];
      }
      $Level = @$_SESSION['UserLevel'];
      pg_free_result($result);
    }
    } else {
      $this->CheckUser($UID);
      $Level = @$_SESSION['UserLevel'];
    }

    /* Disable all plugins with >= $Level access */
    plugin_disable($Level);

    $this->State = PLUGIN_STATE_READY;
  } // PostInitialize()

  /**
   * \brief See if a username is valid.
   * \return string on match, or null on no-match.
   */
  function CheckUser($Email) {
    global $PG_CONN;
    global $SysConf;

    if (empty($Email)) {
      return;
    }
    $Email = str_replace("'", "''", $Email); /* protect DB */
    $FolderName = substr($Email, 0, strpos($Email,'@'));
    $FolderName = trim($FolderName);
    if (empty($FolderName)) {
      return;
    }
    $FolderDes = "Folder created for " . $FolderName;

    /* Get default User description and bucketpool from sysconf table */
    $UserDesc = 'null';
    $BucketPool = 'null';
    $sql = "SELECT typlen  FROM pg_type where typname='sysconfig' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) {
      pg_free_result($result);
      // Get UserDesc from sysconfig
      $sql = "SELECT conf_value FROM sysconfig WHERE variablename = 'UserDesc';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $R = pg_fetch_assoc($result);
      pg_free_result($result);
      if (!empty($R['conf_value']))
      $UserDesc = "'".$R['conf_value']."'";

      // Get BucketPool from sysconfig
      $sql = "SELECT conf_value FROM sysconfig WHERE variablename = 'BucketPool';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $R = pg_fetch_assoc($result);
      pg_free_result($result);
      if (!empty($R['conf_value'])) {
        $BucketPool = $R['conf_value'];
        //Check if the bucketpool defined in sysconfig exist in bucketpool table
        $sql = "SELECT bucketpool_pk  FROM bucketpool WHERE bucketpool_pk=$BucketPool AND active='Y'";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        if (pg_num_rows($result) < 1) {
          $BucketPool = 'null';       //didn't exist in bucketpool table, set it 'null'
        }
        pg_free_result($result);
      } else {
        /* if didn't define bucketpool from sycconf.And only a single bucketpool record, get bucketpool from bucketpool table. If more than one, set it null*/
        $sql = "SELECT bucketpool_pk FROM bucketpool;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        if (pg_num_rows($result) == 1) {
          $R = pg_fetch_assoc($result);
          if (!empty($R['bucketpool_pk']))
          $BucketPool = $R['bucketpool_pk'];
        } else {
          $BucketPool = 'null';
        }
        pg_free_result($result);
      }
    }
    else pg_free_result($result);

    /* See if the user exists */
    $sql = "SELECT * FROM users WHERE user_email = '$Email';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $R = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($R['user_name'])) {
      //check if folder name exists under the parent?
      pg_exec("BEGIN;");
      $sql = "SELECT * FROM folderlist WHERE name = '$FolderName' AND parent = '1' AND foldercontents_mode = '1';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      if (empty($row['name'])){
        //create folder for the user
        $sql = "INSERT INTO folder (folder_name, folder_desc) VALUES ('$FolderName', '$FolderDes');";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
        $sql = "SELECT folder_pk FROM folder WHERE folder_name='$FolderName' AND folder_desc = '$FolderDes';";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        pg_free_result($result);
        //print_r($row);
        if (empty($row['folder_pk']))
        return;
        $FolderPk = $row['folder_pk'];
        //echo $FolderPk;

        $sql = "INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES ('1','1','$FolderPk');";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      } else {
        $sql = "SELECT folder_pk FROM folder WHERE folder_name='$FolderName' AND folder_desc = '$FolderDes';";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        pg_free_result($result);
        //print_r($row);
        if (empty($row['folder_pk']))
        return;
        $FolderPk = $row['folder_pk'];
        //echo $FolderPk;
      }

      //create user
      add_user($Email,$UserDesc,null,null,5,$Email,y,agent_bucket,agent_copyright,$FolderPk,$BucketPool);

      pg_exec("COMMIT;");
    } /* no user */

    $sql = "SELECT * FROM users WHERE user_email = '$Email';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $R = pg_fetch_assoc($result);
    pg_free_result($result);

    /* Check the email */
    if (strcmp($Email, $R['user_email']) != 0) {
      return;
    }
    /* If you make it here, then username and email were good! */
    $_SESSION['User'] = $R['user_name'];
    $_SESSION['UserId'] = $R['user_pk'];
    $SysConf['auth']['UserId'] = $R['user_pk'];
    $_SESSION['UserEmail'] = $R['user_email'];
    $_SESSION['UserEnote'] = $R['email_notify'];
    if(empty($R['ui_preference']))
    {
      $_SESSION['UiPref'] = 'simple';
    }
    else
    {
      $_SESSION['UiPref'] = $R['ui_preference'];
    }
    $_SESSION['Folder'] = $R['root_folder_fk'];
    $_SESSION['time_check'] = time() + (480 * 60);
    /* No specified permission means ALL permission */
    if ("X" . $R['user_perm'] == "X") {
      $_SESSION['UserLevel'] = PLUGIN_DB_ADMIN;
    } else {
      $_SESSION['UserLevel'] = $R['user_perm'];
    }
    /* Check for the no-popup flag */
    if (GetParm("nopopup", PARM_INTEGER) == 1) {
      $_SESSION['NoPopup'] = 1;
    } else {
      $_SESSION['NoPopup'] = 0;
    }
  } // CheckUser()

  /**
   * \brief generate the output for this plug-in
   */
  function Output() {
    global $SysConf;
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    if (siteminder_check() == -1) {return;}

    $UID = siteminder_check();

    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* TODO:logout need to clear SiteMinder session */
        $_SESSION['User'] = NULL;
        $_SESSION['UserId'] = NULL;
        $SysConf['auth']['UserId'] = NULL;
        $_SESSION['UserLevel'] = NULL;
        $_SESSION['UserEmail'] = NULL;
        $_SESSION['Folder'] = NULL;
        $_SESSION['UiPref'] = NULL;
        $Uri = Traceback_uri() . "logout.html?" . rand();
        //$Uri = Traceback_uri() . "?mod=refresh&remod=default";
        $V.= "<script language='javascript'>\n";
        $V.= "window.open('$Uri','_top');\n";
        $V.= "</script>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ($V);
    return;
  } // Output()
};
$NewPlugin = new core_smauth;
$NewPlugin->Initialize();
?>
