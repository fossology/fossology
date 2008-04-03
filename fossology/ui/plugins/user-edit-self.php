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

class user_edit_self extends FO_Plugin
{
  var $Name       = "user_edit_self";
  var $Title      = "Edit Your Account Settings";
  var $MenuList   = "Admin::Users::Account Settings";
  var $Version    = "1.0";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_NONE;

  /***********************************************************
   PostInitialize(): This function is called before the plugin
   is used and after all plugins have been initialized.
   Returns true on success, false on failure.
   NOTE: Do not assume that the plugin exists!  Actually check it!
   Purpose: Only allow people who are logged in to edit their own properties.
   ***********************************************************/
  function PostInitialize()
    {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_VALID) { return(0); } // don't run
    if (empty($_SESSION['UserId']))
	{
	/* Only valid if the user is logged in. */
	$this->State = PLUGIN_STATE_INVALID;
	return(0);
	}
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val)
	{
	$id = plugin_find_id($val);
	if ($id < 0) { $this->Destroy(); return(0); }
	}

    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if ($this->MenuList !== "")
	{
	menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);
	}
    return($this->State == PLUGIN_STATE_READY);
    } // PostInitialize()

  /***********************************************************
   RegisterMenus(): Register additional menus.
   ***********************************************************/
  function RegisterMenus()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); } // don't run
    } // RegisterMenus()

  /*********************************************
   Edit(): Alter a user.
   Returns NULL on success, string on failure.
   *********************************************/
  function Edit	()
    {
    global $DB;
    /* Get the parameters */
    $UserId = @$_SESSION['UserId'];
    $User = GetParm('username',PARM_TEXT);
    $Pass0 = GetParm('pass0',PARM_TEXT);
    $Pass1 = GetParm('pass1',PARM_TEXT);
    $Pass2 = GetParm('pass2',PARM_TEXT);
    $Seed = rand() . rand();
    $Hash = sha1($Seed . $Pass);
    $Desc = GetParm('description',PARM_TEXT);
    $Perm = GetParm('permission',PARM_INTEGER);
    $Folder = GetParm('folder',PARM_INTEGER);
    $Email = GetParm('email',PARM_TEXT);

    /* Make sure username looks valid */
    if (empty($_SESSION['UserId'])) { return("You must be logged in."); }

    /* Make sure password matches */
    if (!empty($Pass1) || !empty($Pass2))
	{
	if ($Pass1 != $Pass2) { return("New passwords did not match. No change."); }
	}

    /* Make sure email looks valid */
    $Check = preg_replace("/[^a-zA-Z0-9@_.+-]/","",$Email);
    if ($Check != $Email)
	{
	return("Invalid email address.  Not added.");
	}

    /* See if the user already exists (better not!) */
    $SQL = "SELECT * FROM users WHERE user_name = '$User' AND user_pk != '$UserId' LIMIT 1;";
    $Results = $DB->Action($SQL);
    if (!empty($Results[0]['user_name']))
	{
	return("User already exists.  Not added.");
	}

    /* Load current user */
    $SQL = "SELECT * FROM users WHERE user_pk = '$UserId' LIMIT 1;";
    $Results = $DB->Action($SQL);
    $R = &$Results[0];

    /* Make sure old password matched */
    $Hash = sha1($R['user_seed'] . $Pass0);
    if ($Hash != $R['user_pass']) { return("Authentication password did not match. No change."); }

    /* Update the user */
    $GotUpdate=0;
    $SQL = "UPDATE users SET";
    if (!empty($User) && ($User != $R['user_name']))
	{
	$_SESSION['User'] = '$User';
	$User = str_replace("'","''",$User);
	$SQL .= " user_name = '$User'";
	$GotUpdate=1;
	}
    if ($Desc != $R['user_desc'])
	{
	$Desc = str_replace("'","''",$Desc);
	if ($GotUpdate) { $SQL .= ", "; }
	$SQL .= " user_desc = '$Desc'";
	$GotUpdate=1;
	}
    if ($Email != $R['user_email'])
	{
	$Email = str_replace("'","''",$Email);
	if ($GotUpdate) { $SQL .= ", "; }
	$SQL .= " user_email = '$Email'";
	$GotUpdate=1;
	}
    if (!empty($Pass1) && ($Pass0 != $Pass1) && ($Pass1 == $Pass2))
	{
	$Seed = rand() . rand();
	$Hash = sha1($Seed . $Pass1);
	if ($GotUpdate) { $SQL .= ", "; }
	$SQL .= " user_seed = '$Seed'";
	$SQL .= ", user_pass = '$Hash'";
	$GotUpdate=1;
	}
    $SQL .= " WHERE user_pk = '$UserId';";
    if ($GotUpdate) { $Results = $DB->Action($SQL); }
    $_SESSION['timeout_check'] = 1; /* force a recheck */
    return(NULL);
    } // Edit()

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
	  $rc = $this->Edit();
	  if (empty($rc))
	    {
	    /* Need to refresh the screen */
	    $V .= "<script language='javascript'>\n";
	    $V .= "alert('User information updated.')\n";
	    $Uri = Traceback_uri() . "?mod=" . $this->Name;
	    $V .= "window.open('$Uri','_top');\n";
	    $V .= "</script>\n";
	    }
	  else
	    {
	    $V .= "<script language='javascript'>\n";
	    $rc = htmlentities($rc,ENT_QUOTES);
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	/* Build HTML form */
	$V .= "<form name='formy' method='POST'>\n"; // no url = this url
	$V .= "You <font color='red'>must</font> provide your current password in order to make any changes.<br />\n";
	$V .= "Enter your password: <input type='password' name='pass0' size=20>\n";
	$V .= "<hr>\n";

	$Results = $DB->Action("SELECT * FROM users WHERE user_pk='" . @$_SESSION['UserId'] . "';");
	$R = $Results[0];

	$V .= "To change user information, edit the following fields. You do not need to edit every field. Only fields with edits will be changed.<P />\n";
	$Style = "<tr><td colspan=3 style='background:black;'></td></tr><tr>";
	$V .= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='100%'>";

	$Val = htmlentities($R['user_name'],ENT_QUOTES);
	$V .= "$Style<th width='5%'>1.</th><th width='25%'>Change your username. This will be checked to ensure that it is unique among all users.</th>";
	$V .= "<td><input type='text' value='$Val' name='username' size=20></td>\n";
	$V .= "</tr>\n";

	$Val = htmlentities($R['user_desc'],ENT_QUOTES);
	$V .= "$Style<th>2.</th><th>Change your description (name, contact, or other information).  This may be blank.</th>\n";
	$V .= "<td><input type='text' name='description' value='$Val' size=60></td>\n";
	$V .= "</tr>\n";

	$Val = htmlentities($R['user_email'],ENT_QUOTES);
	$V .= "$Style<th>3.</th><th>Change your email address. This may be blank.</th>\n";
	$V .= "<td><input type='text' name='email' value='$Val' size=60></td>\n";
	$V .= "</tr>\n";

	$V .= "$Style<th>4.</th><th>Change your password.<br>Re-enter your password.</th><td>";
	$V .= "<input type='password' name='pass1' size=20><br />\n";
	$V .= "<input type='password' name='pass2' size=20></td>\n";
	$V .= "</tr>\n";

	$V .= "</table><P />";
	$V .= "<input type='submit' value='Edit!'>\n";
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
$NewPlugin = new user_edit_self;
$NewPlugin->Initialize();
?>
