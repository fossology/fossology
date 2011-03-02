<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
if (!isset($GlobalReady)) {
  exit;
}

define("TITLE_core_smauth", _("SiteMinder_Login"));

class core_smauth extends FO_Plugin {
  var $Name = "smauth";
  var $Title = TITLE_core_smauth;
  var $Version = "1.0";
  var $Dependency = array("db");
  var $PluginLevel = 1000; /* make this run first! */
  var $LoginFlag = 0;
  /***********************************************************
   Install(): Only used during installation.
   This may be called multiple times.
   Used to ensure the DB has the right default columns.
   Returns 0 on success, non-zero on failure.
   ***********************************************************/
  function Install() {
    global $PG_CONN;
    if (empty($PG_CONN)) {
      return (1);
    } /* No DB */
    return (0);
  } // Install()

  /******************************************
   PostInitialize(): This is where the magic for
   Authentication happens.
   ******************************************/
  function PostInitialize() {
    global $Plugins;
    global $PG_CONN;

    if (siteminder_check() == -1) {return;}

    $UID = siteminder_check();

    session_name("Login");
    session_start();
    $Now = time();
    if (!empty($_SESSION['time'])) {
      /* Logins older than 60 secs/min * 480 min = 8 hr are auto-logout */
      if (@$_SESSION['time'] + (60 * 480) < $Now) {
        $_SESSION['User'] = NULL;
        $_SESSION['UserId'] = NULL;
        $_SESSION['UserLevel'] = NULL;
        $_SESSION['UserEmail'] = NULL;
        $_SESSION['Folder'] = NULL;
      }
    }

    /* check db connection */
    if (!$PG_CONN) { $dbok = $DB->db_init(); if (!$dbok) echo "NO DB connection"; }

    /* Enable or disable plugins based on login status */
    $Level = PLUGIN_DB_NONE;
    if (@$_SESSION['User']) {
      /* If you are logged in, then the default level is "Download". */
      if ("X" . $_SESSION['UserLevel'] == "X") {
        $Level = PLUGIN_DB_DOWNLOAD;
      } else {
        $Level = @$_SESSION['UserLevel'];
      }
      /* Recheck the user in case he is suddenly blocked or changed. */
      if (empty($_SESSION['time_check'])) {
        $_SESSION['time_check'] = time() + (480 * 60);
      }
      if (time() >= @$_SESSION['time_check']) {
        $sql = "SELECT * FROM users WHERE user_pk='" . @$_SESSION['UserId'] . "';";
        $results = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $R = pg_fetch_assoc($result);
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

  /******************************************
   CheckUser(): See if a username is valid.
   Returns string on match, or null on no-match.
   ******************************************/
  function CheckUser($Email) {
    global $PG_CONN;
    if (empty($Email)) {
      return;
    }
    $Email = str_replace("'", "''", $Email); /* protect DB */

    /* See if the user exists */
    $sql = "SELECT * FROM users WHERE user_email = '$Email';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $R = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($R['user_name'])) {
      $sql = "INSERT INTO users
              (user_name,user_desc,user_seed,user_pass,user_perm,user_email,
       email_notify,user_agent_list,root_folder_fk, default_bucketpool_fk,
       ui_preference)
              VALUES ('$Email','HP User','','',5,'$Email','y','',1,1,'simple')";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
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
      $_SESSION['UserLevel'] = PLUGIN_DB_USERADMIN;
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

  /******************************************
   Output():
   ******************************************/
  function Output() {
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
        $_SESSION['User'] = NULL;
        $_SESSION['UserId'] = NULL;
        $_SESSION['UserLevel'] = NULL;
        $_SESSION['UserEmail'] = NULL;
        $_SESSION['Folder'] = NULL;
        $Uri = Traceback_uri() . "?mod=refresh&remod=default";
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
