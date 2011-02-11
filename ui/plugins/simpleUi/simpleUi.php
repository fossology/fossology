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

global $GlobalReady;
if (!isset($GlobalReady))
{
  exit;
}

/*
 global $WEBDIR;

 require($WEBDIR . '/plugins/admin-bucket-pool.php');
 require($WEBDIR . '/plugins/admin-check-template.php');
 require($WEBDIR . '/plugins/admin-config.php');
 require($WEBDIR . '/plugins/admin-db.php');
 require($WEBDIR . '/plugins/admin-folder-create.php');
 require($WEBDIR . '/plugins/admin-folder-delete.php');
 require($WEBDIR . '/plugins/admin-folder-edit.php');
 require($WEBDIR . '/plugins/admin-folder-move.php');
 require($WEBDIR . '/plugins/admin-tag-ns-perm.php');
 require($WEBDIR . '/plugins/admin-tag-ns.php');
 require($WEBDIR . '/plugins/admin-upload-edit.php');
 require($WEBDIR . '/plugins/admin-upload-move.php');
 require($WEBDIR . '/plugins/admin-upload-delete.php');
 require($WEBDIR . '/plugins/agent-add.php');
 require($WEBDIR . '/plugins/agent-bucket.php');
 require($WEBDIR . '/plugins/agent-mimetype.php');
 require($WEBDIR . '/plugins/agent-nomos-once.php');
 require($WEBDIR . '/plugins/agent-nomos.php');
 require($WEBDIR . '/plugins/agent-pkgagent.php');
 require($WEBDIR . '/plugins/agent-unpack.php');
 require($WEBDIR . '/plugins/ajax-filebucket.php');
 require($WEBDIR . '/plugins/ajax-filelic.php');
 require($WEBDIR . '/plugins/ajax-perms.php');
 require($WEBDIR . '/plugins/ajax-tags.php');
 require($WEBDIR . '/plugins/ajax-upload-agents.php');
 require($WEBDIR . '/plugins/ajax-uploads.php');
 require($WEBDIR . '/plugins/copyright.php');
 require($WEBDIR . '/plugins/core-auth.php');
 require($WEBDIR . '/plugins/core-db.php');
 require($WEBDIR . '/plugins/core-debug-fileloc.php');
 require($WEBDIR . '/plugins/core-debug-flush-cache.php');
 require($WEBDIR . '/plugins/core-debug-menus.php');
 require($WEBDIR . '/plugins/core-debug-plugins.php');
 require($WEBDIR . '/plugins/core-debug-user.php');
 require($WEBDIR . '/plugins/core-init.php');
 require($WEBDIR . '/plugins/core-schema.dat');
 require($WEBDIR . '/plugins/core-schema.php');
 require($WEBDIR . '/plugins/group-manage.php');
 require($WEBDIR . '/plugins/group-manage-self.php');
 require($WEBDIR . '/plugins/jobs-showjobs.php');
 require($WEBDIR . '/plugins/jobs-showjobs-upload.php');
 require($WEBDIR . '/plugins/myjobs.php');
 require($WEBDIR . '/plugins/search-file-advance.php');
 require($WEBDIR . '/plugins/search-file-by-license.php');
 require($WEBDIR . '/plugins/search.php');
 //require($WEBDIR . '/plugins/search-file.php');
 require($WEBDIR . '/plugins/search-repo.php');
 require($WEBDIR . '/plugins/ui-about.php');
 require($WEBDIR . '/plugins/ui-browse.php');
 require($WEBDIR . '/plugins/ui-buckets.php');
 require($WEBDIR . '/plugins/ui-default.php');
 require($WEBDIR . '/plugins/ui-download.php');
 require($WEBDIR . '/plugins/ui-folders.php');
 require($WEBDIR . '/plugins/ui-license.php');
 require($WEBDIR . '/plugins/ui-list-bucket-files.php');
 require($WEBDIR . '/plugins/ui-menus.php');
 require($WEBDIR . '/plugins/ui-nomos-license.php');
 require($WEBDIR . '/plugins/ui-refresh.php');
 require($WEBDIR . '/plugins/ui-reunpack.php');
 require($WEBDIR . '/plugins/ui-tags.php');
 require($WEBDIR . '/plugins/ui-topnav.php');
 require($WEBDIR . '/plugins/ui-treenav.php');
 require($WEBDIR . '/plugins/ui-view-info.php');
 require($WEBDIR . '/plugins/ui-view-license.php');
 require($WEBDIR . '/plugins/ui-view.php');
 require($WEBDIR . '/plugins/ui-welcome.php');
 require($WEBDIR . '/plugins/upload-file.php');
 require($WEBDIR . '/plugins/upload-instructions.php');
 require($WEBDIR . '/plugins/upload-url.php');
 require($WEBDIR . '/plugins/upload-srv-files.php');
 require($WEBDIR . '/plugins/user-add.php');
 require($WEBDIR . '/plugins/user-del.php');
 require($WEBDIR . '/plugins/user-edit-self.php');
 require($WEBDIR . '/plugins/user-edit-any.php');
 */

define("TITLE_SimpleUi", _("Simplified UI"));

class simpleUi extends FO_Plugin
{
  public $Name = "simple UI";
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
    //global $MenuList;

    // change the user-edit-self menu
    $userEditSelf = plugin_find_any(user_edit_self);  // can be null
    if(!empty($userEditSelf))
    {
      $userEditSelf->MenuList = "My Account";
      $md = menu_insert("My Account",$userEditSelf->MenuOrder,
      $userEditSelf->Name,$userEditSelf->MenuTarget);
    }
    /*
     $maxdepth = null;
     $admin = array();
     $admin = menu_find("Main::Admin", & $maxdepth);
     echo "<pre>admin menu found is:\n";
     print_r($admin) . "\n";
     echo "</pre>";
     */
    //return(TRUE);
  }

  /**
   * \brief adjust the dependencies of selected plugins so they work with the
   * simplified UI.
   *
   * @return void
   */
  function adjustDependencies()
  {
    /*
     * List of plugins that need dependencies adjusted
     */
    $newDependencies = array(
      'upload_instructions' => array("upload_file", "upload_url"),
      'upload_file' => array("db", "agent_unpack"),
    //'myjobs' => array('db'),
    );
    $upmenus = array(
      'upload_instructions' => 'Main::Upload::Instructions',
      'upload_file' => 'Main::Upload::From File',
    //'myjobs' => 'Main::Jobs::My Jobs',
    );

    foreach($newDependencies as $plugin => $depends)
    {
      $pluginRef = plugin_find_any($plugin);  // can be null
      if(!empty($pluginRef))
      {
        $pluginRef->Dependency = $depends;
        if($this->setState($pluginRef))
        {
          if($pluginRef->State == PLUGIN_STATE_READY)
          {
            $md = menu_insert($upmenus[$pluginRef->Name],$pluginRef->MenuOrder,
            $pluginRef->Name,$pluginRef->MenuTarget);
          }
        }
      }
    }
  } // adjustPlugins

  /**
   * \brief change the LoginFlag for selected plugins
   *
   */
  function adjustLoginFlag()
  {
    $plist = array(
      'search',
      'search_file',
      'browse'
      );
      foreach($plist as $plugin)
      {
        $pluginRef = plugin_find_any($plugin);  // can be null
        if(!empty($pluginRef))
        {
          $pluginRef->LoginFlag = 1; // must be logged in to use this plugin
          //$pluginRef->PostInitialize();
        }
      }
      // hack
      foreach (array('search_file', 'search') as $plugin)
      {
        $pluginRef = plugin_find_any($plugin);  // can be null
        if(!empty($pluginRef))
        {
          $pluginRef->DBaccess = PLUGIN_DB_DELETE;
        }
      }
  }

  /**
   * \brief disable plugins not needed for simple UI, when users with perms
   * > 5 login, these disabled plugins should get enabled.
   *
   * @param mixed $plugins either a scaler or an array
   *
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

  /**
   * \brief check and set the state of the plugin
   *
   * @param $pluginRef object reference to the plugin
   * @return boolean
   */
  function setState($pluginRef)
  {
    if(!is_object($pluginRef)) { return(FALSE); }

    if($pluginRef->State == PLUGIN_STATE_INVALID)
    {
      //echo "<pre>";
      //echo "SUI: Plugin state is $pluginRef->State for $pluginRef->Name\n";
      $pluginRef->State = PLUGIN_STATE_VALID;
      //echo "<pre>SUI: State after setting is:$pluginRef->State\n";
      $pluginRef->PostInitialize();
      //echo "<pre>SUI: State after PostInit is:$pluginRef->State\n";
      if ($pluginRef->State == PLUGIN_STATE_READY) { $pluginRef->RegisterMenus(); }
    }
    //echo "</pre>";
    return(TRUE);
  } // setState($pluginRef)

  function PostInitialize()
  {
    global $Plugins;
    //echo "<pre>SIMP: State is:$this->State for $this->Name\n</pre>";
    if ($this->State != PLUGIN_STATE_VALID) {
      return(0);
    } // don't run

    if (empty($_SESSION['User']) && $this->LoginFlag) {
      //echo "<pre>SIMP: Didn't pass session/LoginFlag check\n</pre>";
      return(0);
    }
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val) {
      $id = plugin_find_id($val);
      if ($id < 0) {
        echo "<pre>SIMP: depdendencies not met! for $this->Name\n</pre>";
        $this->Destroy();
        return(0);
      }
    }
    // this makes it so anybody above user level 5 gets the full UI ?
    if($_SESSION['UserLevel'] <= 5)
    {
      //$this->adjustDependencies();
      $this->adjustMenus();
      //echo "<pre>SIMP: adjusting loginflag\n</pre>";
      $this->adjustLoginFlag();
      plugin_disable(@$_SESSION['UserLevel']);
      $this->disablePlugins(array
      ('upload_srv_files','agent_nomos_once','agent_copyright_once',));

    }
    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    return($this->State == PLUGIN_STATE_READY);
  } // PostInitialize()

  /**
   * \brief Remove a menu item and it's children from the menu list.  Optionally
   * keep the children. (do they become orphans and not visible?).
   */
  function removeMenuItem($item)
  {
    return(TRUE);
  }

};
$NewPlugin = new simpleUi();
?>