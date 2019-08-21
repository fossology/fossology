<?php

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\UI\Component\MicroMenu;
use GuzzleHttp\Client;
use Fossology\Lib\Dao\SpashtDao;

/**
 * @class ui_spashts
 * Install spashts plugin to UI menu
 */
class ui_spasht extends FO_Plugin
{

  /** @var SpashtDao  $spashtDao*/
  private $spashtDao;

  function __construct()
  {
    $this->Name       = "spashtbrowser";
    $this->Title      = _("Spasht Browser");
    $this->Dependency = array("browse","view");
    $this->DBaccess   = PLUGIN_DB_WRITE;
    $this->LoginFlag  = 0;
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->spashtDao = $GLOBALS['container']->get('dao.spasht');
    parent::__construct();
  }

  public $uploadAvailable = "no";
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
  public function Output()
  {
    $optionSelect = GetParm("optionSelectedToOpen",PARM_RAW);
    $uploadAvailable = GetParm("uploadAvailable",PARM_STRING);

    $vars = array();
    $statusbody = "true";

    $patternName = GetParm("patternName",PARM_STRING); //Get the entery from search box
    $advanceSearch = GetParm("advanceSearch",PARM_STRING); //Get the status of advance search
    
    $vars['advanceSearch'] = ""; //Set advance search to empty
    $vars['storeStatus'] = "false";
    $vars['pageNo'] = 0;

    $uploadId = GetParm("upload",PARM_INTEGER);
    /** @var UploadDao $uploadDao */

    if(!empty($optionSelect))
    {
      $str = explode ("/", $optionSelect);
      
      $body = array();

      $body['body_type'] = $str[0];
      $body['body_provider'] = $str[1];
      $body['body_namespace'] = $str[2];
      $body['body_name'] = $str[3];
      $body['body_revision'] = $str[4];

      if($uploadAvailable == "yes"){
        $result = $this->spashtDao->alterComponentRevision($body, $uploadId);
        }
      else{
        $result = $this->spashtDao->addComponentRevision($body, $uploadId);
      }

      if($result >= 0)
      {
        $patternName = null;
      }

      else
      {
        $patternName = $body['body_name'];
      }
    }

    if($patternName != null && !empty($patternName)) //Check if search is not empty
    {
      /** Guzzle/http Guzzle Client that connect with ClearlyDefined API */
      $client = new Client([
        // Base URI is used with relative requests
        'base_uri' => 'https://api.clearlydefined.io/',
        ]);

        // Point to definitions section in the api
      $res = $client->request('GET','definitions',[
          'query' => ['pattern' => $patternName] //Perform query operation into the api
        ]);

      if($res->getStatusCode()==200) //Get the status of http request
      {
         $body = json_decode($res->getBody()->getContents()); //Fetch's body response from the request and convert it into json_decoded

         if(sizeof($body) == 0) //Check if no element is found
         {
          $statusbody = "false";
         }
         else
         {
           $temp = array();
           $details = array();
          for ($x = 0; $x < sizeof($body) ; $x++)
          {
            $str = explode ("/", $body[$x]);

            $temp2 = array();

            $temp2['revision'] = $str[4];
            $temp2['type'] = $str[0];
            $temp2['name'] = $str[3];
            $temp2['provider'] = $str[1];
            $temp2['namespace'] = $str[2];

            $temp[] = $temp2;
            $uri = "definitions/".$body[$x];

            $detail_body = array();

            //details section
            $res_details = $client->request('GET',$uri,[
              'query' => [
                'expand' => "-files"
              ] //Perform query operation into the api
            ]);

            $detail_body = json_decode($res_details->getBody()->getContents(),true);

            $details_temp = array();

            $details_temp['declared'] = $detail_body["licensed"]["declared"];
            $details_temp['source'] = $detail_body["described"]["sourceLocation"]["url"];
            $details_temp['release'] = $detail_body["described"]["releaseDate"];
            $details_temp['files'] = $detail_body["licensed"]["facets"]["core"]["files"];
            $details_temp['attribution'] = $detail_body['licensed']["facets"]["core"]['attribution']['parties'];
            $details_temp['discovered'] = $detail_body['licensed']["facets"]["core"]['discovered']['expressions'];

            $details[] = $details_temp;
          }

          $vars['details'] = $details;
          $vars['body'] = $temp;
         }
      }
      /** Check for advance Search enabled
        * If enabled the revisions are retrieved from the body to display them in the form.
        * As options to users.
        */
        if($advanceSearch == "advanceSearch"){
          $vars['advanceSearch'] = "checked";
        }
        if($vars['storeStatus'] == "true")
        {
          $vars['pageNo'] = 3;
        }
        else
        {
          $vars['pageNo'] = 2;
        }

      $vars['uploadAvailable'] = $uploadAvailable;
      $upload_name = $patternName;
    }

    else{
      if ( !$this->uploadDao->isAccessible($uploadId, Auth::getGroupId()) )
        {
          $text = _("Upload Id Not found");
          return "<h2>$text</h2>";
        }

      $upload_name = GetUploadName($uploadId);
      $vars['pageNo'] = 1;

      $searchUploadId = $this->spashtDao->getComponent($uploadId);

      if(!empty($searchUploadId)){
        $vars['uploadAvailable'] = "yes";
        $vars['pageNo'] = 4;
        $vars['body'] = $searchUploadId;
      }
      else{
        $uploadAvailable = "no";
      }
    }

    $vars['uploadName'] = $upload_name;

    $vars['statusbody'] = $statusbody;
    $out = $this->renderString('agent_spasht.html.twig',$vars);
    
    return($out);
  }

}

$NewPlugin = new ui_spasht;
$NewPlugin->Initialize();

?>
