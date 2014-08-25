<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_ui_welcome", _("Getting Started with FOSSology"));

class ui_welcome extends FO_Plugin
{
  var $Name       = "Getting Started";
  var $Title      = TITLE_ui_welcome;
  var $Version    = "1.0";
  var $MenuList   = "Help::Getting Started";
  var $DBaccess   = PLUGIN_DB_NONE;
  var $LoginFlag  = 0;

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $SiteURI = Traceback_uri();
    $V = "";
    if ($this->OutputType == "HTML")
    {
      $Login = _("Login");
      if (empty($_SESSION['User']) && (plugin_find_id("auth") >= 0))
      {
        $Login = "<a href='$SiteURI?mod=auth'>$Login</a>";
      }
      global $container;
      $renderer = $container->get('renderer');
      $renderer->login = $Login;
      $V = str_replace('${SiteURI}', $SiteURI, $renderer->renderTemplate("welcome"));
    }
    if (!$this->OutputToStdout)
    {
      return($V);
    }
    print($V);
    return;
  }
}
  $NewPlugin = new ui_welcome;
  $NewPlugin->Initialize();
