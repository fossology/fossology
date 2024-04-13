<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Data\Highlight;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Util\StringOperation;
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
   * @param array $agentId
   * @param array $typeToHighlightTypeMap
   * @throws \Exception
   * @return Highlight[]
   */
  public function getHighlights($uploadTreeId, $tableName="copyright", $agentId=array(0),
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
    if (!empty($agentId) && $agentId[0] != 0) {
      $agentIds = implode(",", $agentId);
      $statementName .= '.agentId';
      $addAgentValue = ' AND agent_fk= ANY($2::int[])';
      $params[] = "{" . $agentIds . "}";
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
    if (empty($textFinding)) {
      return;
    }
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
   * @param $agentId
   * @param int $scope
   * @return array $rows
   */
  public function getAllEventEntriesForUpload($uploadFk, $agentId, $scope=1)
  {
    $statementName = __METHOD__ . $uploadFk;
    $params[] = $uploadFk;
    $params[] = $agentId;
    $params[] = $scope;
    $sql = "SELECT copyright_pk, CE.is_enabled, C.content, c.hash,
              CE.content AS contentedited, CE.hash AS hashedited
            FROM copyright_event CE
              INNER JOIN copyright C ON C.copyright_pk = CE.copyright_fk
            WHERE CE.upload_fk=$1 AND scope=$3 AND C.agent_fk = $2";
    return $this->dbManager->getRows($sql, $params, $statementName);
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
  public function getScannerEntries($tableName, $uploadTreeTableName, $uploadId,
    $type, $extrawhere, $enabled='true')
  {
    $statementName = __METHOD__.$tableName.$uploadTreeTableName;
    $params = array($uploadId);
    $extendWClause = null;
    $tableNameEvent = $tableName.'_event';

    if ($uploadTreeTableName === "uploadtree_a") {
      $extendWClause .= " AND UT.upload_fk = $1";
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

    $activatedClause = "ce.is_enabled = 'false'";
    if ($enabled != 'false') {
      $activatedClause = "ce.is_enabled IS NULL OR ce.is_enabled = 'true'";
      $statementName .= "._"."enabled";
    }

    $sql = "SELECT DISTINCT ON(copyright_pk, UT.uploadtree_pk)
copyright_pk, UT.uploadtree_pk as uploadtree_pk,
(CASE WHEN (CE.content IS NULL OR CE.content = '') THEN C.content ELSE CE.content END) AS content,
(CASE WHEN (CE.hash IS NULL OR CE.hash = '') THEN C.hash ELSE CE.hash END) AS hash,
C.agent_fk as agent_fk
  FROM $tableName C
  INNER JOIN $uploadTreeTableName UT ON C.pfile_fk = UT.pfile_fk
  LEFT JOIN $tableNameEvent AS CE ON CE.".$tableName."_fk = C.".$tableName."_pk
    AND CE.upload_fk = $1 AND CE.uploadtree_fk = UT.uploadtree_pk
  WHERE C.content IS NOT NULL
    AND C.content!=''
    AND ($activatedClause)
  $extendWClause
ORDER BY copyright_pk, UT.uploadtree_pk, content DESC";
    return $this->dbManager->getRows($sql, $params, $statementName);
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
   * @param bool $onlyCleared
   * @param $decisionType
   * @param $extrawhere
   * @param $groupId
   * @return array
   */
  public function getAllEntriesReport($tableName, $uploadId, $uploadTreeTableName, $type=null, $onlyCleared=false, $decisionType=null, $extrawhere=null, $groupId=null)
  {
    $tableNameDecision = $tableName."_decision";
    if ($tableName == 'copyright') {
      $scannerEntries = $this->getScannerEntries($tableName, $uploadTreeTableName, $uploadId, $type, $extrawhere);
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

    $params = array($uploadId);
    $whereClause = "";
    $distinctContent = "";
    $tableNameDecision = $tableName."_decision";

    if ($uploadTreeTableName === "uploadtree_a") {
      $whereClause .= " AND UT.upload_fk = $1";
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
      $whereClause .= " AND ". $extrawhere;
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
              AND CE.upload_fk = $1 AND CE.uploadtree_fk = UT.uploadtree_pk
            $joinType JOIN (SELECT * FROM $tableNameDecision WHERE is_enabled='true') AS CD
              ON C.pfile_fk = CD.pfile_fk
            WHERE C.content IS NOT NULL
              AND C.content!=''
              AND (ce.is_enabled IS NULL OR ce.is_enabled = 'true')
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
    $sql = "SELECT * FROM $tableName where pfile_fk = $1 order by $orderTablePk desc";
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
  public function updateTable($item, $hash, $content, $userId, $cpTable, $action='', $scope=1)
  {
    $cpTablePk = $cpTable."_pk";
    $cpTableEvent = $cpTable."_event";
    $cpTableEventFk = $cpTable."_fk";
    $itemTable = $item->getUploadTreeTableName();
    $stmt = __METHOD__.".$cpTable.$itemTable";
    $uploadId = $item->getUploadId();
    $params = array($item->getLeft(),$item->getRight(),$uploadId);
    $withHash = "";

    if (!empty($hash)) {
      $params[] = $hash;
      $withHash = " (cp.hash = $4 OR ce.hash = $4) AND ";
      $stmt .= ".hash";
    }
    // get latest agent id for agent
    $agentName = $this->getAgentName($cpTable);
    $scanJobProxy = new ScanJobProxy($GLOBALS['container']->get('dao.agent'),
      $uploadId);
    if ($agentName == "copyright") {
      $scanJobProxy->createAgentStatus(array($agentName, 'reso'));
    } else {
      $scanJobProxy->createAgentStatus(array($agentName));
    }
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($agentName, $selectedScanners)) {
      return array();
    }
    $latestXpAgentId[] = $selectedScanners[$agentName];
    if (array_key_exists('reso', $selectedScanners)) {
      $latestXpAgentId[] = $selectedScanners['reso'];
    }
    $agentFilter = '';
    if (!empty($latestXpAgentId)) {
      $latestAgentIds = implode(",", $latestXpAgentId);
      $agentFilter = ' AND cp.agent_fk IN ('. $latestAgentIds .')';
    }

    $sql = "SELECT DISTINCT ON ($cpTablePk, ut.uploadtree_pk) $cpTablePk, ut.uploadtree_pk, ut.upload_fk, ce." . $cpTableEvent . "_pk
FROM $cpTable as cp
INNER JOIN $itemTable AS ut ON cp.pfile_fk = ut.pfile_fk
LEFT JOIN $cpTableEvent AS ce ON ce.$cpTableEventFk = cp.$cpTablePk
  AND ce.upload_fk = ut.upload_fk AND ce.uploadtree_fk = ut.uploadtree_pk
WHERE $withHash ( ut.lft BETWEEN $1 AND $2 ) $agentFilter AND ut.upload_fk = $3";

    $rows = $this->dbManager->getRows($sql, $params, $stmt);

    foreach ($rows as $row) {
      $paramEvent = array();
      $paramEvent[] = $row['upload_fk'];
      $paramEvent[] = $row[$cpTablePk];
      $paramEvent[] = $row['uploadtree_pk'];
      $sqlExists = "SELECT exists(SELECT 1 FROM $cpTableEvent WHERE $cpTableEventFk = $1 AND upload_fk = $2 AND uploadtree_fk = $3)::int";
      $rowExists = $this->dbManager->getSingleRow($sqlExists, array($row[$cpTablePk], $row['upload_fk'], $row['uploadtree_pk']), $stmt.'Exists');
      $eventExists = $rowExists['exists'];
      if ($action == "delete") {
        $paramEvent[] = $scope;
        if ($eventExists) {
          $sqlEvent = "UPDATE $cpTableEvent SET scope = $4, is_enabled = false
          WHERE upload_fk = $1 AND $cpTableEventFk = $2 AND uploadtree_fk = $3";
          $statement = "$stmt.delete.up";
        } else {
          $sqlEvent = "INSERT INTO $cpTableEvent (upload_fk, $cpTableEventFk, uploadtree_fk, is_enabled, scope) VALUES($1, $2, $3, 'f', $4)";
          $statement = "$stmt.delete";
        }
      } else if ($action == "rollback" && $eventExists) {
          $sqlEvent = "UPDATE $cpTableEvent SET scope = 1, is_enabled = true
          WHERE upload_fk = $1 AND $cpTableEventFk = $2 AND uploadtree_fk = $3";
          $statement = "$stmt.rollback.up";
      } else {
        $paramEvent[] = StringOperation::replaceUnicodeControlChar($content);

        if ($eventExists) {
          $sqlEvent = "UPDATE $cpTableEvent
                       SET upload_fk = $1, content = $4, hash = md5($4)
                       WHERE $cpTableEventFk = $2 AND uploadtree_fk = $3";
          $statement = "$stmt.update";
        } else {
          $sqlEvent = "INSERT INTO $cpTableEvent(upload_fk, uploadtree_fk, $cpTableEventFk, is_enabled, content, hash)
                       VALUES($1, $3, $2, 'true', $4, md5($4))";
          $statement = "$stmt.insert";
        }
      }
      $this->dbManager->getSingleRow($sqlEvent, $paramEvent, $statement);
    }
  }

  /**
   * @brief Get agent name based on table name
   *
   * - copyright => copyright
   * - ecc       => ecc
   * - keyword   => keyword
   * - ipra      => ipra
   * - scancode_copyright, scancode_author => scancode
   * - others    => copyright
   * @param string $table Table name
   * @return string Agent name
   */
  private function getAgentName($table)
  {
    if (array_search($table, ["ecc", "keyword", "copyright", "ipra"]) !== false) {
      return $table;
    } else if (array_search($table, ["scancode_copyright", "scancode_author"]) !== false) {
      return "scancode";
    }
    return "copyright";
  }

  /**
   * @brief Get table name based on statement type
   *
   * - statement => copyright
   * - ecc       => ecc
   * - others    => author
   * - scancode_statement => scancode copyright
   * - scancode_email => scancode email
   * - scancode_author => scancode author
   * - scancode_url => scancode url
   * @param string $type Result type
   * @return string Table name
   */
  public function getTableName($type)
  {
    switch ($type) {
      case "ipra":
        $tableName = "ipra";
        break;
      case "ecc":
        $tableName = "ecc";
        break;
      case "keyword":
        $tableName = "keyword";
        $filter = "none";
        break;
      case "statement":
        $tableName = "copyright";
        break;
      case "userfindingcopyright":
        $tableName = "copyright_decision";
        break;
      case "scancode_statement":
        $tableName = "scancode_copyright";
        break;
      case "scancode_email":
      case "scancode_author":
      case "scancode_url":
        $tableName = "scancode_author";
        break;
      default:
        $tableName = "author";
    }
    return $tableName;
  }

  /**
   * @brief Get total number of copyrights for a uploadtree
   * @param int     $upload_pk           Upload id to get results from
   * @param int     $item                Upload tree id of the item
   * @param string  $uploadTreeTableName Upload tree table to use
   * @param string  $agentId             Id of the agent who loaded the results
   * @param string  $type                Type of the statement (statement, url, email, author or ecc)
   * @param boolean $activated           True to get activated copyrights, else false
   * @return integer Number of total Records
   */
  public function getTotalCopyrights($upload_pk, $item, $uploadTreeTableName, $agentId, $type, $activated = true)
  {
    $tableName = $this->getTableName($type);
    $tableNameEvent = $tableName . '_event';
    list($left, $right) = $this->uploadDao->getLeftAndRight($item, $uploadTreeTableName);
    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName) {
      $sql_upload = " AND UT.upload_fk=$5 ";
    }
    $activatedClause = "ce.is_enabled = 'false'";
    if ($activated) {
      $activatedClause = "ce.is_enabled IS NULL OR ce.is_enabled = 'true'";
    }
    $params = array($left, $right, $type, "{" . $agentId . "}", $upload_pk);
    $join = " INNER JOIN license_file AS LF on cp.pfile_fk=LF.pfile_fk ";
    $unorderedQuery = "FROM $tableName AS cp " .
    "INNER JOIN $uploadTreeTableName AS UT ON cp.pfile_fk = UT.pfile_fk " .
    "LEFT JOIN $tableNameEvent AS ce ON ce." . $tableName . "_fk = cp." . $tableName . "_pk " .
    "AND ce.upload_fk = $5 AND ce.uploadtree_fk = UT.uploadtree_pk " .
    $join .
      "WHERE cp.content!='' " .
      "AND ( UT.lft  BETWEEN  $1 AND  $2 ) " .
      "AND cp.type = $3 " .
      "AND cp.agent_fk = ANY($4::int[]) " .
      "AND ($activatedClause)" .
      $sql_upload;
    $grouping = " GROUP BY mcontent ";
    $countAllQuery = "SELECT count(*) FROM (SELECT
    (CASE WHEN (ce.content IS NULL OR ce.content = '') THEN cp.content ELSE ce.content END) AS mcontent
    $unorderedQuery$grouping) as K";
    $iTotalRecordsRow = $this->dbManager->getSingleRow($countAllQuery, $params, __METHOD__, $tableName . "count.all" . ($activated ? '' : '_deactivated'));
    return $iTotalRecordsRow['count'];
  }

  /**
   * @brief get user copyright findings for a uploadtree
   * @param int     $upload_pk           Upload id to get results from
   * @param int     $item                Upload tree id of the item
   * @param string  $uploadTreeTableName Upload tree table to use
   * @param string  $type                Type of the statement (statement, url, email, author or ecc)
   * @param boolean $activated           True to get activated copyrights, else false
   * @return array[][] Array of table records, total records
   */
  public function getUserCopyrights($upload_pk, $item, $uploadTreeTableName, $type, $activated = true, $offset = null , $limit = null)
  {
    $tableName = $this->getTableName($type);
    list($left, $right) = $this->uploadDao->getLeftAndRight($item, $uploadTreeTableName);

    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName) {
      $sql_upload = " AND UT.upload_fk=$3 ";
    }

    $params = array($left, $right, $upload_pk);

    $activatedClause = "cd.is_enabled = 'false'";
    if ($activated) {
      $activatedClause = "cd.is_enabled IS NULL OR cd.is_enabled = 'true'";
    }

    $unorderedQuery = "FROM $tableName AS cd " .
        "INNER JOIN $uploadTreeTableName AS UT ON cd.pfile_fk = UT.pfile_fk " .
        "WHERE cd.textfinding!='' " .
        "AND ( UT.lft  BETWEEN  $1 AND  $2 ) " .
        "AND cd.user_fk IS NOT NULL " .
        "AND ($activatedClause)" .
        $sql_upload;
    $grouping = " GROUP BY cd.textfinding, cd.hash ";

    $countAllQuery = "SELECT count(*) FROM (SELECT
    (CASE WHEN (cd.textfinding IS NULL OR cd.textfinding = '') THEN '' ELSE cd.textfinding END) AS mcontent
    $unorderedQuery GROUP BY cd.textfinding) as K;";
    $iTotalRecordsRow = $this->dbManager->getSingleRow($countAllQuery, $params, __METHOD__,$tableName . "count.all" . ($activated ? '' : '_deactivated'));
    $iTotalRecords = $iTotalRecordsRow['count'];

    $range = "";
    $params[] = $offset;
    $range .= ' OFFSET $' . count($params);
    $params[] = $limit;
    $range .= ' LIMIT $' . count($params);

    $sql = "SELECT cd.textfinding AS content, cd.hash AS hash, COUNT(DISTINCT cd.copyright_decision_pk) AS copyright_count " .
    "$unorderedQuery $grouping $range";
    $statement = __METHOD__ . $tableName . $uploadTreeTableName . ($activated ? '' : '_deactivated');
    $rows = $this->dbManager->getRows($sql, $params, $statement);

    return array($rows, $iTotalRecords);
  }
}
