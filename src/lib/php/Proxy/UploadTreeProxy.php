<?php
/*
Copyright (C) 2014-2015, Siemens AG

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

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;

class UploadTreeProxy extends DbViewProxy
{
  const OPT_SKIP_THESE = 'skipThese';
  const OPT_ITEM_FILTER = 'ut.filter';
  const OPT_GROUP_ID = 'groupId';
  const OPT_REALPARENT = 'realParent';
  const OPT_RANGE = 'lft,rgt';
  const OPT_EXT = 'ext';
  const OPT_HEAD = 'head';
  const OPT_AGENT_SET = 'agentArray';
  const OPT_SCAN_REF = 'scanRef';
  const OPT_CONCLUDE_REF = 'conRef';
  const OPT_SKIP_ALREADY_CLEARED = 'alreadyCleared';

  /** @var string */
  private $uploadTreeTableName;
  /** @var int */
  private $uploadId;
  /** @var array */
  private $params = array();

  /**
   * @param int $uploadId
   * @param array $options (OPT_* supported)
   * @param string $uploadTreeTableName
   */
  public function __construct($uploadId, $options, $uploadTreeTableName, $uploadTreeViewName=null)
  {
    $this->uploadId = $uploadId;
    $this->uploadTreeTableName = $uploadTreeTableName;
    $dbViewName = $uploadTreeViewName ?: 'UploadTreeView'.(isset($this->dbViewName) ?: '');
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

    $filter = '';
    $unifier = '';
    
    if (array_key_exists(self::OPT_REALPARENT, $options))
    {
      $filter .= " AND ut.ufile_mode & (1<<28) = 0 AND ut.realparent=".$this->addParamAndGetExpr('realParent',$options[self::OPT_REALPARENT]);
      $unifier .= "_".self::OPT_REALPARENT;
    } 
    elseif (array_key_exists(self::OPT_RANGE,$options))
    {
      $itemBounds = $options[self::OPT_RANGE];
      $filter .= " AND ut.ufile_mode & (3<<28) = 0 AND (ut.lft BETWEEN ".$this->addParamAndGetExpr('lft',$itemBounds->getLeft()).
             " AND ".$this->addParamAndGetExpr('rgt',$itemBounds->getRight()).")";
      $unifier .= "_".self::OPT_RANGE;
    }
    
    if (array_key_exists(self::OPT_EXT, $options))
    {
      $filter .= " AND ufile_name ILIKE ".$this->addParamAndGetExpr('patternExt','%.'.$options[self::OPT_EXT]);
      $unifier .= "_".self::OPT_EXT;
    }
    
    if (array_key_exists(self::OPT_HEAD, $options))
    {
      $filter .= " AND ufile_name ILIKE ".$this->addParamAndGetExpr('patternHead',$options[self::OPT_HEAD].'%');
      $unifier .= "_".self::OPT_HEAD;
    }
    
    if(array_key_exists(self::OPT_SCAN_REF,$options) && array_key_exists(self::OPT_REALPARENT, $options))
    {
      $filter .= " AND EXISTS(SELECT * FROM ".$this->uploadTreeTableName." usub, license_file"
              . " LEFT JOIN license_map ON license_file.rf_fk=license_map.rf_fk AND usage=".LicenseMap::CONCLUSION
              . " WHERE";
      $unifier .= "_".self::OPT_SCAN_REF;
      if (array_key_exists(self::OPT_AGENT_SET, $options))
      {
        $agentIdSet = '{' . implode(',', array_values($options[self::OPT_AGENT_SET])) . '}';
        $filter .= " agent_fk=ANY(".$this->addParamAndGetExpr('agentIdSet', $agentIdSet).") AND";
        $unifier .= "_".self::OPT_AGENT_SET;
      }
      $rfId = $this->addParamAndGetExpr('scanRef', $options[self::OPT_SCAN_REF]);
      $filter .= " (license_file.rf_fk=$rfId OR rf_parent=$rfId) "
              . " AND usub.pfile_fk=license_file.pfile_fk"
              . " AND (usub.lft BETWEEN ut.lft AND ut.rgt) AND upload_fk=".$this->uploadId.")";
    }
    elseif(array_key_exists(self::OPT_SCAN_REF,$options) && array_key_exists(self::OPT_RANGE, $options))
    {
      $filter .= " AND EXISTS(SELECT * FROM license_file"
                . " LEFT JOIN license_map ON license_file.rf_fk=license_map.rf_fk AND usage=".LicenseMap::CONCLUSION
                . " WHERE";
      $unifier .= "_".self::OPT_EXT;
      if (array_key_exists(self::OPT_AGENT_SET, $options))
      {
        $agentIdSet = '{' . implode(',', array_values($options[self::OPT_AGENT_SET])) . '}';
        $filter .= " agent_fk=ANY(".$this->addParamAndGetExpr('agentIdSet', $agentIdSet).") AND";
        $unifier .= "_".self::OPT_AGENT_SET;
      }
      $rfId = $this->addParamAndGetExpr('scanRef', $options[self::OPT_SCAN_REF]);
      $filter .= " (license_file.rf_fk=$rfId OR rf_parent=$rfId) AND ut.pfile_fk=license_file.pfile_fk)";
    }
   
    if(array_key_exists(self::OPT_CONCLUDE_REF, $options) && array_key_exists(self::OPT_GROUP_ID, $options)
            && array_key_exists(self::OPT_REALPARENT, $options))
    {
      $filter .=" AND EXISTS(SELECT * FROM ".$this->uploadTreeTableName." usub"
              . " WHERE (usub.lft BETWEEN ut.lft AND ut.rgt) AND upload_fk=".$this->uploadId
              . " AND NOT(SELECT removed FROM clearing_decision cd, clearing_decision_event cde, clearing_event ce"
              . "   WHERE cd.group_fk=".$this->addParamAndGetExpr('groupId', $options[self::OPT_GROUP_ID])
              . "   AND cd.uploadtree_fk=usub.uploadtree_pk AND clearing_decision_pk=clearing_decision_fk"
              . "   AND clearing_event_fk=clearing_event_pk"
              . "   AND rf_fk=".$this->addParamAndGetExpr('conId',$options[self::OPT_CONCLUDE_REF])
              . "   AND cd.decision_type!=".DecisionTypes::WIP." ORDER BY cd.date_added DESC LIMIT 1)"
              . ")";
      $unifier .= "_".self::OPT_CONCLUDE_REF;
    }
    elseif(array_key_exists(self::OPT_CONCLUDE_REF, $options) && array_key_exists(self::OPT_GROUP_ID, $options)
            && array_key_exists(self::OPT_RANGE, $options))
    {
      $filter.= " AND NOT(SELECT removed FROM clearing_decision cd, clearing_decision_event cde, clearing_event ce"
              . " WHERE cd.group_fk=".$this->addParamAndGetExpr('groupId', $options[self::OPT_GROUP_ID])
              . " AND cd.uploadtree_fk=ut.uploadtree_pk AND clearing_decision_pk=clearing_decision_fk"
              . " AND clearing_event_fk=clearing_event_pk"
              . " AND rf_fk=".$this->addParamAndGetExpr('conId',$options[self::OPT_CONCLUDE_REF])
              . " AND cd.decision_type!=".DecisionTypes::WIP." ORDER BY cd.date_added DESC)";
      $unifier .= "_".self::OPT_CONCLUDE_REF;
    }
  
    if(array_key_exists(self::OPT_SKIP_ALREADY_CLEARED, $options) && array_key_exists(self::OPT_GROUP_ID, $options)
            && array_key_exists(self::OPT_AGENT_SET, $options)  && array_key_exists(self::OPT_RANGE, $options))
    {
      $agentIdSet = '{' . implode(',', array_values($options[self::OPT_AGENT_SET])) . '}';
      $agentFilter = " AND agent_fk=ANY(".$this->addParamAndGetExpr('agentIdSet', $agentIdSet).")";
      $filter .= ' AND '.self::getQueryCondition(self::OPT_SKIP_ALREADY_CLEARED, $options[self::OPT_GROUP_ID], $agentFilter);
      $unifier .= "_".self::OPT_SKIP_ALREADY_CLEARED;
    }
    elseif(array_key_exists(self::OPT_SKIP_ALREADY_CLEARED, $options) && array_key_exists(self::OPT_GROUP_ID, $options)
            && array_key_exists(self::OPT_AGENT_SET, $options)  && array_key_exists(self::OPT_REALPARENT, $options))
    {
      $agentIdSet = '{' . implode(',', array_values($options[self::OPT_AGENT_SET])) . '}';
      $agentFilter = " AND agent_fk=ANY(".$this->addParamAndGetExpr('agentIdSet', $agentIdSet).")";
      $childFilter = self::getQueryCondition(self::OPT_SKIP_ALREADY_CLEARED, $options[self::OPT_GROUP_ID], $agentFilter);
      $filter .= ' AND EXISTS(SELECT * FROM '.$this->uploadTreeTableName.' utc WHERE utc.upload_fk='.$this->uploadId
              . ' AND (utc.lft BETWEEN ut.lft AND ut.rgt) AND utc.ufile_mode&(3<<28)=0 AND '
                 .str_replace(' ut.', ' utc.', $childFilter).')';
      $unifier .= "_".self::OPT_SKIP_ALREADY_CLEARED;
    }
    
    if (array_key_exists(self::OPT_ITEM_FILTER, $options)) {
      $filter .= ' '.$options[self::OPT_ITEM_FILTER];
      $unifier .= "_".md5($options[self::OPT_ITEM_FILTER]);
    }
    $options[self::OPT_ITEM_FILTER] = $filter;
    $this->dbViewName = $unifier;
    return self::getUploadTreeView($this->uploadId, $options, $uploadTreeTableName);
  }

  /**
   * @param int $uploadId
   * @param string $uploadTreeTableName
   * @param string $additionalCondition
   * @return string
   */
  private static function getDefaultUploadTreeView($uploadId, $uploadTreeTableName, $additionalCondition='')
  {
    $condition = "";
    if ('uploadtree_a' == $uploadTreeTableName)
    {
      $condition = " WHERE ut.upload_fk=$uploadId $additionalCondition";
    }
    elseif ($additionalCondition)
    {
      $condition = " WHERE 1=1 $additionalCondition";
    }
    $uploadTreeView = "SELECT * FROM $uploadTreeTableName ut $condition";
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
    $agentFilter = '';
    switch ($skipThese)
    {
      case "noLicense":
      case self::OPT_SKIP_ALREADY_CLEARED:
        if(array_key_exists(self::OPT_AGENT_SET, $options))
        {        
          $agentIds = 'array[' . implode(',',$options[self::OPT_AGENT_SET]) . ']';
          $agentFilter = " AND lr.agent_fk=ANY($agentIds)";
        }
        else
        {
          $scanJobProxy = new ScanJobProxy($GLOBALS['container']->get('dao.agent'),$uploadId);
          $scanJobProxy->createAgentStatus(array('nomos','monk','ninka'));
          $latestAgentIds = $scanJobProxy->getLatestSuccessfulAgentIds();
          $agentFilter = $latestAgentIds ? " AND lr.agent_fk=ANY(array[".implode(',',$latestAgentIds)."])" : "AND 0=1";
        }
      case "noCopyright":
      case "noIp":
      case "noEcc":

        $queryCondition = self::getQueryCondition($skipThese, $groupId, $agentFilter)." ".$additionalCondition;
        if ('uploadtree_a' == $uploadTreeTableName)
        {
          $queryCondition = "ut.upload_fk=$uploadId AND ($queryCondition)";
        }
        $uploadTreeView = "SELECT * FROM $uploadTreeTableName ut WHERE $queryCondition";
        break;

      case "none":
      default:
        $uploadTreeView = self::getDefaultUploadTreeView($uploadId, $uploadTreeTableName, $additionalCondition);
    }

    return $uploadTreeView;
  }

  /**
   * @param $skipThese
   * @return string
   */
  private static function getQueryCondition($skipThese, $groupId = null, $agentFilter='')
  {
    $conditionQueryHasLicense = "(EXISTS (SELECT 1 FROM license_file_ref lr WHERE rf_shortname NOT IN ('No_license_found', 'Void') AND lr.pfile_fk= ut.pfile_fk $agentFilter)
        OR EXISTS (SELECT 1 FROM clearing_decision AS cd WHERE cd.group_fk = $groupId AND ut.uploadtree_pk = cd.uploadtree_fk))";

    switch ($skipThese)
    {
      case "noLicense":
        return $conditionQueryHasLicense;
      case self::OPT_SKIP_ALREADY_CLEARED:
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
   * @deprecated
   * @return array
   */
  public function countMaskedNonArtifactChildren($parent)
  {
    $dbManager = $GLOBALS['container']->get('db.manager');
    $params = $this->params;
    if(array_key_exists('uploadId', $params))
    {
      $uploadExpr = '$'.(1+array_search('uploadId', array_keys($params)));
    }
    else
    {
      $params[] = $this->uploadId;
      $uploadExpr = '$'.count($params);
    }
    $params[] = $parent;
    $parentExpr = '$'.count($params);

    $sql = "SELECT count(*) cnt, u.uploadtree_pk, u.ufile_mode FROM ".$this->uploadTreeTableName." u, "
            . $this->getDbViewName() ." v where u.upload_fk=$uploadExpr"
            . " AND v.lft BETWEEN u.lft and u.rgt and u.parent=$parentExpr GROUP BY u.uploadtree_pk, u.ufile_mode";
    $stmt = __METHOD__.'.'.$this->getDbViewName();
    if(!$this->materialized)
    {
      $sql = $this->asCTE().' '.$sql;
      $stmt .= '.cte';
    }
    $dbManager->prepare($stmt,$sql);
    $res = $dbManager->execute($stmt,$params);
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
    $uploadExpr = '$'.(count($this->params)+1);
    $lftExpr = '$'.(count($this->params)+2);
    $rgtExpr = '$'.(count($this->params)+3);
    $dbManager = $GLOBALS['container']->get('db.manager');
    $sql = "SELECT u.uploadtree_pk FROM ".$this->getDbViewName()." u "
         . "WHERE u.upload_fk=$uploadExpr AND (u.lft BETWEEN $lftExpr AND $rgtExpr) AND u.ufile_mode & (3<<28) = 0";
    $stmt = __METHOD__.'.'.$this->getDbViewName();
    if(!$this->materialized)
    {
      $sql = $this->asCTE().' '.$sql;
      $stmt .= '.cte';
    }
    $dbManager->prepare($stmt,$sql);
    $params = array_merge($this->params,
            array($itemTreeBounds->getUploadId(),$itemTreeBounds->getLeft(),$itemTreeBounds->getRight()));
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
    $summary = $dbManager->getSingleRow($sql,$this->params,$this->dbViewName);
    return $summary['count'];
  }
  
  private function addParamAndGetExpr($key,$value)
  {
    if (array_key_exists($key, $this->params)) {
      return '$' . (1 + array_search($key, array_keys($this->params)));
    }

    $this->params[] = $value;
    return '$'.count($this->params);
  }
  
  public function getParams()
  {
    return $this->params;
  }
  
}
