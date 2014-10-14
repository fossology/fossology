<?php
/***********************************************************
 * Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

define("TITLE_core_auth", _("Login"));

class core_auth extends FO_Plugin
{
  public static $origReferer;
  
  function __construct()
  {
    $this->Name = "auth";
    $this->Title = TITLE_core_auth;
    $this->PluginLevel = 1000; /* make this run first! */
    $this->LoginFlag = 0;
    parent::__construct();
  }

  /**
   * \brief getter to retreive value of static var
   */
  public function staticValue()
  {
    return self::$origReferer;
  }

  /**
   * \brief Only used during installation.
   * This may be called multiple times.
   * Used to ensure the DB has the right default columns.
   *
   * \return 0 on success, non-zero on failure.
   */
  function Install()
  {
    global $PG_CONN;

    if (empty($PG_CONN))
    {
      return (1);
    } /* No DB */
    /* No users with no seed and no pass */
    $sql = "UPDATE users SET user_seed = " . rand() . " WHERE user_seed IS NULL;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    /* No users with no seed and no perm -- make them read-only */
    $sql = "UPDATE users SET user_perm = " . PLUGIN_DB_READ . " WHERE user_perm IS NULL;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /* There must always be at least one default user. */
    $sql = "SELECT * FROM users WHERE user_name = 'Default User';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($row['user_name']))
    {
      /* User "fossy" does not exist.  Create it. */
      /* No valid username/password */
      $Level = PLUGIN_DB_NONE;
      $sql = "INSERT INTO users (user_name,user_desc,user_seed,user_pass,user_perm,user_email,root_folder_fk)
        VALUES ('Default User','Default User when nobody is logged in','Seed','Pass',$Level,NULL,1);";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
      $text = _("*** Created default user: 'Default User'.");
      //print "$text\n";
    }
    /* There must always be at least one user with user-admin access.
     If he does not exist, make it user "fossy".
     If user "fossy" does not exist, add him with the default password 'fossy'. */
    $Perm = PLUGIN_DB_ADMIN;
    $sql = "SELECT * FROM users WHERE user_perm = $Perm;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($row['user_name']))
    {
      /* No user with PLUGIN_DB_ADMIN access. */
      $Seed = rand() . rand();
      $Hash = sha1($Seed . "fossy");
      $sql = "SELECT * FROM users WHERE user_name = 'fossy';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row0 = pg_fetch_assoc($result);
      pg_free_result($result);
      if (empty($row0['user_name']))
      {
        /* User "fossy" does not exist.  Create it. */
        $SQL = "INSERT INTO users (user_name,user_desc,user_seed,user_pass," .
            "user_perm,user_email,email_notify,root_folder_fk)
      VALUES ('fossy','Default Administrator','$Seed','$Hash',$Perm,'fossy','y',1);";
        $text = _("*** Created default administrator: 'fossy' with password 'fossy'.");
        //print "$text\n";
      } else
      {
        /* User "fossy" exists!  Update it. */
        $SQL = "UPDATE users SET user_perm = $Perm, email_notify = 'y'," .
            " user_email= 'fossy' WHERE user_name = 'fossy';";
        $text = _("*** Existing user 'fossy' promoted to default administrator.");
        //print "$text\n";
      }
      $result = pg_query($PG_CONN, $SQL);
      DBCheckResult($result, $SQL, __FILE__, __LINE__);
      pg_free_result($result);

      $sql = "SELECT * FROM users WHERE user_perm = $Perm;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
    }
    if (empty($row['user_name']))
    {
      return (1);
    } /* Failed to insert */
    return (0);
  } // Install()

  /**
   * \brief Retrieve the user's IP address.
   * Some proxy systems pass forwarded IP address info.
   * This ensures that someone who steals the cookie won't
   * gain access unless they come from the same IP.
   */
  function GetIP()
  {
    /* NOTE: This can be easily defeated wtih fake HTTP headers. */
    $Vars = array('HTTP_CLIENT_IP', 'HTTP_X_COMING_FROM', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED');
    foreach ($Vars as $V)
    {
      if (!empty($_SERVER[$V]))
      {
        return ($_SERVER[$V]);
      }
    }
    return (@$_SERVER['REMOTE_ADDR']);
  } // GetIP()

  /**
   * \brief This is where the magic for
   * Authentication happens.
   */
  function PostInitialize()
  {
    global $PG_CONN;
    global $SysConf;

    if (empty($PG_CONN))
    {
      return (0);
    }

    /* if Site Minder enabled core-auth will be disabled*/
    if (siteminder_check() != -1)
    {
      return (0);
    }

    session_name("Login");
    $mysess = session_id();
    if (empty($mysess)) session_start();

    if (array_key_exists('selectMemberGroup', $_POST))
    {
      global $container;
      $dbManager = $container->get("db.manager");
      $dbManager->prepare("selectMemberGroup", "UPDATE users SET group_fk=$1 WHERE user_pk=$2");
      $dbManager->execute("selectMemberGroup", array($_POST['selectMemberGroup'], $_SESSION['UserId']));
      $_SESSION['GroupId'] = $_POST['selectMemberGroup'];
      $SysConf['auth']['groupId'] = $_SESSION['GroupId'];
    }

    if (array_key_exists('UserId', $_SESSION)) $SysConf['auth']['UserId'] = $_SESSION['UserId'];
    $Now = time();
    if (!empty($_SESSION['time']))
    {
      /* Logins older than 60 secs/min * 480 min = 8 hr are auto-logout */
      if (@$_SESSION['time'] + (60 * 480) < $Now) $this->UpdateSess("");
    }

    $_SESSION['time'] = $Now;
    if (empty($_SESSION['ip']))
    {
      $_SESSION['ip'] = $this->GetIP();
    } else
      if ((@$_SESSION['checkip'] == 1) && (@$_SESSION['ip'] != $this->GetIP()))
      {
        /* Sessions are not transferable. */
        $this->UpdateSess("");
        $_SESSION['ip'] = $this->GetIP();
      }

    /* Enable or disable plugins based on login status */
    $Level = PLUGIN_DB_NONE;
    if (@$_SESSION['User'])
    {
      /* If you are logged in, then the default level is "Download". */
      if ("X" . $_SESSION['UserLevel'] == "X")
      {
        $Level = PLUGIN_DB_WRITE;
      } else
      {
        $Level = @$_SESSION['UserLevel'];
      }
      /* Recheck the user in case he is suddenly blocked or changed. */
      if (empty($_SESSION['time_check']))
      {
        $_SESSION['time_check'] = time() + (480 * 60);
      }
      if (time() >= @$_SESSION['time_check'])
      {
        global $container;
        $dbManager = $container->get("db.manager");
        $sql = "SELECT users.*,group_name FROM users LEFT JOIN groups ON group_fk=group_pk WHERE user_name=$1";
        $R = $dbManager->getSingleRow($sql, array(@$_SESSION['UserId']));
        /* Check for instant logouts */
        if (empty($R['user_pass']))
        {
          $R = "";
        }
        $this->UpdateSess($R);
      }
    } else
      $this->UpdateSess("");

    /* Disable all plugins with >= level access */
    plugin_disable($_SESSION['UserLevel']);
    $this->State = PLUGIN_STATE_READY;
  } // PostInitialize()

  /**
   * \brief Set $_SESSION and $SysConf user variables
   * \param $UserRow users table row, if empty, use Default User
   * \return void, updates globals $_SESSION and $SysConf[auth][UserId] variables
   */
  function UpdateSess($UserRow)
  {
    global $SysConf;

    if (empty($UserRow))
    {
      global $container;
      $dbManager = $container->get("db.manager");
      $sql = "SELECT users.*,group_name FROM users LEFT JOIN groups ON group_fk=group_pk WHERE user_name=$1";
      $UserRow = $dbManager->getSingleRow($sql, array('Default User'));
    }

    $_SESSION['UserId'] = $UserRow['user_pk'];
    $SysConf['auth']['UserId'] = $UserRow['user_pk'];
    $_SESSION['User'] = $UserRow['user_name'];
    $_SESSION['Folder'] = $UserRow['root_folder_fk'];
    $_SESSION['UserLevel'] = $UserRow['user_perm'];
    $_SESSION['UserEmail'] = $UserRow['user_email'];
    $_SESSION['UserEnote'] = $UserRow['email_notify'];
    $_SESSION['GroupId'] = $UserRow['group_fk'];
    $SysConf['auth']['GroupId'] = $UserRow['group_fk'];
    $_SESSION['GroupName'] = $UserRow['group_name'];
  }


  /**
   * \brief See if a username/password is valid.
   *
   * \return string on match, or null on no-match.
   */
  function CheckUser($User, $Pass, $Referer)
  {
    global $container;

    if (empty($User) || $User == 'Default User')
    {
      return;
    }
    $dbManager = $container->get('db.manager');

    $R = $dbManager->getSingleRow("SELECT users.*,group_name FROM users LEFT JOIN groups ON group_fk=group_pk WHERE user_name=$1",
                                array($User),$logNote=__METHOD__ . 'select.user');
    if (empty($R['user_name']))
    {
      return;
    }
    /* Check the password -- only if a password exists */
    if (!empty($R['user_seed']) && !empty($R['user_pass']))
    {
      $Hash = sha1($R['user_seed'] . $Pass);
      if (strcmp($Hash, $R['user_pass']) != 0)
      {
        return;
      }
    } else if (!empty($R['user_seed']))
    {
      /* Seed with no password hash = no login */
      return;
    } else if (!empty($Pass))
    {
      /* empty password required */
      return;
    }
    /* If you make it here, then username and password were good! */
    $this->UpdateSess($R);
    $_SESSION['time_check'] = time() + (480 * 60);
    /* No specified permission means ALL permission */
    if ("X" . $R['user_perm'] == "X")
    {
      $_SESSION['UserLevel'] = PLUGIN_DB_ADMIN;
    } else
    {
      $_SESSION['UserLevel'] = $R['user_perm'];
    }
    $_SESSION['checkip'] = GetParm("checkip", PARM_STRING);
    /* Check for the no-popup flag */
    if (GetParm("nopopup", PARM_INTEGER) == 1)
    {
      $_SESSION['NoPopup'] = 1;
    } else
    {
      $_SESSION['NoPopup'] = 0;
    }

    /* Use the previous redirect, but only use it if it comes from this
      server's Traceback_uri().  (Ignore hostname.) */
    $Redirect = preg_replace("@^[^/]*//[^/]*@", "", GetParm("redirect", PARM_TEXT));
    $Uri = Traceback_uri();
    if (preg_match("/[?&]mod=(Default|" . $this->Name . ")/", $Redirect))
    {
      $Redirect = ""; /* don't reference myself! */
    }
    if (empty($Redirect) || strncmp($Redirect, $Uri, strlen($Uri)))
    {
      $Uri = Traceback_uri();
    } else
    {
      $Uri = $Redirect;
    }
    /* Redirect window */
    header("Location: $Referer");
  }

  /**
   * \brief This is only called when the user logs out.
   */
  function Output()
  {
    global $SysConf;

    if ($this->State != PLUGIN_STATE_READY)
    {
      return;
    }
    $V = "";
    if (!array_key_exists('User',$_SESSION) || $_SESSION['User'] == "Default User")
    {
      $User = GetParm("username", PARM_TEXT);
      $Pass = GetParm("password", PARM_TEXT);
      $Referer = GetParm("HTTP_REFERER", PARM_TEXT);
      if (empty($Referer))
      {
        $Referer = GetArrayVal('HTTP_REFERER', $_SERVER);
      }
      $VP = !empty($User) ? $this->CheckUser($User, $Pass, $Referer) : '';
      if (!empty($VP))
      {
        $V .= $VP;
      } else
      {
        /* Check for init and first-time use */
        if (plugin_find_id("init") >= 0)
        {
          $text = _("The system requires initialization. Please login and use the Initialize option under the Admin menu.");
          $V .= "<b>$text</b>";
          $V .= "<P />\n";
          /* Check for a default user */
          global $PG_CONN;
          $Level = PLUGIN_DB_ADMIN;
          $sql = "SELECT * FROM users WHERE user_perm = $Level LIMIT 1;";
          $result = pg_query($PG_CONN, $sql);
          DBCheckResult($result, $sql, __FILE__, __LINE__);
          $R = pg_fetch_assoc($result);
          pg_free_result($result);
          if (array_key_exists("user_seed", $R) && array_key_exists("user_pass", $R))
          {
            $sql = "SELECT user_name FROM users WHERE user_seed IS NULL AND user_pass IS NULL;";
            $result = pg_query($PG_CONN, $sql);
            DBCheckResult($result, $sql, __FILE__, __LINE__);
          } else
          {
            $sql = "SELECT user_name FROM users;";
            $result = pg_query($PG_CONN, $sql);
            DBCheckResult($result, $sql, __FILE__, __LINE__);
          }
          $R = pg_fetch_assoc($result);
          pg_free_result($result);
          if (!empty($R['user_name']))
          {
            $V .= _("If you need an account, use '" . $R['user_name'] . "' with no password.\n");
            $V .= "<P />\n";
          }
        }
        /* Inform about the protocol. */
        $Protocol = preg_replace("@/.*@", "", @$_SERVER['SERVER_PROTOCOL']);
        if ($Protocol != 'HTTPS')
        {
          $V .= "This login uses $Protocol, so passwords are transmitted in plain text.  This is not a secure connection.<P />\n";
        }
        $V .= "<form method='post'>\n";
        $V .= "<input type='hidden' name='HTTP_REFERER' value='$Referer'>";
        $V .= "<table border=0>";
        $text = _("Username:");
        $V .= "<tr><td>$text</td><td><input type='text' size=20 name='username' id='unamein'></td></tr>\n";
        $text = _("Password:");
        $V .= "<tr><td>$text</td><td><input type='password' size=20 name='password'></td></tr>\n";
        $V .= "</table>";
        $V .= "<P/>";
        $V .= "<script type=\"text/javascript\">document.getElementById(\"unamein\").focus();</script>";
        $text = _("Login");
        $V .= "<input type='submit' value='$text'>\n";
        $V .= "</form>\n";
      }
    } else
      /* It's a logout */
    {
      $this->UpdateSess("");
      $Uri = Traceback_uri();
      $V .= "<script language='javascript'>\n";
      $V .= "window.open('$Uri','_top');\n";
      $V .= "</script>\n";
    }

    $this->vars['content'] = $V;
  } // Output()

}

$NewPlugin = new core_auth;
