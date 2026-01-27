<?php
/*
 SPDX-FileCopyrightText: © 2008-2015 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Fossology\Lib\Data\AgentRef;

/**
 * \file ui-browse-license.php
 * \brief browse a directory to display all licenses in this directory
 */

class AjaxFileBrowser extends DefaultPlugin
{
  const NAME = "ajax_file_browser";

  private $uploadtree_tablename = "";
  /** @var UploadDao */
  private $uploadDao;
  /** @var LicenseDao */
  private $licenseDao;
  /** @var AgentDao */
  private $agentDao;
  /** @var LicenseMap */
  private $licenseProjector;
  /** @var array */
  protected $agentNames = AgentRef::AGENT_LIST;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Ajax: File Browser"),
        self::DEPENDENCIES => array("fileBrowse"),
        self::PERMISSION => Auth::PERM_READ,
        self::REQUIRES_LOGIN => false
    ));

    $this->uploadDao = $this->getObject('dao.upload');
    $this->licenseDao = $this->getObject('dao.license');
    $this->agentDao = $this->getObject('dao.agent');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
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
    $isFlat = isset($_GET['flatten']);

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

    // Multi-filter support
    $statusFilter = $request->get('statusFilter');
    if (!empty($statusFilter) && $statusFilter !== '0') {
      $options[UploadTreeProxy::OPT_STATUS_FILTER] = $statusFilter;
      $options[UploadTreeProxy::OPT_GROUP_ID] = $groupId;
    }

    $evidenceFilter = $request->get('evidenceFilter');
    if (!empty($evidenceFilter) && $evidenceFilter !== '0') {
      $options[UploadTreeProxy::OPT_SKIP_THESE] = $evidenceFilter;
      $options[UploadTreeProxy::OPT_GROUP_ID] = $groupId;
      if (!isset($options[UploadTreeProxy::OPT_AGENT_SET])) {
        $options[UploadTreeProxy::OPT_AGENT_SET] = $selectedScanners;
      }
    }

    $descendantView = new UploadTreeProxy($uploadId, $options, $itemTreeBounds->getUploadTreeTableName(), 'uberItems');

    $vars['iTotalDisplayRecords'] = $descendantView->count();

    $columnNamesInDatabase = array($isFlat?'ufile_name':'lft');
    $defaultOrder = array(array(0, "asc"));
    $orderString = $this->getObject('utils.data_tables_utility')->getSortingString($_GET, $columnNamesInDatabase, $defaultOrder);

    $offset = GetParm('iDisplayStart', PARM_INTEGER);
    $limit = GetParm('iDisplayLength', PARM_INTEGER);
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

    /*******    File Listing     ************/
    $pfileLicenses = array();
    foreach ($selectedScanners as $agentName=>$agentId) {
      $licensePerPfile = $this->licenseDao->getLicenseIdPerPfileForAgentId($itemTreeBounds, $agentId, $isFlat, $nameRange);
      foreach ($licensePerPfile as $pfile => $licenseRow) {
        foreach ($licenseRow as $licId => $row) {
          $lic = $this->licenseProjector->getProjectedShortname($licId);
          $pfileLicenses[$pfile][$lic][$agentName] = $row;
        }
      }
    }

    $baseUri = Traceback_uri().'?mod=fileBrowse'.Traceback_parm_keep(array('upload','folder','show'));

    $tableData = array();
    $latestSuccessfulAgentIds = $scanJobProxy->getLatestSuccessfulAgentIds();
    foreach ($descendants as $child) {
      if (empty($child)) {
        continue;
      }
      $tableData[] = $this->createFileDataRow($child, $uploadId, $selectedAgentId,
        $pfileLicenses, $groupId, $baseUri, $UniqueTagArray, $isFlat, $latestSuccessfulAgentIds);
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
   * @param string $uri
   * @param array $UniqueTagArray
   * @param boolean $isFlat
   * @param int[] $latestSuccessfulAgentIds
   * @return array
   */
  private function createFileDataRow($child, $uploadId, $selectedAgentId, $pfileLicenses, $groupId, $uri, &$UniqueTagArray, $isFlat, $latestSuccessfulAgentIds)
  {
    $fileId = $child['pfile_fk'];
    $childUploadTreeId = $child['uploadtree_pk'];
    $linkUri = '';
    if (!empty($fileId)) {
      $linkUri = Traceback_uri();
      $linkUri .= "?mod=view-license&upload=$uploadId&item=$childUploadTreeId";
      if ($selectedAgentId) {
        $linkUri .= "&agentId=$selectedAgentId";
      }
    }

    /* Determine link for containers */
    $isContainer = Iscontainer($child['ufile_mode']);
    if ($isContainer && !$isFlat) {
      $uploadtree_pk = $child['uploadtree_pk'];
      $linkUri = "$uri&item=" . $uploadtree_pk;
      if ($selectedAgentId) {
        $linkUri .= "&agentId=$selectedAgentId";
      }
    } else if ($isContainer) {
      $uploadtree_pk = Isartifact($child['ufile_mode']) ? DirGetNonArtifact($childUploadTreeId, $this->uploadtree_tablename) : $childUploadTreeId;
      $linkUri = "$uri&item=" . $uploadtree_pk;
      if ($selectedAgentId) {
        $linkUri .= "&agentId=$selectedAgentId";
      }
    }

    /* Populate the output ($VF) - file list */
    /* id of each element is its uploadtree_pk */
    $fileName = htmlspecialchars($child['ufile_name']);
    if ($isContainer) {
      $fileName = "<a href='$linkUri'><span style='color: darkblue'> <b>$fileName</b> </span></a>";
    } else if (!empty($linkUri)) {
      $fileName = "<a href='$linkUri'>$fileName</a>";
    }
    /* show licenses under file name */
    $childItemTreeBounds =
        new ItemTreeBounds($childUploadTreeId, $this->uploadtree_tablename, $child['upload_fk'], $child['lft'], $child['rgt']);
    $licenseEntries = array();
    if ($isContainer) {
      $agentFilter = $selectedAgentId ? array($selectedAgentId) : $latestSuccessfulAgentIds;
      $licenseEntries = $this->licenseDao->getLicenseShortnamesContained($childItemTreeBounds, $agentFilter, array());
    } else {
      if (array_key_exists($fileId, $pfileLicenses)) {
        foreach ($pfileLicenses[$fileId] as $shortName => $rfInfo) {
          $agentEntries = array();
          foreach ($rfInfo as $agent => $match) {
            $agentName = $this->agentNames[$agent];
            $agentEntry = "<a href='?mod=view-license&upload=$child[upload_fk]&item=$childUploadTreeId&format=text&agentId=$match[agent_id]&licenseId=$match[license_id]#highlight'>" . $agentName . "</a>";

            if ($match['match_percentage'] > 0) {
              $agentEntry .= ": $match[match_percentage]%";
            }
            $agentEntries[] = $agentEntry;
          }
          $licenseEntries[] = $shortName . " [" . implode("][", $agentEntries) . "]";
        }
      }
    }

    $licenseList = implode(', ', $licenseEntries);

    $fileListLinks = FileListLinks($uploadId, $childUploadTreeId, 0, $fileId, true, $UniqueTagArray, $this->uploadtree_tablename, !$isFlat);

    if (! $isContainer) {
      $text = _("Copyright/Email/Url");
      $fileListLinks .= "[<a href='" . Traceback_uri() . "?mod=copyright-view&upload=$uploadId&item=$childUploadTreeId' >$text</a>]";
      $text = _("ReadMe_OSS");
      $fileListLinks .= "[<a href='" . Traceback_uri() . "?mod=ui_readmeoss&upload=$uploadId&item=$childUploadTreeId' >$text</a>]";
      $text = _("SPDX");
      $fileListLinks .= "[<a href='" . Traceback_uri() . "?mod=ui_spdx2&upload=$uploadId&item=$childUploadTreeId' >$text</a>]";
    }

    return array($fileName, $licenseList, $fileListLinks);
  }
}

register_plugin(new AjaxFileBrowser());
