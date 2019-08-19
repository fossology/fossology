<?php

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\UI\Component\MicroMenu;

/**
 * @class ui_spashts
 * Install spashts plugin to UI menu
 */
class ui_spasht extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "spashtbrowser";
    $this->Title      = _("Spasht Browser");
    $this->Dependency = array("browse","view");
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->LoginFlag  = 0;
    parent::__construct();
  }


  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
      if (GetParm("mod",PARM_STRING) == $this->Name)
      {
        menu_insert("Browse::Spasht",10);
        menu_insert("Browse::[BREAK]",100);
      }
      else
      {
        $text = _("View in ClearlyDefined");
        menu_insert("Browse::Spasht",10,$URI,$text);
      }
    }
  } // RegisterMenus()


  /**
   * @brief This is called before the plugin is used.
   * It should assume that Install() was already run one time
   * (possibly years ago and not during this object's creation).
   *
   * @return boolean true on success, false on failure.
   * A failed initialize is not used by the system.
   * @note This function must NOT assume that other plugins are installed.
   * @see FO_Plugin::Initialize()
   */
  function Initialize()
  {
    global $_GET;

    if ($this->State != PLUGIN_STATE_INVALID) {
      return(1);
    } // don't re-run
    if ($this->Name !== "") // Name must be defined
    {
      global $Plugins;
      $this->State=PLUGIN_STATE_VALID;
      array_push($Plugins,$this);
    }

    return($this->State == PLUGIN_STATE_VALID);
  } // Initialize()

  /**
   * @brief This function returns the scheduler status.
   * @see FO_Plugin::Output()
   */
  public function output()
  {
    $uploadId = GetParm("upload",PARM_INTEGER);
    /** @var UploadDao $uploadDao */
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    if ( !$uploadDao->isAccessible($uploadId, Auth::getGroupId()) )
    {
      $text = _("Permission Denied");
      return "<h2>$text</h2>";
    }

    $upload_name = GetUploadName($uploadId);
    return($upload_name);
  }

}

$NewPlugin = new ui_spasht;
$NewPlugin->Initialize();
