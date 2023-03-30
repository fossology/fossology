<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG
 Author: Daniele Fognini, Shaheem Azmal M MD
 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;

abstract class ClearedGetterCommon
{
  /** @var UploadDao */
  protected $uploadDao;

  /** @var TreeDao */
  protected $treeDao;

  /** @var array $fileNameCache */
  private $fileNameCache = array();

  /** @var array $fileHashes */
  private $fileHashes = array();

  private $userId;
  private $groupId;
  private $uploadId;
  private $groupBy;

  public function __construct($groupBy = "content")
  {
    global $container;

    $this->uploadDao = $container->get('dao.upload');
    $this->treeDao = $container->get('dao.tree');

    $this->groupBy = $groupBy;
  }

  /**
   * @throws \Exception Throws exception if user argument is missing
   */
  public function getCliArgs()
  {
    $args = getopt("u:", array("uId:","gId:"));

    if (!array_key_exists('u',$args)) {
      throw new \Exception("missing required parameter -u {uploadId}\n",2);
    }

    $this->uploadId = intval($args['u']);
    $this->userId = intval(@$args['uId']);
    $this->groupId = intval(@$args['gId']);
  }

  public function getUploadId()
  {
    $uploadId = $this->uploadId;

    if ($uploadId <= 0) {
      print "invalid uploadId ".$uploadId;
      exit(2);
    }
    return $uploadId;
  }

  public function getUserId()
  {
    $userId = $this->userId;

    if ($userId <= 0) {
      print "invalid user ".$userId;
      exit(2);
    }
    return $userId;
  }

  public function getGroupId()
  {
    $groupId = $this->groupId;

    if ($groupId <= 0) {
      print "invalid group ".$groupId;
      exit(2);
    }
    return $groupId;
  }

  protected function changeTreeIdsToPaths(&$ungrupedStatements, $uploadTreeTableName, $uploadId)
  {
    $parentId = $this->treeDao->getMinimalCoveringItem($uploadId, $uploadTreeTableName);

    foreach ($ungrupedStatements as &$statement) {
      $uploadTreeId = $statement['uploadtree_pk'];
      unset($statement['uploadtree_pk']);

      if (!array_key_exists($uploadTreeId, $this->fileNameCache)) {
        $this->fileNameCache[$uploadTreeId] = $this->treeDao->getFullPath($uploadTreeId, $uploadTreeTableName, $parentId);
        $this->fileHashes[$uploadTreeId] = $this->treeDao->getItemHashes($uploadTreeId);
      }

      $statement['fileName'] = $this->fileNameCache[$uploadTreeId];
      $statement['fileHash'] = strtolower($this->fileHashes[$uploadTreeId]["sha1"]);
    }
    unset($statement);
  }

  protected function groupStatements($ungrupedStatements, $extended, $agentCall, $isUnifiedReport, $objectAgent)
  {
    $statements = array();
    $findings = array();
    $countLoop = 0;
    foreach ($ungrupedStatements as $statement) {
      $licenseId = (array_key_exists('licenseId', $statement)) ? convertToUTF8($statement['licenseId'], false) : '';
      $content = (array_key_exists('content', $statement)) ? convertToUTF8($statement['content'], false) : '';
      $content = htmlspecialchars($content, ENT_DISALLOWED);
      $comments = (array_key_exists('comments', $statement)) ? convertToUTF8($statement['comments'], false) : '';
      $fileName = $statement['fileName'];
      $fileHash = $statement['fileHash'];
      if (array_key_exists('acknowledgement', $statement)) {
        $acknowledgement = $statement['acknowledgement'];
      } else {
        $acknowledgement = "";
      }

      if (!array_key_exists('text', $statement)) {
        $description = (array_key_exists('description', $statement)) ? convertToUTF8($statement['description'], false) : '';
        $textfinding = (array_key_exists('textfinding', $statement)) ? convertToUTF8($statement['textfinding'], false) : '';

        if ($description === null) {
          $text = "";
        } else {
          if (!empty($textfinding) && empty($agentCall)) {
            $content = $textfinding;
          }
          $text = $description;
        }
      } else {
        $text = $statement['text'];
      }

      if ($agentCall == "license") {
        $this->groupBy = "text";
        $groupBy = md5($statement[$this->groupBy].$statement["content"]);
      } else {
        $this->groupBy = "content";
        $groupBy = $statement[$this->groupBy];
      }

      if (empty($comments) && array_key_exists($groupBy, $statements)) {
        $currentFiles = &$statements[$groupBy]['files'];
        $currentHash = &$statements[$groupBy]['hash'];
        $currentAcknowledgement = &$statements[$groupBy]['acknowledgement'];
        if (!in_array($fileName, $currentFiles)) {
          $currentFiles[] = $fileName;
          $currentHash[] = $fileHash;
          $currentAcknowledgement[] = $acknowledgement;
        }
      } else {
        $singleStatement = array(
            "licenseId" => $licenseId,
            "content" => convertToUTF8($content, false),
            "text" => convertToUTF8($text, false),
            "files" => array($fileName),
            "hash" => array($fileHash),
            "acknowledgement" => array($acknowledgement)
          );
        if ($extended) {
          $singleStatement["licenseId"] = $licenseId;
          $singleStatement["comments"] = convertToUTF8($comments, false);
          $singleStatement["risk"] = (array_key_exists('risk', $statement)) ? convertToUTF8($statement['risk'], false) : 0;
        }

        if (empty($comments)) {
          $statements[$groupBy] = $singleStatement;
        } else {
          $statements[] = $singleStatement;
        }
      }

      if (!empty($statement['textfinding']) && !empty($agentCall) && $agentCall != "license") {
        $findings[] = array(
            "licenseId" => $licenseId,
            "content" => convertToUTF8($statement['textfinding'], false),
            "text" => convertToUTF8($text, false),
            "files" => array($fileName),
            "hash" => array($fileHash)
          );
        if ($extended) {
          $key = array_search($statement['textfinding'], array_column($findings, 'content'));
          $findings[$key]["comments"] = convertToUTF8($comments, false);
          $findings[$key]["licenseId"] = $licenseId;
        }
      }
      //To keep the schedular alive for large files
      $countLoop += 1;
      if ($countLoop % 500 == 0) {
        $objectAgent->heartbeat(0);
      }
    }
    arsort($statements);
    if ($agentCall == "copyright" && $isUnifiedReport) {
      arsort($findings);
      if (!empty($objectAgent)) {
        $actualHeartbeat = (count($statements) + count($findings));
        $objectAgent->heartbeat($actualHeartbeat);
      }
      return array("userFindings" => $findings, "scannerFindings" => $statements);
    } else {
      $statements = array_merge($findings, $statements);
      if (!empty($objectAgent)) {
        $objectAgent->heartbeat(count($statements));
      }
      return array("statements" => array_values($statements));
    }
  }

  /**
   * @param int $uploadId
   * @param string $uploadTreeTableName
   * @param null|int $groupId
   * @return array
   */
  abstract protected function getStatements($uploadId, $uploadTreeTableName, $groupId=null);

  public function getCleared($uploadId, $objectAgent, $groupId=null, $extended=true, $agentcall=null, $isUnifiedReport=false)
  {
    $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
    $ungroupedStatements = $this->getStatements($uploadId, $uploadTreeTableName, $groupId);
    $this->changeTreeIdsToPaths($ungroupedStatements, $uploadTreeTableName, $uploadId);
    return $this->groupStatements($ungroupedStatements, $extended, $agentcall,
      $isUnifiedReport, $objectAgent);
  }

  public function getLicenseHistogramForReport($uploadId, $groupId)
  {
    $histogramStatements = $this->getHistogram($uploadId, $groupId);
    return array("statements" => $histogramStatements);
  }

  public function cJson($uploadId, $groupId=null)
  {
    $escapeChars = array('\\f',"\\", "/", "\"");
    $withThisValue = array("","\\\\", "\\/", "\\\"");
    $groupedStatements = $this->getCleared($uploadId, null, $groupId, false,
      null, false);
    if (array_key_exists("statements", $groupedStatements)) {
      $clearedString = str_replace($escapeChars, $withThisValue,
        $groupedStatements["statements"]);
    } else { // Called from unknown entity
      $clearedString = $groupedStatements;
    }
    $json = json_encode($clearedString);
    return str_replace('\u001b','',$json);
  }

  public function cJsonHist($uploadId, $groupId=null)
  {
    $jsonHist = json_encode($this->getLicenseHistogramForReport($uploadId, $groupId));
    return str_replace('\u001b','',str_replace('\\f','',$jsonHist));
  }
}
