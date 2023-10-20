<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Authors: Andreas Würl, Steffen Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exception;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\Lib\Proxy\UploadTreeViewProxy;
use Monolog\Logger;

require_once(dirname(dirname(__FILE__)) . "/common-dir.php");

class UploadDao
{

  const REUSE_NONE = 0;
  const REUSE_ENHANCED = 2;
  const REUSE_MAIN = 4;
  const REUSE_CONF = 16;
  const REUSE_COPYRIGHT = 128;
  const UNIFIED_REPORT_HEADINGS = array(
    "assessment" => array("Assessment Summary" => true),
    "compliancetasks" => array("Required license compliance tasks" => true),
    "acknowledgements" => array("Acknowledgements" => true),
    "exportrestrictions" => array("Export Restrictions" => true),
    "intellectualProperty" => array("Patent Relevant Statements" => true),
    "notes" => array("Notes" => true),
    "scanresults" => array("Results of License Scan" => true),
    "mainlicenses" => array("Main Licenses" => true),
    "redlicense" => array("Other OSS Licenses (red) - Do not Use Licenses" => true),
    "yellowlicense" => array("Other OSS Licenses (yellow) - additional obligations to common rules (e.g. copyleft)" => true),
    "whitelicense" => array("Other OSS Licenses (white) - only common rules" => true),
    "overviewwithwithoutobligations" => array("Overview of All Licenses with or without Obligations" => true),
    "copyrights" => array("Copyrights" => true),
    "copyrightsuf" => array("Copyrights (User Findings)" => true),
    "bulkfindings" => array("Bulk Findings" => true),
    "licensenf" => array("Non-Functional Licenses" => true),
    "irrelevantfiles" => array("Irrelevant Files" => true),
    "dnufiles" => array("Do not use Files" => true),
    "changelog" => array("Clearing Protocol Change Log" => true)
  );

  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var UploadPermissionDao */
  private $permissionDao;

  public function __construct(DbManager $dbManager, Logger $logger, UploadPermissionDao $uploadPermissionDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
    $this->permissionDao = $uploadPermissionDao;
  }


  /**
   * @param $uploadTreeId
   * @param string $uploadTreeTableName
   * @return array
   */
  public function getUploadEntry($uploadTreeId, $uploadTreeTableName = "uploadtree")
  {
    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $uploadEntry = $this->dbManager->getSingleRow("SELECT * FROM $uploadTreeTableName WHERE uploadtree_pk = $1",
        array($uploadTreeId), $stmt);
    if ($uploadEntry) {
      $uploadEntry['tablename'] = $uploadTreeTableName;
    }
    return $uploadEntry;
  }

  /**
   * Get the first entry for uploadtree_pk for a given pfile in a given upload.
   * @param integer $uploadFk Upload id
   * @param integer $pfileFk  Pfile id
   * @return integer Uploadtree_pk
   */
  public function getUploadtreeIdFromPfile($uploadFk, $pfileFk)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadFk);
    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $uploadEntry = $this->dbManager->getSingleRow("SELECT uploadtree_pk " .
      "FROM $uploadTreeTableName " .
      "WHERE upload_fk = $1 AND pfile_fk = $2",
      array($uploadFk, $pfileFk), $stmt);
    return intval($uploadEntry['uploadtree_pk']);
  }

  /**
   * @param int $uploadId
   * @return Upload|null
   */
  public function getUpload($uploadId)
  {
    $stmt = __METHOD__;
    $row = $this->dbManager->getSingleRow("SELECT * FROM upload WHERE upload_pk = $1",
        array($uploadId), $stmt);

    return $row ? Upload::createFromTable($row) : null;
  }

  public function getActiveUploadsArray()
  {
    $stmt = __METHOD__;
    $queryResult = $this->dbManager->getRows("SELECT * FROM upload where pfile_fk IS NOT NULL",
        array(), $stmt);

    $results = array();
    foreach ($queryResult as $row) {
      $results[] = Upload::createFromTable($row);
    }

    return $results;
  }

  /**
   * @param $itemId
   * @param $uploadTreeTableName
   * @return ItemTreeBounds
   */
  public function getItemTreeBounds($itemId, $uploadTreeTableName = "uploadtree")
  {
    $uploadEntryData = $this->getUploadEntry($itemId, $uploadTreeTableName);
    return $this->createItemTreeBounds($uploadEntryData, $uploadTreeTableName);
  }

  /**
   * @param $uploadTreeId
   * @param $uploadId
   * @return ItemTreeBounds
   */
  public function getItemTreeBoundsFromUploadId($uploadTreeId, $uploadId)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);
    return $this->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
  }

  /**
   * @param int $uploadId
   * @param string|null
   * @throws Exception
   * @return ItemTreeBounds
   */
  public function getParentItemBounds($uploadId, $uploadTreeTableName = null)
  {
    if ($uploadTreeTableName === null) {
      $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);
    }

    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $parameters = array();
    $uploadCondition = $this->handleUploadIdForTable($uploadTreeTableName, $uploadId, $parameters);

    $uploadEntryData = $this->dbManager->getSingleRow("SELECT * FROM $uploadTreeTableName
        WHERE parent IS NULL
              $uploadCondition
          ",
        $parameters, $stmt);

    return $uploadEntryData ? $this->createItemTreeBounds($uploadEntryData, $uploadTreeTableName) : false;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return int
   */
  public function countPlainFiles(ItemTreeBounds $itemTreeBounds)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();

    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $parameters = array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    $uploadCondition = $this->handleUploadIdForTable($uploadTreeTableName, $itemTreeBounds->getUploadId(), $parameters);

    $row = $this->dbManager->getSingleRow("SELECT count(*) as count FROM $uploadTreeTableName
        WHERE  lft BETWEEN $1 AND $2
          $uploadCondition
          AND ((ufile_mode & (3<<28))=0)
          AND pfile_fk != 0",
        $parameters, $stmt);
    return intval($row["count"]);
  }

  private function handleUploadIdForTable($uploadTreeTableName, $uploadId, &$parameters)
  {
    if ($uploadTreeTableName === "uploadtree" || $uploadTreeTableName === "uploadtree_a") {
      $parameters[] = $uploadId;
      return " AND upload_fk = $" . count($parameters) . " ";
    } else {
      return "";
    }
  }

  /**
   * @return array
   */
  public function getStatusTypeMap()
  {
    global $container;
    /** @var UploadStatus */
    $uploadStatus = $container->get('upload_status.types');
    return $uploadStatus->getMap();
  }

  /**
   * @brief Get the upload status.
   * @param int $uploadId Upload to get status for
   * @param int $groupId  Effective group
   * @return integer Status fk or 1 if not found
   * @throws Exception if upload not accessible.
   */
  public function getStatus($uploadId, $groupId)
  {
    if ($this->isAccessible($uploadId, $groupId)) {
      $row = $this->dbManager->getSingleRow("SELECT status_fk " .
        "FROM upload_clearing WHERE upload_fk=$1 AND group_fk=$2;",
        array($uploadId, $groupId));
      if (false === $row) {
        return 1;
      }
      return $row['status_fk'];
    } else {
      throw new \Exception("permission denied");
    }
  }


  /**
   * @brief Get the upload assignee id.
   * @param int $uploadId Upload to get assignee id
   * @param int $groupId  Effective group
   * @return integer 1 if not found
   * @throws Exception if upload not accessible.
   */
  public function getAssignee($uploadId, $groupId)
  {
    if ($this->isAccessible($uploadId, $groupId)) {
      $row = $this->dbManager->getSingleRow("SELECT assignee FROM upload_clearing WHERE upload_fk=$1 AND group_fk=$2;",
       array($uploadId, $groupId));
      if (false === $row) {
        return 1;
      }
      return $row['assignee'];
    } else {
      throw new \Exception("permission denied");
    }
  }


  /**
   * \brief Get the uploadtree table name for this upload_pk
   *        If upload_pk does not exist, return "uploadtree".
   *
   * \param $upload_pk
   *
   * \return uploadtree table name
   */
  public function getUploadtreeTableName($uploadId)
  {
    if (!empty($uploadId)) {
      $statementName = __METHOD__;
      $row = $this->dbManager->getSingleRow(
          "SELECT uploadtree_tablename FROM upload WHERE upload_pk=$1",
          array($uploadId),
          $statementName
      );
      if (!empty($row['uploadtree_tablename'])) {
        return $row['uploadtree_tablename'];
      }
    }
    return "uploadtree";
  }

  /**
   * @param int $uploadId
   * @param int $itemId
   * @return Item|null
   */
  public function getNextItem($uploadId, $itemId, $options = null)
  {
    return $this->getItemByDirection($uploadId, $itemId, self::DIR_FWD, $options);
  }

  /**
   * @param $uploadId
   * @param $itemId
   * @param $options
   * @return mixed
   */
  public function getPreviousItem($uploadId, $itemId, $options = null)
  {
    return $this->getItemByDirection($uploadId, $itemId, self::DIR_BCK, $options);
  }

  const DIR_FWD = 1;
  const DIR_BCK = -1;
  const NOT_FOUND = null;


  /**
   * @param $uploadId
   * @param $itemId
   * @param $direction
   * @param $options
   * @return Item|null
   */
  public function getItemByDirection($uploadId, $itemId, $direction, $options)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);
    $originItem = $this->getUploadEntry($itemId, $uploadTreeTableName);
    $originLft = $originItem['lft'];

    $options[UploadTreeProxy::OPT_ITEM_FILTER] = " AND ut.ufile_mode & (3<<28) = 0";
    $uploadTreeViewName = 'items2care';

    if ($direction == self::DIR_FWD) {
      $uploadTreeViewName .= 'fwd';
      $options[UploadTreeProxy::OPT_ITEM_FILTER] .= " AND lft>$1";
      $order = 'ASC';
    } else {
      $uploadTreeViewName .= 'bwd';
      $options[UploadTreeProxy::OPT_ITEM_FILTER] .= " AND lft<$1";
      $order = 'DESC';
    }

    $uploadTreeView = new UploadTreeProxy($uploadId, $options, $uploadTreeTableName, $uploadTreeViewName);
    $statementName = __METHOD__ . ".$uploadTreeViewName.";
    $query = $uploadTreeView->getDbViewQuery()." ORDER BY lft $order";

    $newItemRow = $this->dbManager->getSingleRow("$query LIMIT 1", array($originLft), $statementName);
    if ($newItemRow) {
      return $this->createItem($newItemRow, $uploadTreeTableName);
    } else {
      return self::NOT_FOUND;
    }
  }


  /**
   * @param $uploadId
   * @return int uploadtreeId of top item
   */
  public function getUploadParent($uploadId)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);
    $statementname = __METHOD__ . $uploadTreeTableName;

    $parent = $this->dbManager->getSingleRow(
        "SELECT uploadtree_pk
            FROM $uploadTreeTableName
            WHERE upload_fk=$1 AND parent IS NULL", array($uploadId), $statementname);
    if (false === $parent) {
      throw new \Exception("Missing upload tree parent for upload");
    }
    return $parent['uploadtree_pk'];
  }

  public function getLeftAndRight($uploadtreeID, $uploadTreeTableName = "uploadtree")
  {
    $statementName = __METHOD__ . $uploadTreeTableName;
    $leftRight = $this->dbManager->getSingleRow(
        "SELECT lft,rgt FROM $uploadTreeTableName WHERE uploadtree_pk = $1",
        array($uploadtreeID), $statementName
    );

    return array($leftRight['lft'], $leftRight['rgt']);
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param UploadTreeProxy $uploadTreeView
   * @return int
   */
  public function getContainingFileCount(ItemTreeBounds $itemTreeBounds, UploadTreeProxy $uploadTreeView)
  {
    $sql = "SELECT count(*) FROM " . $uploadTreeView->getDbViewName() . " WHERE lft BETWEEN $1 AND $2";
    $result = $this->dbManager->getSingleRow($sql
        , array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight()), __METHOD__ . $uploadTreeView->asCTE());
    return $result['count'];
  }

  /**
   * @var ItemTreeBounds $itemTreeBounds
   * @param string $addCondition
   * @param array $addParameters
   * @return Item[]
   */
  public function getContainedItems(ItemTreeBounds $itemTreeBounds, $addCondition = "", $addParameters = array())
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();

    $statementName = __METHOD__ . ".$uploadTreeTableName";

    $view = new UploadTreeViewProxy($itemTreeBounds, array(UploadTreeViewProxy::CONDITION_PLAIN_FILES));

    $this->dbManager->prepare($statementName,
        $view->asCTE() . " SELECT * FROM " . $view->getDbViewName() ." ut ".
        ($addCondition ? " WHERE " . $addCondition : ''));
    $res = $this->dbManager->execute($statementName, $addParameters);
    $items = array();

    while ($row = $this->dbManager->fetchArray($res)) {
      $items[] = $this->createItem($row, $uploadTreeTableName);
    }
    $this->dbManager->freeResult($res);
    return $items;
  }

  /**
   * @param int $uploadId
   * @param int $reusedUploadId
   * @param int $groupId
   * @param int $reusedGroupId
   * @param int $reuseMode
   */
  public function addReusedUpload($uploadId, $reusedUploadId, $groupId, $reusedGroupId, $reuseMode=0)
  {
    $this->dbManager->insertTableRow('upload_reuse',
            array('upload_fk'=>$uploadId, 'group_fk'=> $groupId, 'reused_upload_fk'=>$reusedUploadId, 'reused_group_fk'=>$reusedGroupId,'reuse_mode'=>$reuseMode));
  }

  /**
   * @param int $uploadId
   * @param int $groupId
   * @return array Assoc array of reused_upload_fk, reused_group_fk and
   *               reuse_mode
   */
  public function getReusedUpload($uploadId, $groupId)
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
        "SELECT reused_upload_fk, reused_group_fk, reuse_mode FROM upload_reuse WHERE upload_fk = $1 AND group_fk=$2 ORDER BY date_added DESC");
    $res = $this->dbManager->execute($statementName, array($uploadId, $groupId));
    $reusedPairs = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $reusedPairs;
  }

  /**
   * @param array $uploadEntry
   * @param string $uploadTreeTableName
   * @return Item
   */
  protected function createItem($uploadEntry, $uploadTreeTableName)
  {
    $itemTreeBounds = new ItemTreeBounds(
        intval($uploadEntry['uploadtree_pk']),
        $uploadTreeTableName,
        intval($uploadEntry['upload_fk']),
        intval($uploadEntry['lft']), intval($uploadEntry['rgt']));

    $parent = $uploadEntry['parent'];
    return new Item(
        $itemTreeBounds, $parent !== null ? intval($parent) : null, intval($uploadEntry['pfile_fk']), intval($uploadEntry['ufile_mode']), $uploadEntry['ufile_name']
    );
  }

  /**
   * @param array $uploadEntryData
   * @param string $uploadTreeTableName
   * @throws Exception
   * @return ItemTreeBounds
   */
  protected function createItemTreeBounds($uploadEntryData, $uploadTreeTableName)
  {
    if ($uploadEntryData === false) {
      throw new Exception("did not find uploadTreeId in $uploadTreeTableName");
    }
    return new ItemTreeBounds(intval($uploadEntryData['uploadtree_pk']), $uploadTreeTableName, intval($uploadEntryData['upload_fk']), intval($uploadEntryData['lft']), intval($uploadEntryData['rgt']));
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param bool $isFlat plain files from sub*folders instead of folders
   * @return array
   */
  public function countNonArtifactDescendants(ItemTreeBounds $itemTreeBounds, $isFlat=true)
  {
    $stmt=__METHOD__;
    $sql = "SELECT count(*) FROM ".$itemTreeBounds->getUploadTreeTableName()." ut "
         . "WHERE ut.upload_fk=$1";
    $params = array($itemTreeBounds->getUploadId());
    if (!$isFlat) {
      $stmt = __METHOD__.'.parent';
      $params[] = $itemTreeBounds->getItemId();
      $sql .= " AND ut.ufile_mode & (1<<28) = 0 AND ut.realparent = $2";
    } else {
      $params[] = $itemTreeBounds->getLeft();
      $params[] = $itemTreeBounds->getRight();
      $sql .= " AND ut.ufile_mode & (3<<28) = 0 AND (ut.lft BETWEEN $2 AND $3)";
    }

    $descendants = $this->dbManager->getSingleRow($sql,$params);
    return $descendants['count'];
  }


  public function isAccessible($uploadId, $groupId)
  {
    return $this->permissionDao->isAccessible($uploadId, $groupId);
  }

  public function isEditable($uploadId, $groupId)
  {
    return $this->permissionDao->isEditable($uploadId, $groupId);
  }

  public function makeAccessibleToGroup($uploadId, $groupId, $perm=null)
  {
    $this->permissionDao->makeAccessibleToGroup($uploadId, $groupId, $perm);
  }

  public function makeAccessibleToAllGroupsOf($uploadId, $userId, $perm=null)
  {
    $this->permissionDao->makeAccessibleToAllGroupsOf($uploadId, $userId, $perm);
  }

  /**
   * @param int $uploadId
   * @return array with keys sha1, md5, sha256
   */
  public function getUploadHashes($uploadId)
  {
    $pfile = $this->dbManager->getSingleRow('SELECT pfile.* FROM upload, pfile WHERE upload_pk=$1 AND pfile_fk=pfile_pk',
        array($uploadId), __METHOD__);
    return array('sha1'=>$pfile['pfile_sha1'],'md5'=>$pfile['pfile_md5'],'sha256'=>$pfile['pfile_sha256']);
  }

  /**
   * @param int $itemId
   * @param string $uploadId
   * @param string $uploadtreeTablename
   * @return array
   */
  public function getFatItemArray($itemId,$uploadId,$uploadtreeTablename)
  {
    $sqlChildrenOf = "SELECT COUNT(*) FROM $uploadtreeTablename s
         WHERE ufile_mode&(1<<28)=0 and s.upload_fk=$2 AND s.realparent=";
    $sql="WITH RECURSIVE item_path (item_id,num_children,depth,ufile_mode,ufile_name) AS (
        SELECT uploadtree_pk item_id, ($sqlChildrenOf $1) num_children, 0 depth, ufile_mode, ufile_name
          FROM $uploadtreeTablename WHERE upload_fk=$2 AND uploadtree_pk=$1
        UNION
        SELECT uploadtree_pk item_id, ($sqlChildrenOf ut.uploadtree_pk) num_children,
               item_path.depth+1 depth, ut.ufile_mode, item_path.ufile_name||'/'||ut.ufile_name ufile_name
          FROM $uploadtreeTablename ut INNER JOIN item_path ON item_id=ut.realparent
          WHERE upload_fk=$2 AND ut.ufile_mode&(1<<28)=0 AND num_children<2
        )
        SELECT * FROM item_path WHERE num_children!=1 OR ufile_mode&(1<<29)=0 ORDER BY depth DESC LIMIT 1";
    return $this->dbManager->getSingleRow($sql,array($itemId, $uploadId),__METHOD__.$uploadtreeTablename);
  }

  /**
   * @param int $itemId
   * @param string $uploadId
   * @param string $uploadtreeTablename
   * @return int
   */
  public function getFatItemId($itemId,$uploadId,$uploadtreeTablename)
  {
    $itemRow = $this->getFatItemArray($itemId,$uploadId,$uploadtreeTablename);
    return $itemRow['item_id'];
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return array
   */
  public function getPFileDataPerFileName(ItemTreeBounds $itemTreeBounds)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName;
    $param = array();

    $param[] = $itemTreeBounds->getLeft();
    $param[] = $itemTreeBounds->getRight();
    $condition = " lft BETWEEN $1 AND $2";
    $condition .= " AND (ufile_mode & (1<<28)) = 0";

    if ('uploadtree_a' == $uploadTreeTableName) {
      $param[] = $itemTreeBounds->getUploadId();
      $condition .= " AND upload_fk=$".count($param);
    }

    $sql = "
SELECT ufile_name, uploadtree_pk, lft, rgt, ufile_mode,
       pfile_pk, pfile_md5, pfile_sha1, pfile_sha256
FROM $uploadTreeTableName
  LEFT JOIN pfile
    ON pfile_fk = pfile_pk
WHERE $condition
ORDER BY lft asc
";

    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName, $param);
    $pfilePerFileName = array();

    $row = $this->dbManager->fetchArray($result);
    $pathStack = array($row['ufile_name']);
    $rgtStack = array($row['rgt']);
    $lastLft = $row['lft'];
    $this->addToPFilePerFileName($pfilePerFileName, $pathStack, $row);
    while ($row = $this->dbManager->fetchArray($result)) {
      if ($row['lft'] < $lastLft) {
        continue;
      }

      $this->updateStackState($pathStack, $rgtStack, $lastLft, $row);
      $this->addToPFilePerFileName($pfilePerFileName, $pathStack, $row);
    }
    $this->dbManager->freeResult($result);
    return $pfilePerFileName;
  }

  private function updateStackState(&$pathStack, &$rgtStack, &$lastLft, $row)
  {
    if ($row['lft'] >= $lastLft) {
      while (count($rgtStack) > 0 && $row['lft'] > $rgtStack[count($rgtStack)-1]) {
        array_pop($pathStack);
        array_pop($rgtStack);
      }
      if ($row['lft'] > $lastLft) {
        $pathStack[] = $row['ufile_name'];
        $rgtStack[] = $row['rgt'];
        $lastLft = $row['lft'];
      }
    }
  }

  private function addToPFilePerFileName(&$pfilePerFileName, $pathStack, $row)
  {
    if (($row['ufile_mode'] & (1 << 29)) == 0) {
      $path = implode('/', $pathStack);
      $pfilePerFileName[$path]['pfile_pk'] = $row['pfile_pk'];
      $pfilePerFileName[$path]['uploadtree_pk'] = $row['uploadtree_pk'];
      $pfilePerFileName[$path]['md5'] = $row['pfile_md5'];
      $pfilePerFileName[$path]['sha1'] = $row['pfile_sha1'];
      $pfilePerFileName[$path]['sha256'] = $row['pfile_sha256'];
    }
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param String $hashAlgo
   * @return array
   */
  public function getPFilesDataPerHashAlgo(ItemTreeBounds $itemTreeBounds, $hashAlgo="sha1")
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName;
    $param = array();

    $param[] = $itemTreeBounds->getLeft();
    $param[] = $itemTreeBounds->getRight();
    $condition = " lft BETWEEN $1 AND $2";
    $condition .= " AND (ufile_mode & (1<<28)) = 0";

    if ('uploadtree_a' == $uploadTreeTableName) {
      $param[] = $itemTreeBounds->getUploadId();
      $condition .= " AND upload_fk=$".count($param);
    }
    $condition .= " AND pfile_$hashAlgo IS NOT NULL";

    $sql = "
SELECT pfile_fk, uploadtree_pk, ufile_mode, pfile_$hashAlgo as hash
FROM $uploadTreeTableName
  LEFT JOIN pfile
    ON pfile_fk = pfile_pk
WHERE $condition
ORDER BY lft asc
";

    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName, $param);

    $pfilePerHashAlgo = array();
    while ($row = $this->dbManager->fetchArray($result)) {
      if (($row['ufile_mode']&(1<<29)) == 0) {
        $pfilePerHashAlgo[strtolower($row['hash'])][] = array('pfile_pk' => $row['pfile_fk'],
                                                              'uploadtree_pk' => $row['uploadtree_pk']);
      }
    }
    $this->dbManager->freeResult($result);
    return $pfilePerHashAlgo;
  }


   /* @param int $uploadId
   * @return array
   */
  public function getReportInfo($uploadId)
  {
    $stmt = __METHOD__;
    $sql = "SELECT * FROM report_info WHERE upload_fk = $1";
    $row = $this->dbManager->getSingleRow($sql, array($uploadId), $stmt);

    if (empty($row)) {
      $this->dbManager->begin();
      $stmt = __METHOD__.'ifempty';
      $sql = "INSERT INTO report_info (upload_fk) VALUES ($1) RETURNING *";
      $row = $this->dbManager->getSingleRow($sql, array($uploadId), $stmt);
      $this->dbManager->commit();
    }
    return $row;
  }

  /**
   * @brief Update report info for upload
   * @param int $uploadId  Upload ID to update
   * @param string $column Column to update
   * @param string|array $value New value
   * @return boolean True on success
   */
  public function updateReportInfo($uploadId, $column, $value)
  {
    if ($column === "ri_unifiedcolumns") {
      $value = json_decode($value, true);
      $oldValues = $this->getReportInfo($uploadId)["ri_unifiedcolumns"];
      if (!empty($oldValues)) {
        $oldValues = json_decode($oldValues, true);
      } else {
        $oldValues = self::UNIFIED_REPORT_HEADINGS;
      }
      foreach ($value as $key => $val) {
        $newValText = array_keys($val)[0];
        $newValValue = array_values($val)[0];
        $newValValue = ($newValValue === true || $newValValue == "true") ? "on" : null;
        $oldValues[$key] = [$newValText => $newValValue];
      }
      $value = json_encode($oldValues);
    } elseif ($column === "ri_excluded_obligations") {
      $value = json_decode($value, true);
      $oldValues = $this->getReportInfo($uploadId)["ri_excluded_obligations"];
      if (!empty($oldValues)) {
        $oldValues = json_decode($oldValues, true);
      } else {
        $oldValues = [];
      }
      foreach ($value as $key => $newValue) {
        $oldValues[$key] = $newValue;
      }
      $value = json_encode($oldValues);
    } elseif ($column === "ri_globaldecision") {
      $value = filter_var($value, FILTER_VALIDATE_BOOL);
    }

    $sql = "UPDATE report_info SET $column = $2 WHERE upload_fk = $1;";
    $stmt = __METHOD__ . "updateReportInfo" . $column;
    $this->dbManager->getSingleRow($sql, [$uploadId, $value], $stmt);
    return true;
  }

  /* @param int $uploadId
   * @return ri_globaldecision
   */
  public function getGlobalDecisionSettingsFromInfo($uploadId, $setGlobal=null)
  {
    $stmt = __METHOD__ . 'get';
    $sql = "SELECT ri_globaldecision FROM report_info WHERE upload_fk = $1";
    $row = $this->dbManager->getSingleRow($sql, array($uploadId), $stmt);
    if (empty($row)) {
      if ($setGlobal === null) {
        // Old upload, set default value to enable
        $setGlobal = 1;
      }
      $stmt = __METHOD__ . 'ifempty';
      $sql = "INSERT INTO report_info (upload_fk, ri_globaldecision) VALUES ($1, $2) RETURNING ri_globaldecision";
      $row = $this->dbManager->getSingleRow($sql, array($uploadId, $setGlobal), $stmt);
    }

    if (!empty($setGlobal)) {
      $stmt = __METHOD__ . 'update';
      $sql = "UPDATE report_info SET ri_globaldecision = $2 WHERE upload_fk = $1 RETURNING ri_globaldecision";
      $row = $this->dbManager->getSingleRow($sql, array($uploadId, $setGlobal), $stmt);
    }

    return $row['ri_globaldecision'];
  }

  /**
   * @brief Get Pfile hashes from the pfile id
   * @param $pfilePk
   * @return array
   */
  public function getUploadHashesFromPfileId($pfilePk)
  {
    $stmt = __METHOD__."getUploadHashesFromPfileId";
    $sql = "SELECT * FROM pfile WHERE pfile_pk = $1";
    $row = $this->dbManager->getSingleRow($sql, array($pfilePk), $stmt);

    return ["sha1" => $row["pfile_sha1"], "md5" => $row["pfile_md5"], "sha256" => $row["pfile_sha256"]];
  }

  /**
   * @param int $uploadId
   * @param int $reusedUploadId
   * @return bool
   */
  public function insertReportConfReuse($uploadId, $reusedUploadId)
  {
    $stmt = __METHOD__ . ".checkReused";
    $sql = "SELECT 1 AS exists FROM report_info WHERE upload_fk = $1 LIMIT 1;";
    $row = $this->dbManager->getSingleRow($sql, array($reusedUploadId), $stmt);

    if (empty($row['exists'])) {
      return false;
    }

    $this->dbManager->begin();

    $stmt = __METHOD__ . ".removeExists";
    $sql = "DELETE FROM report_info WHERE upload_fk = $1;";
    $this->dbManager->getSingleRow($sql, [$uploadId], $stmt);

    $stmt = __METHOD__ . ".getAllColumns";
    $sql = "SELECT string_agg(column_name, ',') AS columns
      FROM information_schema.columns
      WHERE table_name = 'report_info'
        AND column_name != 'ri_pk'
        AND column_name != 'upload_fk';";
    $row = $this->dbManager->getSingleRow($sql, [], $stmt);
    $columns = $row['columns'];

    $stmt = __METHOD__."CopyinsertReportConfReuse";
    $this->dbManager->prepare($stmt,
      "INSERT INTO report_info(upload_fk, $columns)
      SELECT $1, $columns
      FROM report_info WHERE upload_fk = $2"
    );
    $this->dbManager->freeResult($this->dbManager->execute(
      $stmt, array($uploadId, $reusedUploadId)
    ));

    $this->dbManager->commit();
    return true;
  }
}
