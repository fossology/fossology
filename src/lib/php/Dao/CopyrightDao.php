<?php
/*
Copyright (C) 2014, Siemens AG
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
   * @param array $typeToHighlightTypeMap
   * @throws \Exception
   * @return Highlight[]
   */
  public function getHighlights($uploadTreeId, $tableName="copyright" ,$typeToHighlightTypeMap=array(
                                                                        'statement' => Highlight::COPYRIGHT,
                                                                        'email' => Highlight::EMAIL,
                                                                        'url' => Highlight::URL)
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
    $this->dbManager->prepare($statementName,
        "SELECT * FROM $tableName WHERE copy_startbyte IS NOT NULL and pfile_fk=$1");
    $result = $this->dbManager->execute($statementName, array($pFileId));

    $highlights = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      if (!empty($row['copy_startbyte']))
      {
        $type = $row['type'];
        $content = $row['content'];
        $htmlElement =null;
        $highlightType = array_key_exists($type, $typeToHighlightTypeMap) ? $typeToHighlightTypeMap[$type] : Highlight::UNDEFINED;
        $highlights[] = new Highlight($row['copy_startbyte'], $row['copy_endbyte'], $highlightType, -1, -1, $content, $htmlElement);
      }
    }
    $this->dbManager->freeResult($result);

    return $highlights;
  }

  public function saveDecision($tableName,$pfileId, $userId , $clearingType,
                                         $description, $textFinding, $comment){
    $assocParams = array('user_fk'=>$userId,'pfile_fk'=>$pfileId,'clearing_decision_type_fk'=>$clearingType,
        'description'=>$description, 'textfinding'=>$textFinding, 'comment'=>$comment );
    $this->dbManager->insertTableRow($tableName, $assocParams, $sqlLog=__METHOD__);
  }

  public function getAllEntries($tableName, $uploadId, $uploadTreeTableName, $type=null, $onlyCleared=false, $decisionType=null, $extrawhere=null)
  {
    $statementName = __METHOD__.$tableName.$uploadTreeTableName;

    $params = array();
    $whereClause = "";
    $tableNameDecision = $tableName."_decision";

    if ($uploadTreeTableName === "uploadtree_a")
    {
      $params []= $uploadId;
      $whereClause .= " AND UT.upload_fk = $".count($params);
      $statementName .= ".withUI";
    }
    if ($type !== null)
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
      $whereClause .= " AND ". $extrawhere;
      $statementName .= "._".$extrawhere."_";
    }

    $latestInfo = "SELECT DISTINCT ON(CD.pfile_fk, UT.uploadtree_pk, C.content)
             CD.description as description, CD.textfinding as textfinding,
             UT.uploadtree_pk as uploadtree_pk,
             CD.clearing_decision_type_fk AS clearing_decision_type_fk,
             C.content AS content
            from $tableName C
            INNER JOIN $uploadTreeTableName UT
            ON C.pfile_fk = UT.pfile_fk
            $joinType JOIN $tableNameDecision CD
            ON C.pfile_fk = CD.pfile_fk
            WHERE C.content IS NOT NULL $whereClause
            ORDER BY CD.pfile_fk, UT.uploadtree_pk, C.content, CD.copyright_decision_pk DESC";

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

  public function getDecision($tableName,$pfileId){

    $statementName = __METHOD__.$tableName;
    $sql = "SELECT * from $tableName where pfile_fk = $1 order by copyright_decision_pk desc limit 1";

    $res = $this->dbManager->getSingleRow($sql,array($pfileId),$statementName);

    $description = $res['description'];
    $textFinding = $res['textfinding'];
    $comment = $res['comment'];
    $decisionType = $res['clearing_decision_type_fk'];
    return array($description,$textFinding,$comment, $decisionType);
  }
}