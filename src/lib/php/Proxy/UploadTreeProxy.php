<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
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
  public function createUploadTreeViewQuery($options, $uploadTreeTableName)
  {
    if (empty($options)) {
      return self::getDefaultUploadTreeView($this->uploadId, $uploadTreeTableName);
    }

    $filter = '';
    $this->dbViewName = '';
    /* @var $uploadDao UploadDao */
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    $applyGlobal = $uploadDao->getGlobalDecisionSettingsFromInfo($this->uploadId);
    if ($applyGlobal == 1) {
      $applyGlobal = true;
    } else {
      $applyGlobal = false;
    }

    if (array_key_exists(self::OPT_REALPARENT, $options)) {
      $filter .= " AND ut.ufile_mode & (1<<28) = 0 AND ut.realparent=".$this->addParamAndGetExpr('realParent',$options[self::OPT_REALPARENT]);
      $this->dbViewName .= "_".self::OPT_REALPARENT;
    } elseif (array_key_exists(self::OPT_RANGE,$options)) {
      $itemBounds = $options[self::OPT_RANGE];
      $filter .= " AND ut.ufile_mode & (3<<28) = 0 AND (ut.lft BETWEEN ".$this->addParamAndGetExpr('lft',$itemBounds->getLeft()).
             " AND ".$this->addParamAndGetExpr('rgt',$itemBounds->getRight()).")";
      $this->dbViewName .= "_".self::OPT_RANGE;
    }

    if (array_key_exists(self::OPT_EXT, $options)) {
      $filter .= " AND ufile_name ILIKE ".$this->addParamAndGetExpr('patternExt','%.'.$options[self::OPT_EXT]);
      $this->dbViewName .= "_".self::OPT_EXT;
    }

    if (array_key_exists(self::OPT_HEAD, $options)) {
      $filter .= " AND ufile_name ILIKE ".$this->addParamAndGetExpr('patternHead',$options[self::OPT_HEAD].'%');
      $this->dbViewName .= "_".self::OPT_HEAD;
    }

    if (array_key_exists(self::OPT_SCAN_REF,$options)) {
      $filter .= $this->addScanFilter($options);
    }

    if (array_key_exists(self::OPT_CONCLUDE_REF, $options) && array_key_exists(self::OPT_GROUP_ID, $options)) {
      $filter .= $this->addConFilter($options);
    }

    if (array_key_exists(self::OPT_SKIP_ALREADY_CLEARED, $options) && array_key_exists(self::OPT_GROUP_ID, $options)
            && array_key_exists(self::OPT_AGENT_SET, $options)) {
      $agentIdSet = '{' . implode(',', array_values($options[self::OPT_AGENT_SET])) . '}';
      $agentFilter = " AND agent_fk=ANY(".$this->addParamAndGetExpr('agentIdSet', $agentIdSet).")";
      $this->dbViewName .= "_".self::OPT_SKIP_ALREADY_CLEARED;
      $groupAlias = $this->addParamAndGetExpr('groupId', $options[self::OPT_GROUP_ID]);
      if (array_key_exists(self::OPT_RANGE, $options)) {
        $filter .= ' AND '.self::getQueryCondition(self::OPT_SKIP_ALREADY_CLEARED, $options, $groupAlias, $agentFilter, $applyGlobal);
      } elseif (array_key_exists(self::OPT_SKIP_ALREADY_CLEARED, $options) && array_key_exists(self::OPT_GROUP_ID, $options)
              && array_key_exists(self::OPT_AGENT_SET, $options) && array_key_exists(self::OPT_REALPARENT, $options)) {
        $childFilter = self::getQueryCondition(self::OPT_SKIP_ALREADY_CLEARED, $options, $groupAlias, $agentFilter, $applyGlobal);
        $filter .= ' AND EXISTS(SELECT * FROM '.$this->uploadTreeTableName.' utc WHERE utc.upload_fk='.$this->uploadId
                . ' AND (utc.lft BETWEEN ut.lft AND ut.rgt) AND utc.ufile_mode&(3<<28)=0 AND '
                   .preg_replace('/([a-z])ut\./', '\1utc.', $childFilter).')';
      }
    }

    if (array_key_exists(self::OPT_ITEM_FILTER, $options)) {
      $filter .= ' '.$options[self::OPT_ITEM_FILTER];
      $this->dbViewName .= "_".md5($options[self::OPT_ITEM_FILTER]);
    }
    $options[self::OPT_ITEM_FILTER] = $filter;
    return self::getUploadTreeView($this->uploadId, $options, $uploadTreeTableName, $applyGlobal);
  }

  private function addConFilter($options)
  {
    $filter = '';
    if (array_key_exists(self::OPT_REALPARENT, $options)) {
      $filter .=" AND EXISTS(SELECT * FROM ".$this->uploadTreeTableName." usub"
              . "            WHERE (usub.lft BETWEEN ut.lft AND ut.rgt) AND upload_fk=".$this->uploadId
              . "            AND ".$this->subqueryConcludeRefMatches('usub', $options) . ")";
      $this->dbViewName .= "_".self::OPT_CONCLUDE_REF;
    } elseif (array_key_exists(self::OPT_RANGE, $options)) {
      $filter.= " AND ".$this->subqueryConcludeRefMatches('ut', $options);
      $this->dbViewName .= "_".self::OPT_CONCLUDE_REF;
    }
    return $filter;
  }

  private function addScanFilter($options)
  {
    $this->dbViewName .= "_".self::OPT_SCAN_REF;
    if (array_key_exists(self::OPT_AGENT_SET, $options)) {
      $this->dbViewName .= "_".self::OPT_AGENT_SET;
    }
    if (array_key_exists(self::OPT_REALPARENT, $options)) {
      return " AND EXISTS(SELECT * FROM ".$this->uploadTreeTableName." usub, "
              . $this->subqueryLicenseFileMatchWhere($options)
              . " usub.pfile_fk=license_file.pfile_fk"
              . " AND (usub.lft BETWEEN ut.lft AND ut.rgt) AND upload_fk=".$this->uploadId.")";
    }
    if (array_key_exists(self::OPT_RANGE, $options)) {
      return " AND EXISTS(SELECT * FROM " . $this->subqueryLicenseFileMatchWhere($options)
              . " ut.pfile_fk=license_file.pfile_fk)";
    }
  }

  private function subqueryLicenseFileMatchWhere($options)
  {
    $filter = " license_file LEFT JOIN license_map ON license_file.rf_fk=license_map.rf_fk"
            . " AND usage=".LicenseMap::CONCLUSION." WHERE";
    if (array_key_exists(self::OPT_AGENT_SET, $options)) {
      $agentIdSet = '{' . implode(',', array_values($options[self::OPT_AGENT_SET])) . '}';
      $filter .= " agent_fk=ANY(".$this->addParamAndGetExpr('agentIdSet', $agentIdSet).") AND";
    }
    $rfId = $this->addParamAndGetExpr('scanRef', $options[self::OPT_SCAN_REF]);
    return $filter . " (license_file.rf_fk=$rfId OR rf_parent=$rfId) AND ";
  }

  private function subqueryConcludeRefMatches($itemTable,$options)
  {
    $globalSql = "";
    $orderByGlobal = "";
    /* @var $uploadDao UploadDao */
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    $applyGlobal = $uploadDao->getGlobalDecisionSettingsFromInfo($this->uploadId);
    if ($applyGlobal == 1) {
      $applyGlobal = true;
    } else {
      $applyGlobal = false;
    }
    if ($applyGlobal) {
      $globalSql = "OR (cd.scope=" . DecisionScopes::REPO .
        " AND cd.pfile_fk=$itemTable.pfile_fk)";
      $orderByGlobal = "CASE cd.scope WHEN " . DecisionScopes::REPO .
        " THEN 1 ELSE 0 END,";
    }
    return "NOT(SELECT (removed OR cd.decision_type=".DecisionTypes::IRRELEVANT.") excluded"
            . " FROM clearing_decision cd, clearing_decision_event cde, clearing_event ce"
         . "    WHERE ((cd.group_fk=".$this->addParamAndGetExpr('groupId', $options[self::OPT_GROUP_ID])
         . "      AND cd.uploadtree_fk=$itemTable.uploadtree_pk)"
         . $globalSql
         . ")     AND clearing_decision_pk=clearing_decision_fk"
         . "      AND clearing_event_fk=clearing_event_pk"
         . "      AND rf_fk=".$this->addParamAndGetExpr('conId',$options[self::OPT_CONCLUDE_REF])
         . "      AND cd.decision_type!=".DecisionTypes::WIP
         . "      ORDER BY $orderByGlobal cd.date_added DESC LIMIT 1)";
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
    if ('uploadtree' === $uploadTreeTableName || 'uploadtree_a' == $uploadTreeTableName) {
      $condition = " WHERE ut.upload_fk=$uploadId $additionalCondition";
    } elseif ($additionalCondition) {
      $condition = " WHERE 1=1 $additionalCondition";
    }
    $uploadTreeView = "SELECT * FROM $uploadTreeTableName ut $condition";
    return $uploadTreeView;
  }

  /**
   * @param $uploadId
   * @param $options
   * @param $uploadTreeTableName
   * @param bool $applyGlobal
   * @return string
   */
  private static function getUploadTreeView($uploadId, $options, $uploadTreeTableName, $applyGlobal = false)
  {
    $additionalCondition = array_key_exists(self::OPT_ITEM_FILTER, $options) ? $options[self::OPT_ITEM_FILTER] : '';
    $skipThese = array_key_exists(self::OPT_SKIP_THESE,$options) ? $options[self::OPT_SKIP_THESE] : 'none';
    $groupId = array_key_exists(self::OPT_GROUP_ID, $options) ? $options[self::OPT_GROUP_ID] : null;
    $agentFilter = self::getAgentFilter($options, $uploadId);

    switch ($skipThese) {
      case "noLicense":
      case "nolicensenocopyright":
      case self::OPT_SKIP_ALREADY_CLEARED:
      case "noCopyright":
      case "noIpra":
      case "noEcc":
      case "noKeyword":

        $queryCondition = self::getQueryCondition($skipThese, $options, $groupId, $agentFilter, $applyGlobal)." ".$additionalCondition;
        if ('uploadtree' === $uploadTreeTableName || 'uploadtree_a' == $uploadTreeTableName) {
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

  private static function getAgentFilter($options,$uploadId=0)
  {
    if (!array_key_exists(self::OPT_SKIP_THESE, $options)) {
      return '';
    }
    $skipThese = $options[self::OPT_SKIP_THESE];
    if ($skipThese != "noLicense" && $skipThese != self::OPT_SKIP_ALREADY_CLEARED) {
      return '';
    }

    if (array_key_exists(self::OPT_AGENT_SET, $options)) {
      $agentIds = 'array[' . implode(',',$options[self::OPT_AGENT_SET]) . ']';
      $agentFilter = " AND lf.agent_fk = ANY($agentIds)";
    } else {
      $scanJobProxy = new ScanJobProxy($GLOBALS['container']->get('dao.agent'),$uploadId);
      $scanJobProxy->createAgentStatus(array_keys(AgentRef::AGENT_LIST));
      $latestAgentIds = $scanJobProxy->getLatestSuccessfulAgentIds();
      $agentFilter = $latestAgentIds ? " AND lf.agent_fk = ANY(array[".implode(',',$latestAgentIds)."])" : "AND 0=1";
    }
    return $agentFilter;
  }

  /**
   * @param $skipThese
   * @param $options
   * @param $groupId
   * @param string $agentFilter
   * @param bool $applyGlobal
   * @return string
   */
  private static function getQueryCondition($skipThese, $options, $groupId = null, $agentFilter='', $applyGlobal = false)
  {
    global $container;
    /** @var LicenseDao $licenseDao */
    $licenseDao = $container->get('dao.license');
    $licensesToRemove = [];
    foreach (['No_license_found', 'Void'] as $licenseName) {
      $license = $licenseDao->getLicenseByShortName($licenseName);
      if ($license) {
        $licensesToRemove[] = "lf.rf_fk != " . $license->getId();
      }
    }
    $licensesToRemove = implode(' AND ', $licensesToRemove);
    if (!empty($licensesToRemove)) {
      $licensesToRemove = "($licensesToRemove) AND ";
    }
    if ($applyGlobal) {
      $globalSql = "(
        ut.uploadtree_pk = cd.uploadtree_fk AND cd.group_fk = $groupId
        AND cd.scope = ".DecisionScopes::ITEM."
      ) OR (
        cd.pfile_fk = ut.pfile_fk AND cd.scope=" . DecisionScopes::REPO . "
      )";
    } else {
      $globalSql = "ut.uploadtree_pk = cd.uploadtree_fk AND cd.group_fk = $groupId";
    }
    $conditionQueryHasLicense = "(EXISTS (SELECT 1 FROM license_file lf " .
      "WHERE ($licensesToRemove" .
      "lf.pfile_fk = ut.pfile_fk $agentFilter))" .
      "OR EXISTS (SELECT 1 FROM clearing_decision AS cd " .
      "WHERE cd.group_fk = $groupId AND ut.uploadtree_pk = cd.uploadtree_fk))";

    switch ($skipThese) {
      case "noLicense":
        return $conditionQueryHasLicense;
      case "nolicensenocopyright":
        return "(" . $conditionQueryHasLicense . " OR " .
               "EXISTS (SELECT copyright_pk FROM copyright cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null) OR " .
               "EXISTS (SELECT 1 FROM copyright_decision AS cd WHERE ut.pfile_fk = cd.pfile_fk))";
      case self::OPT_SKIP_ALREADY_CLEARED:
        $decisionQuery = "
SELECT cd.decision_type
FROM clearing_decision cd
WHERE $globalSql
ORDER BY cd.clearing_decision_pk DESC LIMIT 1";
        return " $conditionQueryHasLicense
            AND NOT EXISTS (SELECT 1 FROM ($decisionQuery) AS latest_decision WHERE latest_decision.decision_type IN (".DecisionTypes::IRRELEVANT.",".DecisionTypes::IDENTIFIED.",".DecisionTypes::DO_NOT_USE.",".DecisionTypes::NON_FUNCTIONAL."))";
      case "noCopyright":
        return "EXISTS (SELECT copyright_pk FROM copyright cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null )".
              " OR EXISTS (SELECT 1 FROM copyright_decision AS cd WHERE ut.pfile_fk = cd.pfile_fk)";
      case "noIpra":
        return "EXISTS (SELECT ipra_pk FROM ipra cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null )".
              " OR EXISTS (SELECT 1 FROM ipra_decision AS cd WHERE ut.pfile_fk = cd.pfile_fk)";
      case "noEcc":
        return "EXISTS (SELECT ecc_pk FROM ecc cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null )".
              " OR EXISTS (SELECT 1 FROM ecc_decision AS cd WHERE ut.pfile_fk = cd.pfile_fk)";
      case "noKeyword":
        return "EXISTS (SELECT keyword_pk FROM keyword cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null )";
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
    if (array_key_exists('uploadId', $params)) {
      $uploadExpr = '$'.(1+array_search('uploadId', array_keys($params)));
    } else {
      $params[] = $this->uploadId;
      $uploadExpr = '$'.count($params);
    }
    $params[] = $parent;
    $parentExpr = '$'.count($params);

    $sql = "SELECT count(*) cnt, u.uploadtree_pk, u.ufile_mode FROM ".$this->uploadTreeTableName." u, "
            . $this->getDbViewName() ." v where u.upload_fk=$uploadExpr"
            . " AND v.lft BETWEEN u.lft and u.rgt and u.parent=$parentExpr GROUP BY u.uploadtree_pk, u.ufile_mode";
    $stmt = __METHOD__.'.'.$this->getDbViewName();
    if (!$this->materialized) {
      $sql = $this->asCTE().' '.$sql;
      $stmt .= '.cte';
    }
    $dbManager->prepare($stmt,$sql);
    $res = $dbManager->execute($stmt,$params);
    $children = array();
    $artifactContainers = array();
    while ($row=$dbManager->fetchArray($res)) {
      $children[$row['uploadtree_pk']] = $row['cnt'];
      if (($row['ufile_mode'] & (3<<28)) == (3<<28)) {
        $artifactContainers[] = $row['uploadtree_pk'];
      }
    }
    $dbManager->freeResult($res);
    foreach ($artifactContainers as $ac) {
      foreach ($this->countMaskedNonArtifactChildren($ac) as $utid => $cnt) {
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
    if (!$this->materialized) {
      $sql = $this->asCTE().' '.$sql;
      $stmt .= '.cte';
    }
    $dbManager->prepare($stmt,$sql);
    $params = array_merge($this->params,
            array($itemTreeBounds->getUploadId(),$itemTreeBounds->getLeft(),$itemTreeBounds->getRight()));
    $res = $dbManager->execute($stmt,$params);
    $descendants = array();
    while ($row = $dbManager->fetchArray($res)) {
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
    if ($this->materialized) {
      $sql = "SELECT count(*) FROM $this->dbViewName";
    } else {
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
