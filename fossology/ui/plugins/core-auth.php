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
if (!isset($GlobalReady)) {
  exit;
}
class core_auth extends FO_Plugin {
  var $Name = "auth";
  var $Title = "Login";
  var $Version = "1.0";
  var $PluginLevel = 100; /* make this run first! */
  var $Dependency = array("db");
  var $LoginFlag = 0;
  /***********************************************************
  Install(): Only used during installation.
  This may be called multiple times.
  Used to ensure the DB has the right default columns.
  Returns 0 on success, non-zero on failure.
  ***********************************************************/
  function Install() {
    global $DB;
    if (empty($DB)) {
      return (1);
    } /* No DB */
    /* No users with no seed and no pass */
    $DB->Action("UPDATE users SET user_seed = " . rand() . " WHERE user_seed IS NULL;");
    /* No users with no seed and no perm -- make them read-only */
    $DB->Action("UPDATE users SET user_perm = " . PLUGIN_DB_READ . " WHERE user_perm IS NULL;");

    /* There must always be at least one default user. */
    $Results = $DB->Action("SELECT * FROM users WHERE user_name = 'Default User';");
    if (empty($Results[0]['user_name'])) {
      /* User "fossy" does not exist.  Create it. */
      /* No valid username/password */
      $Level = PLUGIN_DB_NONE;
      $SQL = "INSERT INTO users (user_name,user_desc,user_seed,user_pass,user_perm,user_email,root_folder_fk)
	VALUES ('Default User','Default User when nobody is logged in','Seed','Pass',$Level,NULL,1);";
      $DB->Action($SQL);
      print "*** Created default user: 'Default User'.\n";
    }
    /* There must always be at least one user with user-admin access.
    If he does not exist, make it user "fossy".
    If user "fossy" does not exist, add him with the default password 'fossy'. */
    $Perm = PLUGIN_DB_USERADMIN;
    $Results = $DB->Action("SELECT * FROM users WHERE user_perm = $Perm;");
    if (empty($Results[0]['user_name'])) {
      /* No user with PLUGIN_DB_USERADMIN access. */
      $Seed = rand() . rand();
      $Hash = sha1($Seed . "fossy");
      $Results = $DB->Action("SELECT * FROM users WHERE user_name = 'fossy';");
      if (empty($Results[0]['user_name'])) {
        /* User "fossy" does not exist.  Create it. */
        $SQL = "INSERT INTO users (user_name,user_desc,user_seed,user_pass," .
               "user_perm,user_email,email_notify,root_folder_fk)
		  VALUES ('fossy','Default Administrator','$Seed','$Hash',$Perm,NULL,'y',1);";
        print "*** Created default administrator: 'fossy' with password 'fossy'.\n";
      }
      else {
        /* User "fossy" exists!  Update it. */
        $SQL = "UPDATE users SET user_perm = $Perm, email_notify = 'y' WHERE user_name = 'fossy';";
        print "*** Existing user 'fossy' promoted to default administrator.\n";
      }
      $DB->Action($SQL);
      $Results = $DB->Action("SELECT * FROM users WHERE user_perm = $Perm;");
    }
    if (empty($Results[0]['user_name'])) {
      return (1);
    } /* Failed to insert */
    return (0);
  } // Install()

  /******************************************
  GetIP(): Retrieve the user's IP address.
  Some proxy systems pass forwarded IP address info.
  This ensures that someone who steals the cookie won't
  gain access unless they come from the same IP.
  ******************************************/
  function GetIP() {
    /* NOTE: This can be easily defeated wtih fake HTTP headers. */
    $Vars = array('HTTP_CLIENT_IP', 'HTTP_X_COMING_FROM', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED');
    foreach($Vars as $V) {
      if (!empty($_SERVER[$V])) {
        return ($_SERVER[$V]);
      }
    }
    return (@$_SERVER['REMOTE_ADDR']);
  } // GetIP()

  /******************************************
  PostInitialize(): This is where the magic for
  Authentication happens.
  ******************************************/
  function PostInitialize() {
    global $Plugins;
    global $DB;
    if (empty($DB)) {
      return (0);
    }
    session_name("Login");
    session_start();
    $Now = time();
    if (!empty($_SESSION['time'])) {
      /* Logins older than 60 minutes are auto-logout */
      if (@$_SESSION['time'] + 60 * 60 < $Now) {
        $_SESSION['User'] = NULL;
        $_SESSION['UserId'] = NULL;
        $_SESSION['UserLevel'] = NULL;
        $_SESSION['UserEmail'] = NULL;
        $_SESSION['Folder'] = NULL;
      }
    }
    $_SESSION['time'] = $Now;
    if (empty($_SESSION['ip'])) {
      $_SESSION['ip'] = $this->GetIP();
    } else if ((@$_SESSION['checkip'] == 1) && (@$_SESSION['ip'] != $this->GetIP())) {
      /* Sessions are not transferable. */
      $_SESSION['User'] = NULL;
      $_SESSION['UserId'] = NULL;
      $_SESSION['UserLevel'] = NULL;
      $_SESSION['UserEmail'] = NULL;
      $_SESSION['Folder'] = NULL;
      $_SESSION['ip'] = $this->GetIP();
    }

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
        $_SESSION['time_check'] = time() + 10 * 60;
      }
      if (time() >= @$_SESSION['time_check']) {
        $Results = $DB->Action("SELECT * FROM users WHERE user_pk='" . @$_SESSION['UserId'] . "';");
        $R = $Results[0];
        $_SESSION['User'] = $R['user_name'];
        $_SESSION['Folder'] = $R['root_folder_fk'];
        $_SESSION['UserLevel'] = $R['user_perm'];
        $_SESSION['UserEmail'] = $R['user_email'];
        $_SESSION['UserEnote'] = $R['email_notify'];
        $Level = @$_SESSION['UserLevel'];
        /* Check for instant logouts */
        if (empty($R['user_pass'])) {
          $_SESSION['User'] = NULL;
          $_SESSION['UserId'] = NULL;
          $_SESSION['UserLevel'] = NULL;
          $_SESSION['UserEmail'] = NULL;
          $_SESSION['Folder'] = NULL;
          $Results = $DB->Action("SELECT * FROM users WHERE user_name='Default User';");
          if (empty($Results)) {
            $Level = PLUGIN_DB_NONE;
          } else {
            $Level = $Results[0]['user_perm'];
          }
        }
      }
    } else {
      /* Default to permissions for "Default User" */
      $Results = $DB->Action("SELECT * FROM users WHERE user_name='Default User';");
      if (empty($Results)) {
        $Level = PLUGIN_DB_NONE;
      } else {
        $Level = $Results[0]['user_perm'];
      }
    }

    /* Disable all plugins with >= $Level access */
    $LoginFlag = empty($_SESSION['User']);
    $Max = count($Plugins);
    for ($i = 0;$i < $Max;$i++) {
      $P = & $Plugins[$i];
      if ($P->State == PLUGIN_STATE_INVALID) {
        continue;
      }
      if (($P->DBaccess > $Level) || (empty($_SESSION['User']) && $P->LoginFlag)) {
        $P->Destroy();
        $P->State = PLUGIN_STATE_INVALID;
      }
    }
    $this->State = PLUGIN_STATE_READY;
  } // PostInitialize()

  /******************************************
  CheckUser(): See if a username/password is valid.
  Returns string on match, or null on no-match.
  ******************************************/
  function CheckUser($User, $Pass) {
    global $DB;
    $V = "";
    if (empty($User)) {
      return;
    }
    if ($User == 'Default User') {
      return;
    }
    $User = str_replace("'", "''", $User); /* protect DB */

    /* See if the user exists */
    $Results = $DB->Action("SELECT * FROM users WHERE user_name = '$User';");
    $R = $Results[0];
    if (empty($R['user_name'])) {
      return;
    } /* no user */
    /* Check the password -- only if a password exists */
    if (!empty($R['user_seed']) && !empty($R['user_pass'])) {
      $Hash = sha1($R['user_seed'] . $Pass);
      if (strcmp($Hash, $R['user_pass']) != 0) {
        return;
      }
    } else if (!empty($R['user_seed'])) {
      /* Seed with no password hash = no login */
      return;
    } else {
      if (!empty($Pass)) {
        return;
      } /* empty password required */
    }
    /* If you make it here, then username and password were good! */
    $_SESSION['User'] = $R['user_name'];
    $_SESSION['UserId'] = $R['user_pk'];
    $_SESSION['UserEmail'] = $R['user_email'];
    $_SESSION['UserEnote'] = $R['email_notify'];
    $_SESSION['Folder'] = $R['root_folder_fk'];
    $_SESSION['time_check'] = time() + 10 * 60;
    /* No specified permission means ALL permission */
    if ("X" . $R['user_perm'] == "X") {
      $_SESSION['UserLevel'] = PLUGIN_DB_USERADMIN;
    } else {
      $_SESSION['UserLevel'] = $R['user_perm'];
    }
    $_SESSION['checkip'] = GetParm("checkip", PARM_STRING);
    /* Check for the no-popup flag */
    if (GetParm("nopopup", PARM_INTEGER) == 1) {
      $_SESSION['NoPopup'] = 1;
    } else {
      $_SESSION['NoPopup'] = 0;
    }
    /* Need to refresh the screen */
    $V.= PopupAlert('User Logged In');
    $V.= "<script language='javascript'>\n";
    /* Use the previous redirect, but only use it if it comes from this
    server's Traceback_uri().  (Ignore hostname.) */
    $Redirect = preg_replace("@^[^/]*//[^/]*@", "", GetParm("redirect", PARM_TEXT));
    $Uri = Traceback_uri();
    if (preg_match("/[?&]mod=(Default|" . $this->Name . ")/", $Redirect)) {
      $Redirect = ""; /* don't reference myself! */
    }
    if (empty($Redirect) || strncmp($Redirect, $Uri, strlen($Uri))) {
      $Uri = Traceback_uri();
    } else {
      $Uri = $Redirect;
    }
    /* Redirect window */
    $V.= "window.open('$Uri','_top');\n";
    $V.= "</script>\n";
    return ($V);
  } // CheckUser()

  /******************************************
  Output(): This is only called when the user logs out.
  ******************************************/
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
      break;
      case "HTML":
        if (empty($_SESSION['User'])) {
          $User = GetParm("username", PARM_TEXT);
          $Pass = GetParm("password", PARM_TEXT);
          if (!empty($User)) {
            $VP = $this->CheckUser($User, $Pass);
          } else {
            $VP = "";
          }
          if (!empty($VP)) {
            $V.= $VP;
          } else {
            /* Check for init and first-time use */
            if (plugin_find_id("init") >= 0) {
              $V.= "<b>The system requires initialization. Please login and use the Initialize option under the Admin menu.</b>";
              $V.= "<P />\n";
              /* Check for a default user */
              global $DB;
              $Level = PLUGIN_DB_USERADMIN;
              $Results = $DB->Action("SELECT * FROM users WHERE user_perm = $Level LIMIT 1;");
              $R = & $Results[0];
              if (!is_array($R)) {
                $Results[0] = array();
              } else if (array_key_exists("user_seed", $R) && array_key_exists("user_pass", $R)) {
                $Results = $DB->Action("SELECT user_name FROM users WHERE user_seed IS NULL AND user_pass IS NULL;");
              } else {
                $Results = $DB->Action("SELECT user_name FROM users;");
              }
              $R = & $Results[0];
              if (!empty($R['user_name'])) {
                $V.= "If you need an account, use '" . $R['user_name'] . "' with no password.\n";
                $V.= "<P />\n";
              }
            }
            /* Inform about the protocol. */
            $Protocol = preg_replace("@/.*@", "", @$_SERVER['SERVER_PROTOCOL']);
            if ($Protocol != 'HTTPS') {
              $V.= "This login uses $Protocol, so passwords are tranmitted in plain text.  This is not a secure connection.<P />\n";
            }
            $V.= "<form method='post'>\n";
            $V.= "<table border=0>";
            $V.= "<tr><td>Username:</td><td><input type='text' size=20 name='username'></td></tr>\n";
            $V.= "<tr><td>Password:</td><td><input type='password' size=20 name='password'></td></tr>\n";
            $V.= "</table>";
            $V.= "<P/>";
            $V.= "<input type='checkbox' name='checkip' value='1'>Validate IP.\n";
            $Referer = @$_SERVER['HTTP_REFERER'];
            if (!empty($Referer)) {
              $V.= "<input type='hidden' name='redirect' value='$Referer'>";
            }
            $V.= "This option deters session hijacking by linking your session to your IP address (" . @$_SESSION['ip'] . "). While this option is more secure, it is not ideal for people using proxy networks, where IP addresses regularly change. If you find that are you constantly being logged out, then do not use this option.<P />\n";
            $V.= "<input type='submit' value='Login'>\n";
            $V.= "</form>\n";
          }
        } else
        /* It's a logout */ {
          $_SESSION['User'] = NULL;
          $_SESSION['UserId'] = NULL;
          $_SESSION['UserLevel'] = NULL;
          $_SESSION['UserEmail'] = NULL;
          $_SESSION['Folder'] = NULL;
          $V.= PopupAlert('User Logged Out');
          $Uri = Traceback_uri() . "?mod=refresh&remod=default";
          $V.= "<script language='javascript'>\n";
          $V.= "window.open('$Uri','_top');\n";
          $V.= "</script>\n";
        }
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
$NewPlugin = new core_auth;
$NewPlugin->Initialize();
?>
