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
use Fossology\Lib\Data\Tree\ItemTreeBounds;
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
   * @param $uploadTreeId
   * @param string $uploadTreeView
   * @return array
   */
  public function getUploadEntryFromView($uploadTreeId, $uploadTreeView)
  {
    $stmt = __METHOD__ . ".$uploadTreeView";
    $uploadEntry = $this->dbManager->getSingleRow("$uploadTreeView SELECT * FROM  UploadTreeView WHERE uploadtree_pk = $1",
        array($uploadTreeId), $stmt);
    return $uploadEntry;
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
   * @param $uploadId
   * @param $item
   * @return mixed
   */
  public function getNextItem($uploadId, $item, $options = null )
  {
    return $this->getItemByDirection($uploadId, $item, self::DIR_FWD, $options);
  }

  /**
   * @param $uploadId
   * @param $item
   * @return mixed
   */
  public function getPreviousItem($uploadId, $item, $options = null)
  {
    return $this->getItemByDirection($uploadId, $item, self::DIR_BCK, $options);
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
  public function getItemByDirection($uploadId, $item, $direction, $options)
  {
    $uploadTreeTableName = $this->getUploadtreeTableName($uploadId);
    $uploadTreeView = $this->getUploadTreeView($uploadId, $item, $options, $uploadTreeTableName);

    $itemEntry = $this->getUploadEntryFromView($item, $uploadTreeView);

    return $this->findNextItem($itemEntry, $direction, $uploadTreeView);
  }

  /**
   * @param $itemEntry
   * @param $direction
   * @param $uploadTreeView
   * @return mixed
   */
  protected function findNextItem($itemEntry, $direction, $uploadTreeView)
  {
    $parent = $itemEntry['parent'];
    $item = $itemEntry['uploadtree_pk'];

    if (isset($parent))
    {
      $currentIndex = $this->getItemIndex($parent, $item, $uploadTreeView);
      $parentSize = $this->getParentSize($parent, $uploadTreeView);
    } else
    {
      if ($direction == self::DIR_FWD)
      {
        return $this->enterFolder($item, $direction, $uploadTreeView);
      }
      return self::NOT_FOUND;
    }

    $targetOffset = $currentIndex + ($direction == self::DIR_FWD ? 1 : -1);
    if ($targetOffset >= 0 && $targetOffset < $parentSize)
    {
      return $this->findNextItemInCurrentParent($targetOffset, $parent, $direction, $uploadTreeView);
    } else
    {
      return $this->findNextItemOutsideCurrentParent($parent, $direction, $uploadTreeView);
    }
  }

  /**
   * @param $targetOffset
   * @param $parent
   * @param $direction
   * @param $uploadTreeView
   * @return mixed
   */
  protected function findNextItemInCurrentParent($targetOffset, $parent, $direction, $uploadTreeView)
  {
    $newItem = $this->getNewItemByIndex($parent, $targetOffset, $uploadTreeView);

    $newItemEntry = $this->getUploadEntryFromView($newItem, $uploadTreeView);

    $newItem = $this->handleNewItem($newItemEntry, $direction, $uploadTreeView);

    if ($newItem)
    {
      return $newItem;
    } else
    {
      return $this->findNextItem($newItemEntry, $direction, $uploadTreeView);
    }
  }

  /**
   * @param $parent
   * @param $direction
   * @param $uploadTreeView
   * @return mixed|null
   */
  protected function findNextItemOutsideCurrentParent($parent, $direction, $uploadTreeView)
  {
    if (isset($parent))
    {
      $newItemEntry = $this->getUploadEntryFromView($parent, $uploadTreeView);
      return $this->findNextItem($newItemEntry, $direction, $uploadTreeView);
    } else
    {
      return self::NOT_FOUND;
    }
  }


  /**
   * @param $newItemEntry
   * @param $direction
   * @param $uploadTreeView
   * @return mixed
   */
  protected function handleNewItem($newItemEntry, $direction, $uploadTreeView)
  {
    $newItem = $newItemEntry['uploadtree_pk'];

    $fileMode = $newItemEntry['ufile_mode'];
    if (Isartifact($fileMode) || Isdir($fileMode) || Iscontainer($fileMode))
    {
      $folderSize = $newItemEntry['rgt'] - $newItemEntry['lft'];
      if ($folderSize > 1)
      {
        return $this->enterFolder($newItem, $direction, $uploadTreeView);
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
   * @param $uploadTreeView
   * @return mixed
   */
  protected function enterFolder($item, $direction, $uploadTreeView)
  {
    $name_order = ($direction == self::DIR_FWD ? 'ASC' : 'DESC');

    $statementName = __METHOD__ . "descent_" . $name_order;
    $newItemResult = $this->dbManager->getSingleRow(
        "$uploadTreeView
          select uploadtree_pk from uploadTreeView
          where parent=$1
          order by ufile_name $name_order limit 1",
        array($item), $statementName);
    if(!$newItemResult) return self::NOT_FOUND;
    $newItemEntry = $this->getUploadEntryFromView($newItemResult['uploadtree_pk'], $uploadTreeView);
    if($newItemEntry == null || $newItemEntry == false ) return self::NOT_FOUND;
    return $this->handleNewItem($newItemEntry, $direction, $uploadTreeView);
  }


  /**
   * @param $parent
   * @param $item
   * @param $uploadTreeView
   * @return mixed
   */
  protected function getItemIndex($parent, $item, $uploadTreeView)
  {

    $sql  ="$uploadTreeView
    select row_number from (
      select
        row_number() over (order by ufile_name),
        uploadtree_pk
      from uploadTreeView where parent=$1
    ) as index where uploadtree_pk=$2";

    $currentIndexResult = $this->dbManager->getSingleRow($sql, array($parent, $item), __METHOD__ . "_current_offset".$uploadTreeView);

    $currentIndex = $currentIndexResult['row_number'] - 1;
    return $currentIndex;
  }

  /**
   * @param $parent
   * @param $uploadTreeView
   * @return mixed
   */
  protected function getParentSize($parent, $uploadTreeView)
  {
    $currentCountResult = $this->dbManager->getSingleRow("$uploadTreeView
        select count(*)
               from uploadTreeView
               where parent=$1",
        array($parent), __METHOD__ . "_current_count");
    $currentCount = $currentCountResult['count'];
    return $currentCount;
  }

  /**
   * @param $parent
   * @param $targetOffset
   * @param $uploadTreeView
   * @return mixed
   */
  protected function getNewItemByIndex($parent, $targetOffset, $uploadTreeView)
  {

    $statementName = __METHOD__ ;
    $theQuery ="$uploadTreeView
      SELECT uploadtree_pk
        from uploadTreeView
        where parent=$1
        order by ufile_name offset $2 limit 1";

    $newItemResult = $this->dbManager->getSingleRow($theQuery
        , array($parent, $targetOffset), $statementName);

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

    $parent = $this->dbManager->getSingleRow(
        "select uploadtree_pk
            from $uploadTreeTableName
            where upload_fk=$1 and lft=1", array($uploadId), $statementname);
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
   * @param $uploadId
   * @param $uploadTreeTableName
   * @return string
   */
  protected function getDefaultUploadTreeView($uploadId, $uploadTreeTableName)
  {
    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName)
    {
      $sql_upload = " WHERE ut.upload_fk=$uploadId ";
    }
    $uploadTreeView = "WITH UploadTreeView  As ( SELECT * FROM $uploadTreeTableName UT $sql_upload)";
    return $uploadTreeView;
  }

  /**
   * @param $uploadId
   * @param $item
   * @param $options
   * @param $uploadTreeTableName
   * @return string
   */
  protected function getUploadTreeView($uploadId, $item, $options, $uploadTreeTableName)
  {
    if ($options === null)
    {
      $uploadTreeView = $this->getDefaultUploadTreeView($uploadId, $uploadTreeTableName);
      return $uploadTreeView;
    } else
    {
      $skipThese = $options['skipThese'];
      if (isset($skipThese))
      {
        switch($skipThese)
        {
          case "none":
            break;
          case "noLicense":
          case "alreadyCleared":
          case "noCopyright":
          $conditionQuery = $this->getConditionQuery($skipThese);
            $sql_upload = "";
            if ('uploadtree_a' == $uploadTreeTableName)
            {
            $sql_upload = " AND ut.upload_fk=$uploadId ";
            }
            $uploadTreeView = " WITH UploadTreeView AS (
                                select
                                  *
                                from $uploadTreeTableName ut
                                where
                                  (
                                   $conditionQuery
                                        OR
                                    ut.ufile_mode & (1<<29) <> 0
                                        OR
                                    ut.uploadtree_pk = $item
                                  )
                                  $sql_upload
                                )";
            return $uploadTreeView;
        }
      }
      //default case, if cookie is not set or set to none
      $uploadTreeView = $this->getDefaultUploadTreeView($uploadId, $uploadTreeTableName);
      return $uploadTreeView;

    }
  }

  /**
   * @param $skipThese
   * @return string
   */
  private function getConditionQuery($skipThese)
  {
    $conditionQueryHasLicense = "EXISTS (SELECT rf_pk FROM license_file_ref lr WHERE rf_shortname NOT IN ('No_license_found', 'Void') AND lr.pfile_fk=ut.pfile_fk)";

    switch($skipThese)
    {
      case "noLicense":
        return $conditionQueryHasLicense;
      case "alreadyCleared":
        $conditionQuery = "( $conditionQueryHasLicense
              AND  NOT EXISTS  (SELECT license_decision_event_pk
                                    FROM license_decision_event lde where ut.uploadtree_pk = lde.uploadtree_fk
                                    OR ( lde.pfile_fk = ut.pfile_fk AND lde.is_global = TRUE  ) ) )";

        return $conditionQuery;
      case "noCopyright":
        $conditionQuery = "EXISTS (SELECT ct_pk FROM copyright cp WHERE cp.pfile_fk=ut.pfile_fk)";
       return $conditionQuery;
    }


  }
}