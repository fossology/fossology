<?php
/*
 SPDX-FileCopyrightText: © 2008-2015 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2017, 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use ClearingView;
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
use Fossology\Lib\Data\DecisionTypes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Fossology\Lib\Data\AgentRef;

/**
 * \file ui-browse-license.php
 * \brief browse a directory to display all licenses in this directory
 */

class AjaxExplorer extends DefaultPlugin
{
  const NAME = "ajax_explorer";

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
  /** @var UploadTreeProxy $alreadyClearedUploadTreeView
   * DB proxy view to hold upload tree entries for already cleared files */
  private $alreadyClearedUploadTreeView;
  /** @var UploadTreeProxy $noLicenseUploadTreeView
   * DB proxy view to hold upload tree entries for files with no license */
  private $noLicenseUploadTreeView;
  /** @var array */
  protected $agentNames = AgentRef::AGENT_LIST;
  /** @var array $cacheClearedCounter
   * Array to hold item tree which are already calculated */
  private $cacheClearedCounter;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Ajax: License Browser"),
        self::DEPENDENCIES => array("license"),
        self::PERMISSION => Auth::PERM_READ,
        self::REQUIRES_LOGIN => false
    ));

    $this->uploadDao = $this->getObject('dao.upload');
    $this->licenseDao = $this->getObject('dao.license');
    $this->clearingDao = $this->getObject('dao.clearing');
    $this->agentDao = $this->getObject('dao.agent');
    $this->clearingFilter = $this->getObject('businessrules.clearing_decision_filter');
    $this->filesThatShouldStillBeCleared = [];
    $this->filesToBeCleared = [];
    $this->alreadyClearedUploadTreeView = NULL;
    $this->noLicenseUploadTreeView = NULL;
    $this->cacheClearedCounter = [];
  }

  public function __destruct()
  {
    // Destruct the proxy views before exiting
    if ($this->alreadyClearedUploadTreeView !== NULL) {
      $this->alreadyClearedUploadTreeView->unmaterialize();
    }
    if ($this->noLicenseUploadTreeView !== NULL) {
      $this->noLicenseUploadTreeView->unmaterialize();
    }
  }

  /**
   * @param Request $request
   * @return Response
   */
  public function handle(Request $request)
  {
    $upload = intval($request->get("upload"));
    $groupId = Auth::getGroupId();
    if (!$this->uploadDao->isAccessible($upload, $groupId)) {
      throw new \Exception("Permission Denied");
    }

    $item = intval($request->get("item"));
    $this->uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($upload);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
    $left = $itemTreeBounds->getLeft();
    if (empty($left)) {
       throw new \Exception("Job unpack/adj2nest hasn't completed.");
    }

    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->agentDao, $upload);
    $scanJobProxy->createAgentStatus($scannerAgents);
    $selectedAgentId = intval($request->get('agentId'));
    $tag_pk = intval($request->get('tag'));

    $UniqueTagArray = array();
    $this->licenseProjector = new LicenseMap($this->getObject('db.manager'),$groupId,LicenseMap::CONCLUSION,true);
    $vars = $this->createFileListing($tag_pk, $itemTreeBounds, $UniqueTagArray, $selectedAgentId, $groupId, $scanJobProxy, $request);

    return new JsonResponse(array(
            'sEcho' => intval($request->get('sEcho')),
            'aaData' => $vars['fileData'],
            'iTotalRecords' => intval($request->get('totalRecords')),
            'iTotalDisplayRecords' => $vars['iTotalDisplayRecords']
          ) );
  }


  /**
   * @param $tagId
   * @param ItemTreeBounds $itemTreeBounds
   * @param $UniqueTagArray
   * @param $selectedAgentId
   * @param int $groupId
   * @param ScanJobProxy $scanJobProxy
   * @param Request $request
   * @return array
   */
  private function createFileListing($tagId, ItemTreeBounds $itemTreeBounds, &$UniqueTagArray, $selectedAgentId, $groupId, $scanJobProxy, $request)
  {
    if (!empty($selectedAgentId)) {
      $agentName = $this->agentDao->getAgentName($selectedAgentId);
      $selectedScanners = array($agentName=>$selectedAgentId);
    } else {
      $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    }

    /** change the license result when selecting one version of nomos */
    $uploadId = $itemTreeBounds->getUploadId();
    $isFlat = $request->get('flatten') !== null;

    if ($isFlat) {
      $options = array(UploadTreeProxy::OPT_RANGE => $itemTreeBounds);
    } else {
      $options = array(UploadTreeProxy::OPT_REALPARENT => $itemTreeBounds->getItemId());
    }

    $searchMap = array();
    foreach (explode(' ',$request->get('sSearch')) as $pair) {
      $a = explode(':',$pair);
      if (count($a) == 1) {
        $searchMap['head'] = $pair;
      } else {
        $searchMap[$a[0]] = $a[1];
      }
    }

    if (array_key_exists('ext', $searchMap) && strlen($searchMap['ext'])>=1) {
      $options[UploadTreeProxy::OPT_EXT] = $searchMap['ext'];
    }
    if (array_key_exists('head', $searchMap) && strlen($searchMap['head'])>=1) {
      $options[UploadTreeProxy::OPT_HEAD] = $searchMap['head'];
    }
    if (($rfId=$request->get('scanFilter'))>0) {
      $options[UploadTreeProxy::OPT_AGENT_SET] = $selectedScanners;
      $options[UploadTreeProxy::OPT_SCAN_REF] = $rfId;
    }
    if (($rfId=$request->get('conFilter'))>0) {
      $options[UploadTreeProxy::OPT_GROUP_ID] = Auth::getGroupId();
      $options[UploadTreeProxy::OPT_CONCLUDE_REF] = $rfId;
    }
    $openFilter = $request->get('openCBoxFilter');
    if ($openFilter=='true' || $openFilter=='checked') {
      $options[UploadTreeProxy::OPT_AGENT_SET] = $selectedScanners;
      $options[UploadTreeProxy::OPT_GROUP_ID] = Auth::getGroupId();
      $options[UploadTreeProxy::OPT_SKIP_ALREADY_CLEARED] = true;
    }

    $descendantView = new UploadTreeProxy($uploadId, $options, $itemTreeBounds->getUploadTreeTableName(), 'uberItems');

    $vars['iTotalDisplayRecords'] = $descendantView->count();

    $columnNamesInDatabase = array($isFlat?'ufile_name':'lft');
    $defaultOrder = array(array(0, "asc"));

    $orderString = $this->getObject('utils.data_tables_utility')->getSortingString($request->get('fromRest') ? $request->request->all(): $request->query->all(), $columnNamesInDatabase, $defaultOrder);

    $offset = $request->get('iDisplayStart');
    $limit = $request->get('iDisplayLength');
    if ($offset) {
      $orderString .= " OFFSET $offset";
    }
    if ($limit) {
      $orderString .= " LIMIT $limit";
    }

    /* Get ALL the items under this Uploadtree_pk */
    $sql = $descendantView->getDbViewQuery()." $orderString";
    $dbManager = $this->getObject('db.manager');

    $dbManager->prepare($stmt=__METHOD__.$orderString,$sql);
    $res = $dbManager->execute($stmt,$descendantView->getParams());
    $descendants = $dbManager->fetchAll($res);
    $dbManager->freeResult($res);

    /* Filter out Children that don't have tag */
    if (!empty($tagId)) {
      TagFilter($descendants, $tagId, $itemTreeBounds->getUploadTreeTableName());
    }
    if (empty($descendants)) {
      $vars['fileData'] = array();
      return $vars;
    }

    if ($isFlat) {
      $firstChild = reset($descendants);
      $lastChild = end($descendants);
      $nameRange = array($firstChild['ufile_name'],$lastChild['ufile_name']);
    } else {
      $nameRange = array();
    }

    $allDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds, $groupId, $isFlat);
    $editedMappedLicenses = $this->clearingFilter->filterCurrentClearingDecisions($allDecisions);

    $pfileLicenses = $this->updateTheFindingsAndDecisions($selectedScanners,
      $isFlat, $groupId, $editedMappedLicenses, $itemTreeBounds, $nameRange);

    $baseUri = Traceback_uri().'?mod=license'.Traceback_parm_keep(array('upload','folder','show'));

    $tableData = array();
    global $Plugins;
    $ModLicView = &$Plugins[plugin_find_id("view-license")];
    $latestSuccessfulAgentIds = $scanJobProxy->getLatestSuccessfulAgentIds();
    foreach ($descendants as $child) {
      if (empty($child)) {
        continue;
      }
      $tableData[] = $this->createFileDataRow($child, $uploadId, $selectedAgentId, $pfileLicenses, $groupId, $editedMappedLicenses, $baseUri, $ModLicView, $UniqueTagArray, $isFlat, $latestSuccessfulAgentIds, $request);
    }

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
   * @param string $uri
   * @param null|ClearingView $ModLicView
   * @param array $UniqueTagArray
   * @param boolean $isFlat
   * @param int[] $latestSuccessfulAgentIds
   * @param Request $request
   * @return array
   */
  private function createFileDataRow($child, $uploadId, $selectedAgentId, $pfileLicenses, $groupId, $editedMappedLicenses, $uri, $ModLicView, &$UniqueTagArray, $isFlat, $latestSuccessfulAgentIds, $request)
  {
    $fileDetails = array(
      "fileName" => "", "id" => "", "uploadId" => $uploadId, "agentId" => "", "isContainer" => false,
    );

    $fileId = $child['pfile_fk'];
    $childUploadTreeId = $child['uploadtree_pk'];
    $linkUri = '';
    if (!empty($fileId) && !empty($ModLicView)) {
      $linkUri = Traceback_uri();
      $linkUri .= "?mod=view-license&upload=$uploadId&item=$childUploadTreeId";
      $fileDetails["id"] = intval($childUploadTreeId);
      $fileDetails["uploadId"] = $uploadId;
      if ($selectedAgentId) {
        $linkUri .= "&agentId=$selectedAgentId";
        $fileDetails["agentId"] = $selectedAgentId;
      }
    }

    /* Determine link for containers */
    $isContainer = Iscontainer($child['ufile_mode']);
    if ($isContainer && !$isFlat) {
      $fatChild = $this->uploadDao->getFatItemArray($child['uploadtree_pk'], $uploadId, $this->uploadtree_tablename);
      $uploadtree_pk = $fatChild['item_id'];
      $childUploadTreeId = $uploadtree_pk;
      $upload = $this->uploadDao->getUploadEntry($uploadtree_pk, $this->uploadtree_tablename);
      $fileId = $upload['pfile_fk'];
      $parent = $upload['realparent'];
      $parentItemTreeBound = $this->uploadDao->getItemTreeBounds($parent, $this->uploadtree_tablename);

      $pfileLicenses = array_replace($pfileLicenses,
        $this->updateTheFindingsAndDecisions($latestSuccessfulAgentIds, $isFlat,
          $groupId, $editedMappedLicenses, $parentItemTreeBound));

      $linkUri = "$uri&item=" . $uploadtree_pk;
      $fileDetails["id"] = intval($uploadtree_pk);
      if ($selectedAgentId) {
        $linkUri .= "&agentId=$selectedAgentId";
        $fileDetails["agentId"] = $selectedAgentId;
      }
      $child['ufile_name'] = $fatChild['ufile_name'];
      if (!Iscontainer($fatChild['ufile_mode'])) {
        $isContainer = false;
      }
    } else if ($isContainer) {
      $uploadtree_pk = Isartifact($child['ufile_mode']) ? DirGetNonArtifact($childUploadTreeId, $this->uploadtree_tablename) : $childUploadTreeId;
      $linkUri = "$uri&item=" . $uploadtree_pk;
      $fileDetails["id"] = intval($uploadtree_pk);
      $fileDetails["isContainer"] = true;
      if ($selectedAgentId) {
        $linkUri .= "&agentId=$selectedAgentId";
        $fileDetails["agentId"] = $selectedAgentId;
      }
    }

    /* Populate the output ($VF) - file list */
    /* id of each element is its uploadtree_pk */
    $fileName = $child['ufile_name'];
    if ($isContainer) {
      $fileDetails["fileName"] = $fileName;
      $fileName = "<a href='$linkUri'><span style='color: darkblue'> <b>$fileName</b> </span></a>";
    } else if (!empty($linkUri)) {
      $fileDetails["fileName"] = $fileName;
      $fileName = "<a href='$linkUri'>$fileName</a>";
    }
    /* show licenses under file name */
    $childItemTreeBounds =
        new ItemTreeBounds($childUploadTreeId, $this->uploadtree_tablename, $child['upload_fk'], $child['lft'], $child['rgt']);
    $totalFilesCount = $this->uploadDao->countPlainFiles($childItemTreeBounds);
    $licenseEntriesRest = array();
    if ($isContainer) {
      $fileDetails["isContainer"] = true;
      $agentFilter = $selectedAgentId ? array($selectedAgentId) : $latestSuccessfulAgentIds;
      $licenseEntries = $this->licenseDao->getLicenseShortnamesContained($childItemTreeBounds, $agentFilter, array());
      $editedLicenses = $this->clearingDao->getClearedLicenses($childItemTreeBounds, $groupId);

      if ($request->get('fromRest')) {
        foreach ($licenseEntries as $shortName) {
          $licenseEntriesRest[] = array(
            "id" => $this->licenseDao->getLicenseByShortName($shortName, $groupId)->getId(),
            "name" => $shortName,
            "agents" => []
          );
        }
      }
    } else {
      $licenseEntries = array();
      if (array_key_exists($fileId, $pfileLicenses)) {
        foreach ($pfileLicenses[$fileId] as $shortName => $rfInfo) {
          $agentEntries = array();
          $agentEntriesRest = array();
          foreach ($rfInfo as $agent => $match) {
            $agentName = $this->agentNames[$agent];
            $agentEntry = "<a href='?mod=view-license&upload=$child[upload_fk]&item=$childUploadTreeId&format=text&agentId=$match[agent_id]&licenseId=$match[license_id]#highlight'>" . $agentName . "</a>";

            if ($match['match_percentage'] > 0) {
              $agentEntry .= ": $match[match_percentage]%";
            }
            $agentEntries[] = $agentEntry;
            $agentEntriesRest[] = array(
              "name" => $agentName,
              "id" => intval($match['agent_id']),
              "matchPercentage" => intval($match['match_percentage']),
            );
          }
          $licenseEntriesRest[] = array(
            "id" => $this->licenseDao->getLicenseByShortName($shortName, $groupId)->getId(),
            "name" => $shortName,
            "agents" => $agentEntriesRest,
          );
          $licenseEntries[] = $shortName . " [" . implode("][", $agentEntries) . "]";
        }
      }

      /* @var $decision ClearingDecision */
      if (false !== ($decision = $this->clearingFilter->getDecisionOf($editedMappedLicenses,$childUploadTreeId, $fileId))) {
        $editedLicenses = $decision->getPositiveLicenses();
      } else {
        $editedLicenses = array();
      }
    }
    $concludedLicensesRest = array();
    $concludedLicenses = array();
    /** @var LicenseRef $licenseRef */
    foreach ($editedLicenses as $licenseRef) {
      $projectedId = $this->licenseProjector->getProjectedId($licenseRef->getId());
      $projectedName = $this->licenseProjector->getProjectedShortname($licenseRef->getId(),$licenseRef->getShortName());
      $concludedLicenses[$projectedId] = $projectedName;
      $concludedLicensesRest[] = array('id' => $projectedId, 'name' => $projectedName);
    }

    $editedLicenseList = implode(', ', $concludedLicenses);
    $licenseList = implode(', ', $licenseEntries);

    $fileListLinks = FileListLinks($uploadId, $childUploadTreeId, 0, $fileId, true, $UniqueTagArray, $this->uploadtree_tablename, !$isFlat);

    $getTextEditUser = _("Edit");
    $fileListLinks .= "[<a href='#' onclick='openUserModal($childUploadTreeId)' >$getTextEditUser</a>]";

    if ($isContainer) {
      $getTextEditBulk = _("Bulk");
      $fileListLinks .= "[<a href='#' data-toggle='modal' data-target='#bulkModal' onclick='openBulkModal($childUploadTreeId)' >$getTextEditBulk</a>]";
    }
    $fileListLinks .= "<input type='checkbox' class='selectedForIrrelevant' class='info-bullet view-license-rc-size' value='".$childUploadTreeId."'>";
    $filesThatShouldStillBeCleared = array_key_exists($childItemTreeBounds->getItemId()
        , $this->filesThatShouldStillBeCleared) ? $this->filesThatShouldStillBeCleared[$childItemTreeBounds->getItemId()] : 0;

    $filesToBeCleared = array_key_exists($childItemTreeBounds->getItemId()
        , $this->filesToBeCleared) ? $this->filesToBeCleared[$childItemTreeBounds->getItemId()] : 0;

    $filesCleared = $filesToBeCleared - $filesThatShouldStillBeCleared;

    $img = ($filesCleared == $filesToBeCleared) ? 'green' : 'red';

    // override green/red flag with grey flag in case of no_license_found scanner finding
    if (!empty($licenseList) && empty($editedLicenseList)) {
      $img = (
              (strpos($licenseList, LicenseDao::NO_LICENSE_FOUND) !== false)
              &&
              (count(explode(",", $licenseList)) == 1)
             ) ? 'grey' : $img;
    }

    // override green/red flag with yellow flag in case of single file with decision type "To Be Discussed"
    $isDecisionTBD = $this->clearingDao->isDecisionCheck($childUploadTreeId, $groupId, DecisionTypes::TO_BE_DISCUSSED);
    $img = $isDecisionTBD ? 'yellow' : $img;

    // override green/red flag with greenRed flag in case of single file with decision type "Do Not Use" or "Non functional"
    $isDecisionDNU = $this->clearingDao->isDecisionCheck($childUploadTreeId, $groupId, DecisionTypes::DO_NOT_USE);
    $isDecisionNonFunctional = $this->clearingDao->isDecisionCheck($childUploadTreeId, $groupId, DecisionTypes::NON_FUNCTIONAL);

    $img = ($isDecisionDNU || $isDecisionNonFunctional) ? 'redGreen' : $img;

    return $request->get('fromRest') ? array(
      "fileDetails" => $fileDetails,
      "licenseList" => $licenseEntriesRest,
      "editedLicenseList" => $concludedLicensesRest,
      "clearingStatus" => $img,
      "clearingProgress" => array(
        "filesCleared" => intval($filesCleared),
        "filesToBeCleared" => intval($filesToBeCleared),
        "totalFilesCount" => intval($totalFilesCount)
      ),
    ) : array($fileName, $licenseList, $editedLicenseList, $img, "$filesCleared / $filesToBeCleared / $totalFilesCount", $fileListLinks);
  }

  /**
   * @brief Fetch the license findings and decisions
   * @param array $agentIds Map of agents run on the upload (with agent name as
   *          key and id as value)
   * @param boolean $isFlat Is the flat view required?
   * @param integer $groupId The user group
   * @param[in,out] array $editedMappedLicenses Map of decisions
   * @param ItemTreeBounds $itemTreeBounds The current item tree bound
   * @param array $nameRange The name range for current view
   * @return array Array of license findings mapped as
   *         `[pfile_id][license_id][agent_name] = license_findings`
   */
  private function updateTheFindingsAndDecisions($agentIds, $isFlat, $groupId,
    &$editedMappedLicenses, $itemTreeBounds, $nameRange = array())
  {
    /**
     * ***** File Listing ***********
     */
    $pfileLicenses = [];
    foreach ($agentIds as $agentName => $agentId) {
      $licensePerPfile = $this->licenseDao->getLicenseIdPerPfileForAgentId(
        $itemTreeBounds, $agentId, $isFlat, $nameRange);
      foreach ($licensePerPfile as $pfile => $licenseRow) {
        foreach ($licenseRow as $licId => $row) {
          $lic = $this->licenseProjector->getProjectedShortname($licId);
          $pfileLicenses[$pfile][$lic][$agentName] = $row;
        }
      }
    }

    if ($this->alreadyClearedUploadTreeView === NULL) {
      // Initialize the proxy view only once for the complete table
      $this->alreadyClearedUploadTreeView = new UploadTreeProxy(
        $itemTreeBounds->getUploadId(),
        $options = array(
          UploadTreeProxy::OPT_SKIP_THESE => UploadTreeProxy::OPT_SKIP_ALREADY_CLEARED,
          UploadTreeProxy::OPT_ITEM_FILTER => "AND (lft BETWEEN " .
          $itemTreeBounds->getLeft() . " AND " . $itemTreeBounds->getRight() . ")",
          UploadTreeProxy::OPT_GROUP_ID => $groupId
        ), $itemTreeBounds->getUploadTreeTableName(),
        $viewName = 'already_cleared_uploadtree' . $itemTreeBounds->getUploadId());

      $this->alreadyClearedUploadTreeView->materialize();
    }

    if ($this->noLicenseUploadTreeView === NULL) {
      // Initialize the proxy view only once for the complete table
      $this->noLicenseUploadTreeView = new UploadTreeProxy(
        $itemTreeBounds->getUploadId(),
        $options = array(
          UploadTreeProxy::OPT_SKIP_THESE => "noLicense",
          UploadTreeProxy::OPT_ITEM_FILTER => "AND (lft BETWEEN " .
          $itemTreeBounds->getLeft() . " AND " . $itemTreeBounds->getRight() . ")",
          UploadTreeProxy::OPT_GROUP_ID => $groupId
        ), $itemTreeBounds->getUploadTreeTableName(),
        $viewName = 'no_license_uploadtree' . $itemTreeBounds->getUploadId());
      $this->noLicenseUploadTreeView->materialize();
    }

    $this->updateFilesToBeCleared($isFlat, $itemTreeBounds);
    $allDecisions = $this->clearingDao->getFileClearingsFolder($itemTreeBounds,
      $groupId, $isFlat);
    $editedMappedLicenses = array_replace($editedMappedLicenses,
      $this->clearingFilter->filterCurrentClearingDecisions($allDecisions));
    return $pfileLicenses;
  }

  /**
   * Update filesThatShouldStillBeCleared and filesToBeCleared counts if the
   * passed item has not been cached yet.
   * @param boolean $isFlat
   * @param ItemTreeBounds $itemTreeBounds
   */
  private function updateFilesToBeCleared($isFlat, $itemTreeBounds)
  {
    $itemId = $itemTreeBounds->getItemId();
    if (in_array($itemId, $this->cacheClearedCounter)) {
      // Already calculated, no need to recount
      return;
    }
    $this->cacheClearedCounter[] = $itemId;
    if (! $isFlat) {
      $this->filesThatShouldStillBeCleared = array_replace(
        $this->filesThatShouldStillBeCleared,
        $this->alreadyClearedUploadTreeView->countMaskedNonArtifactChildren(
          $itemId));
      $this->filesToBeCleared = array_replace($this->filesToBeCleared,
        $this->noLicenseUploadTreeView->countMaskedNonArtifactChildren(
          $itemId));
    } else {
      $this->filesThatShouldStillBeCleared = array_replace(
        $this->filesThatShouldStillBeCleared,
        $this->alreadyClearedUploadTreeView->getNonArtifactDescendants(
          $itemTreeBounds));
      $this->filesToBeCleared = array_replace($this->filesToBeCleared,
        $this->noLicenseUploadTreeView->getNonArtifactDescendants($itemTreeBounds));
    }
  }
}

register_plugin(new AjaxExplorer());
