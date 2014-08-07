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

/**
 * Each plugin has a state to identify if it is invalid.
 * For example, if a plugin crashes then it should mark the state
 * as invalid.
 */
define("PLUGIN_STATE_FAIL",-1); // mark it as a total failure
define("PLUGIN_STATE_INVALID",0);
define("PLUGIN_STATE_VALID",1); // used during install
define("PLUGIN_STATE_READY",2); // used during post-install

/**
 * Each plugin has a state to identify the kind of access required.
 * Plugins should select the highest level of access.
 */
define("PLUGIN_DB_NONE",0);
define("PLUGIN_DB_READ",1);
define("PLUGIN_DB_WRITE",3);	/* DB writes permitted */
define("PLUGIN_DB_ADMIN",10);	/* add/delete users */

/**
 * Permissions
 * See http://www.fossology.org/projects/fossology/wiki/PermsPt2
 */
define("PERM_NONE",0);   /* No permissions */
define("PERM_READ",1);   /* Read only */
define("PERM_WRITE",3);	 /* Create and write data. */
define("PERM_ADMIN",10); /* Control permissions    */

$NoneText = _("None");
$ReadText = _("Read");
$WriteText = _("Write");
$AdminText = _("Admin");
$GLOBALS['PERM_NAMES'] = array(PERM_NONE=>$NoneText, PERM_READ=>$ReadText, PERM_WRITE=>$WriteText, PERM_ADMIN=>$AdminText);

/**
 * \class FO_Plugin
 * \brief This is the Plugin class.  All plugins should:
 * 1. Use this class or extend this class.
 * 2. After defining the necessary functions and values, the plugin
 * must add the new element to the Plugins array.
 * For example:
 * $NewPlugin = new Plugin;
 * $NewPlugin->Name="Fred";
 * if ($NewPlugin->Initialize() != 0) { destroy $NewPlugin; }
 */
class FO_Plugin
{
  /**
   *  All public fields can be empty, indicating that it does not apply.
   */

  var $State=PLUGIN_STATE_INVALID;

  /**
   * Name defines the official name of this plugin.  Other plugins may
   * call this plugin based on this name.
   */
  var $Name="";
  var $Version="1.0";
  var $Title="";  // used for HTML title tags and window menu bars

  /**
   * Access level restrictions
   */
  var $DBaccess=PLUGIN_DB_NONE; /* what kind of access is needed? */
  var $LoginFlag=0;	/* Must you be logged in to access this plugin? 1=yes, 0=no */

  /**
   * Common for HTML output
   */
  var $NoMenu=0;	/* 1 = Don't show the HTML menu at the top of page */
  var $NoHeader=0;	/* 1 = Don't show the HTML header at the top of page */
  var $NoHTML=0;	/* 1 = Don't add any HTML to the output */

  /**
   * This array lists plugin dependencies by name and initialization order.
   * These are used to call PostInitialize in the correct order.
   * PostInitialize will be called when all dependencies are ready.
   * InitOrder says "after all dependencies are ready, do higher value
   * items first."  For example, this allows for menus to be initialized
   * before anything else.  (You probably won't need to change InitOrder.)
   */
  var $PluginLevel=10; /* used for sorting plugins -- higher comes first after dependencies are met */
  var $Dependency = array();
  var $InitOrder=0;

  /**
   * Plugins may define a menu item.
   * The menu name defines where it belongs.
   * Each menu item belongs in a category (menu list) and could be in
   * subcategories (menu sublists).  The MenuList identifies
   * the list (and sublists) where this item belongs.  The menu heirarchy
   * is defined by a name and a "::" to denote a submenu item.
   *
   * The MenuName defines the name for this item in the menu.
   *
   * Finally, multiple plugins may place multiple items under the same menu.
   * The MenuOrder assigns a numeric ranking for items.  All items
   * at the same level are sorted alphabetically by MenuName.
   *
   * For example, to define an "About" menu item under the "Help" menu:
   * $MenuList = "Help::About";
   * $MenuOrder=0;
   * And a "delete" agent under the tool, administration menu would be:
   * $MenuList = "Tools::Administration::Delete";
   * $MenuOrder=0;
   *
   * Since menus may link to results that belong in a specific window,
   * $MenuTarget can identify the window.  If not defined, the UI will use
   * a default results window.
   *
   * /note
   * 1. If the MenuList location does not exist, then it will be created.
   * 2. If a plugin does not have a menulist item, then it will not appear
   * in any menus.
   * 3. MenuList is case and SPACE sensitive.  "Help :: About" defines
   * "Help " and " About".  While "Help::About" defines "Help" and "About".
   */
  var $MenuList=NULL;
  var $MenuOrder=0;
  var $MenuTarget=NULL;

  /**
   * These next variables define required functionality.
   * If the functions exist, then they are called.  However, plugins are
   * not required to define any of these.
   */

  /**
   * \brief This function (when defined) is only called when
   * the plugin is first installed.  It should make sure all
   * requirements are available and create anything it needs to run.
   * It returns 0 on success, non-zero on failure.
   * A failed install is not inserted in the system.
   *
   * \note It may be called multiple times.  It must check that
   * changes are needed BEFORE doing any changes.
   * Also, it must check for partial installs in case the user is
   * recovering from an installation failure.
   */
  function Install()
  {
    return(0);
  } // Install()

  /**
   * \brief This function (when defined) is only called once,
   * when the plugin is removed.  It should uninstall and remove
   * all items that are only used by this plugin.  There should be
   * no residues -- if the plugin is ever installed again, it should
   * act like a clean install.  Thus, any DB, files, or state variables
   * specific to this plugin must be removed.
   * This function must always succeed.
   */ 
  function Remove()
  {
    return;
  } // Remove()

  /**
   * \brief base constructor.  Most plugins will just use this
   *
   * Makes sure the plugin is in the correct state.  If so, the plugin is
   * inserted into the Plugins data structure.
   *
   * The constructor assumes that Install() was already run one time (possibly
   * years ago and not during this object's creation).
   *
   * \return true on success, false on failure.
   *
   * On failure the plugin is not used by the system. NOTE: This function must
   * NOT assume that other plugins are installed.  See PostInitialize.
   */
  public function __construct() {

    global $Plugins;

    if ($this->State != PLUGIN_STATE_INVALID) {
      //print "<pre>TDB: returning state invalid\n</pre>";
      return(1); // don't re-run
    }
    if ($this->Name !== "") { // Name must be defined
      $this->State=PLUGIN_STATE_VALID;
      array_push($Plugins,$this);
    }
    return($this->State == PLUGIN_STATE_VALID);
  }

  /**
   * \brief dummy stub till all references are removed.
   */
  function Initialize()
  {
    return(TRUE);
  } // Initialize()

  /**
   * \brief This function is called before the plugin
   * is used and after all plugins have been initialized.
   * If there is any initialization step that is dependent on other
   * plugins, put it here.
   *
   * \return true on success, false on failure.
   *
   * \note Do not assume that the plugin exists!  Actually check it!
   */
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

    // Put your code here!
    // If this fails, set $this->State to PLUGIN_STATE_INVALID.
    // If it succeeds, then set $this->State to PLUGIN_STATE_READY.

    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if ($this->MenuList !== "") {
      menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);
    }
    return($this->State == PLUGIN_STATE_READY);
  } // PostInitialize()

  /**
   * \brief While menus can be added to any time at or after
   * the PostInitialize phase, this is the standard location for
   * registering this item with menus.
   * 
   * \note 1: Menu registration may be plugin specific!
   * \note 2: This is intended for cross-plugin registration and not
   * for the main menu.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); } // don't run
    // Add your own menu items here.
    // E.g., menu_insert("Menu_Name::Item");
  }

  /**
   * \brief This is a destructor called after the plugin
   * is no longer needed.  It should assume that PostInitialize() was
   * already run one time (this session) and succeeded.
   * This function must always succeed.
   */
  function Destroy()
  {
    if ($this->State != PLUGIN_STATE_INVALID)
    {
      ; // Put your cleanup here
    }
    $this->State=PLUGIN_STATE_INVALID;
    return;
  } // Destroy()

  /**
   * The output functions generate "output" for use in a text CLI or web page.
   * For agents, the outputs generate status information.
   */

  /* Possible values: Text, HTML, or XML. */
  var $OutputType="Text";
  var $OutputToStdout=0;

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for assigning headers.
   * If $Type is "HTML" then generate an HTTP header.
   * If $Type is "XML" then begin an XML header.
   * If $Type is "Text" then generate a text header as needed.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function OutputOpen($Type,$ToStdout)
  {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $this->OutputType=$Type;
    $this->OutputToStdout=$ToStdout;
    // Put your code here
    switch($this->OutputType)
    {
      case "XML":
        $V = "<xml>\n";
        break;
      case "HTML":
        header('Content-type: text/html');
        header("Pragma: no-cache"); /* for IE cache control */
        header('Cache-Control: no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0'); /* prevent HTTP/1.1 caching */
        header('Expires: Expires: Thu, 19 Nov 1981 08:52:00 GMT'); /* mark it as expired (value from Apache default) */
        if ($this->NoHTML) { return; }
        $V = "";
        if (($this->NoMenu == 0) && ($this->Name != "menus"))
        {
          $Menu = &$Plugins[plugin_find_id("menus")];
          $Menu->OutputSet($Type,$ToStdout);
        }
        else { $Menu = NULL; }

        /* DOCTYPE is required for IE to use styles! (else: css menu breaks) */
        $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "xhtml1-frameset.dtd">' . "\n";
        // $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\n";
        // $V .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Loose//EN" "http://www.w3.org/TR/html4/loose.dtd">' . "\n";
        // $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "xhtml1-strict.dtd">' . "\n";

        $V .= "<html>\n";
        $V .= "<head>\n";
        $V .= "<meta name='description' content='The study of Open Source'>\n";
        $V .= "<meta http-equiv='Content-Type' content='text/html;charset=UTF-8'>\n";
        if ($this->NoHeader == 0)
        {
          /** Known bug: DOCTYPE "should" be in the HEADER
           and the HEAD tags should come first.
           Also, IE will ignore <style>...</style> tags that are NOT
           in a <head>...</head> block.
           **/
          if (!empty($this->Title)) { $V .= "<title>" . htmlentities($this->Title) . "</title>\n"; }
          $V .= "<link rel='stylesheet' href='css/fossology.css'>\n";
          $V .= "<link rel='stylesheet' href='css/jquery.dataTables.css'>\n";
          print $V; $V="";
          if (!empty($Menu)) { print $Menu->OutputCSS(); }
          $V .= "</head>\n";

          $V .= "<body class='text'>\n";
          print $V; $V="";
          if (!empty($Menu)) { $Menu->Output($this->Title); }
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print $V;
    return;
  } // OutputOpen()

  /**
   * \brief This function is called when user output is done.
   * If $Type is "HTML" then display the HTML footer as needed.
   * If $Type is "XML" then end the XML.
   * If $Type is "Text" then generate a text footer as needed.
   * This uses $OutputType.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function OutputClose()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    // Put your code here
    $V = "";
    switch($this->OutputType)
    {
      case "XML":
        $V = "</xml>\n";
        break;
      case "HTML":
        if ($this->NoHTML) { return; }
        if (!$this->NoHeader)
        {
          $V = "</body>\n";
          $V .= "</html>\n";
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print $V;
    return;
  } // OutputClose()

  /**
   * \brief Similar to OutputOpen, this sets the output type
   * for this object.  However, this does NOT change any global
   * settings.  This is called when this object is a dependency
   * for another object.
   */
  function OutputSet($Type,$ToStdout)
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $this->OutputType=$Type;
    $this->OutputToStdout=$ToStdout;
    // Put your code here
    $V= "";
    switch($this->OutputType)
    {
      case "XML":
        $V = "<xml>\n";
        break;
      case "HTML":
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print $V;
    return;
  } // OutputSet()

  /**
   * \brief Similar to OutputClose, this ends the output type
   * for this object.  However, this does NOT change any global
   * settings.  This is called when this object is a dependency
   * for another object.
   */
  function OutputUnSet()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
    $V = "";
    switch($this->OutputType)
    {
      case "XML":
        $V = "</xml>\n";
        break;
      case "HTML":
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print $V;
    return;
  } // OutputUnSet()

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for content.
   * (OutputOpen and Output are separated so one plugin
   * can call another plugin's Output.)
   * This uses $OutputType.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return(0); }
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
    print $V;
    return;
  } // Output()
  
  /**
   * 
   * @return string
   */
  function outputHtml()
  {
    $html = "";
    return $html;
  }
}