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
    session_name("Login");
    session_start();
    $Now = time();
    if (!empty($_SESSION['time']))
      {
      /* Logins older than 60 minutes are auto-logout */
      if ($_SESSION['time'] + 60*60 < $Now)
	{
	$_SESSION['User'] = NULL;
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
	$_SESSION['ip'] = $this->GetIP();
	}

    /* Enable or disable plugins based on login status */
    if ($_SESSION['User'])
      {
      // menu_insert("Main::Admin::Logout",10,$this->Name);
      }
    else
      {
      // menu_insert("Main::Admin::Login",10,$this->Name);
      /* Disable all plugins with >= WRITE access */
      global $Plugins;
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
	  if ((GetParm("username",PARM_STRING) == 'fossy') &&
	      (GetParm("password",PARM_STRING) == 'fossy'))
		{
		$_SESSION['User'] = GetParm("username",PARM_STRING);
		$_SESSION['checkip'] = GetParm("checkip",PARM_STRING);
		/* Need to refresh the screen */
		$V .= "<script language='javascript'>\n";
		$V .= "alert('User Logged In')\n";
		$Uri = Traceback_uri();
		$V .= "window.open('$Uri','_top');\n";
		$V .= "</script>\n";
		}
	  else
		{
	  	$V .= "This login uses HTTP, so passwords are tranmitted in plain text.\n";
	  	$V .= "Until the DB schema is updated and testing completes, the username is 'fossy' and password is 'fossy'.\n";
	  	$V .= "<P>\n";
	  	$V .= "<form method='post'>\n";
	  	$V .= "Username: <input type='text' size=20 name='username'><P>\n";
	  	$V .= "Password: <input type='password' size=20 name='password'><P>\n";
	  	$V .= "<input type='checkbox' name='checkip' value='1'>Validate IP.\n";
		$V .= "With this option, your session is linked to your IP address (" . $_SESSION['ip'] . "). This deters session hijacking. While this option is more secure, it is not ideal for people using proxy networks, where their IP address regularly changes. If you find that are you constantly being logged out, then do not use this option.<P />\n";
	  	$V .= "<input type='submit' value='Login'>\n";
	  	$V .= "</form>\n";
		}
	  }
	else /* It's a logout */
	  {
	  $_SESSION['User'] = NULL;
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
