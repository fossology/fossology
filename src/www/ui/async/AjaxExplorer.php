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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
  /** @var array */
  protected $agentNames = array('nomos' => 'N', 'monk' => 'M', 'ninka' => 'Nk');
  
  public function __construct() {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Ajax: License Browser"),
        self::DEPENDENCIES => array("license"),
        self::PERMISSION => Auth::PERM_READ
    ));

    $this->uploadDao = $this->getObject('dao.upload');
    $this->licenseDao = $this->getObject('dao.license');
    $this->clearingDao = $this->getObject('dao.clearing');
    $this->agentDao = $this->getObject('dao.agent');
    $this->clearingFilter = $this->getObject('businessrules.clearing_decision_filter');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request) {
    $upload = intval($request->get("upload"));
    $groupId = Auth::getGroupId();
    if (!$this->uploadDao->isAccessible($upload, $groupId)) {
      throw new \Exception("Permission Denied");
    }

    $item = intval($request->get("item"));
    $this->uploadtree_tablename = $this->uploadDao->getUploadtreeTableName($upload);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($item, $this->uploadtree_tablename);
    $left = $itemTreeBounds->getLeft();
    if (empty($left))
    {
       throw new \Exception("Job unpack/adj2nest hasn't completed.");
    }
    
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($this->agentDao, $upload);
    $scanJobProxy->createAgentStatus($scannerAgents);
    $selectedAgentId = intval($request->get('agentId'));
    $tag_pk = intval($request->get('tag'));

    $UniqueTagArray = array();
    $this->licenseProjector = new LicenseMap($this->getObject('db.manager'),$groupId,LicenseMap::CONCLUSION,true);
    $vars =  $this->createFileListing($tag_pk, $itemTreeBounds, $UniqueTagArray, $selectedAgentId, $groupId, $scanJobProxy);
    
    return new JsonResponse(array(
            'sEcho' => intval($_GET['sEcho']),
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
   * @return array
   */
  private function createFileListing($tagId, ItemTreeBounds $itemTreeBounds, &$UniqueTagArray, $selectedAgentId, $groupId, $scanJobProxy)
  {
    if (!empty($selectedAgentId))
    {
      $agentName = $this->agentDao->getAgentName($selectedAgentId);
      $selectedScanners = array($agentName=>$selectedAgentId);
    }
    else
    {
      $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    }
    
    /** change the license result when selecting one version of nomos */
    $uploadId = $itemTreeBounds->getUploadId();
    $isFlat = isset($_GET['flatten']);
    
    $conditionalAdd = '';
    
    $searchMap = array();
    foreach(explode(' ',GetParm('sSearch', PARM_RAW)) as $pair)
    {
      $a = explode(':',$pair);
      if(count($a)==1)
        $searchMap['any'] = $pair;
      else
        $searchMap[$a[0]] = $a[1];
    }
    
    if(array_key_exists('ext', $searchMap) && strlen($searchMap['ext'])>=1)
    {
      $dbM = $this->getObject('db.manager');
      $dbD = $dbM->getDriver();
      $ext = $dbD->escapeString($searchMap['ext']);
      $conditionalAdd .= " AND ufile_name ilike '%.$ext'";
    }
    
    if(array_key_exists('scan', $searchMap) && ($rfId=intval($searchMap['scan']))>0)
    {
      $agentIdSet = '{'.implode(',', array_values($selectedScanners)).'}';
      if($isFlat)
      {
        $conditionalAdd .= " AND EXISTS(SELECT * FROM license_file"
                . " WHERE agent_fk=ANY('$agentIdSet') AND rf_fk=$rfId AND u.pfile_fk=license_file.pfile_fk)";
      }
      else
      {
        $conditionalAdd .= " AND EXISTS(SELECT * FROM license_file,".$itemTreeBounds->getUploadTreeTableName()." usub"
                . " WHERE agent_fk=ANY('$agentIdSet') AND rf_fk=$rfId AND usub.pfile_fk=license_file.pfile_fk AND (usub.lft BETWEEN u.lft AND u.rgt))";
      }
    }

    $nonArtifactDescantants = $this->uploadDao->getNonArtifactDescendants($itemTreeBounds, $isFlat, $conditionalAdd,'count(*)');
    $vars['iTotalDisplayRecords'] = $nonArtifactDescantants[0]['count'];
    
    $columnNamesInDatabase = array($isFlat?'ufile_name':'lft');
    $defaultOrder = array(array(0, "asc"));
    $orderString = $this->getObject('utils.data_tables_utility')->getSortingString($_GET, $columnNamesInDatabase, $defaultOrder);
    
    $offset = GetParm('iDisplayStart', PARM_INTEGER);
    $limit = GetParm('iDisplayLength', PARM_INTEGER);
    if($offset)
      $orderString .= " OFFSET $offset";
    if($limit)
      $orderString .= " LIMIT $limit";
    
    /* Get ALL the items under this Uploadtree_pk */
    $descendants = $this->uploadDao->getNonArtifactDescendants($itemTreeBounds, $isFlat, "$conditionalAdd $orderString");
    
    /* Filter out Children that don't have tag */
    if (!empty($tagId))
    {
      TagFilter($descendants, $tagId, $itemTreeBounds->getUploadTreeTableName());
    }
    if (empty($descendants))
    {
      $vars['fileData'] = array();
      return $vars;
    }

    /*******    File Listing     ************/
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
    $baseUri = Traceback_uri().'?mod=license'.Traceback_parm_keep(array('upload','folder','show'));

    $tableData = array();    
    global $Plugins;
    $ModLicView = &$Plugins[plugin_find_id("view-license")];
    foreach ($descendants as $child)
    {
      if (empty($child))
      {
        continue;
      }
      $tableData[] = $this->createFileDataRow($child, $uploadId, $selectedAgentId, $pfileLicenses, $groupId, $editedMappedLicenses, $baseUri, $ModLicView, $UniqueTagArray, $isFlat);
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
   * @return array
   */
  private function createFileDataRow($child, $uploadId, $selectedAgentId, $pfileLicenses, $groupId, $editedMappedLicenses, $uri, $ModLicView, &$UniqueTagArray, $isFlat)
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
      $uploadtree_pk = Isartifact($child['ufile_mode']) ? DirGetNonArtifact($childUploadTreeId, $this->uploadtree_tablename) : $childUploadTreeId;
      $LicUri = "$uri&item=" . $uploadtree_pk;
      if ($selectedAgentId)
      {
        $LicUri .= "&agentId=$selectedAgentId";
      }
    }
    else
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
}

register_plugin(new AjaxExplorer());
