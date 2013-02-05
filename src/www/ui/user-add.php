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
  var $Name = "user_add";
  var $Title = TITLE_user_add;
  var $MenuList = "Admin::Users::Add";
  var $Version = "1.0";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_ADMIN;

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

    /* debug
     print "<pre>";
     print "UserAddDB: User is:$User\n";
     print "UserAddDB: Desc is:$Desc\n";
     print "UserAddDB: Pass is:$Pass\n";
     print "UserAddDB: Pass2 is:$Pass2\n";
     print "UserAddDB: Seed is:$Seed\n";
     print "UserAddDB: Hash is:$Hash\n";
     print "UserAddDB: Perm is:$Perm\n";
     print "UserAddDB: Email is:$Email\n";
     print "UserAddDB: EM_notify is:$Email_notify\n";
     print "UserAddDB: agent list is:$agentList\n";
     print "UserAddDB: folder is:$Folder\n";
     print "UserAddDB: default_bucket_pool is:$default_bucketpool_fk\n";
     print "UserAddDB: uiChioce is:$uiChoice\n";
     print "</pre>";
     */


    /* Make sure username looks valid */
    if (empty($User)) {
      $text = _("Username must be specified. Not added.");
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

    if (empty($default_bucketpool_fk)) {
      $VALUES = " VALUES ('$User','$Desc','$Seed','$Hash',$Perm,'$Email',
      '$Email_notify','$agentList',$Folder, NULL,
      '$uiChoice', $new_upload_group_fk, $new_upload_perm);";
    }
    else {
      $VALUES = " VALUES ('$User','$Desc','$Seed','$Hash',$Perm,'$Email',
               '$Email_notify','$agentList',$Folder, $default_bucketpool_fk,
               '$uiChoice', $new_upload_group_fk, $new_upload_perm);";
    }

    $SQL = "INSERT INTO users
      (user_name,user_desc,user_seed,user_pass,user_perm,user_email,
       email_notify,user_agent_list,root_folder_fk, default_bucketpool_fk,
       ui_preference, new_upload_group_fk, new_upload_perm)
       $VALUES";
     //print "<pre>SQL is:\n$SQL\n</pre>";

     $result = pg_query($PG_CONN, $SQL);
     DBCheckResult($result, $SQL, __FILE__, __LINE__);
     pg_free_result($result);
     /* Make sure it was added */
     $SQL = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
     $result = pg_query($PG_CONN, $SQL);
     DBCheckResult($result, $SQL, __FILE__, __LINE__);
     $row = pg_fetch_assoc($result);
     pg_free_result($result);
     if (empty($row['user_name'])) {
       $text = _("Failed to insert user.");
       return ($text);
     }
     else
     {
      $user_name = $row['user_name'];
      $user_pk = $row['user_pk'];
      // Add user group
      $sql = "insert into groups(group_name) values ('$user_name')";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      /* Get new group_pk */
      $sql = "select group_pk from groups where group_name='$user_name'";
      $GroupResult = pg_query($PG_CONN, $sql);
      DBCheckResult($GroupResult, $sql, __FILE__, __LINE__);
      $GroupRow = pg_fetch_assoc($GroupResult);
      $group_pk = $GroupRow['group_pk'];
      pg_free_result($GroupResult);
      // make user a member of their own group
      $sql = "insert into group_user_member(group_fk, user_fk, group_perm) values($group_pk, $user_pk, 1)";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }

    return (NULL);
  } // Add()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    global $PG_CONN;
    global $PERM_NAMES;

    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $User = GetParm('username', PARM_TEXT);
        if (!empty($User)) {
          $rc = $this->Add();
          if (empty($rc)) {
            /* Need to refresh the screen */
            $text = _("User");
            $text1 = _("added");
            $V.= displayMessage("$text $User $text1.");
          } else {
            $V.= displayMessage($rc);
          }
        }

        $default_bucketpool_fk =0;

        /* Build HTML form */
        $V.= "<form name='formy' method='POST'>\n"; // no url = this url
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
        $V.= SelectBucketPool($default_bucketpool_fk);
        $V.= "</td>";
        $V .= "</tr>\n";

        /******  New Upload Group ******/
        /* Get master array of groups */
        $sql = "select group_pk, group_name from groups order by group_name";
        $groupresult = pg_query($PG_CONN, $sql);
        DBCheckResult($groupresult, $sql, __FILE__, __LINE__);
        $GroupArray = array();
        while ($GroupRow = pg_fetch_assoc($groupresult))
          $GroupArray[$GroupRow['group_pk']] = $GroupRow['group_name'];
        pg_free_result($groupresult);
        $text = _("New Upload Group<br>(Group to give a new upload permission to access)");
        $V.= "$Style<th>$text</th>";
        $V.= "<td>";
        $V .= Array2SingleSelect($GroupArray, "new_upload_group_fk", "", true, false);
        $V.= "</td>";
        $V .= "</tr>\n";

        /******  New Upload Permissions ******/
        $text = _("New Upload Permission<br>(Permission to give a new upload group)");
        $V.= "$Style<th>$text</th>";
        $V.= "<td>";
        $V .= Array2SingleSelect($PERM_NAMES, "new_upload_perm", "", true, false);
        $V.= "</td>";
        $V .= "</tr>\n";

        $V.= "</table border=0><P />";


        $text = _("Add User");
        $V.= "<input type='submit' value='$text'>\n";
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
$NewPlugin = new user_add;
?>
