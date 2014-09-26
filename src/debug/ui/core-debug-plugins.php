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

define("TITLE_core_debug", _("Debug Plugins"));

class core_debug extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "debug";
    $this->Title      = TITLE_core_debug;
    $this->MenuList   = "Help::Debug::Debug Plugins";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /**
   * \brief display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }
    if ($this->OutputToStdout && $this->OutputType=="Text") {
      global $Plugins;
      print_r($Plugins);
    }
    $output = "";
    if ($this->OutputType=='HTML')
    {
      $output = $this->htmlContent();
    }
    if (!$this->OutputToStdout)
    {
      $this->vars['content'] = $output;
      return; // $output;
    }
    print $output;
  }
  
  /**
   * \brief Display the loaded menu and plugins.
   */
  protected function htmlContent()
  {
    $V="";
    global $Plugins;

    $text = _("Plugin Summary");
    $V .= "<H2>$text</H2>";
    foreach ($Plugins as $key => $val)
    {
      $V .=  "$key : $val->Name (state=$val->State)<br>\n";
    }
    $text = _("Plugin State Details");
    $V .= "<H2>$text</H2>";
    $V .= "<pre>";
    $V .= print_r($Plugins,1);
    $V .= "</pre>";

    return $V;
  }

}
$NewPlugin = new core_debug;
$NewPlugin->Initialize();
