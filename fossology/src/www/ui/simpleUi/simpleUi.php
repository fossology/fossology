<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 */

/**
 * \file simpleUi.php
 *
 * \brief simpleUi, create new menus for users with DBacess <= 5.
 *
 * This code depends on symlinks existing between the directory this code is in
 * and the plugins directory.
 *
 * \version "$Id: simpleUi.php 4404 2011-06-14 21:46:47Z rrando $"
 */

define("TITLE_SimpleUi", _("Simplified UI"));

/**
 * \class simpleUi extend from FO_Plugin
 */
class simpleUi extends FO_Plugin
{
  public $Name = "simple_UI";
  public $Title = TITLE_SimpleUi;
  public $Version = "1.0";
  public $MenuList = "";
  public $LoginFlag = 1;
  public $Dependency = array();
  public $DBaccess = PLUGIN_DB_READ;
  public $PluginLevel= 20; /* make this run before level 10 plugins */


  /**
   * \brief adjust the menu structure $MenuList
   *
   * Replace or adjust the contents of selected menu items to so they display
   * as needed in the simplified UI.
   */
  function adjustMenus()
  {
    // change the user-edit-self menu
    $userEditSelf = plugin_find_any('user_edit_self');  // can be null
    if(!empty($userEditSelf))
    {
      $userEditSelf->MenuList = "My Account";
      $md = menu_insert("My Account",$userEditSelf->MenuOrder,
      $userEditSelf->Name,$userEditSelf->MenuTarget);
    }
  }

  /**
   * \brief disable plugins not needed for simple UI, when users with perms
   * > 5 login, these disabled plugins should get enabled.
   *
   * \param mixed $plugins either a scalar or an array
   */
  function disablePlugins($plugins)
  {
    if(empty($plugins))
    {
      return(0);
    }
    if(is_array($plugins))
    {
      foreach($plugins as $plugin)
      {
        $pluginRef = plugin_find_any($plugin);  // can be null
        if(!empty($pluginRef))
        {
          $pluginRef->Destroy();    // state invalid
        }
      }
    }
    else
    {
      $pluginRef = plugin_find_any($plugins);  // can be null
      if(!empty($pluginRef))
      {
        //echo "<pre>Disabling $pluginRef->Name\n</pre>";
        $pluginRef->Destroy();    // state invalid
      }

    }
  } //disablePlugins

  function PostInitialize()
  {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_VALID) {
      return(0);
    } // don't run

    if (empty($_SESSION['User']) && $this->LoginFlag) {
      return(0);
    }
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val) {
      $id = plugin_find_id($val);
      if ($id < 0) {
        $this->Destroy();
        return(0);
      }
    }

    // if user wants simple ui, make adjustments
    if(array_key_exists('UiPref', $_SESSION))
    {
      if($_SESSION['UiPref'] == 'simple')
      {
        $this->adjustMenus();
        plugin_disable(@$_SESSION['UserLevel']);
        $this->disablePlugins(array('agent_nomos_once','agent_copyright_once',
        'upload_file', 'upload_url', 'upload_srv_files',
        'upload_instructions','admin_license_file','Admin_License'));
      }
    }
    else        // no UiPref or original ui, disable simple ui plugins
    {
      $this->disablePlugins(array('uploads'));
    }
    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    return($this->State == PLUGIN_STATE_READY);
  } // PostInitialize()
};
$NewPlugin = new simpleUi();
?>
