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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\ClearingDecisionFilter;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \file ui-browse-license.php
 * \brief browse a directory to display all licenses in this directory
 */

class ui_browse_license extends DefaultPlugin
{
  const NAME = "license";
  
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
  /** @var array */
  protected $agentNames = array('nomos' => 'N', 'monk' => 'M', 'ninka' => 'Nk');
  
  protected $vars = array();

  
  public function __construct() {
    parent::__construct(self::NAME, array(
        self::TITLE => _("License Browser"),
        self::DEPENDENCIES => array("browse", "view"),
        self::PERMISSION => Auth::PERM_READ
    ));

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
    if (GetParm("mod", PARM_STRING) == self::NAME)
    {
      menu_insert("Browse::$menuName", 100);
    }
    else
    {
      $text = _("license histogram");
      menu_insert("Browse::$menuName", 100, $URI, $text);
      menu_insert("View::$menuName", 100, $viewLicenseURI, $text);
    }
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request) {
    $upload = intval($request->get("upload"));
    $groupId = Auth::getGroupId();
    if (!$this->uploadDao->isAccessible($upload, $groupId)) {
      return $this->flushContent(_("Permission Denied"));
    }
    $uTime = microtime(true);

    $item = intval($request->get("item"));
    $updateCache = GetParm("updcache", PARM_INTEGER);

    $vars['baseuri'] = Traceback_uri();
    $vars['uploadId'] = $upload;
    $vars['itemId'] = $item;

    list($CacheKey, $V) = $this->cleanGetArgs($updateCache);
    /** empty cache */
    $this->uploadtree_tablename = GetUploadtreeTableName($upload);
    $vars['micromenu'] = Dir2Browse($this->Name, $item, NULL, $showBox = 0, "Browse", -1, '', '', $this->uploadtree_tablename);
    $vars['licenseArray'] = $this->licenseDao->getLicenseArray();

    $Cached = false;
    if (!$Cached && !empty($upload))
    {
      $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
      $left = $itemTreeBounds->getLeft();
      if (empty($left))
      {
        return $this->flushContent(_("Job unpack/adj2nest hasn't completed."));
      }
      $histVars = $this->showUploadHist($itemTreeBounds);
      if(is_a($histVars, 'Symfony\\Component\\HttpFoundation\\RedirectResponse'))
      {
        return 0;//$histVars;
      }
      $vars = array_merge($vars, $histVars);
    }
    
    
    if($request->get('rtn')==='files')
    {
      return new Symfony\Component\HttpFoundation\JsonResponse(array(
            'sEcho' => intval($_GET['sEcho']),
            'aaData' => $vars['fileData'],
            'iTotalRecords' => $vars['iTotalRecords'],
            'iTotalDisplayRecords' => $vars['iTotalDisplayRecords']
          ) );

    }
    
    $vars['content'] = $V;
    $vars['content'] .= js_url();

    return $this->render("browse.html.twig",$this->mergeWithDefault($vars));
  }


  /**
   * \brief Given an $Uploadtree_pk, display:
   *   - The histogram for the directory BY LICENSE.
   *   - The file listing for the directory.
   */
  private function showUploadHist(ItemTreeBounds $itemTreeBounds)
  {
    $groupId = Auth::getGroupId();
    $selectedAgentId = GetParm('agentId', PARM_INTEGER);
    $tag_pk = GetParm("tag", PARM_INTEGER);

    $uploadId = $itemTreeBounds->getUploadId();
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->agentDao, $uploadId);
    $scannerVars = $scanJobProxy->createAgentStatus($scannerAgents);
    $agentMap = $scanJobProxy->getAgentMap();
    
    $vars = array('agentId' => GetParm('agentId', PARM_INTEGER),
                  'agentShowURI' => Traceback_uri() . '?mod=' . Traceback_parm() . '&updcache=1',
                  'agentMap' => $agentMap,
                  'scanners'=>$scannerVars);

    $selectedAgentIds = empty($selectedAgentId) ? $scanJobProxy->getLatestSuccessfulAgentIds() : $selectedAgentId;
    
    if(!empty($agentMap))
    {
      $licVars = $this->createLicenseHistogram($itemTreeBounds->getItemId(), $tag_pk, $itemTreeBounds, $selectedAgentIds, $groupId);
      $vars = array_merge($vars, $licVars);
    }

    $UniqueTagArray = array();
    global $container;
    $this->licenseProjector = new LicenseMap($container->get('db.manager'),$groupId,LicenseMap::CONCLUSION,true);
    $dirVars = $this->createFileListing($tag_pk, $itemTreeBounds, $UniqueTagArray, $selectedAgentId, $groupId, $scanJobProxy);
    $childCount = $dirVars['iTotalRecords'];
    /***************************************
     * Problem: $ChildCount can be zero if you have a container that does not
     * unpack to a directory.  For example:
     * file.gz extracts to archive.txt that contains a license.
     * Same problem seen with .pdf and .Z files.
     * Solution: if $ChildCount == 0, then just view the license!
     *
     * $ChildCount can also be zero if the directory is empty.
     * **************************************/
    if ($childCount == 0)
    {
      return new RedirectResponse("?mod=view-license" . Traceback_parm_keep(array("upload", "item")));
    }

    /******  Filters  *******/
    /* Only display the filter pulldown if there are filters available
     * Currently, this is only tags.
     */
    /** @todo qualify with tag namespace to avoid tag name collisions.  * */
    /* turn $UniqueTagArray into key value pairs ($SelectData) for select list */
    $V = '';
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

    $vars['licenseUri'] = Traceback_uri() . "?mod=popup-license&rf=";
    $vars['bulkUri'] = Traceback_uri() . "?mod=popup-license";

    $vars = array_merge($vars, $dirVars);

    $vars['content'] = $V;
    return $vars;
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
    $CacheKey = "?mod=" . $this->Name . Traceback_parm_keep(array("upload", "item", "tag", "agent", "orderBy", "orderl", "orderc", "flatten"));
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
    $isFlat = isset($_GET['flatten']);
    
    $vars['isFlat'] = $isFlat;
    
    $columnNamesInDatabase = array($isFlat?'ufile_name':'lft');
    $defaultOrder = array(array(0, "asc"));
    $orderString = $this->getObject('utils.data_tables_utility')->getSortingString($_GET, $columnNamesInDatabase, $defaultOrder);
    
    $vars['iTotalRecords'] = count($this->uploadDao->getNonArtifactDescendants($itemTreeBounds, $isFlat));

    $searchMap = array();
    foreach(explode(' ',GetParm('sSearch', PARM_RAW)) as $pair)
    {
      $a = explode(':',$pair);
      if(count($a)==1)
        $searchMap['any'] = $pair;
      else
        $searchMap[$a[0]] = $a[1];
    }
    
    if(array_key_exists('ext', $searchMap) and strlen($searchMap['ext'])>=1)
    {
      $dbM = $this->getObject('db.manager');
      $dbD = $dbM->getDriver();
      $ext = $dbD->escapeString($searchMap['ext']);
      $orderString = " AND ufile_name ilike '%.$ext' $orderString";
    }

    $vars['iTotalDisplayRecords'] = count($this->uploadDao->getNonArtifactDescendants($itemTreeBounds, $isFlat, $orderString));
    
    
    
    $offset = GetParm('iDisplayStart', PARM_INTEGER);
    $limit = GetParm('iDisplayLength', PARM_INTEGER);
    if($offset)
      $orderString .= " OFFSET $offset";
    if($limit)
      $orderString .= " LIMIT $limit";
    

    /* Get ALL the items under this Uploadtree_pk */
    $Children = $this->uploadDao->getNonArtifactDescendants($itemTreeBounds, $isFlat, $orderString);
    
    /* Filter out Children that don't have tag */
    if (!empty($tagId))
    {
      TagFilter($Children, $tagId, $itemTreeBounds->getUploadTreeTableName());
    }
    if (empty($Children))
    {
      $vars['fileData'] = array();
      return $vars;
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

    $pfileLicenses = array();
    foreach($selectedScanners as $agentName=>$agentId)
    {
      $licensePerPfile = $this->licenseDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $agentId, $isFlat);
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
    $tableData = array();

    $alreadyClearedUploadTreeView = new UploadTreeProxy($itemTreeBounds->getUploadId(),
        $options = array(UploadTreeProxy::OPT_SKIP_THESE => "alreadyCleared",
                         UploadTreeProxy::OPT_ITEM_FILTER => "AND (lft BETWEEN ".$itemTreeBounds->getLeft()." AND ".$itemTreeBounds->getRight().")",
                         UploadTreeProxy::OPT_GROUP_ID => $groupId),
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
        $options = array(UploadTreeProxy::OPT_SKIP_THESE => "noLicense",
                         UploadTreeProxy::OPT_ITEM_FILTER => "AND (lft BETWEEN ".$itemTreeBounds->getLeft()." AND ".$itemTreeBounds->getRight().")",
                         UploadTreeProxy::OPT_GROUP_ID => $groupId),
        $itemTreeBounds->getUploadTreeTableName(),
        $viewName = 'no_license_uploadtree' . $itemTreeBounds->getUploadId());
    $noLicenseUploadTreeView->materialize();
    if (!$isFlat)
    {
      $this->filesToBeCleared = $noLicenseUploadTreeView->countMaskedNonArtifactChildren($itemTreeBounds->getItemId());
    }
    else
    {
      $this->filesToBeCleared = $noLicenseUploadTreeView->getNonArtifactDescendants($itemTreeBounds);
    }
    $noLicenseUploadTreeView->unmaterialize();

    $allDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId, $isFlat);
    $editedMappedLicenses = $this->clearingFilter->filterCurrentClearingDecisions($allDecisions);
    $Uri = Traceback_uri().'?mod='.$this->Name.Traceback_parm_keep(array('upload','folder','show','item')); //  preg_replace("/&item=([0-9]*)/", "", Traceback());
            
    $fileSwitch = $isFlat ? $Uri : $Uri."&flatten=yes";
    foreach ($Children as $child)
    {
      if (empty($child))
      {
        continue;
      }
      $tableData[] = $this->createFileDataRow($child, $uploadId, $selectedAgentId, $pfileLicenses, $groupId, $editedMappedLicenses, $Uri, $ModLicView, $UniqueTagArray, $isFlat);
    }
    
    $vars['fileSwitch'] = $fileSwitch;
    $vars['fileData'] = $tableData;

    return $vars;
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
      $concludedLicenses[$projectedId] = $projectedName;
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
   * @return array
   */
  private function createLicenseHistogram($uploadTreeId, $tagId, ItemTreeBounds $itemTreeBounds, $agentIds, $groupId)
  {
    $fileCount = $this->uploadDao->countPlainFiles($itemTreeBounds);
    $licenseHistogram = $this->licenseDao->getLicenseHistogram($itemTreeBounds, $agentIds);
    $editedLicensesHist = $this->clearingDao->getClearedLicenseMultiplicities($itemTreeBounds, $groupId);

    $agentId = GetParm('agentId', PARM_INTEGER);
    $licListUri = Traceback_uri()."?mod=license_list_files&item=$uploadTreeId";
    if ($tagId)
    {
      $licListUri .= "&tag=$tagId";
    }
    if ($agentId)
    {
      $licListUri .= "&agentId=$agentId";
    }
    
    /* Write license histogram to $VLic  */
    list($tableData, $totalScannerLicenseCount, $editedTotalLicenseCount)
        = $this->createLicenseHistogramJSarray($licenseHistogram, $editedLicensesHist, $licListUri);
    
    $uniqueLicenseCount = count($tableData);
    $scannerUniqueLicenseCount = count( $licenseHistogram );
    $editedUniqueLicenseCount = count($editedLicensesHist);
    $noScannerLicenseFoundCount = array_key_exists("No_license_found", $licenseHistogram) ? $licenseHistogram["No_license_found"]['count'] : 0;
    $editedNoLicenseFoundCount = array_key_exists("No_license_found", $editedLicensesHist) ? $editedLicensesHist["No_license_found"]['count'] : 0;

    $vars = array('tableDataJson'=>json_encode($tableData),
        'uniqueLicenseCount'=>$uniqueLicenseCount,
        'fileCount'=>$fileCount,
        'scannerUniqueLicenseCount'=>$scannerUniqueLicenseCount,
        'editedUniqueLicenseCount'=>$editedUniqueLicenseCount,
        'scannerLicenseCount'=> $totalScannerLicenseCount-$noScannerLicenseFoundCount,
        'editedLicenseCount'=> $editedTotalLicenseCount-$editedNoLicenseFoundCount,
        'noScannerLicenseFoundCount'=>$noScannerLicenseFoundCount,
        'editedNoLicenseFoundCount'=>$editedNoLicenseFoundCount);

    return $vars;
  }

  /**
   * @param array $scannerLics
   * @param array $editedLics
   * @param string
   * @return array
   * @todo convert to template
   */
  protected function createLicenseHistogramJSarray($scannerLics, $editedLics, $licListUri)
  {
    $allScannerLicenseNames = array_keys($scannerLics);
    $allEditedLicenseNames = array_keys($editedLics);

    $allLicNames = array_unique(array_merge($allScannerLicenseNames, $allEditedLicenseNames));

    $totalScannerLicenseCount = 0;
    $editedTotalLicenseCount = 0;

    $tableData = array();
    foreach ($allLicNames as $licenseShortName)
    {
      $count = 0;
      if (array_key_exists($licenseShortName, $scannerLics))
      {
        $count = $scannerLics[$licenseShortName]['unique'];
      }
      $editedCount = array_key_exists($licenseShortName, $editedLics) ? $editedLics[$licenseShortName] : 0;

      $totalScannerLicenseCount += $count;
      $editedTotalLicenseCount += $editedCount;

      $scannerCountLink = ($count > 0) ? "<a href='$licListUri&lic=" . urlencode($licenseShortName) . "'>$count</a>": "0";
      $editedLink = ($editedCount > 0) ? $editedCount : "0";

      $tableData[] = array($scannerCountLink, $editedLink, $licenseShortName);
    }

    return array($tableData, $totalScannerLicenseCount, $editedTotalLicenseCount);
  }

  /**
   * @param string $templateName
   * @param array $vars
   * @return string
   */
  public function renderString($templateName, $vars = null)
  {
    return $this->renderer->loadTemplate($templateName)->render($vars ?: $this->vars);
  }  
}

register_plugin(new ui_browse_license());
