<?php

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
    $uploadId = GetParm("upload", PARM_INTEGER);
    $tooltipText = _("View in ClearlyDefined");

    $URI = $this->getName() . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    menu_insert("Browse::Spasht", 10, $URI, $tooltipText);

    $itemId = GetParm("item", PARM_INTEGER);
    $textFormat = $this->microMenu->getFormatParameter($itemId);
    $pageNumber = GetParm("page", PARM_INTEGER);
    $this->microMenu->addFormatMenuEntries($textFormat, $pageNumber);

    // For all other menus, permit coming back here.
    
    if (!empty($itemId) && !empty($uploadId))
    {
      $menuText = "Spasht";
      $menuPosition = 55;
      menu_insert("Browse::[BREAK]",100);
      $tooltipText = _("View licenses from Clearly Defined");
      $URI = $this->getName() . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
      $this->microMenu->insert(MicroMenu::TARGET_DEFAULT, $menuText, $menuPosition, $this->getName(), $URI, $tooltipText);
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
    $patternName = GetParm("patternName",PARM_STRING); //Get the entery from search box
    $advanceSearch = GetParm("advanceSearch",PARM_STRING); //Get the status of advance search

    $vars = array();
    $statusbody = "true";
    
    $vars['advanceSearch'] = ""; //Set advance search to empty

    $uploadId = GetParm("upload",PARM_INTEGER);
    /** @var UploadDao $uploadDao */

    if($patternName != null && !empty($patternName)) //Check if search is not empty
    {
      /** Guzzle/http Guzzle Client that connect with ClearlyDefined API */
      $client = new Client([
        // Base URI is used with relative requests
        'base_uri' => 'https://api.clearlydefined.io/',
        ]);

        // Point to definitions secton in the api
      $res = $client->request('GET','definitions',[
          'query' => ['pattern' => $patternName] //Perform query operation into the api
        ]);

      if($res->getStatusCode()==200) //Get the status of http request
      {
         $body = json_decode($res->getBody()->getContents()); //Fetch's body response from the request and convert it into json_decode

         if(sizeof($body) == 0) //Check if no element is found
         {
          $statusbody = "false";
         }
         else
         {
          for ($x = 0; $x < sizeof($body) ; $x++)
          {
            $str = explode ("/", $body[$x]);

            $body['index'] = $x;
            $body_revision[$x] = $str[4];
            $body_type[$x] = $str[0];
            $body_name[$x] = $str[3];
            $body_provider[$x] = $str[1];
            $body_namespace[$x] = $str[2];
          }
          $body['body_revision'] = $body_revision;
          $body['body_type'] = $body_type;
          $body['body_name'] = $body_name;
          $body['body_provider'] = $body_provider;
          $body['body_namespace'] = $body_namespace;
         }
      }
          /** Check for advance Search enabled
            * If enabled the revisions are retrieved from the body to display them in the form.
            * As options to users.
            */
            if($advanceSearch == "advanceSearch"){
              $vars['advanceSearch'] = "checked";
            }
            $vars['body'] = $body;
            $vars['statusbody'] = $statusbody;
              
      $upload_name = $patternName;
    }

    else{
      if ( !$this->uploadDao->isAccessible($uploadId, Auth::getGroupId()) )
        {
          $text = _("Upload Id Not found");
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
