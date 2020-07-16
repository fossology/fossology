<?php
/*
 Copyright (C) 2014-2017, Siemens AG
 Author: Daniele Fognini, Shaheem Azmal M MD

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

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\TreeDao;
use Fossology\Lib\Dao\UploadDao;

abstract class ClearedGetterCommon
{
  /** @var UploadDao */
  protected $uploadDao;

  /** @var TreeDao */
  protected $treeDao;

  /** @var array */
  private $fileNameCache = array();

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

  public function getCliArgs()
  {
    $args = getopt("u:", array("uId:","gId:"));

    if (!array_key_exists('u',$args)) {
      throw new Exception("missing required parameter -u {uploadId}\n",2);
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
      }

      $statement['fileName'] = $this->fileNameCache[$uploadTreeId];
    }
    unset($statement);
  }

  protected function groupStatements($ungrupedStatements, $extended, $agentCall, $isUnifiedReport, $objectAgent)
  {
    $statements = array();
    $findings = array();
    $countLoop = 0;
    foreach ($ungrupedStatements as $statement) {
      $licenseId = $statement['licenseId'];
      $content = convertToUTF8($statement['content'], false);
      $content = htmlspecialchars($content, ENT_DISALLOWED);
      $comments = convertToUTF8($statement['comments'], false);
      $fileName = $statement['fileName'];

      if (!array_key_exists('text', $statement)) {
        $description = $statement['description'];
        $textfinding = $statement['textfinding'];

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
      } else {
        $this->groupBy = "content";
      }
      $groupBy = $statement[$this->groupBy];

      if (empty($comments) && array_key_exists($groupBy, $statements)) {
        $currentFiles = &$statements[$groupBy]['files'];
        if (!in_array($fileName, $currentFiles)) {
          $currentFiles[] = $fileName;
        }
      } else {
        $singleStatement = array(
            "licenseId" => $licenseId,
            "content" => convertToUTF8($content, false),
            "text" => convertToUTF8($text, false),
            "files" => array($fileName)
          );
        if ($extended) {
          $singleStatement["comments"] = convertToUTF8($comments, false);
          $singleStatement["risk"] =  $statement['risk'];
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
            "files" => array($fileName)
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
    $ungrupedStatements = $this->getStatements($uploadId, $uploadTreeTableName, $groupId);
    $this->changeTreeIdsToPaths($ungrupedStatements, $uploadTreeTableName, $uploadId);
    $statements = $this->groupStatements($ungrupedStatements, $extended, $agentcall, $isUnifiedReport, $objectAgent);
    return $statements;
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
    $clearedString = str_replace($escapeChars, $withThisValue, $this->getCleared($uploadId, null, $groupId, false, null, false));
    $json = json_encode($clearedString);
    return str_replace('\u001b','',$json);
  }

  public function cJsonHist($uploadId, $groupId=null)
  {
    $jsonHist = json_encode($this->getLicenseHistogramForReport($uploadId, $groupId));
    return str_replace('\u001b','',str_replace('\\f','',$jsonHist));
  }
}
