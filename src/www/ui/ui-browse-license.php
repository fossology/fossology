<?php
/***********************************************************
 * Copyright (C) 2008-2015 Hewlett-Packard Development Company, L.P.
 *               2014-2015 Siemens AG
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
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\Lib\Proxy\ScanJobProxy;

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
  private $agentDao;
  /** @var ClearingDecisionFilter */
  private $clearingFilter;
  /** @var LicenseMap */
  private $licenseProjector;
  /** @var array [uploadtree_id]=>cnt */
  private $filesThatShouldStillBeCleared;
  /** @var array [uploadtree_id]=>cnt */
  private $filesToBeCleared;
  
  protected $agentNames = array('nomos' => 'N', 'monk' => 'M', 'ninka' => 'Nk');

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
    $this->agentDao = $container->get('dao.agent');
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
    if ($UploadPerm < Auth::PERM_READ)
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
    $groupId = $SysConf['auth'][Auth::GROUP_ID];
    $selectedAgentId = GetParm('agentId', PARM_INTEGER);
    $tag_pk = GetParm("tag", PARM_INTEGER);

    $uploadId = $itemTreeBounds->getUploadId();
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->agentDao, $uploadId);
    $scannerVars = $scanJobProxy->createAgentStatus($scannerAgents);
    $agentMap = $scanJobProxy->getAgentMap();
    if (empty($agentMap))
    {
      $this->vars['noUploadHist'] = TRUE;
    }
    $vars = array('agentId' => GetParm('agentId', PARM_INTEGER),
                  'agentShowURI' => Traceback_uri() . '?mod=' . Traceback_parm() . '&updcache=1',
                  'agentMap' => $agentMap,
                  'scanners'=>$scannerVars);
    $agentStatus = $this->renderString('browse_license-agent_selector.html.twig', $vars);

    $selectedAgentIds = empty($selectedAgentId) ? $scanJobProxy->getLatestSuccessfulAgentIds() : $selectedAgentId;
    list($jsBlockLicenseHist, $VLic) = $this->createLicenseHistogram($itemTreeBounds->getItemId(), $tag_pk, $itemTreeBounds, $selectedAgentIds, $groupId);
    $VLic .= "\n" . $agentStatus;
    
    $UniqueTagArray = array();
    global $container;
    $this->licenseProjector = new LicenseMap($container->get('db.manager'),$groupId,LicenseMap::CONCLUSION,true);
    list($ChildCount, $jsBlockDirlist) = $this->createFileListing($tag_pk, $itemTreeBounds, $UniqueTagArray, $selectedAgentId, $groupId, $scanJobProxy);

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
      return new \Symfony\Component\HttpFoundation\RedirectResponse("?mod=view-license" . Traceback_parm_keep(array("upload", "item")));
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
   * @param int $groupId
   * @param ScanJobProxy $scanJobProxy
   * @return array
   */
  private function createFileListing($tagId, ItemTreeBounds $itemTreeBounds, &$UniqueTagArray, $selectedAgentId, $groupId, $scanJobProxy)
  {
    /** change the license result when selecting one version of nomos */
    $uploadId = $itemTreeBounds->getUploadId();
    $uploadTreeId = $itemTreeBounds->getItemId();
    $isFlat = isset($_GET['flatten']);
    
    /* Get ALL the items under this Uploadtree_pk */
    if (!$isFlat)
    {
      $Children = GetNonArtifactChildren($uploadTreeId, $itemTreeBounds->getUploadTreeTableName());
    }
    else
    {
      $Children = $this->uploadDao->getNonArtifactDescendants($itemTreeBounds);
    }

    /* Filter out Children that don't have tag */
    if (!empty($tagId))
    {
      TagFilter($Children, $tagId, $itemTreeBounds->getUploadTreeTableName());
    }
    if (empty($Children))
    {
      return array($ChildCount = 0, "");
    }
    
    /*******    File Listing     ************/
    if (!empty($selectedAgentId))
    {
      $agentName = $this->agentDao->getAgentName($selectedAgentId);
      $selectedScanners = array($agentName=>$selectedAgentId);
    }
    else
    {
      $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    }
    
    $childrenList = array();
    foreach($Children as $child)
    {
      $childrenList[] = $child['uploadtree_pk'];
    }

    $pfileLicenses = array();
    foreach($selectedScanners as $agentName=>$agentId)
    {
      $licensePerPfile = $this->licenseDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $agentId, $childrenList);
      foreach ($licensePerPfile as $pfile => $licenseRow)
      {
        foreach ($licenseRow as $licId => $row)
        {
          $lic = $this->licenseProjector->getProjectedShortname($licId);
          $pfileLicenses[$pfile][$lic][$agentName] = $row;
        }
      }
    }

    global $Plugins;
    $ModLicView = &$Plugins[plugin_find_id("view-license")];
    $Uri = preg_replace("/&item=([0-9]*)/", "", Traceback());
    $tableData = array();

    $alreadyClearedUploadTreeView = new UploadTreeProxy($itemTreeBounds->getUploadId(),
        $options = array(UploadTreeProxy::OPT_SKIP_THESE => "alreadyCleared", UploadTreeProxy::OPT_GROUP_ID=>$groupId),
        $itemTreeBounds->getUploadTreeTableName(),
        $viewName = 'already_cleared_uploadtree' . $itemTreeBounds->getUploadId());

    $alreadyClearedUploadTreeView->materialize();
    if (!$isFlat)
    {
      $this->filesThatShouldStillBeCleared = $alreadyClearedUploadTreeView->countMaskedNonArtifactChildren($itemTreeBounds->getItemId());
    }
    else
    {
      $this->filesThatShouldStillBeCleared = $alreadyClearedUploadTreeView->getNonArtifactDescendants($itemTreeBounds);
    }
    $alreadyClearedUploadTreeView->unmaterialize();

    $noLicenseUploadTreeView = new UploadTreeProxy($itemTreeBounds->getUploadId(),
        $options = array(UploadTreeProxy::OPT_SKIP_THESE=>"noLicense", UploadTreeProxy::OPT_GROUP_ID=>$groupId),
        $itemTreeBounds->getUploadTreeTableName(),
        $viewName = 'no_license_uploadtree' . $itemTreeBounds->getUploadId());
    $noLicenseUploadTreeView->materialize();
    if (!isset($_GET['flatten']))
    {
      $this->filesToBeCleared = $noLicenseUploadTreeView->countMaskedNonArtifactChildren($itemTreeBounds->getItemId());
    }
    else
    {
      $this->filesToBeCleared = $noLicenseUploadTreeView->getNonArtifactDescendants($itemTreeBounds);
    }
    $noLicenseUploadTreeView->unmaterialize();

    $allDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId, true);
    $editedMappedLicenses = $this->clearingFilter->filterCurrentClearingDecisions($allDecisions);
    foreach ($Children as $child)
    {
      if (empty($child))
      {
        continue;
      }
      $tableData[] = $this->createFileDataRow($child, $uploadId, $selectedAgentId, $pfileLicenses, $groupId, $editedMappedLicenses, $Uri, $ModLicView, $UniqueTagArray, $isFlat);
    }
    
    $fileSwitch = $isFlat ?
            Traceback_uri().'?mod='.$this->Name.Traceback_parm_keep(array('upload','folder','show','item')) :
            Traceback()."&flatten=yes";
    $vars = array('aaData' => json_encode($tableData), 'isFlat'=>$isFlat, 'fileSwitch'=>$fileSwitch);
    $VF = '<script>' . $this->renderString('ui-browse-license_file-list.js.twig', $vars) . '</script>';

    $ChildCount = count($tableData);
    return array($ChildCount, $VF);
  }


  /**
   * @param array $child
   * @param int $uploadId
   * @param int $selectedAgentId
   * @param array $pfileLicenses
   * @param int $groupId
   * @param ClearingDecision[][] $editedMappedLicenses
   * @param string $Uri
   * @param null|ClearingView $ModLicView
   * @param array $UniqueTagArray
   * @param boolean $isFlat
   * @return array
   */
  private function createFileDataRow($child, $uploadId, $selectedAgentId, $pfileLicenses, $groupId, $editedMappedLicenses, $Uri, $ModLicView, &$UniqueTagArray, $isFlat)
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
      $LinkUri = null;
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
      $LicUri = null;
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
            $agentName = $this->agentNames[$agent];
            $agentEntry = "<a href='?mod=view-license&upload=$child[upload_fk]&item=$childUploadTreeId&format=text&agentId=$match[agent_id]&licenseId=$match[license_id]#highlight'>" . $agentName . "</a>";

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

    $fileListLinks = FileListLinks($uploadId, $childUploadTreeId, 0, $fileId, true, $UniqueTagArray, $this->uploadtree_tablename, !$isFlat);

    $getTextEditUser = _("Edit");
    $fileListLinks .= "[<a href='#' onclick='openUserModal($childUploadTreeId)' >$getTextEditUser</a>]";

    if($isContainer)
    {
      $getTextEditBulk = _("Bulk");
      $fileListLinks .= "[<a href='#' onclick='openBulkModal($childUploadTreeId)' >$getTextEditBulk</a>]";
    }

    $filesThatShouldStillBeCleared = array_key_exists($childItemTreeBounds->getItemId()
        , $this->filesThatShouldStillBeCleared) ? $this->filesThatShouldStillBeCleared[$childItemTreeBounds->getItemId()] : 0;

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
    $rendered .= $this->renderString('browse_license-summary.html.twig', $vars);
    
    return array($jsBlockLicenseHist, $rendered);
  }

  
  public function getTemplateName()
  {
    return "browse_license.html.twig";
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

    $uniqueLicenseCount = count($allLicNames);

    $totalScannerLicenseCount = 0;
    $scannerUniqueLicenseCount = count( array_keys($scannerLics) );

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
      $count = 0;
      if (array_key_exists($licenseShortName, $scannerLics))
      {
        $count = $scannerLics[$licenseShortName]['unique'];
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

    $js = $this->renderString('browse_license-lic_hist.js.twig', array('tableDataJson'=>json_encode($tableData)));
    $rendered = "<script>$js</script>";

    return array($rendered, $uniqueLicenseCount, $totalScannerLicenseCount, $scannerUniqueLicenseCount, $editedTotalLicenseCount, $editedUniqueLicenseCount);
  }

}

$NewPlugin = new ui_browse_license;
$NewPlugin->Initialize();
