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

class core_init extends Plugin
  {
  var $Name       = "init";
  var $Title      = "Initialize";
  var $Version    = "1.0";
  var $MenuList   = "Admin::Initialize";
  var $PluginLevel = 100; /* make this run first! */
  var $Dependency = array("db","auth","Default");
  var $DBaccess   = PLUGIN_DB_WRITE;

  /******************************************
   PostInitialize(): This is where the magic for
   Authentication happens.
   ******************************************/
  function PostInitialize()
    {
    /** Disable everything but me, DB, menu **/
    /* Enable or disable plugins based on login status */
    global $Plugins;
    global $DATADIR;
    $Filename = $DATADIR . "/init.ui";
    if (!file_exists($Filename))
	{
	return;
	}
    menu_insert("Main::" . $this->MenuList,-100,$this->Name);
    $Max = count($Plugins);
    for($i=0; $i < $Max; $i++)
	{
	$P = &$Plugins[$i];
	if ($P->State == PLUGIN_STATE_INVALID) { continue; }
	$Key = array_search($P->Name,$this->Dependency);
	if ($Key === FALSE)
	  {
	  // print "Disable " . $P->Name . " as $Key\n";
	  $P->Destroy();
	  }
	else
	  {
	  // print "Keeping " . $P->Name . " as $Key\n";
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
    global $Plugins;
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	/* If you are not logged in, then force a login. */
	if (empty($_SESSION['User']))
	  {
	  $P = &$Plugins[plugin_find_id("auth")];
	  $P->OutputSet($this->OutputType,0);
	  $V .= $P->Output();
	  $P->OutputUnSet();
	  }
	else /* It's an init */
	  {
	  $Max = count($Plugins);
	  $FailFlag=0;
	  global $DATADIR;
	  $Filename = $DATADIR . "/init.ui";
	  for($i=0; $i < $Max; $i++)
	    {
	    $P = &$Plugins[$i];
	    /* Init ALL plugins */
	    print "Initializing: " . htmlentities($P->Name) . "...\n";
	    $State = $P->Initialize();
	    if ($State == 1) { print "Done.<br />\n"; }
	    else { $FailFlag = 1; print "<font color='red'>FAILED.</font><br />\n"; }
	    }
	  if (!$FailedFlag)
	    {
	    $V .= "Initialization complete.<br />";
	    if (is_writable($DATADIR)) { $State = unlink($Filename); }
	    else { $State = 0; }
	    if (!$State)
		{
		$V .= "<font color='red'>";
		$V .= "Failed to remove " . $DATADIR . "/init.ui\n";
		$V .= "<br />Remove this file to complete the initialization.\n";
		$V .= "</font>\n";
		$FailedFlag = 1;
		}
	    }
	  else
	    {
	    $V .= "<font color='red'>";
	    $V .= "Initialization complete with errors.";
	    $V .= "</font>\n";
	    }
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
$NewPlugin = new core_init;
$NewPlugin->Initialize();
?>
