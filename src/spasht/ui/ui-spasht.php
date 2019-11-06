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

    parent::__construct();
  }

  public $uploadAvailable = "no";
  /**
    * \brief Customize submenus.
    */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item"));
    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);

    if (!empty($Item) && !empty($Upload)) {

      if (GetParm("mod",PARM_STRING) == $this->Name) {
        menu_insert("Browse::Spasht agent/view in clearlydefined",10);
        menu_insert("Browse::[BREAK]",100);
      } else {
        $text = _("view in clearlydefined");
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
    $optionSelect = GetParm("optionSelectedToOpen",PARM_RAW);
    $uploadAvailable = GetParm("uploadAvailable",PARM_STRING);

    $statusbody = "true";

    $patternName = GetParm("patternName",PARM_STRING); //Get the entery from search box
    $advanceSearch = GetParm("advanceSearch",PARM_STRING); //Get the status of advance search

    $this->vars['advanceSearch'] = ""; //Set advance search to empty
    $this->vars['storeStatus'] = "false";
    $this->vars['pageNo'] = 0;

    $uploadId = GetParm("upload",PARM_INTEGER);
    /** @var UploadDao $uploadDao */

    $upload_name = GetUploadName($uploadId);
    $uri = preg_replace("/&item=([0-9]*)/", "", Traceback());
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $uploadtree_tablename = GetUploadtreeTableName($uploadId);
    $agentId = $this->agentDao->getCurrentAgentId("spasht");

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
      'base_uri' => 'https://api.clearlydefined.io/',
      ]);

      // Point to definitions section in the api
      $res = $client->request('GET','definitions',[
        'query' => ['pattern' => $patternName] //Perform query operation into the api
        ]);

      if ($res->getStatusCode()==200) {//Get the status of http request
        $body = json_decode($res->getBody()->getContents()); //Fetch's body response from the request and convert it into json_decoded

        if (sizeof($body) == 0) {//Check if no element is found
          $statusbody = "false";
        } else {
          $temp = array();
          $details = array();

          for ($x = 0; $x < sizeof($body) ; $x++) {
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
          $this->vars['details'] = $details;
          $this->vars['body'] = $temp;
        }
      }

      /** Check for advance Search enabled
       * If enabled the revisions are retrieved from the body to display them in the form.
       * As options to users.
       */
      if ($advanceSearch == "advanceSearch") {
        $this->vars['advanceSearch'] = "checked";
      }
      if ($this->vars['storeStatus'] == "true") {
        $this->vars['pageNo'] = "data_stored_successfully";
      } else {
        $this->vars['pageNo'] = "show_definitions";
      }

      $this->vars['uploadAvailable'] = $uploadAvailable;
      $upload_name = $patternName;
    } else {
      if ( !$this->uploadDao->isAccessible($uploadId, Auth::getGroupId()) ) {
         $text = _("Upload Id Not found");
        return "<h2>$text</h2>";
      }

      $this->vars['pageNo'] = 1;

      $searchUploadId = $this->spashtDao->getComponent($uploadId);

      if (!empty($searchUploadId)) {
        $this->vars['uploadAvailable'] = "yes";
        $this->vars['pageNo'] = "show_upload_table";
        $this->vars['body'] = $searchUploadId;

        list($this->vars['countOfFile'], $this->vars['fileList']) = $this->getFileListing($uploadtree_pk, $uri, $uploadtree_tablename, $agentId, $uploadId);
      } else {
        $uploadAvailable = "no";
      }
    }
    // $item = $uploadtree_pk;
    // $filter = "Show all";

    // $OutBuf = "";

    // $selectKey = $filter == 'nolic' ? 'nolic' : 'all';
    // $OutBuf .= "<select name='view_filter' id='view_filter' onchange='ChangeFilter(this,$uploadId, $item);'>";
    // foreach(array('all'=>_("Show all"), 'nolic'=> _("Show files without licenses")) as $key=>$text)
    // {
    //   $selected = ($selectKey == $key) ? "selected" : "";
    //   $OutBuf .= "<option $selected value=\"$key\">$text</option>";
    // }
    // $OutBuf .= "</select>";

    // list($tables, $tableVars) = $this->ShowUploadHist($uploadId, $item, $uri, $selectKey, $uploadtree_tablename, $agentId);
    // $this->vars['tables'] = $tableVars;
    // $this->vars['pageContent'] = $OutBuf . $tables;

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

    $this->vars['statusbody'] = $statusbody;
    $out = $this->render('agent_spasht.html.twig',$this->vars);

    //$this->Output_tables();

    return($out);
  }

  // public function Output_tables()
  // {
  //   $OutBuf="";
  //   $uploadId = GetParm("upload",PARM_INTEGER);
  //   $item = GetParm("item",PARM_INTEGER);
  //   $filter = GetParm("filter",PARM_STRING);

  //   /* check upload permissions */
  //   if (!$this->uploadDao->isAccessible($uploadId, Auth::getGroupId()))
  //   {
  //     $text = _("Permission Denied");
  //     return "<h2>$text</h2>";
  //   }

  //   /* Get uploadtree_tablename */
  //   $uploadtree_tablename = GetUploadtreeTableName($uploadId);
  //   $this->uploadtree_tablename = $uploadtree_tablename;

  //   /************************/
  //   /* Show the folder path */
  //   /************************/

  //   $this->vars['dir2browse'] =  Dir2Browse($this->Name,$item,NULL,1,"Browse",-1,'','',$uploadtree_tablename);
  //   if (empty($uploadId))
  //   {
  //     return 'no item selected';
  //   }

  //   /* advanced interface allowing user to select dataset (agent version) */
  //   $dataset = $this->agentName."_dataset";
  //   $arstable = $this->agentName."_ars";
  //   /* get proper agent_id */
  //   $agentId = GetParm("agent", PARM_INTEGER);
  //   if (empty($agentId))
  //   {
  //     $agentId = LatestAgentpk($uploadId, $arstable);
  //   }

  //   if ($agentId == 0)
  //   {
  //     /* schedule copyright */
  //     $OutBuf .= ActiveHTTPscript("Schedule");
  //     $OutBuf .= "<script language='javascript'>\n";
  //     $OutBuf .= "function Schedule_Reply()\n";
  //     $OutBuf .= "  {\n";
  //     $OutBuf .= "  if ((Schedule.readyState==4) && (Schedule.status==200 || Schedule.status==400))\n";
  //     $OutBuf .= "    document.getElementById('msgdiv').innerHTML = Schedule.responseText;\n";
  //     $OutBuf .= "  }\n";
  //     $OutBuf .= "</script>\n";

  //     $OutBuf .= "<form name='formy' method='post'>\n";
  //     $OutBuf .= "<div id='msgdiv'>\n";
  //     $OutBuf .= _("No data available.");
  //     $OutBuf .= "<input type='button' name='scheduleAgent' value='Schedule Agent'";
  //     $OutBuf .= "onClick=\"Schedule_Get('" . Traceback_uri() . "?mod=schedule_agent&upload=$uploadId&agent=agent_{$this->agentName}')\">\n";
  //     $OutBuf .= "</input>";
  //     $OutBuf .= "</div> \n";
  //     $OutBuf .= "</form>\n";

  //     $this->vars['pageContent'] = $OutBuf;
  //     return;
  //   }

  //   $AgentSelect = AgentSelect($this->agentName, $uploadId, $dataset, $agentId, "onchange=\"addArsGo('newds', 'spasht_dataset');\"");

  //   /* change the copyright  result when selecting one version of copyright */
  //   if (!empty($AgentSelect))
  //   {
  //     $action = Traceback_uri() . '?mod=' . GetParm('mod',PARM_RAW) . Traceback_parm_keep(array('upload','item'));

  //     $OutBuf .= "<script type='text/javascript'>
  //       function addArsGo(formid, selectid)
  //       {
  //         var selectobj = document.getElementById(selectid);
  //         var Agent_pk = selectobj.options[selectobj.selectedIndex].value;
  //         document.getElementById(formid).action='$action'+'&agent='+Agent_pk;
  //         document.getElementById(formid).submit();
  //         return;
  //       }
  //     </script>";

  //     $OutBuf .= "<form action=\"$action\" id=\"newds\" method=\"POST\">$AgentSelect</form>";
  //   }

  //   $selectKey = $filter == 'nolic' ? 'nolic' : 'all';
  //   $OutBuf .= "<select name='view_filter' id='view_filter' onchange='ChangeFilter(this,$uploadId, $item);'>";
  //   foreach(array('all'=>_("Show all"), 'nolic'=> _("Show files without licenses")) as $key=>$text)
  //   {
  //     $selected = ($selectKey == $key) ? "selected" : "";
  //     $OutBuf .= "<option $selected value=\"$key\">$text</option>";
  //   }
  //   $OutBuf .= "</select>";

  //   $uri = preg_replace("/&item=([0-9]*)/", "", Traceback());
  //   list($tables, $tableVars) = $this->ShowUploadHist($uploadId, $item, $uri, $selectKey, $uploadtree_tablename, $agentId);
  //   $this->vars['tables'] = $tableVars;
  //   $this->vars['pageContent'] = $OutBuf . $tables;

  //   return;
  // }


  // /**
  //  * @brief Given an $Uploadtree_pk, display: \n
  //  * (1) The histogram for the directory BY LICENSE. \n
  //  * (2) The file listing for the directory.
  //  *
  //  * @param int    $Uploadtree_pk        Upload id
  //  * @param string $Uri                  URI
  //  * @param string $filter               Filter for query
  //  * @param string $uploadtree_tablename Uploadtree table to use
  //  * @param int    $Agent_pk             Agent id
  //  * @return array|void
  //  */
  // protected function ShowUploadHist($upload_pk, $Uploadtree_pk, $Uri, $filter, $uploadtree_tablename, $Agent_pk)
  // {
  //   list($ChildCount, $VF) = $this->getFileListing($Uploadtree_pk, $Uri, $uploadtree_tablename, $Agent_pk, $upload_pk);
  //   $this->vars['childcount'] = $ChildCount;
  //   $this->vars['fileListing'] = $VF;

  //   /***************************************
  //   @todo
  //   Problem: $ChildCount can be zero!
  //   This happens if you have a container that does not
  //   unpack to a directory.  For example:
  //   file.gz extracts to archive.txt that contains a license.
  //   Same problem seen with .pdf and .Z files.
  //   Solution: if $ChildCount == 0, then just view the license!
  //   $ChildCount can also be zero if the directory is empty.
  //    ***************************************/
  //   if ($ChildCount == 0)
  //   {
  //     $isADirectory = $this->isADirectory($Uploadtree_pk);
  //     if ($isADirectory) {
  //       return;
  //     }
  //     $ModLicView = plugin_find($this->viewName);
  //     return $ModLicView->execute();
  //   }
  //   return $this->fillTables($upload_pk, $Uploadtree_pk, $filter, $Agent_pk, $VF);
  // }

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

  // /**
  //  * @copydoc HistogramBase::fillTables()
  //  * @see HistogramBase::fillTables()
  //  */
  // protected function fillTables($upload_pk, $Uploadtree_pk, $filter, $agentId, $VF)
  // {
  //   list ($vCopyright, $vTextFindings, $tableVars) = $this->getTableContent(
  //     $upload_pk, $Uploadtree_pk, $filter, $agentId);

  //   $out = $this->renderString('agent_spasht_ui_tabs.html.twig',
  //     array(
  //       'contCopyright' => $vCopyright,
  //       'contTextFindings' => $vTextFindings,
  //       'fileList' => $VF
  //     ));
  //   return array($out, $tableVars);
  // }

  // /**
  //  * @brief Get contents for copyright table
  //  * @param int    $upload_pk    Upload id for fetch request
  //  * @param int    $uploadtreeId Upload tree id of the item
  //  * @param string $filter       Filter to apply for query
  //  * @param int    $agentId      Agent id which populate the result
  //  * @return array Copyright contents, upload tree items in result
  //  */
  // protected function getTableContent($upload_pk, $uploadtreeId, $filter, $agentId)
  // {
  //   $typeDescriptionPairs = array(
  //     'statement' => _("Agent Findings")
  //   );
  //   $tableVars = array();
  //   $output = array();
  //   foreach($typeDescriptionPairs as $type=>$description)
  //   {
  //     list ($out, $this->vars) = $this->getTableForSingleType($type, $description,
  //       $upload_pk, $uploadtreeId, $filter, $agentId);
  //     $tableVars[$type] = $this->vars;
  //     $output[] = $out;
  //   }

  //   $output[] = $tableVars;
  //   return $output;
  // }

  // /**
  //  * @param string $type         Type to be fetched
  //  * @param string $description  Description of the content
  //  * @param int    $uploadId     Upload id to process
  //  * @param int    $uploadTreeId Uploadtree id of the item
  //  * @param string $filter       Filter for query
  //  * @param int    $agentId      Id of the agent populated the result
  //  * @return array
  //  * @todo Template this! For now I just template the js
  //  */
  // public function getTableForSingleType($type, $description, $uploadId, $uploadTreeId, $filter, $agentId)
  // {
  //   $sorting = json_encode($this->returnSortOrder());

  //   $out = array("type" => $type, "sorting" => $sorting, "uploadId" => $uploadId,
  //       "uploadTreeId" => $uploadTreeId, "agentId" => $agentId, "filter" => $filter, "description" => $description);
  //   $typeDescriptor = "";
  //   if($type !== "statement")
  //   {
  //     $typeDescriptor = $description;
  //   }
  //   $output = "<h4>Activated $typeDescriptor statements:</h4>
  //              <div><table border=1 width='100%' id='copyright".$type."'></table></div>
  //              <br/><br/>
  //              <div>
  //                <span>
  //                  Replace: <input type='text' id='replaceText".$type."' style='width:80%'>
  //                </span>
  //              <br/><br/>
  //              <a style='cursor: pointer; margin-left:10px;' id='replaceSelected".$type."' class='buttonLink'>Mark selected rows for replace</a>
  //              <a style='cursor: pointer; margin-left:10px;' id='deleteSelected".$type."' class='buttonLink'>Mark selected rows for deletion</a>
  //              <br/><br/>
  //              <h4>Deactivated $typeDescriptor statements:</h4>
  //              </div>
  //              <div><table border=1 width='100%' id='copyright".$type."deactivated'></table></div>";

  //   return array($output, $out);
  // }

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
}

  $NewPlugin = new ui_spasht;
  $NewPlugin->Initialize();
