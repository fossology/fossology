<?php
/*
Copyright (C) 2014, Siemens AG
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

use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Data\Tree\UploadTreeView;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

require_once(dirname(dirname(__FILE__)) . "/common-dir.php");

class UploadDao extends Object
{

  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var Logger
   */
  private $logger;

  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className());
  }

  /**
   * @param int $itemId
   * @param UploadTreeView $uploadTreeView
   * @return Item
   */
  public function getUploadEntryFromView($itemId, UploadTreeView $uploadTreeView)
  {
    $uploadTreeViewQuery = $uploadTreeView->getUploadTreeViewQuery();
    $stmt = __METHOD__ . ".$uploadTreeViewQuery";
    $uploadEntry = $this->dbManager->getSingleRow("$uploadTreeViewQuery SELECT * FROM UploadTreeView WHERE uploadtree_pk = $1",
        array($itemId), $stmt);

    $item = $this->createItem($uploadEntry, $uploadTreeView->getUploadTreeTableName());
    return $item;
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
    $uploadEntry['tablename'] = $uploadTreeTableName;
    return $uploadEntry;
  }

  /**
   * @param $uploadId
   * @return array
   */
  public function getUploadInfo($uploadId)
  {
    $stmt = __METHOD__;
    $uploadEntry = $this->dbManager->getSingleRow("SELECT * FROM upload WHERE upload_pk = $1",
        array($uploadId), $stmt);
    return $uploadEntry;
  }

  /**
   * @param $uploadTreeId
   * @param $uploadTreeTableName
   * @return ItemTreeBounds
   */
  public function getFileTreeBounds($uploadTreeId, $uploadTreeTableName = "uploadtree")
  {
    $uploadEntry = $this->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if ($uploadEntry === FALSE)
    {
      $this->logger->addWarning("did not find uploadTreeId $uploadTreeId in $uploadTreeTableName");
      return new ItemTreeBounds($uploadTreeId, $uploadTreeTableName, 0, 0, 0);
    }
    return new ItemTreeBounds($uploadTreeId, $uploadTreeTableName, intval($uploadEntry['upload_fk']), intval($uploadEntry['lft']), intval($uploadEntry['rgt']));
  }

  /**
   * @param $uploadTreeId
   * @param $uploadId
   * @return ItemTreeBounds
   */
  public function getFileTreeBoundsFromUploadId($uploadTreeId, $uploadId)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);
    return $this->getFileTreeBounds($uploadTreeId, $uploadTreeTableName);
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return int
   */
  public function countPlainFiles(ItemTreeBounds $itemTreeBounds)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $row = $this->dbManager->getSingleRow("SELECT count(*) as count FROM $uploadTreeTableName
        WHERE upload_fk = $1
          AND lft BETWEEN $2 AND $3
          AND ((ufile_mode & (3<<28))=0)
          AND pfile_fk != 0",
        array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight()), $stmt);
    $fileCount = intval($row["count"]);
    return $fileCount;
  }


  /**
   * @return DatabaseEnum[]
   */
  public function getStatusTypes()
  {
    $clearingTypes = array();
    $statementN = __METHOD__;

    $this->dbManager->prepare($statementN, "select * from upload_status");
    $res = $this->dbManager->execute($statementN);
    while ($rw = pg_fetch_assoc($res))
    {
      $clearingTypes[] = new DatabaseEnum($rw['status_pk'], $rw['meaning']);
    }
    pg_free_result($res);
    return $clearingTypes;
  }

  /**
   * @return array
   */
  public function getStatusTypeMap()
  {
    return $this->dbManager->createMap('upload_status', 'status_pk', 'meaning');
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
          "select uploadtree_tablename from upload where upload_pk=$1",
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
   * @return mixed
   */
  public function getItemByDirection($uploadId, $itemId, $direction, $options)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);
    $uploadTreeView = $this->getNavigableUploadTreeView($uploadId, $itemId, $options, $uploadTreeTableName);

    $item = $this->getUploadEntryFromView($itemId, $uploadTreeView);

    while (true)
    {
      $nextItem = $this->findNextItem($item, $direction, $uploadTreeView);

      if ($nextItem !== null)
      {
        return $nextItem;
      }

      $parent = $item->getParentId();

      if (isset($parent))
      {
        $item = $this->getUploadEntryFromView($parent, $uploadTreeView);
      } else
      {
        return self::NOT_FOUND;
      }
    }
  }

  /**
   * @param $item
   * @param $direction
   * @param $uploadTreeView
   * @return mixed
   */
  protected function findNextItem(Item $item, $direction, UploadTreeView $uploadTreeView)
  {
    $isFile = $item->isFile();
    $indexIncrement = $direction == self::DIR_FWD ? 1 : -1;

    if ($isFile)
    {
      $parent = $item->getParentId();
      $parentSize = $this->getParentSize($parent, $uploadTreeView);
      $targetIndex = $this->getItemIndex($parent, $item, $uploadTreeView) + $indexIncrement;
    } else
    {
      $parent = $item->getId();
      $parentSize = $this->getParentSize($parent, $uploadTreeView);
      $targetIndex = $direction == self::DIR_FWD ? 0 : $parentSize - 1;

      if ($item->getParentId() === null && $direction !== self::DIR_FWD)
      {
        return self::NOT_FOUND;
      }
    }

    $nextItem = null;

    while ($targetIndex >= 0 && $targetIndex < $parentSize && $nextItem === null)
    {
      $nextItem = $this->getNewItemByIndex($parent, $targetIndex, $uploadTreeView);
      $targetIndex += $indexIncrement;

      if ($nextItem === null)
      {
        continue;
      }

      if (!$nextItem->isFile())
      {
        $nextItem = $this->findNextItem($nextItem, $direction, $uploadTreeView);
      }
    }
    return $nextItem;
  }

  /**
   * @param $parent
   * @param $direction
   * @param $uploadTreeView
   * @return mixed|null
   */
  protected function findNextItemOutsideCurrentParent($parent, $direction, $uploadTreeView)
  {

  }


  /**
   * @param int $parent
   * @param Item $item
   * @param UploadTreeView $uploadTreeView
   * @return int
   */
  protected function getItemIndex($parent, Item $item, UploadTreeView $uploadTreeView)
  {
    $uploadTreeViewQuery = $uploadTreeView->getUploadTreeViewQuery();

    $sql = "$uploadTreeViewQuery
    select row_number from (
      select
        row_number() over (order by ufile_name),
        uploadtree_pk
      from uploadTreeView where parent=$1
    ) as index where uploadtree_pk=$2";

    $currentIndexResult = $this->dbManager->getSingleRow($sql, array($parent, $item->getId()), __METHOD__ . "_current_offset" . $uploadTreeViewQuery);

    $currentIndex = $currentIndexResult['row_number'] - 1;
    return $currentIndex;
  }

  /**
   * @param int $parent
   * @param UploadTreeView $uploadTreeView
   * @return int
   */
  protected function getParentSize($parent, UploadTreeView $uploadTreeView)
  {
    $uploadTreeViewQuery = $uploadTreeView->getUploadTreeViewQuery();

    $currentCountResult = $this->dbManager->getSingleRow("$uploadTreeViewQuery
        select count(*)
               from uploadTreeView
               where parent=$1",
        array($parent), __METHOD__ . "_current_count");
    $currentCount = $currentCountResult['count'];
    return $currentCount;
  }

  /**
   * @param int $parent
   * @param int $targetOffset
   * @param UploadTreeView $uploadTreeView
   * @return Item
   */
  protected function getNewItemByIndex($parent, $targetOffset, UploadTreeView $uploadTreeView)
  {
    $uploadTreeViewQuery = $uploadTreeView->getUploadTreeViewQuery();

    $statementName = __METHOD__;
    $theQuery = "$uploadTreeViewQuery
      SELECT *
        from uploadTreeView
        where parent=$1
        order by ufile_name offset $2 limit 1";

    $newItemResult = $this->dbManager->getSingleRow($theQuery
        , array($parent, $targetOffset), $statementName);

    return $this->createItem($newItemResult, $uploadTreeView->getUploadTreeTableName());
  }


  /**
   * @param $uploadId
   * @return mixed
   */
  public function getUploadParent($uploadId)
  {
    $uploadTreeTableName = GetUploadtreeTableName($uploadId);
    $statementname = __METHOD__ . $uploadTreeTableName;

    $parent = $this->dbManager->getSingleRow(
        "select uploadtree_pk
            from $uploadTreeTableName
            where upload_fk=$1 and lft=1", array($uploadId), $statementname);
    return $parent['uploadtree_pk'];
  }


  public
  function getLeftAndRight($uploadtreeID, $uploadTreeTableName = "uploadtree")
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
   * @return mixed
   */
  protected
  function getContainingFileCount(ItemTreeBounds $itemTreeBounds, UploadTreeView $uploadTreeView)
  {
    $uploadTreeViewQuery = $uploadTreeView->getUploadTreeViewQuery();
    $sql = "$uploadTreeViewQuery
            SELECT count(*) from uploadTreeView where lft BETWEEN $1 and $2
            ";

    $result = $this->dbManager->getSingleRow($sql
        , array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight()), __METHOD__ . $uploadTreeViewQuery);

    $output = $result['count'];
    return $output;
  }

  public
  function getFilesClearedAndFilesToClear(ItemTreeBounds $itemTreeBounds)
  {

    $alreadyClearedUploadTreeView = $this->getFileOnlyUploadTreeView($itemTreeBounds->getUploadId(),
        array('skipThese' => "alreadyCleared"),
        $itemTreeBounds->getUploadTreeTableName());

    $filesThatShouldStillBeCleared = $this->getContainingFileCount($itemTreeBounds, $alreadyClearedUploadTreeView);

    $noLicenseUploadTreeView = $this->getFileOnlyUploadTreeView($itemTreeBounds->getUploadId(),
        array('skipThese' => "noLicense"),
        $itemTreeBounds->getUploadTreeTableName());

    $filesToBeCleared = $this->getContainingFileCount($itemTreeBounds, $noLicenseUploadTreeView);

    $filesCleared = $filesToBeCleared - $filesThatShouldStillBeCleared;
    return array($filesCleared, $filesToBeCleared);

  }

  /**
   * @param int $uploadId
   * @param array $options
   * @param string $uploadTreeTableName
   * @return UploadTreeView
   */
  protected
  function getFileOnlyUploadTreeView($uploadId, $options, $uploadTreeTableName)
  {
    return new UploadTreeView($uploadId, $options, $uploadTreeTableName);
  }

  /**
   * @param int $uploadId
   * @param int $itemId
   * @param array $options
   * @param string $uploadTreeTableName
   * @return UploadTreeView
   */
  protected
  function getNavigableUploadTreeView($uploadId, $itemId, $options, $uploadTreeTableName)
  {
    return new UploadTreeView($uploadId, $options, $uploadTreeTableName, "           OR
                                    ut.ufile_mode & (1<<29) <> 0
                                        OR
                                    ut.uploadtree_pk = $itemId");
  }


  /**
   * @param array $uploadEntry
   * @param string $uploadTreeTableName
   * @return Item
   */
  protected
  function createItem($uploadEntry, $uploadTreeTableName)
  {
    $itemTreeBounds = new ItemTreeBounds($uploadEntry['uploadtree_pk'], $uploadTreeTableName, intval($uploadEntry['upload_fk']), intval($uploadEntry['lft']), intval($uploadEntry['rgt']));

    $parent = $uploadEntry['parent'];
    $item = new Item(
        $itemTreeBounds, $parent !== null ? intval($parent) : null, intval($uploadEntry['pfile_fk']), intval($uploadEntry['ufile_mode']), $uploadEntry['ufile_name']
    );
    return $item;
  }
}