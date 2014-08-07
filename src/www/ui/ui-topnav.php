<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        $V = $this->outputHtml();
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
  
  function outputHtml()
  {
    global $SysConf;
    global $Plugins;

    $html = "<table width='100%' border=0 cellpadding=0>\n  <tr>\n";

    $Uri = Traceback_dir();

    if (@$SysConf['SYSCONFIG']['LogoImage'] and @$SysConf['SYSCONFIG']['LogoLink'])
    {
      $LogoLink = $SysConf['SYSCONFIG']['LogoLink'];
      $LogoImg = $SysConf['SYSCONFIG']['LogoImage'];
    }
    else
    {
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

$NewPlugin = new ui_topnav;
$NewPlugin->Initialize();
