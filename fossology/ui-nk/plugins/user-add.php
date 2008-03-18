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
if (!isset($GlobalReady)) { exit; }

class user_add extends FO_Plugin
{
  var $Name       = "user_add";
  var $Title      = "Add A User";
  var $MenuList   = "Admin::Users::Add";
  var $Version    = "1.0";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_USERADMIN;

  /*********************************************
   Add(): Add a user.
   Returns NULL on success, string on failure.
   *********************************************/
  function Add	()
    {
    global $DB;
    /* Get the parameters */
    $User = str_replace("'","''",GetParm('username',PARM_TEXT));
    $Pass = GetParm('pass1',PARM_TEXT);
    $Pass2 = GetParm('pass2',PARM_TEXT);
    $Seed = rand() . rand();
    $Hash = sha1($Seed . $Pass);
    $Desc = str_replace("'","''",GetParm('description',PARM_TEXT));
    $Perm = GetParm('permission',PARM_INTEGER);
    $Folder = GetParm('folder',PARM_INTEGER);
    $Email = str_replace("'","''",GetParm('email',PARM_TEXT));

    /* Make sure username looks valid */
    if (empty($User)) { return("Username must be specified. Not added."); }

    /* Make sure password matches */
    if ($Pass != $Pass2) { return("Passwords did not match. Not added."); }

    /* Make sure email looks valid */
    $Check = preg_replace("/[^a-zA-Z0-9@_.+-]/","",$Email);
    if ($Check != $Email)
	{
	return("Invalid email address.  Not added.");
	}

    /* See if the user already exists (better not!) */
    $SQL = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
    $Results = $DB->Action($SQL);
    if (!empty($Results[0]['user_name']))
	{
	return("User already exists.  Not added.");
	}

    /* Add the user */
    $SQL = "INSERT INTO users
	(user_name,user_desc,user_seed,user_pass,user_perm,user_email,root_folder_fk)
	VALUES
	('$User','$Desc','$Seed','$Hash',$Perm,'$Email',$Folder);";
    $Results = $DB->Action($SQL);
    /* Make sure it was added */
    $SQL = "SELECT * FROM users WHERE user_name = '$User' LIMIT 1;";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['user_name']))
	{
	return("Failed to insert user.");
	}

    return(NULL);
    } // Add()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $DB;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	break;
      case "HTML":
	/* If this is a POST, then process the request. */
	$User = GetParm('username',PARM_TEXT);
	if (!empty($User))
	  {
	  $rc = $this->Add();
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('User added.')\n";
	    $V .= "</script>\n";
	    }
	  else
	    {
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	/* Build HTML form */
	$V .= "<form name='formy' method='POST'>\n"; // no url = this url
	$V .= "To create a new user, enter the following information:<P />\n";
	$Style = "<tr><td colspan=3 style='background:black;'></td></tr><tr>";
	$V .= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='100%'>";

	$Val = htmlentities(GetParm('username',PARM_TEXT),ENT_QUOTES);
	$V .= "$Style<th width='5%'>1.</th><th width='25%'>Enter the username.</th>";
	$V .= "<td><input type='text' value='$Val' name='username' size=20></td>\n";
	$V .= "</tr>\n";

	$Val = htmlentities(GetParm('description',PARM_TEXT),ENT_QUOTES);
	$V .= "$Style<th>2.</th><th>Enter a description for the user (name, contact, or other information).  This may be blank.</th>\n";
	$V .= "<td><input type='text' name='description' value='$Val' size=60></td>\n";
	$V .= "</tr>\n";

	$Val = htmlentities(GetParm('email',PARM_TEXT),ENT_QUOTES);
	$V .= "$Style<th>3.</th><th>Enter an email address for the user. This may be blank.</th>\n";
	$V .= "<td><input type='text' name='email' value='$Val' size=60></td>\n";
	$V .= "</tr>\n";

	$V .= "$Style<th>4.</th><th>Select the user's access level.</th>";
	$V .= "<td><select name='permission'>\n";
	$V .= "<option value='" . PLUGIN_DB_NONE . "'>None (very basic, no database access)</option>\n";
	$V .= "<option selected value='" . PLUGIN_DB_READ . "'>Read-only (read, but no writes or downloads)</option>\n";
	$V .= "<option value='" . PLUGIN_DB_DOWNLOAD . "'>Download (Read-only, but can download files)</option>\n";
	$V .= "<option value='" . PLUGIN_DB_WRITE . "'>Read-Write (read, download, or edit information)</option>\n";
	$V .= "<option value='" . PLUGIN_DB_UPLOAD . "'>Upload (read-write, and permits uploading files)</option>\n";
	$V .= "<option value='" . PLUGIN_DB_ANALYZE . "'>Analyze (... and permits scheduling analysis tasks)</option>\n";
	$V .= "<option value='" . PLUGIN_DB_DELETE . "'>Delete (... and permits deleting uploaded files and analysis)</option>\n";
	$V .= "<option value='" . PLUGIN_DB_DEBUG . "'>Debug (... and allows access to debugging functions)</option>\n";
	$V .= "<option value='" . PLUGIN_DB_USERADMIN . "'>Full Administrator (all access including adding and deleting users)</option>\n";
	$V .= "</select></td>\n";
	$V .= "</tr>\n";

	$V .= "$Style<th>5.</th><th>Select the user's top-level folder. Access is restricted to this folder.";
	$V .= " (NOTE: This is only partially implemented right now. Current users can escape the top of tree limitation.)";
	$V .= "</th>";
	$V .= "<td><select name='folder'>";
	$V .= FolderListOption(-1,0);
	$V .= "</select></td>\n";
	$V .= "</tr>\n";

	$V .= "$Style<th>6.</th><th>Enter the user's password.  It may be blank.</th><td><input type='password' name='pass1' size=20></td>\n";
	$V .= "</tr>\n";
	$V .= "$Style<th>7.</th><th>Re-enter the user's password.</th><td><input type='password' name='pass2' size=20></td>\n";
	$V .= "</tr>\n";

	$V .= "</table border=0><P />";
	$V .= "<input type='submit' value='Add!'>\n";
	$V .= "</form>\n";
	break;
      case "Text":
	break;
      default:
	break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  }
};
$NewPlugin = new user_add;
$NewPlugin->Initialize();
?>
