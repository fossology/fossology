<?php
/*
 SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

class ui_treenav extends FO_Plugin
{
  var $Name       = "treenav";
  var $Version    = "1.0";
  var $MenuList   = "";

  function Output()
  {
    $V="";
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (! $this->OutputToStdout) {
      return ($V);
    }
    print("$V");
    return;
  }
}

$NewPlugin = new ui_treenav;
$NewPlugin->Initialize();
