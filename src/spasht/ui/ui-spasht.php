<?php

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\AgentDao;
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

  protected $viewName;
  /** @var string
   * Name of uploadtree table to use
   */

  /**
    * @var AgentDao $agentDao
    * AgentDao object
    */
  protected $agentDao;

  public $vars;

  function __construct()
  {
    $this->Name       = "spashtbrowser";
    $this->Title      = _("Spasht Browser");
    $this->Dependency = array("browse","view");
    $this->DBaccess   = PLUGIN_DB_WRITE;
    $this->LoginFlag  = 0;
    $this->uploadDao  = $GLOBALS['container']->get('dao.upload');
    $this->spashtDao  = $GLOBALS['container']->get('dao.spasht');
    $this->agentDao   = $GLOBALS['container']->get('dao.agent');
    $this->viewName   = "copyright_spasht_list";
    $this->renderer   = $GLOBALS['container']->get('twig.environment');
    $this->modBack    = $this->Name;

    parent::__construct();
  }

  public $uploadAvailable = "no";
  /**
    * \brief Customize submenus.
    */
  function RegisterMenus()
  {
    $URI = $this->Name . Traceback_parm_keep(array(
      "show", "format", "page", "upload", "item"));
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (! empty($Item) && ! empty($Upload)) {
      $menuText = "Spasht";
      $tooltipText = _("View in clearlydefined");
      $menuPosition = 55;
      if (GetParm("mod", PARM_STRING) == $this->Name) {
        menu_insert("Browse::$menuText", $menuPosition);
      } else {
        menu_insert("Browse::$menuText", $menuPosition, $URI, $tooltipText);
      }
      $this->microMenu->insert(MicroMenu::TARGET_DEFAULT, $menuText,
        $menuPosition, $this->getName(), $URI, $tooltipText);
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
    if ($this->Name !== "") {
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
    global $SysConf;

    $this->agentName = "spasht";
    $optionSelect = GetParm("optionSelectedToOpen",PARM_RAW);
    $uploadAvailable = GetParm("uploadAvailable",PARM_STRING);

    $statusbody = "definition_not_found";

    $patternName = GetParm("patternName",PARM_STRING); //Get the entery from search box
    $revisionName = GetParm("revisionName",PARM_STRING);
    $namespaceName = GetParm("namespaceName",PARM_STRING);
    $typeName = GetParm("typeName",PARM_STRING);
    $providerName = GetParm("providerName",PARM_STRING);

    $this->vars['storeStatus'] = "false";
    $this->vars['pageNo'] = "definition_not_found";

    $uploadId = GetParm("upload",PARM_INTEGER);
    /** @var UploadDao $uploadDao */

    $upload_name = preg_replace('/(?i)(?:\.(?:tar\.xz|tar\.bz2|tar\.gz|zip|tgz|tbz|txz|tar))/',"",GetUploadName($uploadId));
    $uri = preg_replace("/&item=([0-9]*)/", "", Traceback());
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $uploadtree_tablename = GetUploadtreeTableName($uploadId);
    $agentId = $this->agentDao->getCurrentAgentId("spasht");

    $this->vars['micromenu'] = Dir2Browse($this->modBack, $uploadtree_pk, NULL, $showBox = 0, "View", -1, '', '', $uploadtree_tablename);

    if (!empty($optionSelect)) {
      $str = explode ("/", $optionSelect);
      $body = array();
      $body['body_type'] = $str[0];
      $body['body_provider'] = $str[1];
      $body['body_namespace'] = $str[2];
      $body['body_name'] = $str[3];
      $body['body_revision'] = $str[4];

      if ($uploadAvailable == "yes") {
        $result = $this->spashtDao->alterComponentRevision($body, $uploadId);
      } else {
        $result = $this->spashtDao->addComponentRevision($body, $uploadId);
      }

      if ($result >= 0) {
        $patternName = null;
      } else {
        $patternName = $body['body_name'];
      }
    }

    if ($patternName != null && !empty($patternName)) {//Check if search is not empty
      /** Guzzle/http Guzzle Client that connect with ClearlyDefined API */
      $client = new Client([
        // Base URI is used with relative requests
        'base_uri' => $SysConf['SYSCONFIG']["ClearlyDefinedURL"]
      ]);
      // Prepare proxy
      $proxy = [];
      if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['http_proxy'])) {
        $proxy['http'] = $SysConf['FOSSOLOGY']['http_proxy'];
      }
      if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['https_proxy'])) {
        $proxy['https'] = $SysConf['FOSSOLOGY']['https_proxy'];
      }
      if (array_key_exists('no_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['no_proxy'])) {
        $proxy['no'] = explode(',', $SysConf['FOSSOLOGY']['no_proxy']);
      }

      // Point to definitions section in the api
      $res = $client->request('GET', 'definitions', [
        'query' => ['pattern' => $patternName], //Perform query operation into the api
        'proxy' => $proxy
      ]);

      if ($res->getStatusCode()==200) {//Get the status of http request
        $body = json_decode($res->getBody()->getContents()); //Fetch's body response from the request and convert it into json_decoded

        if (sizeof($body) == 0) {//Check if no element is found
          $statusbody = "definition_not_found";
        } else {
          $temp = array();
          $details = array();
          $acceptResult = false;

          for ($x = 0; $x < sizeof($body) ; $x++) {
            $str = explode ("/", $body[$x]);
            $temp2 = array();

            $temp2['revision'] = $str[4];
            $temp2['type'] = $str[0];
            $temp2['name'] = $str[3];
            $temp2['provider'] = $str[1];
            $temp2['namespace'] = $str[2];

            $accept = $this->checkAdvanceSearch($temp2, $revisionName, $namespaceName, $typeName, $providerName);

            if ($accept == true) {
              $acceptResult = true;
              $temp[] = $temp2;
              $uri = "definitions/".$body[$x];

              $detail_body = array();

              //details section
              $res_details = $client->request('GET', $uri, [
                'query' => [
                  'expand' => "-files"
                ], //Perform query operation into the api
                'proxy' => $proxy
              ]);

              $detail_body = json_decode($res_details->getBody()->getContents(),true);

              $details_temp = array();

              $details_temp['declared'] = $detail_body["licensed"]["declared"];
              $details_temp['source'] = $detail_body["described"]["sourceLocation"]["url"];
              $details_temp['release'] = $detail_body["described"]["releaseDate"];
              $details_temp['files'] = $detail_body["licensed"]["facets"]["core"]["files"];
              $details_temp['attribution'] = substr(
                implode(", ",
                  $detail_body['licensed']["facets"]["core"]['attribution']['parties']),
                0, 100);
              $details_temp['discovered'] = substr(
                implode(", ",
                  $detail_body['licensed']["facets"]["core"]['discovered']['expressions']),
                0, 100);

              $details[] = $details_temp;
            }
          }
          if ($acceptResult == true) {
            $this->vars['details'] = $details;
            $this->vars['body'] = $temp;
            $statusbody = "definition_found";
          } else {
            $statusbody = "definition_not_found";
          }
        }
      }

      if ($statusbody == "definition_found") {
        $this->vars['pageNo'] = "show_definitions";
      } else {
        $this->vars['pageNo'] = "definition_not_found";
      }

      $this->vars['uploadAvailable'] = $uploadAvailable;
      $upload_name = $patternName;
    } else {
      if ( !$this->uploadDao->isAccessible($uploadId, Auth::getGroupId()) ) {
        $text = _("Upload Id Not found");
        return "<h2>$text</h2>";
      }

      $this->vars['pageNo'] = "Please Search and Select Revision";

      $searchUploadId = $this->spashtDao->getComponent($uploadId);

      if (!empty($searchUploadId)) {
        $this->vars['uploadAvailable'] = "yes";
        $this->vars['pageNo'] = "show_upload_table";
        $this->vars['body'] = $searchUploadId;

        $arstable = $this->spashtDao->getSpashtArs($uploadId);
        $ars_success = "f";

        if (!empty($arstable)) {
          $ars_success = $arstable['ars_success'];
        }

        $OutBuf = "";

        if ($ars_success == 'f') {
          /* schedule agent directly spasht page */
          $OutBuf .= ActiveHTTPscript("Schedule");
          $OutBuf .= "<script language='javascript'>\n";
          $OutBuf .= "function Schedule_Reply()\n";
          $OutBuf .= "  {\n";
          $OutBuf .= "  if ((Schedule.readyState==4) && (Schedule.status==200 || Schedule.status==400))\n";
          $OutBuf .= "    document.getElementById('msgdiv').innerHTML = Schedule.responseText;\n";
          $OutBuf .= "    document.getElementById('msgdiv').style.color = 'red';\n";
          $OutBuf .= "  }\n";
          $OutBuf .= "</script>\n";

          $OutBuf .= "<form name='formy' method='post'>\n";
          $OutBuf .= "<div id='msgdiv'>\n";
          $OutBuf .= "<input type='button' name='scheduleAgent' value='Schedule Agent'";
          $OutBuf .= "onClick=\"Schedule_Get('" . Traceback_uri() . "?mod=schedule_agent&upload=$uploadId&agent=agent_{$this->agentName}')\">\n";
          $OutBuf .= "</input>";
          $OutBuf .= "</div> \n";
          $OutBuf .= "</form>\n";

        } else {
          $OutBuf = "Scan completed successfully\n";
        }
        $this->vars['body_menu'] = $OutBuf;

        list($this->vars['countOfFile'], $this->vars['fileList']) = $this->getFileListing($uploadtree_pk, $uri, $uploadtree_tablename, $agentId, $uploadId);
      } else {
        $uploadAvailable = "no";
      }
    }

    $table = array();
    $tables = array();

    $table['uploadId'] = $uploadId;
    $table['uploadTreeId'] = $uploadtree_pk;
    $table['agentId'] = $agentId;
    $table['type'] = 'statement';
    $table['filter'] = 'Show all';
    $table['sorting'] = json_encode($this->returnSortOrder());

    $tables[] = $table;

    $this->vars['tables'] = $tables;

    $this->vars['uploadName'] = $upload_name;

    $out = $this->render('agent_spasht.html.twig',$this->vars);

    //$this->Output_tables();

    return($out);
  }

  /**
    * @param int    $Uploadtree_pk        Uploadtree id
    * @param string $Uri                  URI
    * @param string $uploadtree_tablename Uploadtree table name
    * @param int    $Agent_pk             Agent id
    * @param int    $upload_pk            Upload id
    * @return array
    */
  protected function getFileListing($Uploadtree_pk, $Uri, $uploadtree_tablename, $Agent_pk, $upload_pk)
  {
    $VF=""; // return values for file listing
    /*******    File Listing     ************/
    /* Get ALL the items under this Uploadtree_pk */
    $Children = GetNonArtifactChildren($Uploadtree_pk, $uploadtree_tablename);
    $ChildCount = 0;
    $ChildLicCount = 0;
    $ChildDirCount = 0; /* total number of directory or containers */

    foreach ($Children as $c) {
      if (Iscontainer($c['ufile_mode'])) {
        $ChildDirCount++;
      }
    }

    $VF .= "<table border=0>";
    foreach ($Children as $child) {
      if (empty($child)) {
        continue;
      }

      $ChildCount++;

      global $Plugins;
      $ModLicView = &$Plugins[plugin_find_id($this->viewName)];
      /* Determine the hyperlink for non-containers to view-license  */
      if (!empty($child['pfile_fk']) && !empty($ModLicView)) {
        $LinkUri = Traceback_uri();
        $LinkUri .= "?mod=".$this->viewName."&agent=$Agent_pk&upload=$upload_pk&item=$child[uploadtree_pk]";
      } else {
        $LinkUri = NULL;
      }

      /* Determine link for containers */
      if (Iscontainer($child['ufile_mode'])) {
        $uploadtree_pk = DirGetNonArtifact($child['uploadtree_pk'], $uploadtree_tablename);
        $LicUri = "$Uri&item=" . $uploadtree_pk;
      } else {
        $LicUri = NULL;
      }

      /* Populate the output ($VF) - file list */
      /* id of each element is its uploadtree_pk */
      $LicCount = 0;

      $cellContent = Isdir($child['ufile_mode']) ? $child['ufile_name'].'/' : $child['ufile_name'];

      if (Iscontainer($child['ufile_mode'])) {
        $cellContent = "<a href='$LicUri'><b>$cellContent</b></a>";
      } else if (!empty($LinkUri)) {//  && ($LicCount > 0))
        $cellContent = "<a href='$LinkUri'>$cellContent</a>";
      }

      $VF .= "<tr><td id='$child[uploadtree_pk]' align='left'>$cellContent</td><td>";
      if ($LicCount) {
        $VF .= "[" . number_format($LicCount, 0, "", ",") . "&nbsp;";
        $VF .= "license" . ($LicCount == 1 ? "" : "s");
        $VF .= "</a>";
        $VF .= "]";
        $ChildLicCount += $LicCount;
      }
      $VF .= "</td></tr>\n";
    }
    $VF .= "</table>\n";
    return array($ChildCount, $VF);
  }

  /**
    * @brief Check if passed element is a directory
    * @param int $Uploadtree_pk Uploadtree id of the element
    * @return boolean True if it is a directory, false otherwise
    */
  protected function isADirectory($Uploadtree_pk)
  {
    $row =  $this->uploadDao->getUploadEntry($Uploadtree_pk, $this->uploadtree_tablename);
    $isADirectory = IsDir($row['ufile_mode']);
    return $isADirectory;
  }

  /**
  * @brief Get sorting orders
  * @return string[][]
  */
  public function returnSortOrder ()
  {
    $defaultOrder = array (
      array(0, "desc"),
      array(1, "desc"),
    );
    return $defaultOrder;
  }

  /**
   * @brief Check for Advance Search
   * @return boolean
   */
  public function checkAdvanceSearch ($body, $revisionName, $namespaceName, $typeName, $providerName)
  {
    if (! empty($revisionName != "") && $body['revision'] != $revisionName) {
      return false;
    }

    if (! empty($namespaceName != "") && $body['namespace'] != $namespaceName) {
      return false;
    }

    if (! empty($typeName != "") && $body['type'] != $typeName) {
      return false;
    }

    if (! empty($providerName != "") && $body['provider'] != $providerName) {
      return false;
    }

    return true;
  }
}

$NewPlugin = new ui_spasht;
$NewPlugin->Initialize();
