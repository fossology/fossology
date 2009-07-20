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

class user_del extends FO_Plugin
{
  var $Name       = "user_del";
  var $Title      = "Delete A User";
  var $MenuList   = "Admin::Users::Delete";
  var $Version    = "1.0";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_USERADMIN;

  /*********************************************
   Delete(): Delete a user.
   Returns NULL on success, string on failure.
   *********************************************/
  function Delete	($UserId)
    {
    global $DB;

    /* See if the user already exists (better not!) */
    $SQL = "SELECT * FROM users WHERE user_pk = '$UserId' LIMIT 1;";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['user_name']))
	{
	return("User does not exist.");
	}

    /* Delete the user */
    $SQL = "DELETE FROM users WHERE user_pk = '$UserId';";
    $Results = $DB->Action($SQL);
    /* Make sure it was deleted */
    $SQL = "SELECT * FROM users WHERE user_name = '$UserId' LIMIT 1;";
    $Results = $DB->Action($SQL);
    if (!empty($Results[0]['user_name']))
	{
	return("Failed to delete user.");
	}

    return(NULL);
    } // Delete()

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
	$User = GetParm('userid',PARM_TEXT);
	$Confirm = GetParm('confirm',PARM_INTEGER);
	if (!empty($User))
	  {
	  if ($Confirm != 1) { $rc = "Deletion not confirmed. Not deleted."; }
	  else { $rc = $this->Delete($User); }
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= displayMessage('User deleted.');
	    }
	  else
	    {
	    $V .= displayMessage($rc);
	    }
	  }

	/* Get the user list */
	$Results = $DB->Action("SELECT user_pk,user_name,user_desc FROM users WHERE user_pk != '" . @$_SESSION['UserId'] . "' AND user_pk != '1' ORDER BY user_name;");
	if (empty($Results[0]['user_name']))
	  {
	  $V .= "No users to delete.";
	  }
	else
	  {
	  /* Build HTML form */
	  $V .= "Deleting a user removes the user entry from the FOSSology system. The user's name, account information, and password will be <font color='red'>permanently</font> removed. (There is no 'undo' to this delete.)<P />\n";
	  $V .= "<form name='formy' method='POST'>\n"; // no url = this url
	  $V .= "To delete a user, enter the following information:<P />\n";
	  $Style = "<tr><td colspan=3 style='background:black;'></td></tr><tr>";
	  $Val = htmlentities(GetParm('userid',PARM_TEXT),ENT_QUOTES);
	  $V .= "<ol>\n";
	  $V .= "<li>Select the user to delete.<br />";
	  $V .= "<select name='userid'>\n";
	  for($i=0; !empty($Results[$i]['user_name']); $i++)
	    {
	    $V .= "<option value='" . $Results[$i]['user_pk'] . "'>";
	    $V .= $Results[$i]['user_name'];
	    $V .= "</option>\n";
	    }
	  $V .= "</select>\n";

	  $V .= "<P /><li>Confirm user deletion: <input type='checkbox' name='confirm' value='1'>";
	  $V .= "</ol>\n";

	  $V .= "<input type='submit' value='Delete!'>\n";
	  $V .= "</form>\n";
	  }
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
$NewPlugin = new user_del;
?>
