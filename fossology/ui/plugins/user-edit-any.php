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
class user_edit_any extends FO_Plugin {
  var $Name = "user_edit_any";
  var $Title = "Edit A User";
  var $MenuList = "Admin::Users::Edit Users";
  var $Version = "1.0";
  var $Dependency = array("db");
  var $DBaccess = PLUGIN_DB_USERADMIN;
  /*********************************************
  Edit(): Edit a user.
  Returns NULL on success, string on failure.
  *********************************************/
  function Edit() {
    global $DB;
    /* Get the parameters */
    $UserId = GetParm('userid', PARM_INTEGER);
    if (empty($UserId)) {
      return ("No user selected. No change.");
    }
    $User = GetParm('username', PARM_TEXT);
    $Pass1 = GetParm('pass1', PARM_TEXT);
    $Pass2 = GetParm('pass2', PARM_TEXT);
    $Seed = rand() . rand();
    $Hash = sha1($Seed . $Pass1);
    $Desc = GetParm('description', PARM_TEXT);
    $Perm = GetParm('permission', PARM_INTEGER);
    $Folder = GetParm('folder', PARM_INTEGER);
    $Email = GetParm('email', PARM_TEXT);
    $Email_notify = GetParm('enote', PARM_TEXT);
    $Block = GetParm("block", PARM_INTEGER);
    $Blank = GetParm("blank", PARM_INTEGER);
    if (!empty($Email_notify)) {
      print "<pre>email_notif is:$Email_notify\n</pre>";
    }
    /* Make sure username looks valid */
    if (empty($User)) {
      return ("Username must be specified. No change.");
    }
    /* Make sure password matches */
    if ($Pass1 != $Pass2) {
      return ("Passwords did not match. No change.");
    }
    /* Make sure email looks valid */
    $Check = preg_replace("/[^a-zA-Z0-9@_.+-]/", "", $Email);
    if ($Check != $Email) {
      return ("Invalid email address.  Not edited.");
    }
    /* Get existing user info for updating */
    $SQL = "SELECT * FROM users WHERE user_pk = '$UserId' LIMIT 1;";
    $Results = $DB->Action($SQL);
    $R = $Results[0];
    if (empty($R['user_pk'])) {
      return ("User does not exist.  No change.");
    }
    /* Edit the user */
    if (strcmp($User, $R['user_name'])) {
      /* See if the user already exists (better not!) */
      $Val = str_replace("'", "''", $User);
      $SQL = "SELECT * FROM users WHERE user_name = '$Val' LIMIT 1;";
      $Results = $DB->Action($SQL);
      if (!empty($Results[0]['user_name'])) {
        return ("User already exists.  Not edited.");
      }
      $DB->Action("UPDATE users SET user_name = '$Val' WHERE user_pk = '$UserId';");
    }
    if (strcmp($Desc, $R['user_desc'])) {
      $Val = str_replace("'", "''", $Desc);
      $DB->Action("UPDATE users SET user_desc = '$Val' WHERE user_pk = '$UserId';");
    }
    if (strcmp($Email, $R['user_email'])) {
      $Val = str_replace("'", "''", $Email);
      $DB->Action("UPDATE users SET user_email = '$Val' WHERE user_pk = '$UserId';");
    }
    /* check email notification, if empty (box not checked), or if no email
    * specified for the user set to ''. (default value for field is 'y').
    */
    print "<pre>R-email_notif is:{$R['email_notif']}\n</pre>";
    print "<pre>Email_notif is:$Email_notify\n</pre>";
    if ($Email_notify != $R['email_notify']) {
      if ($Email_notify == 'on') {
        $Email_notify = 'y';
      }
      print "<pre>Setting Email_notif to:$Email_notify\n</pre>";
      $DB->Action("UPDATE users SET email_notif = '$Email_notify' WHERE user_pk = '$UserId';");
    } elseif (empty($Email)) {
      $DB->Action("UPDATE users SET email_notif = '' WHERE user_pk = '$UserId';");
    }
    if ($Folder != $R['root_folder_fk']) {
      $DB->Action("UPDATE users SET root_folder_fk = '$Folder' WHERE user_pk = '$UserId';");
    }
    if ($Perm != $R['user_perm']) {
      $DB->Action("UPDATE users SET user_perm = '$Perm' WHERE user_pk = '$UserId';");
    }
    if ($Blank == 1) {
      $Seed = rand() . rand();
      $Hash = sha1($Seed . "");
      $DB->Action("UPDATE users SET user_seed = '$Seed', user_pass = '$Hash' WHERE user_pk = '$UserId';");
      $R['user_seed'] = $Seed;
      $R['user_pass'] = $Pass;
    }
    if (!empty($Pass1)) {
      $Seed = rand() . rand();
      $Hash = sha1($Seed . $Pass1);
      $DB->Action("UPDATE users SET user_seed = '$Seed', user_pass = '$Hash' WHERE user_pk = '$UserId';");
      $R['user_seed'] = $Seed;
      $R['user_pass'] = $Pass;
    }
    if (substr($R['user_pass'], 0, 1) == ' ') {
      $OldBlock = 1;
    } else {
      $OldBlock = 0;
    }
    if (empty($Block)) {
      $Block = 0;
    }
    if ($Block != $OldBlock) {
      if ($Block) {
        $DB->Action("UPDATE users SET user_pass = ' " . $R['user_pass'] . "' WHERE user_pk = '$UserId';");
      } else {
        $DB->Action("UPDATE users SET user_pass = '" . trim($R['user_pass']) . "' WHERE user_pk = '$UserId';");
      }
    }
    $Results = $DB->Action($SQL);
    return (NULL);
  } // Edit()
  /*********************************************
  Output(): Generate the text for this plugin.
  *********************************************/
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    global $DB;
    $V = "";
    switch ($this->OutputType) {
      case "XML":
      break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $UserId = GetParm('userid', PARM_INTEGER);
        if (!empty($UserId)) {
          $rc = $this->Edit();
          if (empty($rc)) {
            /* Need to refresh the screen */
            $V.= PopupAlert('User edited.');
          } else {
            $V.= PopupAlert($rc);
          }
        }
        /* Get the list of users */
        $SQL = "SELECT user_pk,user_name,user_desc,user_pass,root_folder_fk,user_perm,user_email,email_notif FROM users WHERE user_pk != '" . @$_SESSION['UserId'] . "' ORDER BY user_name;";
        $Results = $DB->Action($SQL);
        /* Create JavaScript for updating users */
        $V.= "<script language='javascript'>\n";
        $V.= "<!--\n";
        $V.= "var Username = new Array();\n";
        $V.= "var Userdesc = new Array();\n";
        $V.= "var Useremail = new Array();\n";
        $V.= "var Userenote = new Array();\n";
        $V.= "var Userperm = new Array();\n";
        $V.= "var Userblock = new Array();\n";
        $V.= "var Userfolder = new Array();\n";
        for ($i = 0;!empty($Results[$i]['user_pk']);$i++) {
          $R = & $Results[$i];
          $Id = $R['user_pk'];
          $Val = str_replace('"', "\\\"", $R['user_name']);
          $V.= "Username[" . $Id . '] = "' . $Val . "\";\n";
          $Val = str_replace('"', "\\\"", $R['user_desc']);
          $V.= "Userdesc[" . $Id . '] = "' . $Val . "\";\n";
          $Val = str_replace('"', "\\\"", $R['user_email']);
          $V.= "Useremail[" . $Id . '] = "' . $Val . "\";\n";
          $V.= "Userenote[" . $Id . '] = "' . $R['email_notify'] . "\";\n";
          $V.= "Userfolder[" . $Id . "] = '" . $R['root_folder_fk'] . "';\n";
          $V.= "Userperm[" . $Id . "] = '" . $R['user_perm'] . "';\n";
          if (substr($R['user_pass'], 0, 1) == ' ') {
            $Block = 1;
          } else {
            $Block = 0;
          }
          $V.= "Userblock[" . $Id . "] = '$Block';\n";
        }
        $V.= "function SetInfo(id)\n";
        $V.= "{\n";
        $V.= "  document.formy.username.value = Username[id];\n";
        $V.= "  document.formy.email.value = Useremail[id];\n";
        $V.= "  document.formy.description.value = Userdesc[id];\n";
        $V.= "  document.formy.permission.value = Userperm[id];\n";
        $V.= "  document.formy.folder.value = Userfolder[id];\n";
        $V.= "  if (Userblock[id] == 1) { document.formy.block.checked=true; }\n";
        $V.= "  else { document.formy.block.checked=false; }\n";
        $V.= "  if (Userenote[id] == 'y') { document.formy.enote.checked=true; }\n";
        $V.= "  else { document.formy.enote.checked=false; }\n";
        $V.= "}\n";
        $V.= "// -->\n";
        $V.= "</script>\n";
        /* Build HTML form */
        $V.= "<form name='formy' method='POST'>\n"; // no url = this url
        $V.= "Select the user to edit: ";
        if (empty($UserId)) {
          $UserId = $Results[0]['user_pk'];
        }
        $V.= "<select name='userid' onload='SetInfo($UserId);' onchange='SetInfo(this.value);'>\n";
        for ($i = 0;!empty($Results[$i]['user_pk']);$i++) {
          $Selected = "";
          if ($UserId == $Results[$i]['user_pk']) {
            $Selected = "selected";
          }
          $V.= "<option $Selected value='" . $Results[$i]['user_pk'] . "'>";
          $V.= htmlentities($Results[$i]['user_name']);
        }
        $V.= "</select>\n";
        $V.= "<P />\n";
        $V.= "To edit another user on this system, alter any of the following information.<P />\n";
        $Style = "<tr><td colspan=3 style='background:black;'></td></tr><tr>";
        $V.= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='100%'>";
        $Val = htmlentities(GetParm('username', PARM_TEXT), ENT_QUOTES);
        $V.= "$Style<th width='25%'>Change the username.</th>";
        $V.= "<td><input type='text' value='$Val' name='username' size=20></td>\n";
        $V.= "</tr>\n";
        $Val = htmlentities(GetParm('description', PARM_TEXT), ENT_QUOTES);
        $V.= "$Style<th>Change the user's description (name, contact, or other information).  This may be blank.</th>\n";
        $V.= "<td><input type='text' name='description' value='$Val' size=60></td>\n";
        $V.= "</tr>\n";
        $Val = htmlentities(GetParm('email', PARM_TEXT), ENT_QUOTES);
        $V.= "$Style<th>Change the user's email address. This may be blank.</th>\n";
        $V.= "<td><input type='text' name='email' value='$Val' size=60></td>\n";
        $V.= "</tr>\n";
        $V.= "$Style<th>Select the user's access level.</th>";
        $V.= "<td><select name='permission'>\n";
        $V.= "<option value='" . PLUGIN_DB_NONE . "'>None (very basic, no database access)</option>\n";
        $V.= "<option selected value='" . PLUGIN_DB_READ . "'>Read-only (read, but no writes or downloads)</option>\n";
        $V.= "<option value='" . PLUGIN_DB_DOWNLOAD . "'>Download (Read-only, but can download files)</option>\n";
        $V.= "<option value='" . PLUGIN_DB_WRITE . "'>Read-Write (read, download, or edit information)</option>\n";
        $V.= "<option value='" . PLUGIN_DB_UPLOAD . "'>Upload (read-write, and permits uploading files)</option>\n";
        $V.= "<option value='" . PLUGIN_DB_ANALYZE . "'>Analyze (... and permits scheduling analysis tasks)</option>\n";
        $V.= "<option value='" . PLUGIN_DB_DELETE . "'>Delete (... and permits deleting uploaded files and analysis)</option>\n";
        $V.= "<option value='" . PLUGIN_DB_DEBUG . "'>Debug (... and allows access to debugging functions)</option>\n";
        $V.= "<option value='" . PLUGIN_DB_USERADMIN . "'>Full Administrator (all access including adding and deleting users)</option>\n";
        $V.= "</select></td>\n";
        $V.= "</tr>\n";
        $V.= "$Style<th>Select the user's top-level folder. Access is restricted to this folder.";
        $V.= " (NOTE: This is only partially implemented right now. Current users can escape the top of tree limitation.)";
        $V.= "</th>";
        $V.= "<td><select name='folder'>";
        $V.= FolderListOption(-1, 0);
        $V.= "</select></td>\n";
        $V.= "</tr>\n";
        $V.= "$Style<th>Block the user's account. This will prevent logins.</th><td><input type='checkbox' name='block' value='1'></td>\n";
        $V.= "$Style<th>Blank the user's account. This will will set the password to a blank password.</th><td><input type='checkbox' name='blank' value='1'></td>\n";
        $V.= "$Style<th>Change the user's password.</th><td><input type='password' name='pass1' size=20></td>\n";
        $V.= "</tr>\n";
        $V.= "<tr><th>Re-enter the user's password.</th><td><input type='password' name='pass2' size=20></td>\n";
        $V.= "</tr>\n";
        $V.= "$Style<th>E-mail Notification</th><td><input type=checkbox name='enote'" . "checked=document.formy.enote.checked>" . "Check to enable email notification of completed analysis.</td>\n";
        $V.= "</tr>\n";
        $V.= "</table><P />";
        $V.= "<input type='submit' value='Edit!'>\n";
        $V.= "</form>\n";
      break;
      case "Text":
      break;
      default:
      break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ("$V");
    return;
  }
};
$NewPlugin = new user_edit_any;
$NewPlugin->Initialize();
?>
