<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;

class UploadTreeProxy extends DbViewProxy
{
  const OPT_SKIP_THESE = 'skipThese';
  const OPT_ITEM_FILTER = 'ut.filter';
  const OPT_GROUP_ID = 'groupId';
  
  /** @vars string */
  private $uploadTreeTableName;
  /** @var int */
  private $uploadId;

  /**
   * @param int $uploadId
   * @param array $options (keys , ut.filter, groupId supported)
   * @param string $uploadTreeTableName
   */
  public function __construct($uploadId, $options, $uploadTreeTableName, $uploadTreeViewName=null)
  {
    $this->uploadId = $uploadId;
    $this->uploadTreeTableName = $uploadTreeTableName;
    $dbViewName = $uploadTreeViewName ?: 'UploadTreeView';
    $dbViewQuery = $this->createUploadTreeViewQuery($options, $uploadTreeTableName);
    parent::__construct($dbViewQuery, $dbViewName);
  }

  /**
   * @return string
   */
  public function getUploadTreeTableName()
  {
    return $this->uploadTreeTableName;
  }

  /**
   * @param array $options
   * @param string $uploadTreeTableName
   * @return string
   */
  private function createUploadTreeViewQuery($options, $uploadTreeTableName)
  {
    if ($options === null)
    {
      return self::getDefaultUploadTreeView($this->uploadId, $uploadTreeTableName);
    }
    else
    {
      return self::getUploadTreeView($this->uploadId, $options, $uploadTreeTableName);
    }
  }

  /**
   * @param $uploadId
   * @param $uploadTreeTableName
   * @return string
   */
  private static function getDefaultUploadTreeView($uploadId, $uploadTreeTableName)
  {
    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName)
    {
      $sql_upload = " WHERE ut.upload_fk=$uploadId ";
    }
    $uploadTreeView = "SELECT * FROM $uploadTreeTableName ut $sql_upload";
    return $uploadTreeView;
  }

  /**
   * @param $uploadId
   * @param $options
   * @param $uploadTreeTableName
   * @return string
   */
  private static function getUploadTreeView($uploadId, $options, $uploadTreeTableName)
  {
    $additionalCondition = array_key_exists(self::OPT_ITEM_FILTER, $options) ? $options[self::OPT_ITEM_FILTER] : '';
    $skipThese = array_key_exists(self::OPT_SKIP_THESE,$options) ? $options[self::OPT_SKIP_THESE] : 'none';
    $groupId = array_key_exists(self::OPT_GROUP_ID, $options) ? $options[self::OPT_GROUP_ID] : null;
    switch ($skipThese)
    {
      case "none":
        break;
      case "noLicense":
      case "alreadyCleared":
      case "noCopyright":
      case "noIp":
      case "noEcc":

        $queryCondition = self::getQueryCondition($skipThese, $groupId);
        $sql_upload = ('uploadtree_a' == $uploadTreeTableName) ? "ut.upload_fk=$uploadId AND " : '';
        $uploadTreeView = "SELECT * FROM $uploadTreeTableName ut
                           WHERE $sql_upload $queryCondition $additionalCondition";
        return $uploadTreeView;
    }
    //default case, if cookie is not set or set to none
    $uploadTreeView = self::getDefaultUploadTreeView($uploadId, $uploadTreeTableName);
    return $uploadTreeView;
  }

  /**
   * @param $skipThese
   * @return string
   */
  private static function getQueryCondition($skipThese, $groupId = null)
  {
    $conditionQueryHasLicense = "(EXISTS (SELECT 1 FROM license_file_ref lr WHERE rf_shortname NOT IN ('No_license_found', 'Void') AND lr.pfile_fk=ut.pfile_fk)
        OR EXISTS (SELECT 1 FROM clearing_decision AS cd WHERE cd.group_fk = $groupId AND ut.uploadtree_pk = cd.uploadtree_fk))";

    switch ($skipThese)
    {
      case "noLicense":
        return $conditionQueryHasLicense;
      case "alreadyCleared":
        $decisionQuery = "SELECT decision_type FROM clearing_decision AS cd
                        WHERE cd.group_fk = $groupId
                          AND (ut.uploadtree_pk = cd.uploadtree_fk OR cd.pfile_fk = ut.pfile_fk AND cd.scope=".DecisionScopes::REPO.")
                        ORDER BY cd.clearing_decision_pk DESC LIMIT 1";
        $conditionQuery = " $conditionQueryHasLicense
              AND NOT EXISTS (SELECT * FROM ($decisionQuery) as latest_decision WHERE latest_decision.decision_type IN (".DecisionTypes::IRRELEVANT.",".DecisionTypes::IDENTIFIED.") )";
        return $conditionQuery;
      case "noCopyright":
        $conditionQuery = "EXISTS (SELECT ct_pk FROM copyright cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null )";
        return $conditionQuery;
      case "noIp":
        $conditionQuery = "EXISTS (SELECT ct_pk FROM ip cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null )";
        return $conditionQuery;
      case "noEcc":
        $conditionQuery = "EXISTS (SELECT ct_pk FROM ecc cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null )";
        return $conditionQuery;
    }
  }

  /**
   * @brief count elements childrenwise (or grandchildrenwise if child is artifact)
   * @param int $parent
   * @return array
   */
  public function countMaskedNonArtifactChildren($parent)
  {
    global $container;
    $dbManager = $container->get('db.manager');
    $sql = "SELECT count(*) cnt, u.uploadtree_pk, u.ufile_mode FROM ".$this->uploadTreeTableName." u, "
            . $this->getDbViewName() ." v where u.upload_fk=$1"
            . " AND v.lft BETWEEN u.lft and u.rgt and u.parent = $2 GROUP BY u.uploadtree_pk, u.ufile_mode";
    $stmt = __METHOD__.'.'.$this->getDbViewName();
    if(!$this->materialized)
    {
      $sql = $this->asCTE().' '.$sql;
      $stmt .= '.cte';
    }
    $dbManager->prepare($stmt,$sql);
    $res = $dbManager->execute($stmt,array($this->uploadId,$parent));
    $children = array();
    $artifactContainers = array();
    while($row=$dbManager->fetchArray($res))
    {
      $children[$row['uploadtree_pk']] = $row['cnt'];
      if ( ($row['ufile_mode'] & (3<<28)) == (3<<28))
      {
        $artifactContainers[] = $row['uploadtree_pk'];
      }
    }
    $dbManager->freeResult($res);
    foreach ($artifactContainers as $ac)
    {
      foreach ($this->countMaskedNonArtifactChildren($ac) as $utid => $cnt)
      {
        $children[$utid] = $cnt;
      }
    }
    return $children;
  }
  
  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return array
   */
  public function getNonArtifactDescendants(ItemTreeBounds $itemTreeBounds)
  {
    global $container;
    $dbManager = $container->get('db.manager');
    $sql = "SELECT u.uploadtree_pk FROM ".$this->getDbViewName()." u "
         . "WHERE u.upload_fk=$1 AND (u.lft BETWEEN $2 AND $3) AND u.ufile_mode & (3<<28) = 0";
    $stmt = __METHOD__.'.'.$this->getDbViewName();
    if(!$this->materialized)
    {
      $sql = $this->asCTE().' '.$sql;
      $stmt .= '.cte';
    }
    $dbManager->prepare($stmt,$sql);
    $params = array($itemTreeBounds->getUploadId(),$itemTreeBounds->getLeft(),$itemTreeBounds->getRight());
    $res = $dbManager->execute($stmt,$params);
    $descendants = array();
    while($row = $dbManager->fetchArray($res))
    {
      $descendants[$row['uploadtree_pk']] = 1;
    }
    $dbManager->freeResult($res);
    return $descendants;
  }
  
  /**
   * @return int
   */
  public function count()
  {
    global $container;
    $dbManager = $container->get('db.manager');
    if($this->materialized)
    {
      $sql = "SELECT count(*) FROM $this->dbViewName";
    }
    else
    {
      $sql = "SELECT count(*) FROM ($this->dbViewQuery) $this->dbViewName";
    }
    $summary = $dbManager->getSingleRow($sql,array(),$this->dbViewName);
    return $summary['count'];
  }
  
}
