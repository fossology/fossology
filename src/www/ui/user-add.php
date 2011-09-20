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

define("TITLE_user_add", _("Add A User"));

class user_add extends FO_Plugin {
  var $Name = "user_add";
  var $Title = TITLE_user_add;
  var $MenuList = "Admin::Users::Add";
  var $Version = "1.0";
  var $Dependency = array("db");
  var $DBaccess = PLUGIN_DB_USERADMIN;

  /*********************************************
   Add(): Add a user.
   Returns NULL on success, string on failure.
   *********************************************/
  function Add() {
     
    global $DB;
    global $PG_CONN;

    if (!$PG_CONN) {
      $dbok = $DB->db_init();
      if (!$dbok)
      $text = _("NO DB connection!");
      echo "<pre>$text\n</pre>";
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
    $SQL = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
    $Results = $DB->Action($SQL);
    if (!empty($Results[0]['user_name'])) {
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
    if($default_bucketpool_fk === NULL) {
      $VALUES = " VALUES ('$User','$Desc','$Seed','$Hash',$Perm,'$Email',
	             '$Email_notify','$agentList',$Folder, NULL,
	             '$uiChoice');";
    }
    else {
      $VALUES = " VALUES ('$User','$Desc','$Seed','$Hash',$Perm,'$Email',
               '$Email_notify','$agentList',$Folder, $default_bucketpool_fk,
               '$uiChoice');";
    }

    $SQL = "INSERT INTO users
      (user_name,user_desc,user_seed,user_pass,user_perm,user_email,
       email_notify,user_agent_list,root_folder_fk, default_bucketpool_fk,
       ui_preference)
       $VALUES";
       //print "<pre>SQL is:\n$SQL\n</pre>";

       $Results = pg_query($PG_CONN, $SQL);
       DBCheckResult($Results, $SQL, __FILE__, __LINE__);
       /* Make sure it was added */
       $SQL = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
       $Results = $DB->Action($SQL);
       if (empty($Results[0]['user_name'])) {
         $text = _("Failed to insert user.");
         return ($text);
       }
       return (NULL);
  } // Add()
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
        $Style = "<tr><td colspan=3 style='background:black;'></td></tr><tr>";
        $V.= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='75%'>";
        $Val = htmlentities(GetParm('username', PARM_TEXT), ENT_QUOTES);
        $text = _("Enter the username.");
        $V.= "$Style<th width='5%'>1.</th><th width='25%'>$text</th>";
        $V.= "<td><input type='text' value='$Val' name='username' size=20></td>\n";
        $V.= "</tr>\n";
        $Val = htmlentities(GetParm('description', PARM_TEXT), ENT_QUOTES);
        $text = _("Enter a description for the user (name, contact, or other information).  This may be blank.");
        $V.= "$Style<th>2.</th><th>$text</th>\n";
        $V.= "<td><input type='text' name='description' value='$Val' size=60></td>\n";
        $V.= "</tr>\n";
        $Val = htmlentities(GetParm('email', PARM_TEXT), ENT_QUOTES);
        $text = _("Enter an email address for the user, see step 8. This field may be left blank.");
        $V .= "$Style<th>3.</th><th>$text</th>\n";
        $V.= "<td><input type='text' name='email' value='$Val' size=60></td>\n";
        $V.= "</tr>\n";
        $text = _("Select the user's access level.");
        $V.= "$Style<th>4.</th><th>$text</th>";
        $V.= "<td><select name='permission'>\n";
        $text = _("None (very basic, no database access)");
        $V.= "<option value='" . PLUGIN_DB_NONE . "'>$text</option>\n";
        $text = _("Read-only (read, but no writes or downloads)");
        $V.= "<option selected value='" . PLUGIN_DB_READ . "'>$text</option>\n";
        $text = _("Download (Read-only, but can download files)");
        $V.= "<option value='" . PLUGIN_DB_DOWNLOAD . "'>$text</option>\n";
        $text = _("Read-Write (read, download, or edit information)");
        $V.= "<option value='" . PLUGIN_DB_WRITE . "'>$text</option>\n";
        $text = _("Upload (read-write, and permits uploading files)");
        $V.= "<option value='" . PLUGIN_DB_UPLOAD . "'>$text</option>\n";
        $text = _("Analyze (... and permits scheduling analysis tasks)");
        $V.= "<option value='" . PLUGIN_DB_ANALYZE . "'>$text</option>\n";
        $text = _("Delete (... and permits deleting uploaded files and analysis)");
        $V.= "<option value='" . PLUGIN_DB_DELETE . "'>$text</option>\n";
        $text = _("Debug (... and allows access to debugging functions)");
        $V.= "<option value='" . PLUGIN_DB_DEBUG . "'>$text</option>\n";
        $text = _("Full Administrator (all access including adding and deleting users)");
        $V.= "<option value='" . PLUGIN_DB_USERADMIN . "'>$text</option>\n";
        $V.= "</select></td>\n";
        $V.= "</tr>\n";
        $text = _("Select the user's top-level folder. Access is restricted to this folder.");
        $V.= "$Style<th>5.</th><th>$text";
        $V.= _(" (NOTE: This is only partially implemented right now. Current users can escape the top of tree limitation.)");
        $V.= "</th>";
        $V.= "<td><select name='folder'>";
        $V.= FolderListOption(-1, 0);
        $V.= "</select></td>\n";
        $V.= "</tr>\n";
        $text = _("Enter the user's password.  It may be blank.");
        $V.= "$Style<th>6.</th><th>$text</th><td><input type='password' name='pass1' size=20></td>\n";
        $V.= "</tr>\n";
        $text = _("Re-enter the user's password.");
        $V.= "$Style<th>7.</th><th>$text</th><td><input type='password' name='pass2' size=20></td>\n";
        $V.= "</tr>\n";
        $text = _("E-mail Notification");
        $text1 = _("Check to enable email notification of completed analysis.");
        $V .= "$Style<th>8.</th><th>$text</th><td><input type='checkbox'" .
                "name='enote' value='y' checked='checked'>" .
                "$text1</td>\n";
        $V.= "</tr>\n";
        $text = _("Default Agents: Select the agent(s) to automatically run when uploading data. These selections can be changed on the upload screens.");
        $V .= "$Style<th>9.</th><th>$text\n</th><td> ";
        $V.= AgentCheckBoxMake(-1, "agent_unpack");
        $V .= "</td>\n";
        $text = _("Default bucketpool.");
        $V.= "$Style<th>10.</th><th>$text</th>";
        $V.= "<td>";
        $V.= SelectBucketPool($default_bucketpool_fk);
        $V.= "</td>";
        $V .= "</tr>\n";
        $text = _("User Interface Options");
        $text1 = _("Use the simplified UI (Default)");
        $text2 = _("Use the original UI");
        $V .= "$Style<th>11.</th><th>$text</th><td><input type='radio'" .
                "name='whichui' value='simple' checked='checked'>" .
                "$text1<br><input type='radio'" .
                "name='whichui' value='original'>" .
                "$text2</td>\n";
        $V.= "</table border=0><P />";


        $text = _("Add");
        $V.= "<input type='submit' value='$text!'>\n";
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
