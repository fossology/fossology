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

class ui_refresh extends Plugin
  {
  var $Name       = "refresh";
  var $Version    = "1.0";

  /***********************************************************
   GetRefresh(): Generate a Refresh URL.
   ***********************************************************/
  function GetRefresh()
    {
    $Mod = GetParm("mod",PARM_STRING);
    $Opt = Traceback_parm(0);
    $Opt = preg_replace("/&/","--",$Opt);
    $V = "mod=" . $this->Name . "&remod=$Mod" . "&reopt=$Opt";
    return($V);
    } // GetRefresh()

  /***********************************************************
   OutputOpen(): This function is called when user output is
   requested.  This function is responsible for assigning headers.
   ***********************************************************/
  function OutputOpen($Type,$ToStdout)
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    global $Plugins;
    $P = &$Plugins[plugin_find_id("Default")];
    return($P->OutputOpen($Type,$ToStdout));
    } // OutputOpen()

  /***********************************************************
   Output(): This function is called when user output is
   requested.  This function is responsible for content.
   ***********************************************************/
  function Output()
    {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    global $Plugins;
    $P = &$Plugins[plugin_find_id("Default")];
    $GoMod = GetParm("remod",PARM_STRING);
    $GoOpt = GetParm("reopt",PARM_STRING);
    $GoOpt = preg_replace("/--/","&",$GoOpt);
    return($P->Output($GoMod,$GoOpt));
    } // Output()

  /***********************************************************
   OutputClose(): This function is called when user output is
   finished.
   ***********************************************************/
  function OutputClose()
    {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    global $Plugins;
    $P = &$Plugins[plugin_find_id("Default")];
    return($P->OutputClose());
    } // OutputClose()
  };
$NewPlugin = new ui_refresh;
$NewPlugin->Initialize();
?>
