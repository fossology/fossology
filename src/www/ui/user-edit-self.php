<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_user_edit_self", _("Edit Your Account Settings"));

class user_edit_self extends FO_Plugin
{
  function __construct()
  {
    $this->Name = "user_edit_self";
    $this->Title = TITLE_user_edit_self;
    $this->MenuList = "Admin::Users::Account Settings";
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 1;
    parent::__construct();
  }

  /**
   * \brief This function is called before the plugin
   * is used and after all plugins have been initialized.
   * 
   * \return on success, false on failure.
   * \note Do not assume that the plugin exists!  Actually check it!
   * Purpose: Only allow people who are logged in to edit their own properties.
   */
  function PostInitialize()
  {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_VALID)
    {
      return (0);
    } // don't run
    if ($_SESSION['User'] == "Default User")
    {
      /* Only valid if the user is logged in. */
      $this->State = PLUGIN_STATE_INVALID;
      return (0);
    }
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val)
    {
      $id = plugin_find_id($val);
      if ($id < 0)
      {
        $this->Destroy();
        return (0);
      }
    }
    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if ($this->MenuList !== "")
    {
      menu_insert("Main::" . $this->MenuList, $this->MenuOrder, $this->Name, $this->MenuTarget);
    }
    return ($this->State == PLUGIN_STATE_READY);
  } // PostInitialize()

  /**
   * \brief Register additional menus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    } // don't run

  } // RegisterMenus()

  /**
   * \brief Alter a user.
   * 
   * \return NULL on success, string on failure.
   */
  function Edit() {
    global $PG_CONN;

    /* Get the parameters */
    $UserId = @$_SESSION['UserId'];
    $User = GetParm('username', PARM_TEXT);
    $Pass0 = GetParm('pass0', PARM_TEXT);
    $Pass1 = GetParm('pass1', PARM_TEXT);
    $Pass2 = GetParm('pass2', PARM_TEXT);
    $Seed = rand() . rand();
    $Desc = GetParm('description', PARM_TEXT);
    $Perm = GetParm('permission', PARM_INTEGER);
    $Folder = GetParm('folder', PARM_INTEGER);
    $Email = GetParm('email', PARM_TEXT);
    $Email_notify = GetParm('emailnotify', PARM_TEXT);
    $agentList = userAgents();
    $default_bucketpool_fk = GetParm('default_bucketpool_fk', PARM_INTEGER);
    $new_upload_group_fk = GetParm('new_upload_group_fk', PARM_INTEGER);
    $new_upload_perm = GetParm('new_upload_perm', PARM_INTEGER);
    $uiChoice = GetParm('whichui', PARM_TEXT);

    /* Make sure username looks valid */
    if (empty($_SESSION['UserId']))
    {
      $text = _("You must be logged in.");
      return ($text);
    }
    /* Make sure password matches */
    if (!empty($Pass1) || !empty($Pass2))
    {
      if ($Pass1 != $Pass2)
      {
        $text = _("New passwords did not match. No change.");
        return ($text);
      }
    }
    /* Make sure email looks valid */
    $Check = preg_replace("/[^a-zA-Z0-9@_.+-]/", "", $Email);
    if ($Check != $Email)
    {
      $text = _("Invalid email address.  Not added.");
      return ($text);
    }
    /* See if the user already exists (better not!) */
    $sql = "SELECT * FROM users WHERE user_name = '$User' AND user_pk != '$UserId' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (!empty($row['user_name']))
    {
      $text = _("User already exists.  Not added.");
      return ($text);
    }
    /* Load current user */
    $sql = "SELECT * FROM users WHERE user_pk = '$UserId' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $R = pg_fetch_assoc($result);
    pg_free_result($result);
    /* Make sure old password matched */
    /* if login by siteminder, didn't check old password just get old password*/
    if (siteminder_check() == -1)
    {
      $Hash = sha1($R['user_seed'] . $Pass0);
      if ($Hash != $R['user_pass'])
      {
        $text = _("Authentication password did not match. No change.");
        return ($text);
      }
    } else {
      $Pass0 = $R['user_pass'];
    }
    /* Update the user */
    $GotUpdate = 0;
    $SQL = "UPDATE users SET";
    if (!empty($User) && ($User != $R['user_name']))
    {
      $_SESSION['User'] = '$User';
      $User = str_replace("'", "''", $User);
      $SQL.= " user_name = '$User'";
      $GotUpdate = 1;
    }
    if ($Desc != $R['user_desc'])
    {
      $Desc = str_replace("'", "''", $Desc);
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      $SQL.= " user_desc = '$Desc'";
      $GotUpdate = 1;
    }
    if ($Email != $R['user_email'])
    {
      $Email = str_replace("'", "''", $Email);
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      $SQL.= " user_email = '$Email'";
      $GotUpdate = 1;
    }
    if ($Email_notify != $R['email_notify'])
    {
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      if ($Email_notify == 'on') {
        $Email_notify = 'y';
      } else {
        $Email_notify = '';
      }

      $SQL.= " email_notify = '$Email_notify'";
      $_SESSION['UserEnote'] = $Email_notify;
      $GotUpdate = 1;
    }

    if($agentList != $R['user_agent_list'])
    {
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      $SQL.= " user_agent_list = '$agentList'";
      $GotUpdate = 1;
    }

    if ($default_bucketpool_fk != $R['default_bucketpool_fk'])
    {
      if ($default_bucketpool_fk == 0) $default_bucketpool_fk='NULL';
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      $SQL.= " default_bucketpool_fk = $default_bucketpool_fk";
      $GotUpdate = 1;
    }

    if ($new_upload_group_fk != $R['new_upload_group_fk'])
    {
      if ($new_upload_group_fk == 0) $new_upload_group_fk='NULL';
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      $SQL.= " new_upload_group_fk = $new_upload_group_fk";
      $GotUpdate = 1;
    }

    if ($new_upload_perm != $R['new_upload_perm'])
    {
      if ($new_upload_perm == 0) $new_upload_perm='NULL';
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      $SQL.= " new_upload_perm = $new_upload_perm";
      $GotUpdate = 1;
    }

    if ($uiChoice != $R['ui_preference'])
    {
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      $SQL.= " ui_preference = '$uiChoice'";
      $_SESSION['UiPref'] = $uiChoice;
      $GotUpdate = 1;
    }
    if (!empty($Pass1) && ($Pass0 != $Pass1) && ($Pass1 == $Pass2)) {
      $Seed = rand() . rand();
      $Hash = sha1($Seed . $Pass1);
      if ($GotUpdate)
      {
        $SQL.= ", ";
      }
      $SQL.= " user_seed = '$Seed'";
      $SQL.= ", user_pass = '$Hash'";
      $GotUpdate = 1;
    }
    $SQL.= " WHERE user_pk = '$UserId';";
    if ($GotUpdate)
    {
      $result = pg_query($PG_CONN, $SQL);
      DBCheckResult($result, $SQL, __FILE__, __LINE__);
      pg_free_result($result);
    }
    $_SESSION['timeout_check'] = 1; /* force a recheck */
    return (NULL);
  } // Edit()


  
  protected function htmlContent()
  {
    global $PG_CONN;
    $V = "";

    /* If this is a POST, then process the request. */
    $User = GetParm('username', PARM_TEXT);
    if (!empty($User))
    {
      $rc = $this->Edit();
      if (empty($rc))
      {
        $text = _("User information updated.");
        $this->vars['message'] = $text;
      }
      else
      {
        $this->vars['message'] = $rc;
      }
    }

    $sql = "SELECT * FROM users WHERE user_pk='" . @$_SESSION['UserId'] . "'"; 
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $R = pg_fetch_assoc($result);
    pg_free_result($result);

    /* Build HTML form */
    $V.= "<form name='formy' method='POST'>\n"; // no url = this url
    /* if login by siteminder, didn't show this in page*/
    if (siteminder_check() == -1)
    {
      $V.= _("You <font color='red'>must</font> provide your current password in order to make any changes.<br />\n");
      $text = _("Enter your password");
      $V.= "$text: <input type='password' name='pass0' size=20>\n";
      $V.= "<hr>\n";
    }
    $V.= _("To change user information, edit the following fields. You do not need to edit every field. Only fields with edits will be changed.<P />\n");
    $Style = "<tr><td colspan=2 style='background:black;'></td></tr><tr>";
    $V.= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='100%'>";

    $Val = htmlentities($R['user_name'], ENT_QUOTES);
    $text = _("Username");
    $V.= "$Style<th width='25%'>$text</th>";
    $V.= "<td><input type='text' value='$Val' name='username' size=20></td>\n";
    $V.= "</tr>\n";

    $Val = htmlentities($R['user_desc'], ENT_QUOTES);
    $text = _("Description, full name, contact, etc. (optional) ");
    $V.= "$Style<th>$text</th>\n";
    $V.= "<td><input type='text' name='description' value='$Val' size=60></td>\n";
    $V.= "</tr>\n";

    $Val = htmlentities($R['user_email'], ENT_QUOTES);
    $text = _("Email address (optional)");
    $V.= "$Style<th>$text</th>\n";
    $V.= "<td><input type='text' name='email' value='$Val' size=60></td>\n";
    $V.= "</tr>\n";

    $text = _("Password");
    $text1 = _("Re-enter password");
    $V.= "$Style<th>$text<br>$text1</th><td>";
    $V.= "<input type='password' name='pass1' size=20><br />\n";
    $V.= "<input type='password' name='pass2' size=20></td>\n";
    $V.= "</tr>\n";

    if (empty($R['email_notify']))
      $Checked = "";
    else
      $Checked = "checked='checked'";
    $text = _("E-mail Notification");
    $V .= "$Style<th>$text</th><td>\n";
    $V .= "<input name='emailnotify' type='checkbox' $Checked>";
    $V.= "</tr>\n";

    if (@$_SESSION['UserLevel'] > PLUGIN_DB_READ)
    {
      $text = _("Default scans");
      $V .= "$Style<th>$text\n</th><td>\n";
      $AgentList = menu_find("Agents",$Depth);
      if(!empty($AgentList))
      {
        foreach($AgentList as $AgentItem)
        {
          $uri = $AgentItem->URI;
        }
        if($uri == "agent_unpack" && count($AgentList) == 1 )
        {
          $text = _("You do not have permission to change your default agents");
          $V .= "<h3>$text</h3>\n";
        }
        else
        {
          $V.= AgentCheckBoxMake(-1, array("agent_unpack", "agent_adj2nest", "wget_agent"));
        }
      }
      $V .= "</td></tr>\n";

      $text = _("Default bucketpool");
      $V.= "$Style<th>$text</th>";
      $V.= "<td>";
      $Val = htmlentities($R['default_bucketpool_fk'], ENT_QUOTES);
      $V.= SelectBucketPool($Val);
      $V.= "</td>";
      $V .= "</tr>\n";
    }
    $V.= "</table><P />";
    $text = _("Update Account");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";
    return $V;
  }
}
$NewPlugin = new user_edit_self;
