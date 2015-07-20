<?php
/*
Copyright (C) 2014-2015, Siemens AG
Authors: Andreas Würl, Steffen Weber

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exception;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\Lib\Proxy\UploadTreeViewProxy;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

require_once(dirname(dirname(__FILE__)) . "/common-dir.php");

class UploadDao extends Object
{
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
    if ($uploadEntry)
    {
      $uploadEntry['tablename'] = $uploadTreeTableName;
    }
    return $uploadEntry;
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
  public function getParentItemBounds($uploadId, $uploadTreeTableName = NULL)
  {
    if ($uploadTreeTableName === null)
    {
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
    $fileCount = intval($row["count"]);
    return $fileCount;
  }

  private function handleUploadIdForTable($uploadTreeTableName, $uploadId, &$parameters)
  {
    if ($uploadTreeTableName === "uploadtree" || $uploadTreeTableName === "uploadtree_a")
    {
      $parameters[] = $uploadId;
      return " AND upload_fk = $" . count($parameters) . " ";
    } else
    {
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
   * @brief unused function
   */
  public function getStatus($uploadId, $groupId)
  {
    if ($this->isAccessible($uploadId, $groupId)) {
      $row = $this->dbManager->getSingleRow("SELECT status_fk FROM upload_clearing WHERE upload_fk = $1", array($uploadId));
      if (false === $row) {
        throw new \Exception("cannot find uploadId=$uploadId");
      }
      return $row['status_fk'];
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
    if (!empty($uploadId))
    {
      $statementName = __METHOD__;
      $row = $this->dbManager->getSingleRow(
          "SELECT uploadtree_tablename FROM upload WHERE upload_pk=$1",
          array($uploadId),
          $statementName
      );
      if (!empty($row['uploadtree_tablename']))
      {
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
   * @return Item|null
   */
  public function getItemByDirection($uploadId, $itemId, $direction, $options)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);
    $originItem = $this->getUploadEntry($itemId, $uploadTreeTableName);
    $originLft = $originItem['lft'];

    $options[UploadTreeProxy::OPT_ITEM_FILTER] = " AND ut.ufile_mode & (3<<28) = 0";
    $uploadTreeViewName = 'items2care';
    
    if($direction == self::DIR_FWD)
    {
      $uploadTreeViewName .= 'fwd';
      $options[UploadTreeProxy::OPT_ITEM_FILTER] .= " AND lft>$1";
      $order = 'ASC';
    }
    else
    {
      $uploadTreeViewName .= 'bwd';
      $options[UploadTreeProxy::OPT_ITEM_FILTER] .= " AND lft<$1";
      $order = 'DESC';
    }
    
    $uploadTreeView = new UploadTreeProxy($uploadId, $options, $uploadTreeTableName, $uploadTreeViewName);
    $statementName = __METHOD__ . ".$uploadTreeViewName.";
    $query = $uploadTreeView->getDbViewQuery()." ORDER BY lft $order";

    $newItemRow = $this->dbManager->getSingleRow("$query LIMIT 1", array($originLft), $statementName);
    if ($newItemRow)
    {
      return $this->createItem($newItemRow, $uploadTreeTableName);
    }
    else
    {
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
    if(false === $parent) {
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
   * @var ItemTreeBounds $itemTreeBounds
   * @param $uploadTreeView
   * @return int
   */
  public function getContainingFileCount(ItemTreeBounds $itemTreeBounds, UploadTreeProxy $uploadTreeView)
  {
    $sql = "SELECT count(*) FROM " . $uploadTreeView->getDbViewName() . " WHERE lft BETWEEN $1 AND $2";
    $result = $this->dbManager->getSingleRow($sql
        , array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight()), __METHOD__ . $uploadTreeView->asCTE());
    $output = $result['count'];
    return $output;
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
   * @return int
   */
  public function getReusedUpload($uploadId, $groupId)
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
        "SELECT reused_upload_fk, reused_group_fk, reuse_mode FROM upload_reuse WHERE upload_fk = $1 AND group_fk=$2");
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
    $item = new Item(
        $itemTreeBounds, $parent !== null ? intval($parent) : null, intval($uploadEntry['pfile_fk']), intval($uploadEntry['ufile_mode']), $uploadEntry['ufile_name']
    );
    return $item;
  }

  /**
   * @param array $uploadEntryData
   * @param string $uploadTreeTableName
   * @throws Exception
   * @return ItemTreeBounds
   */
  protected function createItemTreeBounds($uploadEntryData, $uploadTreeTableName)
  {
    if ($uploadEntryData === FALSE)
    {
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
    if (!$isFlat)
    {
      $stmt = __METHOD__.'.parent';
      $params[] = $itemTreeBounds->getItemId();
      $sql .= " AND ut.ufile_mode & (1<<28) = 0 AND ut.realparent = $2";
    }
    else
    {
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
   * @return array with keys sha1, md5
   */
  public function getUploadHashes($uploadId)
  {
    $pfile = $this->dbManager->getSingleRow('SELECT pfile.* FROM upload, pfile WHERE upload_pk=$1 AND pfile_fk=pfile_pk',
        array($uploadId), __METHOD__);
    return array('sha1'=>$pfile['pfile_sha1'],'md5'=>$pfile['pfile_md5']);
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
}
