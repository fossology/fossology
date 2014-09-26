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

define("TITLE_user_add", _("Add A User"));

class user_add extends FO_Plugin {
  function __construct()
  {
    $this->Name = "user_add";
    $this->Title = TITLE_user_add;
    $this->MenuList = "Admin::Users::Add";
    $this->DBaccess = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /**
   * \brief Add a user.
   * 
   * \return NULL on success, string on failure.
   */
  function Add() {
     
    global $PG_CONN;

    if (!$PG_CONN) {
      DBconnect();
      if (!$PG_CONN)
      {
        $text = _("NO DB connection!");
        echo "<pre>$text\n</pre>";
      }
    }

    /* Get the parameters */
    $User = str_replace("'", "''", GetParm('username', PARM_TEXT));
    $User = trim($User);
    $Pass = GetParm('pass1', PARM_TEXT);
    $Pass2 = GetParm('pass2', PARM_TEXT);
    $Seed = rand() . rand();
    $Hash = sha1($Seed . $Pass);
    $Desc = str_replace("'", "''", GetParm('description', PARM_TEXT));
    $Perm = GetParm('permission', PARM_INTEGER);
    $Folder = GetParm('folder', PARM_INTEGER);
    $Email_notify = GetParm('enote', PARM_TEXT);
    $Email = str_replace("'", "''", GetParm('email', PARM_TEXT));
    $agentList = userAgents();
    $default_bucketpool_fk = GetParm('default_bucketpool_fk', PARM_INTEGER);
    $new_upload_group_fk = GetParm('new_upload_group_fk', PARM_INTEGER);
    $new_upload_perm = GetParm('new_upload_perm', PARM_INTEGER);
    $uiChoice = GetParm('whichui', PARM_TEXT);


    /* Make sure username looks valid */
    if (empty($User)) {
      $text = _("Username must be specified. Not added.");
      return ($text);
    }
    /* limit the user name size to 64 characters when creating an account */
    if (strlen($User) > 64)
    {
      $text = _("Username exceed 64 characters. Not added.");
      return ($text);
    }
    /* Make sure password matches */
    if ($Pass != $Pass2) {
      $text = _("Passwords did not match. Not added.");
      return ($text);
    }
    /* Make sure email looks valid */
    $Check = preg_replace("/[^a-zA-Z0-9@_.+-]/", "", $Email);
    if ($Check != $Email) {
      $text = _("Invalid email address.  Not added.");
      return ($text);
    }
    /* See if the user already exists (better not!) */
    $sql = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (!empty($row['user_name'])) {
      $text = _("User already exists.  Not added.");
      return ($text);
    }

    /* check email notification, if empty (box not checked), or if no email
     * specified for the user set to 'n'.
     */
    if(empty($Email_notify)) {
      $Email_notify = '';
    }
    elseif(empty($Email)) {
      $Email_notify = '';
    }

    /* Add the user */
    if($uiChoice != 'simple')
    {
      $uiChoice = 'original';
    }

    if (empty($new_upload_group_fk)) $new_upload_group_fk = 'NULL';
    if (empty($new_upload_perm)) $new_upload_perm = 'NULL';

    $ErrMsg = add_user($User,$Desc,$Seed,$Hash,$Perm,$Email,
                       $Email_notify,$agentList,$Folder, $default_bucketpool_fk);

    return ($ErrMsg);
  } // Add()


  protected function htmlContent() {
    /* If this is a POST, then process the request. */
    $User = GetParm('username', PARM_TEXT);
    if (!empty($User)) {
      $rc = $this->Add();
      if (empty($rc)) {
        $text = _("User");
        $text1 = _("added");
        $this->vars['message'] = "$text $User $text1.";
      }
      else {
        $this->vars['message'] = $rc;
      }
    }

    $V = "<form name='formy' method='POST'>\n";
    $V.= _("To create a new user, enter the following information:<P />\n");
    $Style = "<tr><td colspan=2 style='background:black;'></td></tr><tr>";
    $V.= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='75%'>";
    $Val = htmlentities(GetParm('username', PARM_TEXT), ENT_QUOTES);
    $text = _("Username");
    $V.= "$Style<th width='25%' >$text</th>";
    $V.= "<td><input type='text' value='$Val' name='username' size=20></td>\n";
    $V.= "</tr>\n";
    $Val = htmlentities(GetParm('description', PARM_TEXT), ENT_QUOTES);
    $text = _("Description, full name, contact, etc. (optional)");
    $V.= "$Style<th>$text</th>\n";
    $V.= "<td><input type='text' name='description' value='$Val' size=60></td>\n";
    $V.= "</tr>\n";
    $Val = htmlentities(GetParm('email', PARM_TEXT), ENT_QUOTES);
    $text = _("Email address (optional)");
    $V .= "$Style<th>$text</th>\n";
    $V.= "<td><input type='text' name='email' value='$Val' size=60></td>\n";
    $V.= "</tr>\n";
    $text = _("Access level");
    $V.= "$Style<th>$text</th>";
    $V.= "<td><select name='permission'>\n";
    $text = _("None (very basic, no database access)");
    $V.= "<option value='" . PLUGIN_DB_NONE . "'>$text</option>\n";
    $text = _("Read-only (read, but no writes or downloads)");
    $V.= "<option selected value='" . PLUGIN_DB_READ . "'>$text</option>\n";
    $text = _("Read-Write (read, download, or edit information)");
    $V.= "<option value='" . PLUGIN_DB_WRITE . "'>$text</option>\n";
    $text = _("Full Administrator (all access including adding and deleting users)");
    $V.= "<option value='" . PLUGIN_DB_ADMIN . "'>$text</option>\n";
    $V.= "</select></td>\n";
    $V.= "</tr>\n";
    $text = _("User root folder");
    $V.= "$Style<th>$text";
    $V.= "</th>";
    $V.= "<td><select name='folder'>";
    $V.= FolderListOption(-1, 0);
    $V.= "</select></td>\n";
    $V.= "</tr>\n";
    $text = _("Password (optional)");
    $V.= "$Style<th>$text</th><td><input type='password' name='pass1' size=20></td>\n";
    $V.= "</tr>\n";
    $text = _("Re-enter password");
    $V.= "$Style<th>$text</th><td><input type='password' name='pass2' size=20></td>\n";
    $V.= "</tr>\n";
    $text = _("E-mail Notification");
    $text1 = _("Check to enable email notification when upload scan completes .");
    $V .= "$Style<th>$text</th><td><input type='checkbox'" .
            "name='enote' value='y' checked='checked'>" .
            "$text1</td>\n";
    $V.= "</tr>\n";
    $text = _("Agents selected by default when uploading");
    $V .= "$Style<th>$text\n</th><td> ";
    $V.= AgentCheckBoxMake(-1, array("agent_unpack", "agent_adj2nest", "wget_agent"));

    $V .= "</td>\n";
    $text = _("Default bucketpool");
    $V.= "$Style<th>$text</th>";
    $V.= "<td>";
    $default_bucketpool_fk = 0;
    $V.= SelectBucketPool($default_bucketpool_fk);
    $V.= "</td>";
    $V .= "</tr>\n";
    $V.= "</table border=0><P />";

    $text = _("Add User");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";
    return $V;
  }
}
$NewPlugin = new user_add;
