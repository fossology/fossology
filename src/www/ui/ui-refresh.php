<?php
/*
 SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

class ui_refresh extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "refresh";
    $this->LoginFlag  =  0;
    parent::__construct();
  }

  /**
   * \brief Generate a Refresh URL.
   */
  function GetRefresh()
  {
    $Mod = GetParm("mod",PARM_STRING);
    $parm = Traceback_parm(0);
    $Opt = preg_replace("/&/","--",$parm);
    $V = "mod=" . $this->Name . "&remod=$Mod" . "&reopt=$Opt";
    return($V);
  } // GetRefresh()

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for content.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    global $Plugins;
    $P = &$Plugins[plugin_find_id("Default")];
    $GoMod = GetParm("remod",PARM_STRING);
    $GoOpt = GetParm("reopt",PARM_STRING);
    $GoOpt = preg_replace("/--/","&",$GoOpt);
    return($P->Output($GoMod,$GoOpt));
  }
}

$NewPlugin = new ui_refresh();
$NewPlugin->Initialize();
