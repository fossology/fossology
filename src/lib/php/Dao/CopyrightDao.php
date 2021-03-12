<?php
/*
Copyright (C) 2014-2018, Siemens AG
Author: Andreas Würl

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

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Util\StringOperation;
use Fossology\Lib\Data\AgentRef;
use Monolog\Logger;

class CopyrightDao
{
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;
  /** @var Logger */
  private $logger;

  function __construct(DbManager $dbManager, UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->uploadDao = $uploadDao;
    $this->logger = new Logger(self::class);
  }

  /**
   * @param int $uploadTreeId
   * @param string $tableName
   * @param int $agentId
   * @param array $typeToHighlightTypeMap
   * @throws \Exception
   * @return Highlight[]
   */
  public function getHighlights($uploadTreeId, $tableName="copyright", $agentId=0,
          $typeToHighlightTypeMap=array(
                                          'statement' => Highlight::COPYRIGHT,
                                          'email' => Highlight::EMAIL,
                                          'url' => Highlight::URL,
                                          'author' => Highlight::AUTHOR)
   )
  {
    $pFileId = 0;
    $row = $this->uploadDao->getUploadEntry($uploadTreeId);

    if (!empty($row['pfile_fk'])) {
      $pFileId = $row['pfile_fk'];
    } else {
      $text = _("Could not locate the corresponding pfile.");
      print $text;
    }

    $statementName = __METHOD__.$tableName;
    $params = array($pFileId);
    $addAgentValue = "";
    if (!empty($agentId)) {
      $statementName .= '.agentId';
      $addAgentValue = ' AND agent_fk=$2';
      $params[] = $agentId;
    }
    $columnsToSelect = "type, content, copy_startbyte, copy_endbyte";
    $getHighlightForTableName = "SELECT $columnsToSelect FROM $tableName WHERE copy_startbyte IS NOT NULL AND pfile_fk=$1 $addAgentValue";
    if ($tableName != "copyright") {
      $sql = $getHighlightForTableName;
    } else {
      $sql = "$getHighlightForTableName UNION SELECT $columnsToSelect FROM author WHERE copy_startbyte IS NOT NULL AND pfile_fk=$1 $addAgentValue";
    }
    $this->dbManager->prepare($statementName,$sql);
    $result = $this->dbManager->execute($statementName, $params);

    $highlights = array();
    while ($row = $this->dbManager->fetchArray($result)) {
      $type = $row['type'];
      $content = $row['content'];
      $htmlElement =null;
      $highlightType = array_key_exists($type, $typeToHighlightTypeMap) ? $typeToHighlightTypeMap[$type] : Highlight::UNDEFINED;
      $highlights[] = new Highlight($row['copy_startbyte'], $row['copy_endbyte'], $highlightType, -1, -1, $content, $htmlElement);
    }
    $this->dbManager->freeResult($result);

    return $highlights;
  }

  /**
   * @param $tableName
   * @param $pfileId
   * @param $userId
   * @param $clearingType
   * @param $description
   * @param $textFinding
   * @param $comment
   * @param int $decision_pk
   * @return int decision_pk of decision
   */
  public function saveDecision($tableName, $pfileId, $userId , $clearingType,
                               $description, $textFinding, $comment, $decision_pk=-1)
  {
    $textFinding = StringOperation::replaceUnicodeControlChar($textFinding);
    $primaryColumn = $tableName . '_pk';
    $assocParams = array(
      'user_fk' => $userId,
      'pfile_fk' => $pfileId,
      'clearing_decision_type_fk' => $clearingType,
      'description' => $description,
      'textfinding' => $textFinding,
      'hash' => hash('sha256', $textFinding),
      'comment'=> $comment
    );

    if ($decision_pk <= 0) {
      $rows = $this->getDecisionsFromHash($tableName, $assocParams['hash']);
      foreach ($rows as $row) {
        if ($row['pfile_fk'] == $pfileId) {
          $decision_pk = $row[$primaryColumn];
        }
      }
    }
    if ($decision_pk <= 0) {
      return $this->dbManager->insertTableRow($tableName, $assocParams,
        __METHOD__.'Insert.'.$tableName, $primaryColumn);
    } else {
      $assocParams['is_enabled'] = true;
      $this->dbManager->updateTableRow($tableName, $assocParams, $primaryColumn,
        $decision_pk, __METHOD__.'Update.'.$tableName);
      return $decision_pk;
    }
  }

  public function removeDecision($tableName,$pfileId, $decisionId)
  {
    $primaryColumn = $tableName . '_pk';
    $this->dbManager->prepare(__METHOD__,
      "UPDATE $tableName
        SET is_enabled = 'f'
      WHERE $primaryColumn = $1
        AND pfile_fk = $2");
    $this->dbManager->execute(__METHOD__, array($decisionId, $pfileId));
  }

  public function undoDecision($tableName,$pfileId, $decisionId)
  {
    $primaryColumn = $tableName . '_pk';
    $this->dbManager->prepare(__METHOD__,
      "UPDATE $tableName
        SET is_enabled = 't'
      WHERE $primaryColumn = $1
        AND pfile_fk = $2");
    $this->dbManager->execute(__METHOD__, array($decisionId, $pfileId));
  }

  /**
   * @param $uploadFk
   * @param $scope
   * @return array $rows
   */
  public function getAllEventEntriesForUpload($uploadFk, $agentId, $scope=1)
  {
    $statementName = __METHOD__.$uploadFk;
    $params[] = $uploadFk;
    $params[] = $agentId;
    $params[] = $scope;
    $sql = "SELECT C.content, c.hash, CE.content AS contentedited, CE.hash AS hashedited, CE.is_enabled
              FROM copyright_event CE INNER JOIN copyright C ON C.copyright_pk = CE.copyright_fk
            WHERE CE.upload_fk=$1 AND scope=$3 AND C.agent_fk = $2";

    $this->dbManager->prepare($statementName, $sql);
    $sqlResult = $this->dbManager->execute($statementName, $params);
    $rows = $this->dbManager->fetchAll($sqlResult);
    $this->dbManager->freeResult($sqlResult);

    return $rows;
  }

  /**
   * @param $tableName
   * @param $uploadTreeTableName
   * @param $uploadId
   * @param $type
   * @param $extrawhere
   * @param $enabled
   * @return array $result
   */
  public function getScannerEntries($tableName, $uploadTreeTableName, $uploadId, $type, $extrawhere, $enabled='true')
  {
    $statementName = __METHOD__.$tableName.$uploadTreeTableName;
    $params = array();
    $extendWClause = null;
    $tableNameEvent = $tableName.'_event';

    if ($uploadTreeTableName === "uploadtree_a") {
      $params[]= $uploadId;
      $extendWClause .= " AND UT.upload_fk = $".count($params);
      $statementName .= ".withUI";
    }

    if ($type !== null && $type != "skipcontent") {
      $params[]= $type;
      $extendWClause .= " AND C.type = $".count($params);
      $statementName .= ".withType";
    }

    if ($extrawhere !== null) {
      $extendWClause .= " AND ". $extrawhere;
      $statementName .= "._".$extrawhere."_";
    }

    if (!$enabled == 'false') {
      $activatedClause = " AND CE.is_enabled=false ";
      $statementName .= "._"."enabled";
    } else {
      $activatedClause = " AND C.".$tableName."_pk NOT IN (SELECT ".$tableName."_fk FROM $tableNameEvent WHERE upload_fk = $uploadId AND is_enabled = false) ";
    }

    $sql = "SELECT UT.uploadtree_pk as uploadtree_pk, C.content AS content,
              C.hash AS hash, C.agent_fk as agent_fk, CE.content AS contentedited, CE.hash AS hashedited
              FROM $tableName C
             INNER JOIN $uploadTreeTableName UT ON C.pfile_fk = UT.pfile_fk
             LEFT JOIN $tableNameEvent AS CE ON CE.".$tableName."_fk = C.".$tableName."_pk
             WHERE C.content IS NOT NULL
               AND C.content!=''
               $activatedClause
               $extendWClause
             ORDER BY UT.uploadtree_pk, C.content DESC";
    $this->dbManager->prepare($statementName, $sql);
    $sqlResult = $this->dbManager->execute($statementName, $params);
    $rows = $this->dbManager->fetchAll($sqlResult);
    foreach ($rows as $key => $row) {
      if (!empty($row['contentedited'])) {
        $rows[$key]['content'] = $rows[$key]['contentedited'];
        $row[$key]['hash'] = $rows[$key]['hashedited'];
      }
    }
    $this->dbManager->freeResult($sqlResult);

    return $rows;
  }

  /**
   * @param string  $tableName
   * @param string  $uploadTreeTableName
   * @param integer $uploadId
   * @param integer $decisionType
   * @param string  $extrawhere
   * @return array $result
   */
  public function getEditedEntries($tableName, $uploadTreeTableName, $uploadId,
    $decisionType, $extrawhere="")
  {
    $statementName = __METHOD__.$tableName.$uploadTreeTableName;
    $params = array();
    $extendWClause = null;

    if ($uploadTreeTableName === "uploadtree_a") {
      $params[]= $uploadId;
      $extendWClause .= " AND UT.upload_fk = $".count($params);
      $statementName .= ".withUI";
    }

    if (!empty($decisionType)) {
      $params[]= $decisionType;
      $extendWClause .= " AND clearing_decision_type_fk = $".count($params);
      $statementName .= ".withDecisionType";
    }

    if (!empty($extrawhere)) {
      $extendWClause .= " AND ". $extrawhere;
      $statementName .= "._".$extrawhere."_";
    }

    $columns = "CD.description as description, CD.textfinding as textfinding, CD.comment as comments, UT.uploadtree_pk as uploadtree_pk";

    $primaryColumn = $tableName . '_pk';
    $sql = "SELECT $columns
              FROM $tableName CD
             INNER JOIN $uploadTreeTableName UT ON CD.pfile_fk = UT.pfile_fk
             WHERE CD.is_enabled = 'true'
              $extendWClause
             ORDER BY CD.pfile_fk, UT.uploadtree_pk, CD.textfinding, CD.$primaryColumn DESC";
    $this->dbManager->prepare($statementName, $sql);
    $sqlResult = $this->dbManager->execute($statementName, $params);
    $result = $this->dbManager->fetchAll($sqlResult);
    $this->dbManager->freeResult($sqlResult);

    return $result;
  }

  /**
   * @param $tableName
   * @param $uploadId
   * @param $uploadTreeTableName
   * @param $type
   * @param $onlyCleared
   * @param $decisionType
   * @param $extrawhere
   * @return array
   */
  public function getAllEntriesReport($tableName, $uploadId, $uploadTreeTableName, $type=null, $onlyCleared=false, $decisionType=null, $extrawhere=null, $groupId=null)
  {
    $tableNameDecision = $tableName."_decision";
    if ($tableName == 'copyright') {
      $scannerEntries = $this->getScannerEntries($tableName, $uploadTreeTableName, $uploadId, $type, $extrawhere);
      if (!empty($groupId)) {
        $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
        $irrelevantDecisions = $GLOBALS['container']->get('dao.clearing')->getFilesForDecisionTypeFolderLevel($itemTreeBounds, $groupId);
        $uniqueIrrelevantDecisions = array_unique(array_column($irrelevantDecisions, 'uploadtree_pk'));
        foreach ($scannerEntries as $key => $value) {
          if (in_array($value['uploadtree_pk'], $uniqueIrrelevantDecisions)) {
            unset($scannerEntries[$key]);
          }
        }
      }
      $editedEntries = $this->getEditedEntries($tableNameDecision, $uploadTreeTableName, $uploadId, $decisionType);
      return array_merge($scannerEntries, $editedEntries);
    } else {
      return $this->getEditedEntries($tableNameDecision, $uploadTreeTableName, $uploadId, $decisionType);
    }
  }

  public function getAllEntries($tableName, $uploadId, $uploadTreeTableName, $type=null, $onlyCleared=false, $decisionType=null, $extrawhere=null)
  {
    $statementName = __METHOD__.$tableName.$uploadTreeTableName;
    $tableNameEvent = $tableName.'_event';

    $params = array();
    $whereClause = "";
    $distinctContent = "";
    $tableNameDecision = $tableName."_decision";

    if ($uploadTreeTableName === "uploadtree_a") {
      $params []= $uploadId;
      $whereClause .= " AND UT.upload_fk = $".count($params);
      $statementName .= ".withUI";
    }
    if ($type !== null && $type != "skipcontent") {
      $params []= $type;
      $whereClause .= " AND C.type = $".count($params);
      $statementName .= ".withType";
    }

    $clearingTypeClause = null;
    if ($onlyCleared) {
      $joinType = "INNER";
      if ($decisionType !== null) {
        $params []= $decisionType;
        $clearingTypeClause = "WHERE clearing_decision_type_fk = $".count($params);
        $statementName .= ".withDecisionType";
      } else {
        throw new \Exception("requested only cleared but no type given");
      }
    } else {
      $joinType = "LEFT";
      if ($decisionType !== null) {
        $params []= $decisionType;
        $clearingTypeClause = "WHERE clearing_decision_type_fk IS NULL OR clearing_decision_type_fk = $".count($params);
        $statementName .= ".withDecisionType";
      }
    }
    $statementName .= ".".$joinType."Join";

    if ($extrawhere !== null) {
      $whereClause .= "AND ". $extrawhere;
      $statementName .= "._".$extrawhere."_";
    }
    $decisionTableKey = $tableNameDecision . "_pk";

    $latestInfo = "SELECT DISTINCT ON(CD.pfile_fk, UT.uploadtree_pk, C.content, CD.textfinding)
             CD.description as description, CD.textfinding as textfinding,
             CD.comment as comments, UT.uploadtree_pk as uploadtree_pk,
             CD.clearing_decision_type_fk AS clearing_decision_type_fk,
             C.content AS content
            FROM $tableName C
            INNER JOIN $uploadTreeTableName UT
              ON C.pfile_fk = UT.pfile_fk
            LEFT JOIN $tableNameEvent AS CE
              ON CE.".$tableName."_fk = C.".$tableName."_pk
            $joinType JOIN (SELECT * FROM $tableNameDecision WHERE is_enabled='true') AS CD
              ON C.pfile_fk = CD.pfile_fk
            WHERE C.content IS NOT NULL
              AND C.content!=''
              AND C.".$tableName."_pk NOT IN (SELECT DISTINCT(".$tableName."_fk) FROM $tableNameEvent TE WHERE TE.upload_fk = $uploadId AND is_enabled = false)
              $whereClause
            ORDER BY CD.pfile_fk, UT.uploadtree_pk, C.content, CD.textfinding, CD.$decisionTableKey DESC";

    if ($clearingTypeClause !== null) {
      $sql = "SELECT * FROM ($latestInfo) AS latestInfo $clearingTypeClause";
    } else {
      $sql = $latestInfo;
    }

    $this->dbManager->prepare($statementName, $sql);
    $sqlResult = $this->dbManager->execute($statementName, $params);
    $result = $this->dbManager->fetchAll($sqlResult);
    $this->dbManager->freeResult($sqlResult);

    return $result;
  }

  /**
   * @param $tableName
   * @param $pfileId
   * @return array
   */
  public function getDecisions($tableName,$pfileId)
  {
    $statementName = __METHOD__.$tableName;
    $orderTablePk = $tableName.'_pk';
    $sql = "SELECT * FROM $tableName where pfile_fk = $1 and is_enabled order by $orderTablePk desc";
    $params = array($pfileId);

    return $this->dbManager->getRows($sql, $params, $statementName);
  }

  /**
   * @brief Get all the decisions based on hash
   *
   * Get all the decisions which matches the given hash. If the upload is null,
   * get decision from all uploads, otherwise get decisions only for the given
   * upload.
   *
   * @param string $tableName Decision table name
   * @param string $hash      Hash of the decision
   * @param int    $upload    Upload id
   * @param string $uploadtreetable Name of the upload tree table
   * @return array
   */
  public function getDecisionsFromHash($tableName, $hash, $upload = null, $uploadtreetable = null)
  {
    $statementName = __METHOD__ . ".$tableName";
    $orderTablePk = $tableName.'_pk';
    $join = "";
    $joinWhere = "";
    $params = [$hash];

    if ($upload != null) {
      if (empty($uploadtreetable)) {
        return -1;
      }
      $statementName.= ".filterUpload";
      $params[] = $upload;
      $join = "INNER JOIN $uploadtreetable AS ut ON cp.pfile_fk = ut.pfile_fk";
      $joinWhere = "AND ut.upload_fk = $" . count($params);
    }

    $sql = "SELECT * FROM $tableName AS cp $join " .
      "WHERE cp.hash = $1 $joinWhere ORDER BY $orderTablePk;";

    return $this->dbManager->getRows($sql, $params, $statementName);
  }

  /**
   * @param ItemTreeBounds $item
   * @param string $hash
   * @param string $content
   * @param int $userId
   * @param string $cpTable
   */
  public function updateTable($item, $hash, $content, $userId, $cpTable='copyright', $action='', $scope=1, $forCopyrightTestCases=array())
  {
    $cpTablePk = $cpTable."_pk";
    $cpTableEvent = $cpTable."_event";
    $cpTableEventFk = $cpTable."_fk";
    $paramEvent = array();
    $stmt = '.updateTable';
    if (empty($forCopyrightTestCases)) {
      $itemTable = $item->getUploadTreeTableName();
      $stmt = __METHOD__.".$cpTable.$itemTable";
      $uploadId = $item->getUploadId();
      $params = array($item->getLeft(),$item->getRight(),$uploadId);
      $withHash = "";

      if (!empty($hash)) {
        $params[] = $hash;
        $withHash = " cp.hash = $4 AND ";
        $stmt .= ".hash";
      }
      // get latest agent id for copyright agent
      $agentName = "copyright";
      $scanJobProxy = new ScanJobProxy($GLOBALS['container']->get('dao.agent'),
        $uploadId);
      $scanJobProxy->createAgentStatus([$agentName]);
      $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
      if (!array_key_exists($agentName, $selectedScanners)) {
        return array();
      }
      $latestAgentId = $selectedScanners[$agentName];
      $agentFilter = '';
      if (!empty($latestAgentId)) {
        $agentFilter = ' AND cp.agent_fk='.$latestAgentId;
      }

      $sql = "SELECT DISTINCT ON ($cpTablePk) $cpTablePk, uploadtree_pk, upload_fk FROM $cpTable as cp
                INNER JOIN $itemTable AS ut ON cp.pfile_fk = ut.pfile_fk
                AND $withHash ( ut.lft BETWEEN $1 AND $2 ) $agentFilter AND ut.upload_fk = $3";

      $this->dbManager->prepare($stmt, "$sql");
      $resource = $this->dbManager->execute($stmt, $params);
      $rows = $this->dbManager->fetchAll($resource);
      $this->dbManager->freeResult($resource);
    } else {
      $rows = $forCopyrightTestCases;
    }

    foreach ($rows as $row) {
      $paramEvent[] = $row['upload_fk'];
      $paramEvent[] = $row[$cpTablePk];
      $paramEvent[] = $row['uploadtree_pk'];
      if ($action == "delete") {
        $paramEvent[] = $scope;
        $sqlEvent = "INSERT INTO $cpTableEvent (upload_fk, $cpTableEventFk, uploadtree_fk, scope) VALUES($1, $2, $3, $4)";
        $stmt .= '.delete';
      } else if ($action == "rollback") {
        $sqlEvent = "DELETE FROM $cpTableEvent WHERE upload_fk = $1 AND uploadtree_fk = $3 AND $cpTableEventFk = $2";
        $stmt .= '.rollback';
      } else {
        $paramEvent[] = "true";
        $paramEvent[] = StringOperation::replaceUnicodeControlChar($content);
        $sqlExists = "SELECT exists(SELECT 1 FROM $cpTableEvent WHERE $cpTableEventFk = $1)::int";
        $rowExists = $this->dbManager->getSingleRow($sqlExists, array($row[$cpTablePk]), $stmt.'Exists');

        if ($rowExists['exists']) {
          $sqlEvent = "UPDATE $cpTableEvent
                        SET upload_fk = $1, uploadtree_fk = $3, is_enabled = $4, content = $5, hash = md5($5)
                       WHERE $cpTableEventFk = $2";
          $stmt .= '.update';
        } else {
          $sqlEvent = "INSERT INTO $cpTableEvent(upload_fk, uploadtree_fk, $cpTableEventFk, is_enabled, content, hash)
                       VALUES($1, $3, $2, $4, $5, md5($5))";
          $stmt .= '.insert';
        }
      }
      $this->dbManager->prepare($stmt, "$sqlEvent");
      $resourceEvent = $this->dbManager->execute($stmt, $paramEvent);
      $this->dbManager->freeResult($resourceEvent);
      $paramEvent = array();
    }
  }
}
