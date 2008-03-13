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

class core_auth extends Plugin
  {
  var $Name       = "auth";
  var $Title      = "Login";
  var $Version    = "1.0";
  var $PluginLevel = 100; /* make this run first! */

  /******************************************
   GetIP(): Retrieve the user's IP address.
   Some proxy systems pass forwarded IP address info.
   This ensures that someone who steals the cookie won't
   gain access unless they come from the same IP.
   ******************************************/
  function GetIP()
    {
    /* NOTE: This can be easily defeated wtih fake HTTP headers. */
    $Vars = array(
	'HTTP_CLIENT_IP',
	'HTTP_X_COMING_FROM',
	'HTTP_X_FORWARDED_FOR',
	'HTTP_X_FORWARDED'
	);
    foreach($Vars as $V)
      {
      if (!empty($_SERVER[$V])) { return($_SERVER[$V]); }
      }
    return($_SERVER['REMOTE_ADDR']);
    } // GetIP()

  /******************************************
   PostInitialize(): This is where the magic for
   Authentication happens.
   ******************************************/
  function PostInitialize()
    {
    global $Plugins;
    session_name("Login");
    session_start();
    $Now = time();
    if (!empty($_SESSION['time']))
      {
      /* Logins older than 60 minutes are auto-logout */
      if ($_SESSION['time'] + 60*60 < $Now)
	{
	$_SESSION['User'] = NULL;
	$_SESSION['UserId'] = NULL;
	$_SESSION['UserLevel'] = NULL;
	}
      }
    $_SESSION['time'] = $Now;

    if (empty($_SESSION['ip']))
	{
	$_SESSION['ip'] = $this->GetIP();
	}
    else if (($_SESSION['checkip']==1) && ($_SESSION['ip'] != $this->GetIP()))
	{
	/* Sessions are not transferable. */
	$_SESSION['User'] = NULL;
	$_SESSION['UserId'] = NULL;
	$_SESSION['UserLevel'] = NULL;
	$_SESSION['ip'] = $this->GetIP();
	}

    /* Enable or disable plugins based on login status */
    if ($_SESSION['User'])
      {
      // menu_insert("Main::Admin::Logout",10,$this->Name);
      /* Disable all plugins with >= Level access */
      $Max = count($Plugins);
      for($i=0; $i < $Max; $i++)
	{
	$P = &$Plugins[$i];
	if ($P->State == PLUGIN_STATE_INVALID) { continue; }
	if (empty($_SESSION['UserLevel'])) { $Level = PLUGIN_DB_DOWNLOAD; }
	else { $Level = $_SESSION['UserLevel']; }
	if ($P->DBaccess > $Level)
	  {
	  $P->Destroy();
	  }
	}
      }
    else
      {
      // menu_insert("Main::Admin::Login",10,$this->Name);
      /* Disable all plugins with >= WRITE access */
      $Max = count($Plugins);
      for($i=0; $i < $Max; $i++)
	{
	$P = &$Plugins[$i];
	if ($P->State == PLUGIN_STATE_INVALID) { continue; }
	if ($P->DBaccess >= PLUGIN_DB_WRITE)
	  {
	  $P->Destroy();
	  }
	}
      }

    $this->State = PLUGIN_STATE_READY;
    } // PostInitialize()

  /******************************************
   CheckUser(): See if a username/password is valid.
   Returns string on match, or null on no-match.
   ******************************************/
  function CheckUser	($User,$Pass)
    {
    global $DB;
    $V = "";
    if (empty($User))	{ return; }
    $User = str_replace("'","''",$User);	/* protect DB */

    /* See if the user exists */
    $Results = $DB->Action("SELECT * FROM users WHERE user_name = '$User';");
    $R = $Results[0];
    if (empty($R['user_name']))	{ return; } /* no user */

    /* Check the password -- only if a password exists */
    if (!empty($R['user_seed']) || !empty($Pass))
      {
      $Hash = sha1($R['user_seed'] . $R['user_pass']);
      if ($Hash != $R['user_pass']) { return; }
      }
    if (!empty($R['user_seed']))
      {
      /* Seed with no password hash = no login */
      return;
      }
    else
      {
      if (!empty($Pass)) { return; }	/* empty password required */
      }

    /* If you make it here, then username and password were good! */
    $_SESSION['User'] = $User;
    $_SESSION['UserId'] = $R['user_pk'];
    /* No specified permission means ALL permission */
    if (empty($R['user_perm'])) { $_SESSION['UserLevel']=PLUGIN_DB_USERADMIN; }
    else { $_SESSION['UserLevel'] = $R['user_perm']; }
    $_SESSION['checkip'] = GetParm("checkip",PARM_STRING);
    /* Need to refresh the screen */
    $V .= "<script language='javascript'>\n";
    $V .= "alert('User Logged In')\n";
    $Uri = Traceback_uri();
    $V .= "window.open('$Uri','_top');\n";
    $V .= "</script>\n";
    return($V);
    } // CheckUser()

  /******************************************
   Output(): This is only called when the user logs out.
   ******************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	if (empty($_SESSION['User']))
	  {
	  $User = GetParm("username",PARM_STRING);
	  $Pass = GetParm("password",PARM_STRING);
	  if (!empty($User)) { $VP = $this->CheckUser($User,$Pass); }
	  else { $VP = ""; }
	  if (!empty($VP))
		{
		$V .= $VP;
		}
	  else
		{
	  	$V .= "This login uses HTTP, so passwords are tranmitted in plain text.<br>\n";
	  	$V .= "Until the DB schema is updated and testing completes, the username is '<font color='red'><b>Default User</b></font>' and password is empty.\n";
	  	$V .= "<P>\n";
	  	$V .= "<form method='post'>\n";
	  	$V .= "Username: <input type='text' size=20 name='username'><P>\n";
	  	$V .= "Password: <input type='password' size=20 name='password'><P>\n";
	  	$V .= "<input type='checkbox' name='checkip' value='1'>Validate IP.\n";
		$V .= "This option deters session hijacking by linking your session to your IP address (" . $_SESSION['ip'] . "). While this option is more secure, it is not ideal for people using proxy networks, where IP addresses regularly change. If you find that are you constantly being logged out, then do not use this option.<P />\n";
	  	$V .= "<input type='submit' value='Login'>\n";
	  	$V .= "</form>\n";
		}
	  }
	else /* It's a logout */
	  {
	  $_SESSION['User'] = NULL;
	  $_SESSION['UserId'] = NULL;
	  $_SESSION['UserLevel'] = NULL;
	  $V .= "<script language='javascript'>\n";
	  $V .= "alert('User Logged Out')\n";
	  $Uri = Traceback_uri() . "?mod=refresh&remod=default";
	  $V .= "window.open('$Uri','_top');\n";
	  $V .= "</script>\n";
	  }
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    } // Output()

  };
$NewPlugin = new core_auth;
$NewPlugin->Initialize();
?>
