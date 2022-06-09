<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\Plugin;

define("TITLE_core_debug", _("Debug Plugins"));

/**
 * @class core_debug
 * @brief Plugin for core debug
 */
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
   * @brief display the loaded menu and plugins.
   * @see FO_Plugin::Output()
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
   * @brief Display the loaded menu and plugins.
   * @return string HTML output
   */
  protected function htmlContent()
  {
    $V = "";
    /** @var Plugin[] $Plugins
     * All available plugins
     */
    global $Plugins;

    $text = _("Plugin Summary");
    $V .= "<H2>$text</H2>";
    foreach ($Plugins as $key => $val)
    {
      $V .= "$key : $val->Name (state=$val->State)<br>\n";
    }
    $text = _("Plugin State Details");
    $V .= "<H2>$text</H2>";
    $V .= "<pre>";
    foreach ($Plugins as $plugin)
    {
      $V .= strval($plugin) . "\n";
    }
    $V .= "</pre>";

    return $V;
  }

}
$NewPlugin = new core_debug;
$NewPlugin->Initialize();
