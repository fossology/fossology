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

namespace Fossology\Lib\Data\Tree;


class UploadTreeView
{
  /** @var string */
  private $uploadTreeViewName;
  /** @var string */
  private $uploadTreeViewQuery;
  /** @var string */
  private $uploadTreeTableName;
  /** @var int */
  private $uploadId;
  /** @var bool */
  private $materialized = false;
  
  /**
   * @param int $uploadId
   * @param array $options (keys skipThese, ut.filter supported)
   * @param string $uploadTreeTableName
   */
  public function __construct($uploadId, $options, $uploadTreeTableName, $uploadTreeViewName=null)
  {
    $this->uploadId = $uploadId;
    $this->uploadTreeTableName = $uploadTreeTableName;
    $this->uploadTreeViewName = $uploadTreeViewName ?: 'UploadTreeView';
    $this->uploadTreeViewQuery = $this->createUploadTreeViewQuery($options, $uploadTreeTableName);
  }

  /**
   * @return string
   */
  public function getUploadTreeTableName()
  {
    return $this->uploadTreeTableName;
  }
  
  /**
   * @return string
   */
  public function getUploadTreeViewName()
  {
    return $this->uploadTreeViewName;
  }

  /**
   * @brief create temp table
   */
  public function materialize()
  {
    if ($this->materialized)
    {
      return;
    }
    global $container;
    $dbManager = $container->get('db.manager');
    $dbManager->queryOnce("CREATE TEMPORARY TABLE $this->uploadTreeViewName AS $this->uploadTreeViewQuery");
    $this->materialized = true;
  }

  /**
   * @brief drops temp table
   */
  public function unmaterialize()
  {
    if (!$this->materialized)
    {
      return;
    }
    global $container;
    $dbManager = $container->get('db.manager');
    $dbManager->queryOnce("DROP TABLE $this->uploadTreeViewName");
    $this->materialized = false;
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
   * @brief Common Table Expressions
   * @return string
   */
  public function asCTE(){
    return "WITH $this->uploadTreeViewName AS (".$this->uploadTreeViewQuery.")";
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
    $additionalCondition = array_key_exists('ut.filter', $options) ? $options['ut.filter'] : '';
    $skipThese = array_key_exists('skipThese',$options) ? $options['skipThese'] : 'none';
    switch ($skipThese)
    {
      case "none":
        break;
      case "noLicense":
      case "alreadyCleared":
      case "noCopyright":
      case "noIp":
      case "noEcc":

        $queryCondition = self::getQueryCondition($skipThese);
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
  private static function getQueryCondition($skipThese)
  {
    $conditionQueryHasLicense = "EXISTS (SELECT rf_pk FROM license_file_ref lr WHERE rf_shortname NOT IN ('No_license_found', 'Void') AND lr.pfile_fk=ut.pfile_fk)";

    switch ($skipThese)
    {
      case "noLicense":
        return $conditionQueryHasLicense;
      case "alreadyCleared":
        $decisionQuery = "SELECT type_fk FROM clearing_decision AS cd
                        WHERE ut.uploadtree_pk = cd.uploadtree_fk
                              OR cd.pfile_fk = ut.pfile_fk AND cd.is_global
                        ORDER BY cd.clearing_decision_pk DESC LIMIT 1";
        $conditionQuery = " $conditionQueryHasLicense
              AND NOT EXISTS (SELECT * FROM ($decisionQuery) as latest_decision WHERE latest_decision.type_fk IN (4,5) )";
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
            . $this->uploadTreeViewName ." v where u.upload_fk=$1"
            . " AND v.lft BETWEEN u.lft and u.rgt and u.parent = $2 GROUP BY u.uploadtree_pk, u.ufile_mode";
    $dbManager->prepare($stmt=__METHOD__.$this->uploadTreeViewName,$sql);
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
      foreach($this->countMaskedNonArtifactChildren($ac) as $utid=>$cnt)
      $children[$utid] = $cnt;
    }
    return $children;
  }
}