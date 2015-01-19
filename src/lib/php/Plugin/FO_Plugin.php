<?php
/***********************************************************
Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
Copyright (C) 2014 Siemens AG

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

use Fossology\Lib\Plugin\Plugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Each plugin has a state to identify if it is invalid.
 * For example, if a plugin crashes then it should mark the state
 * as invalid.
 */
define("PLUGIN_STATE_FAIL", -1); // mark it as a total failure
define("PLUGIN_STATE_INVALID", 0);
define("PLUGIN_STATE_VALID", 1); // used during install
define("PLUGIN_STATE_READY", 2); // used during post-install

/**
 * Each plugin has a state to identify the kind of access required.
 * Plugins should select the highest level of access.
 */
define("PLUGIN_DB_NONE", 0);
define("PLUGIN_DB_READ", 1);
define("PLUGIN_DB_WRITE", 3);        /* DB writes permitted */
define("PLUGIN_DB_ADMIN", 10);        /* add/delete users */

/**
 * Permissions
 * See http://www.fossology.org/projects/fossology/wiki/PermsPt2
 */
define("PERM_NONE", 0);   /* No permissions */
define("PERM_READ", 1);   /* Read only */
define("PERM_WRITE", 3);         /* Create and write data. */
define("PERM_ADMIN", 10); /* Control permissions    */

$NoneText = _("None");
$ReadText = _("Read");
$WriteText = _("Write");
$AdminText = _("Admin");
$GLOBALS['PERM_NAMES'] = array(PERM_NONE => $NoneText, PERM_READ => $ReadText, PERM_WRITE => $WriteText, PERM_ADMIN => $AdminText);

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
class FO_Plugin implements Plugin
{
  /**
   *  All public fields can be empty, indicating that it does not apply.
   */

  var $State = PLUGIN_STATE_INVALID;

  /**
   * Name defines the official name of this plugin.  Other plugins may
   * call this plugin based on this name.
   */
  var $Name = "";
  var $Version = "1.0";
  var $Title = "";  // used for HTML title tags and window menu bars

  /**
   * Access level restrictions
   */
  var $DBaccess = PLUGIN_DB_NONE; /* what kind of access is needed? */
  var $LoginFlag = 0;        /* Must you be logged in to access this plugin? 1=yes, 0=no */

  /**
   * Common for HTML output
   */
  var $NoMenu = 0;        /* 1 = Don't show the HTML menu at the top of page */
  var $NoHeader = 0;        /* 1 = Don't show the HTML header at the top of page */

  /**
   * This array lists plugin dependencies by name and initialization order.
   * These are used to call PostInitialize in the correct order.
   * PostInitialize will be called when all dependencies are ready.
   * InitOrder says "after all dependencies are ready, do higher value
   * items first."  For example, this allows for menus to be initialized
   * before anything else.  (You probably won't need to change InitOrder.)
   */
  var $PluginLevel = 10; /* used for sorting plugins -- higher comes first after dependencies are met */
  var $Dependency = array();
  var $InitOrder = 0;

  /** @var Menu */
  private $menu;

  /** @var Twig_Environment */
  protected $renderer;

  /** @var Request|NULL */
  private $request;

  /** @var string[] */
  private $headers = array();

  protected $vars = array();

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
  var $MenuList = NULL;
  var $MenuOrder = 0;
  var $MenuTarget = NULL;

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
    return 0;
  }

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
  }

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
  public function __construct()
  {
    $this->OutputType = $this->OutputType ?: "HTML";
    $this->State = PLUGIN_STATE_VALID;
    register_plugin($this);

    global $container;
    $this->menu = $container->get('ui.component.menu');
    $this->renderer = $container->get('twig.environment');
  }

  /**
   * \brief dummy stub till all references are removed.
   */
  function Initialize()
  {
    return (TRUE);
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
    if ($this->State != PLUGIN_STATE_VALID)
    {
      return 0;
    } // don't run

    if (empty($_SESSION['User']) && $this->LoginFlag)
    {
      return 0;
    }
    // Make sure dependencies are met
    foreach ($this->Dependency as $key => $val)
    {
      $id = plugin_find_id($val);
      if ($id < 0)
      {
        $this->Destroy();
        return (0);
      }
    }

    // Put your code here!
    // If this fails, set $this->State to PLUGIN_STATE_INVALID.
    // If it succeeds, then set $this->State to PLUGIN_STATE_READY.

    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if ($this->MenuList !== "")
    {
      menu_insert("Main::" . $this->MenuList, $this->MenuOrder, $this->Name, $this->MenuTarget);
    }
    return ($this->State == PLUGIN_STATE_READY);
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
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    } // don't run
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
    $this->State = PLUGIN_STATE_INVALID;
  }

  /**
   * The output functions generate "output" for use in a text CLI or web page.
   * For agents, the outputs generate status information.
   */

  /* Possible values: Text, HTML, XML, JSON */
  var $OutputType = "HTML";
  var $OutputToStdout = 0;

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for assigning headers.
   *
   * @internal param $vars
   */
  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }

    $this->headers['Content-type'] = 'text/html';
    $this->headers['Pragma'] = 'no-cache';
    $this->headers['Cache-Control'] = 'no-cache, must-revalidate, maxage=1, post-check=0, pre-check=0';
    $this->headers['Expires'] = 'Expires: Thu, 19 Nov 1981 08:52:00 GMT';

    $metadata = "<meta name='description' content='The study of Open Source'>\n";
    $metadata .= "<meta http-equiv='Content-Type' content='text/html;charset=UTF-8'>\n";

    $this->vars['metadata'] = $metadata;

    if (!empty($this->Title))
    {
      $this->vars['title'] = htmlentities($this->Title);
    }

    $styles = "<link rel='stylesheet' href='css/fossology.css'>\n";
    $styles .= "<link rel='stylesheet' href='css/jquery.dataTables.css'>\n";
    $styles .= "<link rel='icon' type='image/x-icon' href='favicon.ico'>\n";
    $styles .= "<link rel='shortcut icon' type='image/x-icon' href='favicon.ico'>\n";

    if ($this->NoMenu == 0)
    {
      $styles .= $this->menu->OutputCSS();
    }
    $this->vars['styles'] = $styles;

    if ($this->NoMenu == 0)
    {
      $this->vars['menu'] = $this->menu->Output($this->Title);
    }

    global $SysConf;
    $this->vars['versionInfo'] = array(
        'version' => $SysConf['BUILD']['VERSION'],
        'buildDate' => $SysConf['BUILD']['BUILD_DATE'],
        'commitHash' => $SysConf['BUILD']['COMMIT_HASH'],
        'commitDate' => $SysConf['BUILD']['COMMIT_DATE']
    );

  } // OutputOpen()

  /**
   * @brief Similar to OutputClose, this ends the output type
   * for this object.  However, this does NOT change any global
   * settings.  This is called when this object is a dependency
   * for another object.
   */
  function OutputUnSet()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }
    return "";
  }

  function renderOutput()
  {
    ob_start();

    $output = $this->Output();

    if ($output instanceof Response) {
      $response = $output;
    } else
    {
      if (empty($this->vars['content']) && $output)
      {
        $this->vars['content'] = $output;
      } elseif (empty($this->vars['content']))
      {
        $this->vars['content'] = ob_get_contents();
      }
      $response = $this->render($this->getTemplateName());
    }
    ob_end_clean();

    $response->prepare($this->getRequest());
    $response->send();
  }


  /**
   * @brief This function is called when user output is
   * requested.  This function is responsible for content.
   * (OutputOpen and Output are separated so one plugin
   * can call another plugin's Output.)
   */
  function Output()
  {
    return $this->htmlContent();
  }

  protected function htmlContent()
  {
    return '';
  }
  
  public function getTemplateName()
  {
    return "include/base.html.twig";
  }

  /**
   * @param string $templateName
   * @param array $vars
   * @return Response
   */
  protected function render($templateName, $vars = null)
  {
    $useVars = $vars ?: $this->vars;
    $content = $this->renderer->loadTemplate($templateName)->render($useVars);

    return new Response(
        $content,
        Response::HTTP_OK,
        $this->headers
    );
  }

  /**
   * @return Request
   */
  public function getRequest() {
    if (!isset($this->request)) {
      $this->request = Request::createFromGlobals();
    }
    return $this->request;
  }

  public function execute()
  {
    $this->OutputOpen();
    $this->renderOutput();
  }

  function preInstall()
  {
    if ($this->State == PLUGIN_STATE_VALID)
    {
      $this->PostInitialize();
    }
    if ($this->State == PLUGIN_STATE_READY)
    {
      $this->RegisterMenus();
    }
  }

  function postInstall()
  {
    $state = $this->Install();
    if ($state != 0)
    {
      throw new \Exception("install of plugin " . $this->Name . " failed");
    }
  }

  function unInstall()
  {
    $this->Destroy();
  }

  public function getName()
  {
    return $this->Name;
  }

  function __toString()
  {
    return getStringRepresentation(get_object_vars($this), get_class($this));
  }

}