<?php
######################################################################
# Copyright (C)
# Author: Vivek Kumar<vvksindia@gmail.com>
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
######################################################################

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\UI\Component\MicroMenu;
use GuzzleHttp\Client;

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
    $this->DBaccess   = PLUGIN_DB_WRITE;
    $this->LoginFlag  = 0;
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
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
    $patternName = GetParm("patternName",PARM_STRING);

    $vars = array();

    $uploadId = GetParm("upload",PARM_INTEGER);
    /** @var UploadDao $uploadDao */

    if($patternName != null && !empty($patternName))
    {

      $client = new Client([
        // Base URI is used with relative requests
        'base_uri' => 'https://api.clearlydefined.io/',
        ]);

      $res = $client->request('GET','definitions',[
          'query' => ['pattern' => $patternName]
        ]);
      
        $vars['body'] = "error";

      if($res->getStatusCode()==200)
      {
         $body = json_decode($res->getBody()->getContents());

         if(sizeof($body) == 0)
         {
          $body[0] = "No Match Found";
         }

         $vars['body'] = $body;
      }

      $upload_name = $patternName;
    }

    else{
      if ( !$this->uploadDao->isAccessible($uploadId, Auth::getGroupId()) )
        {
          $text = _("Permission Denied");
          return "<h2>$text</h2>";
        }

      $upload_name = GetUploadName($uploadId);
    }
    

    $vars['uploadName'] = $upload_name;

    $out = $this->renderString('agent_spasht.html.twig',$vars);
    

    return($out);
  }

}

$NewPlugin = new ui_spasht;
$NewPlugin->Initialize();

?>
