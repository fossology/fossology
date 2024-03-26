<?php
/*
 SPDX-FileCopyrightText: © 2019 Vivek Kumar <vvksindia@gmail.com>
 Author: Vivek Kumar<vvksindia@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\SpashtDao;
use Fossology\Lib\Data\Spasht\DefinitionSummary;
use Fossology\Lib\Data\Spasht\Coordinate;
use Fossology\Lib\UI\Component\MicroMenu;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

include_once(dirname(__DIR__) . "/agent/version.php");

/**
 * @class ui_spashts
 * Install spashts plugin to UI menu
 */
class ui_spasht extends FO_Plugin
{

  /**
   * @var SpashtDao $spashtDao
   * Spasht dao
   */
  private $spashtDao;

  /**
   * @var string $viewName
   * View name of single files
   */
  protected $viewName;

  /**
    * @var AgentDao $agentDao
    * AgentDao object
    */
  protected $agentDao;

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
    $this->viewName   = "view";
    $this->renderer   = $GLOBALS['container']->get('twig.environment');
    $this->agentName  = "spasht";
    $this->vars['name'] = $this->Name;

    parent::__construct();
  }

  /**
    * \brief Customize submenus.
    */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
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
        menu_insert("Browse::[BREAK]", 100);
      } else {
        menu_insert("Browse::$menuText", $menuPosition, $URI, $tooltipText);
        menu_insert("View::$menuText", $menuPosition, $URI, $tooltipText);
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
      $Plugins[] = $this;
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

    $statusbody = "definition_not_found";

    $optionSelect = trim(GetParm("optionSelectedToOpen", PARM_STRING));
    $patternName = trim(GetParm("patternName", PARM_STRING)); // Get the entry
                                                              // from search box
    $revisionName = trim(GetParm("revisionName", PARM_STRING));
    $namespaceName = trim(GetParm("namespaceName", PARM_STRING));
    $typeName = trim(GetParm("typeName", PARM_STRING));
    $providerName = trim(GetParm("providerName", PARM_STRING));

    // Reading contribution data
    $coordinatesInfo = trim(GetParm("jsonStringCoordinates", PARM_STRING));
    $contributionInfo = trim(GetParm("jsonStringContributionInfo", PARM_STRING));
    $contributionRevision = trim(GetParm("hiddenRevision", PARM_STRING));
    $contributionDataValid = trim(GetParm("contributionDataValid", PARM_STRING));

    $this->vars['storeStatus'] = "false";
    $this->vars['pageNo'] = "definition_not_found";

    $uploadId = GetParm("upload", PARM_INTEGER);

    /* check upload permissions */
    if (empty($uploadId)) {
      return 'no item selected';
    } elseif (!$this->uploadDao->isAccessible($uploadId, Auth::getGroupId())) {
      $text = _("Permission Denied");
      return "<h2>$text</h2>";
    }

    $upload_name = preg_replace(
      '/(?i)(?:\.(?:tar\.xz|tar\.bz2|tar\.gz|zip|tgz|tbz|txz|tar))/', "",
      GetUploadName($uploadId));
    $uri = preg_replace("/&item=([0-9]*)/", "", Traceback());
    $uploadtree_pk = GetParm("item",PARM_INTEGER);
    $uploadtree_tablename = GetUploadtreeTableName($uploadId);
    $agentId = $this->agentDao->getCurrentAgentId("spasht");

    $this->vars['micromenu'] = Dir2Browse($this->Name, $uploadtree_pk, null,
      false, "Browse", -1, '', '', $uploadtree_tablename);

    if (!empty($optionSelect)) {
      $patternName = $this->handleNewSelection($optionSelect, $uploadId);
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

      try {
        // Point to definitions section in the api
        $res = $client->request('GET', 'definitions', [
          'query' => ['pattern' => $patternName], //Perform query operation into the api
          'proxy' => $proxy
        ]);
      } catch (RequestException $e) {
        $this->vars['message'] = "Unable to reach " .
          $SysConf['SYSCONFIG']["ClearlyDefinedURL"] . ". Code: " .
          $e->getCode();
        return $this->render('agent_spasht.html.twig', $this->vars);
      } catch (ClientException $e) {
        $this->vars['message'] = "Request failed. Status: " .
          $e->getCode();
        return $this->render('agent_spasht.html.twig', $this->vars);
      } catch (ServerException $e) {
        $this->vars['message'] = "Request failed. Status: " .
          $e->getCode();
        return $this->render('agent_spasht.html.twig', $this->vars);
      }

      if ($res->getStatusCode()==200) {//Get the status of http request
        $findings = json_decode($res->getBody()->getContents());

        if (count($findings) == 0) {//Check if no element is found
          $statusbody = "definition_not_found";
        } else {
          $matches = array();
          $details = array();

          foreach ($findings as $finding) {
            $obj = Coordinate::generateFromString($finding);

            if ($this->checkAdvanceSearch($obj, $revisionName, $namespaceName,
              $typeName, $providerName)) {
              $uri = "definitions/" . $obj->generateUrlString();

              //details section
              try {
                $res_details = $client->request('GET', $uri, [
                  'query' => [
                    'expand' => "-files"
                  ], //Perform query operation into the api
                  'proxy' => $proxy
                ]);
              } catch (RequestException $e) {
                $this->vars['message'] = "Unable to reach " .
                  $SysConf['SYSCONFIG']["ClearlyDefinedURL"] . ". Code: " .
                  $e->getCode();
                return $this->render('agent_spasht.html.twig', $this->vars);
              } catch (ClientException $e) {
                $this->vars['message'] = "Request failed. Status: " .
                  $e->getCode();
                return $this->render('agent_spasht.html.twig', $this->vars);
              } catch (ServerException $e) {
                $this->vars['message'] = "Request failed. Status: " .
                  $e->getCode();
                return $this->render('agent_spasht.html.twig', $this->vars);
              }

              $detail_body = json_decode($res_details->getBody()->getContents(),
                true);

              $details_temp = new DefinitionSummary($detail_body);
              $obj->setScore($details_temp->getScore());

              $matches[] = $obj;
              $details[] = $details_temp;
            }
          }
          if (!empty($details)) {
            $this->vars['details'] = $details;
            $this->vars['body'] = $matches;
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
      $upload_name = $patternName;
    } else {
      $this->vars['pageNo'] = "Please Search and Select Revision";

      $searchUploadId = $this->spashtDao->getComponent($uploadId);

      if (! empty($searchUploadId)) {
        $this->vars['pageNo'] = "show_upload_table";
        $this->vars['body'] = $searchUploadId;

        $message = "";

        $plugin = plugin_find($this->agentName);
        if ($plugin == null) {
          $message = "Agent spasht not installed";
        } else {
          $results = $plugin->AgentHasResults($uploadId);
          $running = isAlreadyRunning($this->agentName, $uploadId);
          if ($results != 0 && $running == 0) {
            $message = "Scan completed successfully\n";
          } else {
            $jobUrl = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
            $message = _("Agent scheduled.") . " <a href=$jobUrl>Show</a>\n";
          }
        }
        $this->vars['body_menu'] = $message;
      }
    }
    $table = array();

    $table['uploadId'] = $uploadId;
    $table['uploadTreeId'] = $uploadtree_pk;
    $table['agentId'] = $agentId;
    $table['type'] = 'statement';
    $table['filter'] = 'Show all';
    $table['sorting'] = json_encode($this->returnSortOrder());
    $this->vars['tables'] = [$table];

    $advanceSearchFormStatus = "hidden";
    if (empty(trim(GetParm("advanceSearchFormStatus", PARM_STRING)))) {
      // First load
      /**
       * @var Coordinate $coord
       * Coordinate from component ID (can be null)
       */
      $coord = $this->spashtDao->getCoordinateFromCompId($uploadId);
      if ($coord != null) {
        $upload_name = $coord->getName();
        $revisionName = $coord->getRevision();
        $namespaceName = $coord->getNamespace();
        $typeName = $coord->getType();
        $providerName = $coord->getProvider();
        $advanceSearchFormStatus = "show";
      }
    }
    $this->vars['uploadName']    = $upload_name;
    $this->vars['revisionName']  = $revisionName;
    $this->vars['namespaceName'] = $namespaceName;
    $this->vars['typeName']      = $typeName;
    $this->vars['providerName']  = $providerName;
    if (! empty($revisionName) || ! empty($namespaceName) || ! empty($typeName)
      || ! empty($providerName)) {
      $advanceSearchFormStatus = "show";
    }

    $this->vars['advanceSearchFormStatus'] = $advanceSearchFormStatus;
    $this->vars['fileList'] = $this->getFileListing($uploadtree_pk, $uri,
      $uploadtree_tablename, $agentId, $uploadId);

    if ($contributionDataValid === "true" && !empty($coordinatesInfo) && !empty($contributionInfo)) {
      $contributionJsonPacket = $this->handleContributionToClearlyDefined($coordinatesInfo ,$contributionInfo, $contributionRevision);

      try {
        // Point to curations section in the api
        $res = $client->request('PATCH', 'curations', [
          'data' => [$contributionJsonPacket], //send data to the api
          'proxy' => $proxy
        ]);
      } catch (RequestException $e) {
        $this->vars['message'] = "Unable to reach " .
          $SysConf['SYSCONFIG']["ClearlyDefinedURL"] . ". Code: " .
          $e->getCode();
        return $this->render('agent_spasht.html.twig', $this->vars);
      } catch (ClientException $e) {
        $this->vars['message'] = "Request failed. Status: " .
          $e->getCode();
        return $this->render('agent_spasht.html.twig', $this->vars);
      } catch (ServerException $e) {
        $this->vars['message'] = "Request failed. Status: " .
          $e->getCode();
        return $this->render('agent_spasht.html.twig', $this->vars);
      }
    }

    $out = $this->render('agent_spasht.html.twig', $this->vars);

    return($out);
  }


  /**
    * @param string  $coordinatesInfo       coordinates from selected definition
    * @param string  $contributionInfo      PR information filled by user
    * @param string  $contributionRevision  Revision of selected definition
    * @return array
    */
  protected function handleContributionToClearlyDefined($coordinatesInfo, $contributionInfo, $contributionRevision)
  {
    $contributionPacket = array();

    $coordinatesInfo = json_decode($coordinatesInfo);
    $contributionInfo = json_decode($contributionInfo);

    $contributionPacket["contributionInfo"] = $contributionInfo;
        $patches =array();

          $patch = array();
          $patch["coordinates"] = $coordinatesInfo;
            $revision = array();
              $contributionFiles = array();
                $file = array();
                $file["path"] = "";
                $file["license"] = "";
                $file["attributions"] = "";

              array_push($contributionFiles, $file);

            $revision[$contributionRevision]["files"] = $contributionFiles;
          $patch["revisions"] = $revision;

        array_push($patches, $patch);
        $contributionPacket["patches"] = $patches;

    if (!empty($contributionPacket)) {
      $jsonPacket = json_encode($contributionPacket);
      return $jsonPacket;
    }
  }

  /**
    * @param int    $Uploadtree_pk        Uploadtree id
    * @param string $Uri                  URI
    * @param string $uploadtree_tablename Uploadtree table name
    * @param int    $Agent_pk             Agent id
    * @param int    $upload_pk            Upload id
    * @return array
    */
  protected function getFileListing($Uploadtree_pk, $Uri, $uploadtree_tablename,
    $Agent_pk, $upload_pk)
  {
    /*******    File Listing     ************/
    /* Get ALL the items under this Uploadtree_pk */
    $Children = GetNonArtifactChildren($Uploadtree_pk, $uploadtree_tablename);

    $tableData = [];
    $uploadInfo = $this->uploadDao->getUploadEntry($Uploadtree_pk,
      $uploadtree_tablename);
    if (! empty($uploadInfo['parent'])) {
      $uploadtree_pk = $uploadInfo['parent'];
      $row = [];
      $row['url'] = "$Uri&item=$uploadtree_pk";
      $row['content'] = "../";
      $row['id'] = $uploadInfo['uploadtree_pk'];
      $tableData[] = $row;
    }

    foreach ($Children as $child) {
      if (empty($child)) {
        continue;
      }

      /* Populate the output ($VF) - file list */
      /* id of each element is its uploadtree_pk */
      $cellContent = Isdir($child['ufile_mode']) ? $child['ufile_name'].'/' : $child['ufile_name'];

      if (Iscontainer($child['ufile_mode'])) {
        $uploadtree_pk = DirGetNonArtifact($child['uploadtree_pk'], $uploadtree_tablename);
        $LinkUri = "$Uri&item=" . $uploadtree_pk;
      } else {
        $LinkUri = Traceback_uri();
        $LinkUri .= "?mod=".$this->viewName."&agent=$Agent_pk&upload=$upload_pk&item=$child[uploadtree_pk]";
      }

      $row = [];
      $row['url'] = $LinkUri;
      $row['content'] = $cellContent;
      $row['id'] = $child['uploadtree_pk'];
      $tableData[] = $row;
    }
    return $tableData;
  }

  /**
    * @brief Check if passed element is a directory
    * @param int $Uploadtree_pk Uploadtree id of the element
    * @return boolean True if it is a directory, false otherwise
    */
  protected function isADirectory($Uploadtree_pk)
  {
    $row =  $this->uploadDao->getUploadEntry($Uploadtree_pk, $this->uploadtree_tablename);
    return IsDir($row['ufile_mode']);
  }

  /**
  * @brief Get sorting orders
  * @return string[][]
  */
  public function returnSortOrder ()
  {
    return array (
      array(0, "desc"),
      array(1, "desc"),
    );
  }

  /**
   * @brief Check for Advance Search
   * @param Coordinate $coord     Coordinates searched
   * @param string $revisionName  Revision searched
   * @param string $namespaceName Namespace searched
   * @param string $typeName      Type searched
   * @param string $providerName  Provider searched
   * @return boolean
   */
  public function checkAdvanceSearch($coord, $revisionName, $namespaceName,
    $typeName, $providerName)
  {
    if (! empty($revisionName) && $coord->getRevision() != $revisionName) {
      return false;
    }

    if (! empty($namespaceName) && $coord->getNamespace() != $namespaceName) {
      return false;
    }

    if (! empty($typeName) && $coord->getType() != $typeName) {
      return false;
    }

    if (! empty($providerName) && $coord->getProvider() != $providerName) {
      return false;
    }

    return true;
  }

  /**
   * Handle new selection from user, store it in DB and schedule the agent.
   * @param string $optionSelect Option selected by user
   * @return NULL|string null if new selection inserted, name otherwise.
   */
  private function handleNewSelection($optionSelect, $uploadId)
  {
    $patternName = null;
    $selection = Coordinate::generateFromString($optionSelect);

    $uploadAvailable = $this->spashtDao->getComponent($uploadId);
    if (! empty($uploadAvailable)) {
      $result = $this->spashtDao->alterComponentRevision($selection, $uploadId);
    } else {
      $result = $this->spashtDao->addComponentRevision($selection, $uploadId);
    }

    if ($result >= 0) {
      $patternName = null;

      $userId = Auth::getUserId();
      $groupId = Auth::getGroupId();
      $errorMessage = "";

      $plugin = plugin_find($this->agentName);
      $jobId = JobAddJob($userId, $groupId, $this->agentName, $uploadId);
      $rv = $plugin->AgentAdd($jobId, $uploadId, $errorMessage);
      if ($rv < 0) {
        $text = _("Scheduling of agent failed: ");
        $this->vars['message'] = $text . $errorMessage;
      } elseif ($rv == 0) {
        $text = _("Agent already scheduled");
        $this->vars['message'] = $text;
      } else {
        $text = _("Agent scheduled successfully.");
        $jobUrl = Traceback_uri() . "?mod=showjobs&upload=$uploadId";
        $this->vars['message'] = "$text <a href=$jobUrl>Show</a>";
      }
    } else {
      $patternName = $selection->getName();
    }
    return $patternName;
  }
}

$NewPlugin = new ui_spasht;
$NewPlugin->Initialize();
