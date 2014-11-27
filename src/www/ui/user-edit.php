<?php
/***********************************************************
 Copyright (C) 2014 Hewlett-Packard Development Company, L.P.

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

define("TITLE_user_edit", _("Edit User Account"));

class user_edit extends FO_Plugin {
  var $Name = "user_edit";
  var $Title = TITLE_user_edit;
  var $MenuList = "Admin::Users::Edit User Account";
  var $Version = "1.0";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_WRITE;
  var $LoginFlag = 1;

  /**
   * \brief Display the user record edit form
   * 
   * \param $UserRec - Database users record for the user to be edited.
   * \param $SessionIsAdmin - Boolean: This session is by an admin
   * \return the text of the display form on success, or error on failure.
   */
  function DisplayForm($UserRec, $SessionIsAdmin) 
  {
    global $PG_CONN;
    $OutS = "";  // Output string

    /* Build HTML form */
    $OutS .= "<form name='user_edit' method='POST'>\n"; // no url = this url

    $OutS .= "<p><input type='hidden' name='user_pk' value='$UserRec[user_pk]'/></p>";

    $OutS .= "<P />\n";

    if ($SessionIsAdmin)
    {
      $OutS .= _("Select the user to edit: ");
      $OutS .= "<select name='userid' onchange='RefreshPage(this.value);'>\n";
    }

    /* For Admins, get the list of all users 
     * For non-admins, only show themself
     */
    if ($SessionIsAdmin)
      $sql = "SELECT * FROM users ORDER BY user_name;";
    else
      $sql = "SELECT * FROM users WHERE user_pk='" . $UserRec['user_pk'] . "' ORDER BY user_name;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    while ($row = pg_fetch_assoc($result))
    {
      $Selected =  ($row['user_pk'] == $UserRec['user_pk']) ? "Selected" : "";
      $OutS .= "<option $Selected value='" . $row['user_pk'] . "'>";
      $OutS .= htmlentities($row['user_name']);
      $OutS .= "</option>\n";
    }
    pg_free_result($result);
    $OutS .= "</select><hr>\n";

    $TableStyle = "style='border:1px solid black; border-collapse: collapse; '";
    $TRStyle = "style='border:1px solid black; text-align:left; background:lightyellow;'";
    $OutS .= "<table $TableStyle width='100%'>";

    $Field = "user_name";
    $Val = htmlentities($UserRec[$Field], ENT_QUOTES);
    $text = _("Username.");
    $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
    $OutS .= "<td><input type='text' value='$Val' name='$Field' size=20></td>\n";
    $OutS .= "</tr>\n";

    $Field = "user_desc";
    $Val = htmlentities($UserRec[$Field], ENT_QUOTES);
    $text = _("Description (name, contact, or other information).  This may be blank.");
    $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
    $OutS .= "<td><input type='text' value='$Val' name='$Field' size=60></td>\n";
    $OutS .= "</tr>\n";

    $Field = "user_email";
    $Val = htmlentities($UserRec[$Field], ENT_QUOTES);
    $text = _("Email address. This may be blank.");
    $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
    $OutS .= "<td><input type='text' value='$Val' name='$Field' size=60></td>\n";
    $OutS .= "</tr>\n";

    $Field = "email_notify";
    $Checked = ($UserRec[$Field] == 'y') ? "checked" : "";
    $text = _("E-mail notification on job completion");
    $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
    $OutS .= "<td><input type=checkbox name='$Field' $Checked></td>";
    $OutS .= "</tr>\n";

    if ($SessionIsAdmin)
    {
      $Field = "user_perm";
      $Val = htmlentities($UserRec[$Field], ENT_QUOTES);
      $text = _("Select the user's access level.");
      $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
      $OutS .= "<td><select name='$Field'>\n";
      $text1 = _("None (very basic, no database access)");
      $text2 = _("Read-only (read, but no writes or downloads)");
      $text4 = _("Read-Write (read, download, or edit information)");
      $text9 = _("Full Administrator (all access including adding and deleting users)");
      $OutS .= "<option " . (($Val==PLUGIN_DB_NONE)?"selected":"") . " value='" . PLUGIN_DB_NONE . "'>$text1</option>\n";
      $OutS .= "<option " . (($Val==PLUGIN_DB_READ)?"selected":"") . " value='" . PLUGIN_DB_READ . "'>$text2</option>\n";
      $OutS .= "<option " . (($Val==PLUGIN_DB_WRITE)?"selected":"") . " value='" . PLUGIN_DB_WRITE . "'>$text4</option>\n";
      $OutS .= "<option " . (($Val==PLUGIN_DB_ADMIN)?"selected":"") . " value='" . PLUGIN_DB_ADMIN . "'>$text9</option>\n";
      $OutS .= "</select></td>\n";
      $OutS .= "</tr>\n";
    }

    if ($SessionIsAdmin)
    {
      $Field = "root_folder_fk";
      $Val = htmlentities($UserRec[$Field], ENT_QUOTES);
      $text = _("Select the user's top-level folder. Access is restricted to this folder.");
      $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
      $OutS .= "<td><select name='$Field'>";
      $ParentFolder = -1;
      $Depth = 0;
      $IncludeTop = 1;  // include top level folder in selecet list
      $SelectedFolderPk = $UserRec[$Field];
      $OutS .= FolderListOption($ParentFolder, $Depth, $IncludeTop, $SelectedFolderPk);
      $OutS .= "</select></td>\n";
      $OutS .= "</tr>\n";
    }

    if ($SessionIsAdmin)
    {
      $text = _("Blank the user's account. This will will set the password to a blank password.");
      $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
      $OutS .= "<td><input type='checkbox' name='_blank_pass' value='0'></td>\n";
      $OutS .= "</tr>\n";
    }

    $text = _("Password.");
    $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
    $OutS .= "<td><input type='password' name='_pass1' size=20></td>\n";
    $OutS .= "</tr>\n";
    $text = _("Re-enter password.");
    $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
    $OutS .= "<td><input type='password' name='_pass2' size=20></td>\n";
    $OutS .= "</tr>\n";

    $Field = "user_agent_list";
    $text = _("Default agents selected when uploading data. ");
    $OutS .= "<tr $TRStyle><th width='25%'>$text</th><td>";
    $OutS .= AgentCheckBoxMake(-1, array("agent_unpack", "agent_adj2nest", "wget_agent"), $UserRec['user_name']);
    $OutS .= "</td></tr>\n";

    $Field = "default_bucketpool_fk";
    $text = _("Default bucket pool");
    $OutS .= "<tr $TRStyle><th width='25%'>$text</th>";
    $OutS .= "<td>";
    $OutS .= SelectBucketPool($UserRec[$Field]);
    $OutS .= "</td></tr>\n";
    $OutS .= "</table><P />";

    $text = _("Update Account");
    $OutS .= "<input type='submit' name='UpdateBtn' value='$text'>\n";
    $OutS .= "</form>\n";

    return $OutS;
  }

  /**
   * \brief Validate and update the user data.
   * \param $UserRec - Database record for the user to be edited.
   * 
   * \return NULL on success, string (error text) on failure.
   */
  function UpdateUser($UserRec, $SessionIsAdmin) 
  {
    global $PG_CONN;

    $Errors = "";

    /**** Validations ****/
    /* Make sure we have a user_pk */
    if (empty($UserRec['user_pk'])) $Errors .= "<li>" . _("Consistency error.  User_pk missing.");

    /* Make sure username looks valid */
    if (empty($UserRec['user_name'])) $Errors .= "<li>" . _("Username must be specified.");

    /* Verify the user_name is not a duplicate  */
    $CheckUserRec = GetSingleRec("users", "WHERE user_name='$UserRec[user_name]'");
    if ((!empty($CheckUserRec)) and ($CheckUserRec['user_pk'] != $UserRec['user_pk']))
      $Errors .= "<li>" . _("Username is not unique.");

    /* Make sure password matches */
    if ($UserRec['_pass1'] != $UserRec['_pass2']) $Errors .= "<li>". _("Passwords do not match.");

    /* Make sure email looks valid */
    $Check = preg_replace("/[^a-zA-Z0-9@_.+-]/", "", $UserRec['user_email']);
    if ($Check != $UserRec['user_email']) $Errors .= "<li>". _("Invalid email address.");

    /* If we have any errors, return them */
    if (!empty($Errors)) return _("Errors") . ":<ol>$Errors </ol>";

    /**** Update the users database record ****/
    /* First remove user_pass and user_seed if the password wasn't changed. */
    if ($UserRec['_blank_pass'] == 1) 
    {
      $UserRec['user_pass'] = "";
      $UserRec['user_seed'] = "";
    }
    else if (empty($UserRec['_pass1']))   // password wasn't changed
    {
      unset( $UserRec['user_pass']);
      unset( $UserRec['user_seed']);
    }
    

    $sql = "UPDATE users SET ";
    $first = TRUE;
    foreach($UserRec as $key=>$val)
    {
      if ($key[0] == '_') continue;
      if ($key == "user_pk") continue;
      if (!$SessionIsAdmin) 
      {
        if ($key == "user_perm") continue;
        if ($key == "root_folder_fk") continue;
      }

      if (!$first) $sql .= ",";
      $sql .= "$key='$val'";
      $first = FALSE;
    }
    $sql .= " where user_pk=$UserRec[user_pk]";

    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    return (NULL);
  } // UpdateUser()

  /**
   * \brief Get a user record
   * \param $user_pk  fetch this users db record
   * 
   * \return users db record
   */
  function GetUserRec($user_pk) 
  {
    if (empty($user_pk))
    {
      echo _("Invalid access.  Your session has expired.");
      exit(1);
    }
    $UserRec = GetSingleRec("users", "WHERE user_pk=$user_pk");
    if (empty($UserRec))
    {
      echo _("Invalid user. ");
      exit(1);
    }
    return $UserRec;
  }

  /**
   * \brief Determine if the session user is an admin
   * 
   * \return TRUE if the session user is an admin.  Otherwise, return FALSE
   */
  function IsSessionAdmin($UserRec) 
  {
    if ($UserRec['user_perm'] == PLUGIN_DB_ADMIN) return TRUE;
    return FALSE;
  }

  /**
   * \brief Create a user record.
   *        If there is post data, use that to create the user record
   *        else use $user_pk (the session user).
   * \param integer $user_pk: the session user
   * 
   * \return A user record in the same associated array format that you get from a pg_fetch_assoc().
   *         However, there may be additional fields from the data input form that are not in the 
   *         users table.  These additional fields start with an underscore (_pass1, _pass2, _blank_pass)
   *         that come from the edit form.
   */
  function CreateUserRec($user_pk) 
  {
    global $PG_CONN;

    /* If this is a result of pressing the update button then use the post data 
     * Otherwise, use the users own data.
     */
    $BtnText = GetParm('UpdateBtn', PARM_TEXT);
    if (!empty($BtnText)) 
    {
      $UserRec = array();
      $UserRec['user_pk'] = GetParm('user_pk', PARM_TEXT);
      $UserRec['user_name'] = GetParm('user_name', PARM_TEXT);
      $UserRec['root_folder_fk'] = GetParm('root_folder_fk', PARM_INTEGER);
      $UserRec['user_desc'] = GetParm('user_desc', PARM_TEXT);
      $UserRec['user_seed'] = rand() . rand();
      $UserRec['_pass1'] = GetParm('_pass1', PARM_TEXT);
      $UserRec['_pass2'] = GetParm('_pass2', PARM_TEXT);
      if (!empty($UserRec['_pass1']))
        $UserRec['user_pass'] = sha1($UserRec['user_seed'] . $UserRec['_pass1']);
      else
        $UserRec['user_pass'] = "";
      $UserRec['user_perm'] = GetParm('user_perm', PARM_INTEGER);
      $UserRec['user_email'] = GetParm('user_email', PARM_TEXT);
      $UserRec['email_notify'] = GetParm('email_notify', PARM_TEXT);
      if (!empty($UserRec['email_notify'])) $UserRec['email_notify'] = 'y';
      $UserRec['user_agent_list'] = userAgents();
      $UserRec['_blank_pass'] = GetParm("_blank_pass", PARM_INTEGER);
      $UserRec['default_bucketpool_fk'] = GetParm("default_bucketpool_fk", PARM_INTEGER);
    }
    else
    {
      $UserRec = GetSingleRec("users", "WHERE user_pk=$user_pk");
      $UserRec['_pass1'] = "";
      $UserRec['_pass2'] = "";
      $UserRec['_blank_pass'] = 0;
    }
    return $UserRec;
  }

  /**
   * \brief Allow user to change their account settings (users db table).  
   *        If the user is an Admin, they can change settings for any user.\n
   *        This is called in the following circumstances:\n
   *        1) User clicks on Admin > Edit User Account\n
   *        2) User has chosen a user to edit from the 'userid' select list  \n
   *        3) User hit submit to update user data\n
   */
  function Output() 
  {
    if ($this->State != PLUGIN_STATE_READY)  return;

    global $PG_CONN;
    global $PERM_NAMES;

    /* Is the session owner an admin?  */
    $user_pk = $_SESSION['UserId'];
    $SessionUserRec = $this->GetUserRec($user_pk);
    $SessionIsAdmin = $this->IsSessionAdmin($SessionUserRec);

    /* Get the data to edit in an associated array */
    $UserRec = $this->CreateUserRec($user_pk);

    $V = "";

    /* script to refresh this page with the selected user data (newuser=user_pk) */
    $uri = Traceback_uri() . "?mod=$this->Name";
    $V .= "<script language='javascript'>\n";
    $V .= "function RefreshPage(val) {";
    $V .=  "var uri = '$uri' + '&newuser=' + val ;";
    $V .=  "window.location.assign(uri);";
    $V .= "}";
    $V .= "</script>\n";


    /* If this is a POST (the submit button was clicked), then process the request. */
    $BtnText = GetParm('UpdateBtn', PARM_TEXT);
    if (!empty($BtnText)) 
    {
      $rv = $this->UpdateUser($UserRec, $SessionIsAdmin);
      if (empty($rv)) 
      {
        // Successful db update
        $V .= displayMessage("User $UserRec[user_name] updated.");

        /* Reread the user record as update verification */
        $UserRec = $this->CreateUserRec($UserRec['user_pk']);
      }
      else 
      {
        // Unsuccessful so display errors
        $V .= displayMessage($rv);
      }
    }
    else  // was a new user record requested (admin only)?
    {
      $NewUserpk = GetParm('newuser', PARM_INTEGER);
      if (!empty($NewUserpk)) $UserRec = $this->CreateUserRec($NewUserpk);
    }
    
    /* display the edit form with the requested user data */
    $V .= $this->DisplayForm($UserRec, $SessionIsAdmin);

    if (!$this->OutputToStdout) { return ($V); }
    print ("$V");
    return;
  }
}
$NewPlugin = new user_edit;
?>
