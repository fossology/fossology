<?php
/*
 SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \class ui_topnav extends FO_Plugin
 * \brief top navigater logo on UI
 **/
class ui_topnav extends FO_Plugin
{
  var $Name       = "topnav";
  var $Version    = "1.0";
  var $MenuList   = "";
  var $Dependency = array("menus");

  /**
   * \brief Generate output for this plug-in.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    global $SysConf;
    global $Plugins;

    $html = "<table width='100%' border=0 cellpadding=0>\n  <tr>\n";

    $Uri = Traceback_dir();

    if (@$SysConf['SYSCONFIG']['LogoImage'] and
      @$SysConf['SYSCONFIG']['LogoLink']) {
      $LogoLink = $SysConf['SYSCONFIG']['LogoLink'];
      $LogoImg = $SysConf['SYSCONFIG']['LogoImage'];
    } else {
      $LogoLink = 'http://fossology.org';
      $LogoImg = Traceback_uri . 'images/fossology-logo.gif';
    }

    $html .= "    <td width='15%'>";
    $html .= "<a href='$LogoLink' target='_top'><img src='$LogoImg' align=absmiddle border=0></a>";
    $html .= "</td>\n";
    $html .= "    <td valign='top'>";
    $Menu = &$Plugins[plugin_find_id("menus")];
    $Menu->OutputSet($this->OutputType,0);
    $html .= $Menu->Output();
    $html .= "    </td>\n";
    $html .= "  </tr>\n";
    $html .= "</table>\n";
    return $html;
  }
}

$NewPlugin = new ui_topnav();
$NewPlugin->Initialize();
