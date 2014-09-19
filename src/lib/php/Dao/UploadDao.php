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
use Fossology\Lib\Data\FileTreeBounds;
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

  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className());
  }

  /**
   * @param $uploadTreeId
   * @param string $uploadTreeTableName
   * @return array
   */
  function getUploadEntry($uploadTreeId, $uploadTreeTableName = "uploadtree")
  {
    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $uploadEntry = $this->dbManager->getSingleRow("SELECT * FROM $uploadTreeTableName WHERE uploadtree_pk = $1",
        array($uploadTreeId), $stmt);
    return $uploadEntry;
  }

  /**
   * @param $uploadId
   * @return array
   */
  function getUploadInfo($uploadId)
  {
    $stmt = __METHOD__;
    $uploadEntry = $this->dbManager->getSingleRow("SELECT * FROM upload WHERE upload_pk = $1",
        array($uploadId), $stmt);
    return $uploadEntry;
  }

  /**
   * @param $uploadTreeId
   * @param $uploadTreeTableName
   * @return FileTreeBounds
   */
  function getFileTreeBounds($uploadTreeId, $uploadTreeTableName = "uploadtree")
  {
    $uploadEntry = $this->getUploadEntry($uploadTreeId, $uploadTreeTableName);
    if ($uploadEntry === FALSE)
    {
      $this->logger->addWarning("did not find uploadTreeId $uploadTreeId in $uploadTreeTableName");
      return new FileTreeBounds($uploadTreeId, $uploadTreeTableName, 0, 0, 0);
    }
    return new FileTreeBounds($uploadTreeId, $uploadTreeTableName, intval($uploadEntry['upload_fk']), intval($uploadEntry['lft']), intval($uploadEntry['rgt']));
  }

  /**
   * @param FileTreeBounds $fileTreeBounds
   * @return int
   */
  public function countPlainFiles(FileTreeBounds $fileTreeBounds)
  {
    $uploadTreeTableName = $fileTreeBounds->getUploadTreeTableName();
    $stmt = __METHOD__ . ".$uploadTreeTableName";
    $row = $this->dbManager->getSingleRow("SELECT count(*) as count FROM $uploadTreeTableName
        WHERE upload_fk = $1
          AND lft BETWEEN $2 AND $3
          AND ((ufile_mode & (3<<28))=0)
          AND pfile_fk != 0",
        array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()), $stmt);
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
  function getUploadtreeTableName($uploadId)
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
   * @param $uploadId
   * @param $item
   * @return mixed
   */
  public function getNextItem($uploadId, $item)
  {
    return $this->getItemByDirection($uploadId, $item, self::DIR_FWD);
  }

  /**
   * @param $uploadId
   * @param $item
   * @return mixed
   */
  public function getPreviousItem($uploadId, $item)
  {
    return $this->getItemByDirection($uploadId, $item, self::DIR_BCK);
  }

  const DIR_FWD = 1;
  const DIR_BCK = -1;
  const NOT_FOUND = null;

  /**
   * @param $uploadId
   * @param $item
   * @param $direction
   * @return mixed
   */
  public function getItemByDirection($uploadId, $item, $direction)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);

    $itemEntry = $this->getUploadEntry($item, $uploadTreeTableName);

    return $this->findNextItem($itemEntry, $direction, $uploadTreeTableName);
  }

  /**
   * @param $itemEntry
   * @param $direction
   * @param $uploadTreeTableName
   * @return mixed
   */
  protected function findNextItem($itemEntry, $direction, $uploadTreeTableName)
  {
    $parent = $itemEntry['parent'];
    $item = $itemEntry['uploadtree_pk'];

    if (isset($parent))
    {
      $currentIndex = $this->getItemIndex($parent, $item, $uploadTreeTableName);
      $parentSize = $this->getParentSize($parent, $uploadTreeTableName);
    } else
    {
      if ($direction == self::DIR_FWD)
      {
        return $this->enterFolder($item, $direction, $uploadTreeTableName);
      }
      return self::NOT_FOUND;
    }

    $targetOffset = $currentIndex + ($direction == self::DIR_FWD ? 1 : -1);
    if ($targetOffset >= 0 && $targetOffset < $parentSize)
    {
      return $this->findNextItemInCurrentParent($targetOffset, $parent, $direction, $uploadTreeTableName);
    } else
    {
      return $this->findNextItemOutsideCurrentParent($parent, $direction, $uploadTreeTableName);
    }
  }

  /**
   * @param $targetOffset
   * @param $parent
   * @param $direction
   * @param $uploadTreeTableName
   * @return mixed
   */
  protected function findNextItemInCurrentParent($targetOffset, $parent, $direction, $uploadTreeTableName)
  {
    $newItem = $this->getNewItemByIndex($parent, $targetOffset, $uploadTreeTableName);
    $newItemEntry = $this->getUploadEntry($newItem, $uploadTreeTableName);

    $newItem = $this->handleNewItem($newItemEntry, $direction, $uploadTreeTableName);

    if ($newItem)
    {
      return $newItem;
    } else
    {
      return $this->findNextItem($newItemEntry, $direction, $uploadTreeTableName);
    }
  }

  /**
   * @param $parent
   * @param $direction
   * @param $uploadTreeTableName
   * @return mixed|null
   */
  protected function findNextItemOutsideCurrentParent($parent, $direction, $uploadTreeTableName)
  {
    if (isset($parent))
    {
      $newItemEntry = $this->getUploadEntry($parent, $uploadTreeTableName);
      return $this->findNextItem($newItemEntry, $direction, $uploadTreeTableName);
    } else
    {
      return self::NOT_FOUND;
    }
  }


  /**
   * @param $newItemEntry
   * @param $direction
   * @param $uploadTreeTableName
   * @return mixed
   */
  protected function handleNewItem($newItemEntry, $direction, $uploadTreeTableName)
  {
    $newItem = $newItemEntry['uploadtree_pk'];

    $fileMode = $newItemEntry['ufile_mode'];
    if (Isartifact($fileMode) || Isdir($fileMode) || Iscontainer($fileMode))
    {
      $folderSize = $newItemEntry['rgt'] - $newItemEntry['lft'];
      if ($folderSize > 1)
      {
        return $this->enterFolder($newItem, $direction, $uploadTreeTableName);
      }
      return self::NOT_FOUND;
    } else
    {
      return $newItem;
    }
  }

  /**
   * @param $item
   * @param $direction
   * @param $uploadTreeTableName
   * @return mixed
   */
  protected function enterFolder($item, $direction, $uploadTreeTableName)
  {
    $name_order = ($direction == self::DIR_FWD ? 'ASC' : 'DESC');

    $statementName = __METHOD__ . "descent_" . $name_order;
    $newItemResult = $this->dbManager->getSingleRow("
select uploadtree_pk from $uploadTreeTableName where parent=$1 order by ufile_name $name_order limit 1", array($item), $statementName);

    $newItemEntry = $this->getUploadEntry($newItemResult['uploadtree_pk'], $uploadTreeTableName);

    return $this->handleNewItem($newItemEntry, $direction, $uploadTreeTableName);
  }


  /**
   * @param $parent
   * @param $item
   * @param $uploadTreeTableName
   * @return mixed
   */
  protected function getItemIndex($parent, $item, $uploadTreeTableName)
  {
    $currentIndexResult = $this->dbManager->getSingleRow("
select row_number from (
  select
    row_number() over (order by ufile_name),
    uploadtree_pk
  from $uploadTreeTableName where parent=$1
) as index where uploadtree_pk=$2", array($parent, $item), __METHOD__ . "_current_offset");

    $currentIndex = $currentIndexResult['row_number'] - 1;
    return $currentIndex;
  }

  /**
   * @param $parent
   * @param $uploadTreeTableName
   * @return mixed
   */
  protected function getParentSize($parent, $uploadTreeTableName)
  {
    $currentCountResult = $this->dbManager->getSingleRow("select count(*) from $uploadTreeTableName where parent=$1", array($parent), __METHOD__ . "_current_count");
    $currentCount = $currentCountResult['count'];
    return $currentCount;
  }

  /**
   * @param $parent
   * @param $targetOffset
   * @param $uploadTreeTableName
   * @return mixed
   */
  protected function getNewItemByIndex($parent, $targetOffset, $uploadTreeTableName)
  {
    $statementName = __METHOD__ . "_offset";
    $newItemResult = $this->dbManager->getSingleRow("
select uploadtree_pk from $uploadTreeTableName where parent=$1 order by ufile_name offset $2 limit 1", array($parent, $targetOffset), $statementName);

    $newItem = $newItemResult['uploadtree_pk'];
    return $newItem;
  }


  /**
   * @param $uploadId
   * @return mixed
   */
  public function getUploadParent($uploadId)
  {

    $uploadTreeTableName = GetUploadtreeTableName($uploadId);
    $statementname = __METHOD__ . $uploadTreeTableName;

    $parent = $this->dbManager->getSingleRow("select uploadtree_pk from $uploadTreeTableName where upload_fk=$1 and lft=1", array($uploadId), $statementname);
    return $parent['uploadtree_pk'];
  }


  public function getLeftAndRight($uploadtreeID, $uploadTreeTableName="uploadtree"  )
  {
    $statementName = __METHOD__.$uploadTreeTableName;
    $leftRight = $this->dbManager->getSingleRow(
              "SELECT lft,rgt FROM $uploadTreeTableName WHERE uploadtree_pk = $1",
              array($uploadtreeID), $statementName
    );

    return array($leftRight['lft'], $leftRight['rgt']);
  }
}