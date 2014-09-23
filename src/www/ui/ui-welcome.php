<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

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

  function __construct()
  {
    // $this->State = PLUGIN_STATE_READY;
    $this->Name       = "Getting Started";
    $this->Title      = TITLE_ui_welcome;
    $this->Version    = "1.0";
    $this->MenuList   = "Help::Getting Started";
    $this->DBaccess   = PLUGIN_DB_NONE;
    $this->LoginFlag  = 0;
    parent::__construct();
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }
    $show = GetParm('show', PARM_STRING);
    if ($show=='licensebrowser'){
      $this->Title = TITLE_ui_welcome.': License Browser';
    }
    
    $topMenuList = "Main::" . $this->MenuList;
    menu_insert($topMenuList.'::Overview', $this->MenuOrder-10, $this->Name."&show=welcome");
    menu_insert($topMenuList.'::License Browser', $this->MenuOrder, $this->Name."&show=licensebrowser");
  }
  
  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V = "";
    if ($this->OutputType=="HTML"){
      $V = $this->outputHtml();
    }
    if (!$this->OutputToStdout)
    {
      return $V;
    }
    print($V);
    return;
  }
  
  /*
   * @return string rendered template
   */
  function outputHtml() {
    global $container;
    $twig=$container->get('twig.environment');
    $show = GetParm('show', PARM_STRING);
    global $container;
    if ($show=='licensebrowser'){
      return $twig->loadTemplate("licensebrowser.htc")->render(array());
    }
    $Login = _("Login");
    if (empty($_SESSION['User']) && (plugin_find_id("auth") >= 0))
    {
      $Login = "<a href='$SiteURI?mod=auth'>$Login</a>";
    }
    $vars['login'] = $Login;
    $vars['SiteURI'] = Traceback_uri();
    return $twig->loadTemplate('welcome.htc')->render(array('login'=> $Login,'SiteURI' => Traceback_uri() ));

    
    
    //return $renderer->renderTemplate("welcome");
  }
 
}

$NewPlugin = new ui_welcome;
$NewPlugin->Initialize();