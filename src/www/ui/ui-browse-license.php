<?php
/***********************************************************
 * Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.
 *               2014 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Proxy\UploadTreeProxy;

/**
 * \file ui-browse-license.php
 * \brief browse a directory to display all licenses in this directory
 */
define("TITLE_ui_license", _("License Browser"));

class ui_browse_license extends FO_Plugin
{
  private $uploadtree_tablename = "";
  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var ClearingDao */
  private $clearingDao;
  /** @var AgentDao */
  private $agentsDao;
  /** @var ClearingDecisionFilter */
  private $clearingFilter;
  /** @var LicenseMap */
  private $licenseProjector;
  /** @var array [uploadtree_id]=>cnt */
  private $filesThatShouldStillBeCleared;
  /** @var array [uploadtree_id]=>cnt */
  private $filesToBeCleared;

  function __construct()
  {
    $this->Name = "license";
    $this->Title = TITLE_ui_license;
    $this->Dependency = array("browse", "view");
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;
    parent::__construct();

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->licenseDao = $container->get('dao.license');
    $this->clearingDao = $container->get('dao.clearing');
    $this->agentsDao = $container->get('dao.agent');
    $this->clearingFilter = $container->get('businessrules.clearing_decision_filter');
  }

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array("upload", "item"));

    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (empty($Item) || empty($Upload))
      return;
    $viewLicenseURI = "view-license" . Traceback_parm_keep(array("show", "format", "page", "upload", "item"));
    $menuName = $this->Title;
    if (GetParm("mod", PARM_STRING) == $this->Name)
    {
      menu_insert("Browse::$menuName", 100);
    } else
    {
      $text = _("license histogram");
      menu_insert("Browse::$menuName", 100, $URI, $text);
      menu_insert("View::$menuName", 100, $viewLicenseURI, $text);
    }
  }

  /**
   * \brief This is called before the plugin is used.
   * \return true on success, false on failure.
   */
  function Initialize()
  {
    if ($this->State != PLUGIN_STATE_INVALID)
    {
      return (1);
    } // don't re-run

    if ($this->Name !== "") // Name must be defined
    {
      global $Plugins;
      $this->State = PLUGIN_STATE_VALID;
      array_push($Plugins, $this);
    }

    return ($this->State == PLUGIN_STATE_VALID);
  }

  function Output()
  {
    $uTime = microtime(true);
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }
    $upload = GetParm("upload", PARM_INTEGER);
    $UploadPerm = GetUploadPerm($upload);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      $this->vars['content'] = "<h2>$text<h2>";
      return;
    }

    $item = GetParm("item", PARM_INTEGER);
    $updateCache = GetParm("updcache", PARM_INTEGER);

    $this->vars['baseuri'] = Traceback_uri();
    $this->vars['uploadId'] = $upload;
    $this->vars['itemId'] = $item;

    list($CacheKey, $V) = $this->cleanGetArgs($updateCache);

    $this->uploadtree_tablename = GetUploadtreeTableName($upload);
    $this->vars['micromenu'] = Dir2Browse($this->Name, $item, NULL, $showBox = 0, "Browse", -1, '', '', $this->uploadtree_tablename);
    $this->vars['licenseArray'] = $this->licenseDao->getLicenseArray();

    $Cached = !empty($V);
    if (!$Cached && !empty($upload))
    {
      $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
      $left = $itemTreeBounds->getLeft();
      if (empty($left))
      {
        $text = _("Job unpack/adj2nest hasn't completed.");
        $this->vars['content'] = "<b>$text</b><p>";
        return;
      }
      $V .= $this->showUploadHist($itemTreeBounds);
    }

    $this->vars['content'] = $V;
    $Time = microtime(true) - $uTime;

    if ($Cached)
    {
      $text = _("This is cached view.");
      $text1 = _("Update now.");
      $this->vars['message'] = " <i>$text</i>   <a href=\"$_SERVER[REQUEST_URI]&updcache=1\"> $text1 </a>";
    }
    else
    {
      $text = _("Elapsed time: %.3f seconds");
      $this->vars['content'] .= sprintf("<hr/><small>$text</small>", $Time);
      if ($Time > 3.0)
        ReportCachePut($CacheKey, $V);
    }
    $this->vars['content'] .= js_url();
    
    return;
  }

  
  /**
   * \brief Given an $Uploadtree_pk, display:
   *   - The histogram for the directory BY LICENSE.
   *   - The file listing for the directory.
   */
  private function showUploadHist(ItemTreeBounds $itemTreeBounds)
  {
    global $SysConf;
    $groupId = $SysConf['auth']['GroupId'];
    $selectedAgentId = GetParm('agentId', PARM_INTEGER);
    $tag_pk = GetParm("tag", PARM_INTEGER);
        
    $uploadId = $itemTreeBounds->getUploadId();
    $scannerAgents = array('nomos', 'monk', 'ninka');
    $latestSucessfulAgents = array();
    $agentStatus = $this->createAgentStatus($scannerAgents, $uploadId, $latestSucessfulAgents);
    
    $selectedAgentIds = empty($selectedAgentId) ? $latestSucessfulAgents : $selectedAgentId;

    list($jsBlockLicenseHist, $VLic) = $this->createLicenseHistogram($itemTreeBounds->getItemId(), $tag_pk, $itemTreeBounds, $selectedAgentIds, $groupId);
    $VLic .= "\n" . $agentStatus;
    
    $UniqueTagArray = array();
    global $container;
    $this->licenseProjector = new LicenseMap($container->get('db.manager'),$groupId);
    list($ChildCount, $jsBlockDirlist) = $this->createFileListing($tag_pk, $itemTreeBounds, $UniqueTagArray, $selectedAgentId, $groupId);

    /***************************************
     * Problem: $ChildCount can be zero if you have a container that does not
     * unpack to a directory.  For example:
     * file.gz extracts to archive.txt that contains a license.
     * Same problem seen with .pdf and .Z files.
     * Solution: if $ChildCount == 0, then just view the license!
     *
     * $ChildCount can also be zero if the directory is empty.
     * **************************************/
    if ($ChildCount == 0)
    {
      header("Location: ?mod=view-license" . Traceback_parm_keep(array("upload", "item")));
    }

    /******  Filters  *******/
    /* Only display the filter pulldown if there are filters available
     * Currently, this is only tags.
     */
    /** @todo qualify with tag namespace to avoid tag name collisions.  * */
    /* turn $UniqueTagArray into key value pairs ($SelectData) for select list */
    $V = "";
    $SelectData = array();
    if (count($UniqueTagArray))
    {
      foreach ($UniqueTagArray as $UTA_row)
        $SelectData[$UTA_row['tag_pk']] = $UTA_row['tag_name'];
      $V .= "Tag filter";
      $myurl = "?mod=" . $this->Name . Traceback_parm_keep(array("upload", "item"));
      $Options = " id='filterselect' onchange=\"js_url(this.value, '$myurl&tag=')\"";
      $V .= Array2SingleSelectTag($SelectData, "tag_ns_pk", $tag_pk, true, false, $Options);
    }

    $dirlistPlaceHolder = "<table border=0 id='dirlist' style=\"margin-left: 9px;\" class='semibordered'></table>\n";
    /****** Combine VF and VLic ********/
    $V .= "<table border=0 cellpadding=2 width='100%'>\n";
    $V .= "<tr><td valign='top' width='25%'>$VLic</td><td valign='top' width='75%'>$dirlistPlaceHolder</td></tr>\n";
    $V .= "</table>\n";

    $this->vars['licenseUri'] = Traceback_uri() . "?mod=popup-license&rf=";
    $this->vars['bulkUri'] = Traceback_uri() . "?mod=popup-license";

    $V .= $jsBlockDirlist;
    $V .= $jsBlockLicenseHist;
    
    $V .= "<button onclick='loadBulkHistoryModal();'>" . _("Show bulk history") . "</button>";
    $V .= "<br/><span id='bulkIdResult' hidden></span>";
    
    return $V;
  }
  
  
  /**
   * @param $updcache
   * @return array
   */
  protected function cleanGetArgs($updcache)
  {
    /* Remove "updcache" from the GET args.
         * This way all the url's based on the input args won't be
         * polluted with updcache
         * Use Traceback_parm_keep to ensure that all parameters are in order */
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload", "item", "tag", "agent", "orderBy", "orderl", "orderc"));
    if ($updcache)
    {
      $_SERVER['REQUEST_URI'] = preg_replace("/&updcache=[0-9]*/", "", $_SERVER['REQUEST_URI']);
      unset($_GET['updcache']);
      $V = ReportCachePurgeByKey($CacheKey);
    }
    else
    {
      $V = ReportCacheGet($CacheKey);
    }
    return array($CacheKey, $V);
  }

  /**
   * @param $tagId
   * @param ItemTreeBounds $itemTreeBounds
   * @param $UniqueTagArray
   * @param $selectedAgentId
   * @internal param $uploadTreeId
   * @internal param $uploadId
   * @return array
   */
  private function createFileListing($tagId, ItemTreeBounds $itemTreeBounds, &$UniqueTagArray, $selectedAgentId, $groupId)
  {
    /** change the license result when selecting one version of nomos */
    $uploadId = $itemTreeBounds->getUploadId();
    $uploadTreeId = $itemTreeBounds->getItemId();
    
    /* Get ALL the items under this Uploadtree_pk */
    $Children = GetNonArtifactChildren($uploadTreeId, $itemTreeBounds->getUploadTreeTableName());

    /* Filter out Children that don't have tag */
    if (!empty($tagId))
    {
      TagFilter($Children, $tagId, $itemTreeBounds->getUploadTreeTableName());
    }
    if (empty($Children))
    {
      return array($ChildCount = 0, "");
    }
    
    $latestNomos = LatestAgentpk($uploadId, "nomos_ars");
    $newestNomos = $this->agentsDao->getCurrentAgentRef("nomos");
    $latestMonk = LatestAgentpk($uploadId, "monk_ars");
    $newestMonk = $this->agentsDao->getCurrentAgentRef("monk");
    $latestNinka = LatestAgentpk($uploadId, "ninka_ars");
    $newestNinka = $this->agentsDao->getCurrentAgentRef("ninka");
    $goodAgents = array('nomos' => array('name' => 'N', 'latest' => $latestNomos, 'newest' => $newestNomos, 'latestIsNewest' => $latestNomos == $newestNomos->getAgentId()),
        'monk' => array('name' => 'M', 'latest' => $latestMonk, 'newest' => $newestMonk, 'latestIsNewest' => $latestMonk == $newestMonk->getAgentId()),
        'ninka' => array('name' => 'Nk', 'latest' => $latestNinka, 'newest' => $newestNinka, 'latestIsNewest' => $latestNinka == $newestNinka->getAgentId()));

    /*******    File Listing     ************/
    if (!empty($selectedAgentId))
    {
      $agentName = $this->agentsDao->getAgentName($selectedAgentId);
      $selectedScanners = array($agentName=>$selectedAgentId);
    }
    else
    {
      foreach ($goodAgents as $agentName=>$goodAgent)
      {
        if ($goodAgent['latest'])
        {
          $selectedScanners[$agentName] = $goodAgent['latest'];
        }
      }
    }

    $pfileLicenses = array();
    foreach($selectedScanners as $agentName=>$agentId)
    {
      $licensePerPfile = $this->licenseDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $agentId);
      foreach ($licensePerPfile as $pfile => $licenseRow)
      {
        foreach ($licenseRow as $licId => $row)
        {
          $lic = $this->licenseProjector->getProjectedShortname($licId,$row['license_shortname']);
          $pfileLicenses[$pfile][$lic][$agentName] = $row;
        }
      }
    }

    global $Plugins;
    $ModLicView = &$Plugins[plugin_find_id("view-license")];
    $Uri = preg_replace("/&item=([0-9]*)/", "", Traceback());
    $tableData = array();

    $alreadyClearedUploadTreeView = new UploadTreeProxy($itemTreeBounds->getUploadId(),
        $options = array('skipThese' => "alreadyCleared",'groupId'=>$groupId),
        $itemTreeBounds->getUploadTreeTableName(),
        $viewName = 'already_cleared_uploadtree' . $itemTreeBounds->getUploadId());

    $alreadyClearedUploadTreeView->materialize();
    $this->filesThatShouldStillBeCleared = $alreadyClearedUploadTreeView->countMaskedNonArtifactChildren($itemTreeBounds->getItemId());
    $alreadyClearedUploadTreeView->unmaterialize();

    $noLicenseUploadTreeView = new UploadTreeProxy($itemTreeBounds->getUploadId(),
        $options = array('skipThese' => "noLicense"),
        $itemTreeBounds->getUploadTreeTableName(),
        $viewName = 'no_license_uploadtree' . $itemTreeBounds->getUploadId());
    $noLicenseUploadTreeView->materialize();
    $this->filesToBeCleared = $noLicenseUploadTreeView->countMaskedNonArtifactChildren($itemTreeBounds->getItemId());
    $noLicenseUploadTreeView->unmaterialize();

    $allDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId, true);
    $editedMappedLicenses = $this->clearingFilter->filterCurrentClearingDecisions($allDecisions);
    foreach ($Children as $child)
    {
      if (empty($child))
      {
        continue;
      }
      $tableData[] = $this->createFileDataRow($child, $uploadId, $selectedAgentId, $goodAgents, $pfileLicenses, $groupId, $editedMappedLicenses, $Uri, $ModLicView, $UniqueTagArray);
    }

    $VF = '<script>' . $this->renderTemplate('ui-browse-license_file-list.js.twig', array('aaData' => json_encode($tableData))) . '</script>';

    $ChildCount = count($tableData);
    return array($ChildCount, $VF);
  }


  /**
   * @param array $child
   * @param int $uploadId
   * @param int $selectedAgentId
   * @param AgentRef[] $goodAgents
   * @param array $pfileLicenses
   * @param int $groupId
   * @param ClearingDecision[][] $editedMappedLicenses
   * @param string $Uri
   * @param null|ClearingView $ModLicView
   * @param array $UniqueTagArray
   * @return array
   */
  private function createFileDataRow($child, $uploadId, $selectedAgentId, $goodAgents, $pfileLicenses, $groupId, $editedMappedLicenses, $Uri, $ModLicView, &$UniqueTagArray)
  {
    $fileId = $child['pfile_fk'];
    $childUploadTreeId = $child['uploadtree_pk'];

    if (!empty($fileId) && !empty($ModLicView))
    {
      $LinkUri = Traceback_uri();
      $LinkUri .= "?mod=view-license&upload=$uploadId&item=$childUploadTreeId";
      if ($selectedAgentId)
      {
        $LinkUri .= "&agentId=$selectedAgentId";
      }
    } else
    {
      $LinkUri = NULL;
    }

    /* Determine link for containers */
    $isContainer = Iscontainer($child['ufile_mode']);
    if ($isContainer)
    {
      $uploadtree_pk = DirGetNonArtifact($childUploadTreeId, $this->uploadtree_tablename);
      $LicUri = "$Uri&item=" . $uploadtree_pk;
      if ($selectedAgentId)
      {
        $LicUri .= "&agentId=$selectedAgentId";
      }
    } else
    {
      $LicUri = NULL;
    }

    /* Populate the output ($VF) - file list */
    /* id of each element is its uploadtree_pk */
    $fileName = $child['ufile_name'];
    if ($isContainer)
    {
      $fileName = "<a href='$LicUri'><span style='color: darkblue'> <b>$fileName</b> </span></a>";
    } else if (!empty($LinkUri))
    {
      $fileName = "<a href='$LinkUri'>$fileName</a>";
    }
    /* show licenses under file name */
    $childItemTreeBounds = // $this->uploadDao->getFileTreeBounds($childUploadTreeId, $this->uploadtree_tablename);
        new ItemTreeBounds($childUploadTreeId, $this->uploadtree_tablename, $child['upload_fk'], $child['lft'], $child['rgt']);
    if ($isContainer)
    {
      $licenseEntries = $this->licenseDao->getLicenseShortnamesContained($childItemTreeBounds, array());
      $editedLicenses = $this->clearingDao->getClearedLicenses($childItemTreeBounds, $groupId);
    } else
    {
      $licenseEntries = array();
      if (array_key_exists($fileId, $pfileLicenses))
      {
        foreach ($pfileLicenses[$fileId] as $shortName => $rfInfo)
        {
          $agentEntries = array();
          foreach ($rfInfo as $agent => $match)
          {
            $agentInfo = $goodAgents[$agent];
            $agentEntry = "<a href='?mod=view-license&upload=$child[upload_fk]&item=$childUploadTreeId&format=text&agentId=$match[agent_id]&licenseId=$match[license_id]#highlight'>" . $agentInfo['name'] . "</a>";

            if ($match['match_percentage'] > 0)
            {
              $agentEntry .= ": $match[match_percentage]%";
            }
            $agentEntries[] = $agentEntry;
          }
          $licenseEntries[] = $shortName . " [" . implode("][", $agentEntries) . "]";
        }
      }

      /** @var ClearingDecision $decision */
      if (false !== ($decision = $this->clearingFilter->getDecisionOf($editedMappedLicenses,$childUploadTreeId, $fileId)))
      {
        $editedLicenses = $decision->getPositiveLicenses();
      }
      else
      {
        $editedLicenses = array();
      }
    }
    
    $concludedLicenses = array();
    /** @var LicenseRef $licenseRef */
    foreach($editedLicenses as $licenseRef){
      $projectedId = $this->licenseProjector->getProjectedId($licenseRef->getId());
      $projectedName = $this->licenseProjector->getProjectedShortname($licenseRef->getId(),$licenseRef->getShortName());
      $concludedLicenses[$projectedId] = "<a href='?mod=view-license&upload=$uploadId&item=$childUploadTreeId&format=text'>" . $projectedName . "</a>";
    }

    $editedLicenseList = implode(', ', $concludedLicenses);
    $licenseList = implode(', ', $licenseEntries);

    $fileListLinks = FileListLinks($uploadId, $childUploadTreeId, 0, $fileId, true, $UniqueTagArray, $this->uploadtree_tablename);

    $getTextEditUser = _("Edit");
    $getTextEditBulk = _("Bulk");
    $fileListLinks .= "[<a onclick='openUserModal($childUploadTreeId)' >$getTextEditUser</a>]";
    $fileListLinks .= "[<a onclick='openBulkModal($childUploadTreeId)' >$getTextEditBulk</a>]";

    // $filesThatShouldStillBeCleared = $this->uploadDao->getContainingFileCount($childItemTreeBounds, $this->alreadyClearedUploadTreeView);
    $filesThatShouldStillBeCleared = array_key_exists($childItemTreeBounds->getItemId()
        , $this->filesThatShouldStillBeCleared) ? $this->filesThatShouldStillBeCleared[$childItemTreeBounds->getItemId()] : 0;

    // $filesToBeCleared = $this->uploadDao->getContainingFileCount($childItemTreeBounds, $this->noLicenseUploadTreeView);
    $filesToBeCleared = array_key_exists($childItemTreeBounds->getItemId()
        , $this->filesToBeCleared) ? $this->filesToBeCleared[$childItemTreeBounds->getItemId()] : 0;

    $filesCleared = $filesToBeCleared - $filesThatShouldStillBeCleared;

    $img = ($filesCleared == $filesToBeCleared) ? 'green' : 'red';

    return array($fileName, $licenseList, $editedLicenseList, $img, "$filesCleared/$filesToBeCleared", $fileListLinks);
  }


  /**
   * @param $uploadTreeId
   * @param $tagId
   * @param ItemTreeBounds $itemTreeBounds
   * @param int|int[] $agentIds
   * @param ClearingDecision []
   * @return string
   */
  private function createLicenseHistogram($uploadTreeId, $tagId, ItemTreeBounds $itemTreeBounds, $agentIds, $groupId)
  {
    if(array_key_exists('noUploadHist',$this->vars))
    {
      return array('','');
    }
    $fileCount = $this->uploadDao->countPlainFiles($itemTreeBounds);
    $licenseHistogram = $this->licenseDao->getLicenseHistogram($itemTreeBounds, $orderStmt = "", $agentIds);
    $editedLicensesHist = $this->clearingDao->getClearedLicenseMultiplicities($itemTreeBounds, $groupId);

    /* Write license histogram to $VLic  */
    $rendered = "<table border=0 class='semibordered' id='lichistogram'></table>\n";
    list($jsBlockLicenseHist, $uniqueLicenseCount, $totalScannerLicenseCount, $scannerUniqueLicenseCount,
        $editedTotalLicenseCount, $editedUniqueLicenseCount)
        = $this->createLicenseHistogramJSarray($licenseHistogram, $editedLicensesHist, $uploadTreeId, $tagId);
    $noScannerLicenseFoundCount = array_key_exists("No_license_found", $licenseHistogram) ? $licenseHistogram["No_license_found"]['count'] : 0;
    $editedNoLicenseFoundCount = array_key_exists("No_license_found", $editedLicensesHist) ? $editedLicensesHist["No_license_found"]['count'] : 0;

    $rendered .= "<br/><br/>";
    $rendered .= _("Hint: Click on the license name to search for where the license is found in the file listing.") . "<br/><br/>\n";
    
    $vars = array('uniqueLicenseCount'=>$uniqueLicenseCount,
        'fileCount'=>$fileCount,
        'scannerUniqueLicenseCount'=>$scannerUniqueLicenseCount,
        'editedUniqueLicenseCount'=>$editedUniqueLicenseCount,
        'scannerLicenseCount'=> $totalScannerLicenseCount-$noScannerLicenseFoundCount,
        'editedLicenseCount'=> $editedTotalLicenseCount-$editedNoLicenseFoundCount,
        'noScannerLicenseFoundCount'=>$noScannerLicenseFoundCount,
        'editedNoLicenseFoundCount'=>$editedNoLicenseFoundCount);
    $rendered .= $this->renderTemplate('browse_license-summary.html.twig', $vars);
    
    return array($jsBlockLicenseHist, $rendered);
  }


  /**
   * @param $scannerAgents
   * @param $uploadId
   * @return array
   */
  private function createAgentStatus($scannerAgents, $uploadId, &$latestSucessfulAgentIds)
  {
    $output = "";
    $successfulAgents = array();
    foreach ($scannerAgents as $agentName)
    {
      $agentHasArsTable = $this->agentsDao->arsTableExists($agentName);
      if (empty($agentHasArsTable))
      {
        continue;
      }
      $output .= '<p>'.$this->renderAgentStatusWithSideEffect($agentName,$uploadId,$successfulAgents,$latestSucessfulAgentIds).'</p>';
    }

    if (empty($successfulAgents))
    {
      $this->vars['noUploadHist'] = TRUE;
      return _("There is no successful scan for this upload, please schedule one license scanner on this upload. ").$output;
    }

    $agentMap = array();
    foreach ($successfulAgents as $agent)
    {
      $agentMap[$agent->getAgentId()] = $agent->getAgentName() . " " . $agent->getAgentRevision();
    }
    if (count($successfulAgents) > 1)
    {
      $agentMap[0] = _('Latest run of all available agents');
    }
    $vars = array('agentId' => GetParm('agentId', PARM_INTEGER),
                  'agentShowURI' => Traceback_uri() . '?mod=' . Traceback_parm() . '&updcache=1',
                  'agentMap' => $agentMap);
    $header =  $this->renderTemplate('browse_license-agent_selector.html.twig', $vars);
    return ($header . $output);
  }

  public function getTemplateName()
  {
    return "browse_license.html.twig";
  }

  private function renderAgentStatusWithSideEffect($agentName, $uploadId, &$allSuccessfulAgents, &$latestSucessfulAgentIds)
  {
    $successfulAgents = $this->agentsDao->getSuccessfulAgentEntries($agentName, $uploadId);
    $vars['successfulAgents'] = $successfulAgents;
    $vars['uploadId'] = $uploadId;
    $vars['agentName'] = $agentName;
   
    if (!count($successfulAgents))
    {
      $vars['isAgentRunning'] = count($this->agentsDao->getRunningAgentIds($uploadId, $agentName)) > 0;
      return $this->renderTemplate('browse_license-agent.html.twig', $vars);
    }  
    
    $latestSuccessfulAgent = $successfulAgents[0];
    $currentAgentRef = $this->agentsDao->getCurrentAgentRef($agentName);
    $vars['currentAgentId'] = $currentAgentRef->getAgentId();
    $vars['currentAgentRev'] = $currentAgentRef->getAgentRevision();
    if ($currentAgentRef->getAgentId() != $latestSuccessfulAgent['agent_id'])
    {
      $runningJobs = $this->agentsDao->getRunningAgentIds($uploadId, $agentName);
      $vars['isAgentRunning'] = in_array($currentAgentRef->getAgentId(), $runningJobs);
    }

    foreach ($successfulAgents as $agent)
    {
      $allSuccessfulAgents[] = new AgentRef($agent['agent_id'], $agent['agent_name'], $agent['agent_rev']);
    }
    $latestSucessfulAgentIds[] = $latestSuccessfulAgent['agent_id'];
    return $this->renderTemplate('browse_license-agent.html.twig', $vars);
  }
  
  /**
   * @param array $scannerLics
   * @param array $editedLics
   * @param $uploadTreeId
   * @param $tagId
   * @return array
   * @todo convert to template
   */
  protected function createLicenseHistogramJSarray($scannerLics, $editedLics, $uploadTreeId, $tagId)
  {
    $agentId = GetParm('agentId', PARM_INTEGER);

    $allScannerLicenseNames = array_keys($scannerLics);
    $allEditedLicenseNames = array_keys($editedLics);

    $allLicNames = array_unique(array_merge($allScannerLicenseNames, $allEditedLicenseNames));

    $uniqueLicenseCount = 0;

    $totalScannerLicenseCount = 0;
    $scannerUniqueLicenseCount = count ( array_keys($scannerLics) );

    $editedTotalLicenseCount = 0;
    $editedUniqueLicenseCount = 0;

    $licListUri = Traceback_uri()."?mod=license_list_files&item=$uploadTreeId";
    if ($tagId)
    {
      $licListUri .= "&tag=$tagId";
    }
    if ($agentId)
    {
      $licListUri .= "&agentId=$agentId";
    }

    $tableData = array();
    foreach ($allLicNames as $licenseShortName)
    {
      $uniqueLicenseCount++;
      $count = 0;
      if (array_key_exists($licenseShortName, $scannerLics))
      {
        $count = $scannerLics[$licenseShortName]['count'];
      }
      $editedCount = 0;
      if (array_key_exists($licenseShortName, $editedLics))
      {
        $editedCount = $editedLics[$licenseShortName];
        $editedUniqueLicenseCount++;
      }

      $totalScannerLicenseCount += $count;
      $editedTotalLicenseCount += $editedCount;

      $scannerCountLink = ($count > 0) ? "<a href='$licListUri&lic=" . urlencode($licenseShortName) . "'>$count</a>": "0";
      $editedLink = ($editedCount > 0) ? $editedCount : "0";

      $tableData[] = array($scannerCountLink, $editedLink, $licenseShortName);
    }

    $js = $this->renderTemplate('browse_license-lic_hist.js.twig', array('tableDataJson'=>json_encode($tableData)));
    $rendered = "<script>$js</script>";

    return array($rendered, $uniqueLicenseCount, $totalScannerLicenseCount, $scannerUniqueLicenseCount, $editedTotalLicenseCount, $editedUniqueLicenseCount);
  }

}

$NewPlugin = new ui_browse_license;
$NewPlugin->Initialize();
