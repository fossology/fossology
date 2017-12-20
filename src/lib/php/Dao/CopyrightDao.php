<?php
/*
Copyright (C) 2014-2017, Siemens AG
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
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class CopyrightDao extends Object
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
    $this->logger = new Logger(self::className());
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

    if (!empty($row['pfile_fk']))
    {
      $pFileId = $row['pfile_fk'];
    } else
    {
      $text = _("Could not locate the corresponding pfile.");
      print $text;
    }

    $statementName = __METHOD__.$tableName;
    $params = array($pFileId);
    $addAgentValue = "";
    if(!empty($agentId)) {
      $statementName .= '.agentId';
      $addAgentValue = ' AND agent_fk=$2';
      $params[] = $agentId;
    }
    $getHighlightForTableName = "SELECT * FROM $tableName WHERE copy_startbyte IS NOT NULL AND pfile_fk=$1 $addAgentValue";
    if($tableName != "copyright"){
      $sql = $getHighlightForTableName;
    }else{
      $sql = "$getHighlightForTableName UNION SELECT * FROM author WHERE copy_startbyte IS NOT NULL AND pfile_fk=$1 $addAgentValue";
    }
    $this->dbManager->prepare($statementName,$sql);
    $result = $this->dbManager->execute($statementName, $params);

    $highlights = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
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
   * @return int copyright_decision_pk of decision
   */
  public function saveDecision($tableName, $pfileId, $userId , $clearingType,
                               $description, $textFinding, $comment, $decision_pk=-1)
  {
    $assocParams = array('user_fk'=>$userId,'pfile_fk'=>$pfileId,'clearing_decision_type_fk'=>$clearingType,
        'description'=>$description, 'textfinding'=>$textFinding, 'comment'=>$comment );
    if ($decision_pk <= 0)
    {
      return $this->dbManager->insertTableRow($tableName, $assocParams, __METHOD__.'Insert.'.$tableName, 'copyright_decision_pk');
    }
    else
    {
      $assocParams['is_enabled'] = True;
      $this->dbManager->updateTableRow($tableName, $assocParams, "copyright_decision_pk", $decision_pk, __METHOD__.'Update.'.$tableName);
      return $decision_pk;
    }
  }

  public function removeDecision($tableName,$pfileId, $decisionId)
  {
    $this->dbManager->prepare(__METHOD__,
      "UPDATE $tableName
        SET is_enabled = 'f'
      WHERE copyright_decision_pk = $1
        AND pfile_fk = $2");
    $this->dbManager->execute(__METHOD__, array($decisionId, $pfileId));
  }

  public function undoDecision($tableName,$pfileId, $decisionId)
  {
    $this->dbManager->prepare(__METHOD__,
      "UPDATE $tableName
        SET is_enabled = 't'
      WHERE copyright_decision_pk = $1
        AND pfile_fk = $2");
    $this->dbManager->execute(__METHOD__, array($decisionId, $pfileId));
  }

  /**
   * @param $tableName
   * @param $uploadTreeTableName
   * @param $uploadId
   * @param $type
   * @param $extrawhere
   * @return array $result
   */
  public function getScannerEntries($tableName, $uploadTreeTableName, $uploadId, $type, $extrawhere)
  {
    $statementName = __METHOD__.$tableName.$uploadTreeTableName;
    $params[]= $uploadId;

    $whereClause = null;
    if ($type !== null && $type != "skipcontent")
    {
      $params[]= $type;
      $whereClause .= " AND C.type = $".count($params);
      $statementName .= ".withType";
    }

    if ($extrawhere !== null)
    {
      $whereClause .= "AND ". $extrawhere;
      $statementName .= "._".$extrawhere."_";
    }

    $sql = "SELECT UT.uploadtree_pk as uploadtree_pk, C.content AS content
              FROM $tableName C
             INNER JOIN $uploadTreeTableName UT ON C.pfile_fk = UT.pfile_fk
             WHERE C.content IS NOT NULL
               AND C.content!=''
               AND C.is_enabled='true'
               AND UT.upload_fk = $1
               $whereClause
             ORDER BY UT.uploadtree_pk, C.content DESC";
    $this->dbManager->prepare($statementName, $sql);
    $sqlResult = $this->dbManager->execute($statementName, $params);
    $result = $this->dbManager->fetchAll($sqlResult);
    $this->dbManager->freeResult($sqlResult);

    return $result;
  }

  /**
   * @param $tableName
   * @param $uploadTreeTableName
   * @param $uploadId
   * @param $decisionType
   * @return array $result
   */
  public function getEditedEntries($tableName, $uploadTreeTableName, $uploadId, $decisionType)
  {
    $statementName = __METHOD__.$tableName.$uploadTreeTableName;

    $columns = "CD.description as description, CD.textfinding as textfinding, CD.comment as comments, UT.uploadtree_pk as uploadtree_pk";

    $sql = "SELECT $columns
              FROM $tableName CD
             INNER JOIN uploadtree_a UT ON CD.pfile_fk = UT.pfile_fk
             WHERE CD.is_enabled = 'true'
               AND UT.upload_fk = $1
               AND clearing_decision_type_fk = $2
             ORDER BY CD.pfile_fk, UT.uploadtree_pk, CD.textfinding, CD.copyright_decision_pk DESC";
    $this->dbManager->prepare($statementName, $sql);
    $sqlResult = $this->dbManager->execute($statementName, array($uploadId, $decisionType));
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
  public function getAllEntriesReport($tableName, $uploadId, $uploadTreeTableName, $type=null, $onlyCleared=false, $decisionType=null, $extrawhere=null)
  {
    $tableNameDecision = $tableName."_decision";
    if($tableName == 'copyright'){
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

    $params = array();
    $whereClause = "";
    $distinctContent = "";
    $tableNameDecision = $tableName."_decision";

    if ($uploadTreeTableName === "uploadtree_a")
    {
      $params []= $uploadId;
      $whereClause .= " AND UT.upload_fk = $".count($params);
      $statementName .= ".withUI";
    }
    if ($type !== null && $type != "skipcontent")
    {
      $params []= $type;
      $whereClause .= " AND C.type = $".count($params);
      $statementName .= ".withType";
    }

    $clearingTypeClause = null;
    if ($onlyCleared)
    {
      $joinType = "INNER";
      if ($decisionType !== null)
      {
        $params []= $decisionType;
        $clearingTypeClause = "WHERE clearing_decision_type_fk = $".count($params);
        $statementName .= ".withDecisionType";
      }
      else
      {
        throw new \Exception("requested only cleared but no type given");
      }
    }
    else
    {
      $joinType = "LEFT";
      if ($decisionType !== null)
      {
        $params []= $decisionType;
        $clearingTypeClause = "WHERE clearing_decision_type_fk IS NULL OR clearing_decision_type_fk = $".count($params);
        $statementName .= ".withDecisionType";
      }
    }
    $statementName .= ".".$joinType."Join";

    if ($extrawhere !== null)
    {
      $whereClause .= "AND ". $extrawhere;
      $statementName .= "._".$extrawhere."_";
    }

    $latestInfo = "SELECT DISTINCT ON(CD.pfile_fk, UT.uploadtree_pk, C.content, CD.textfinding)
             CD.description as description, CD.textfinding as textfinding,
             CD.comment as comments, UT.uploadtree_pk as uploadtree_pk,
             CD.clearing_decision_type_fk AS clearing_decision_type_fk,
             C.content AS content
            FROM $tableName C
            INNER JOIN $uploadTreeTableName UT
              ON C.pfile_fk = UT.pfile_fk
            $joinType JOIN (SELECT * FROM $tableNameDecision WHERE is_enabled='true') AS CD
              ON C.pfile_fk = CD.pfile_fk
            WHERE C.content IS NOT NULL
              AND C.content!=''
              AND C.is_enabled='true'
              $whereClause
            ORDER BY CD.pfile_fk, UT.uploadtree_pk, C.content, CD.textfinding, CD.copyright_decision_pk DESC";

    if ($clearingTypeClause !== null)
    {
      $sql = "SELECT * FROM ($latestInfo) AS latestInfo $clearingTypeClause";
    }
    else
    {
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
    $sql = "SELECT * FROM $tableName where pfile_fk = $1 and is_enabled order by copyright_decision_pk desc";
    $params = array($pfileId);

    return $this->dbManager->getRows($sql, $params, $statementName);
  }

  /**
   * @param ItemTreeBounds $item
   * @param string $hash
   * @param string $content
   * @param int $userId
   * @param string $cpTable
   */
  public function updateTable($item, $hash, $content, $userId, $cpTable='copyright', $action='')
  {
    $itemTable = $item->getUploadTreeTableName();
    $stmt = __METHOD__.".$cpTable.$itemTable";
    $params = array($hash,$item->getLeft(),$item->getRight());

    if($action == "delete") {
      $setSql = "is_enabled='false'";
      $stmt .= '.delete';
    } else if($action == "rollback") {
      $setSql = "is_enabled='true'";
      $stmt .= '.rollback';
    } else {
      $setSql = "content = $4, hash = md5($4), is_enabled='true'";
      $params[] = $content;
    }

    $sql = "UPDATE $cpTable AS cpr SET $setSql
            FROM $cpTable as cp
            INNER JOIN $itemTable AS ut ON cp.pfile_fk = ut.pfile_fk
            WHERE cpr.ct_pk = cp.ct_pk
              AND cp.hash = $1
              AND ( ut.lft BETWEEN $2 AND $3 )";
    if ('uploadtree_a' == $item->getUploadTreeTableName())
    {
      $params[] = $item->getUploadId();
      $sql .= " AND ut.upload_fk=$".count($params);
      $stmt .= '.upload';
    }
    
    $this->dbManager->prepare($stmt, "$sql");
    $resource = $this->dbManager->execute($stmt, $params);
    $this->dbManager->freeResult($resource);
  }
}
