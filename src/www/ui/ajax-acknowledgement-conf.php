<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Db\DbManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class AjaxAcknowledgementConf
 * @brief AJAX handler for acknowledgement operations on report-conf page
 */
class AjaxAcknowledgementConf extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name = "ajax-acknowledgement-conf";
    $this->Title = _("Ajax Acknowledgement Configuration");
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->OutputType = 'JSON';
    $this->LoginFlag = 0;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  function PostInitialize()
  {
    $this->State = PLUGIN_STATE_READY;
    return $this->State;
  }

  function Output()
  {
    $action = GetParm("action", PARM_STRING);
    $uploadId = GetParm("upload", PARM_INTEGER);
    $groupId = Auth::getGroupId();

    if (!$this->uploadDao->isAccessible($uploadId, $groupId)) {
      return new Response('Permission denied', Response::HTTP_FORBIDDEN,
        array('Content-type' => 'text/plain'));
    }

    switch ($action) {
      case "getFiles":
        return $this->doGetFiles($uploadId, $groupId);
      case "updateAcknowledgement":
      case "deleteAcknowledgement":
        if (!$this->uploadDao->isEditable($uploadId, $groupId)) {
          return new Response('Permission denied', Response::HTTP_FORBIDDEN,
            array('Content-type' => 'text/plain'));
        }
        if ($action === "updateAcknowledgement") {
          return $this->doUpdateAcknowledgement($uploadId, $groupId);
        }
        return $this->doDeleteAcknowledgement($uploadId, $groupId);
      default:
        return new Response('Unknown action', Response::HTTP_BAD_REQUEST,
          array('Content-type' => 'text/plain'));
    }
  }

  /**
   * @param string $uploadTreeTable
   * @return string CTE SQL fragment
   */
  private function latestValidDecisionCte($uploadTreeTable)
  {
    return "WITH latest_valid_decision AS (
        SELECT DISTINCT ON (ut.uploadtree_pk)
            cd.clearing_decision_pk AS decision_id,
            ut.uploadtree_pk,
            ut.ufile_name,
            cd.decision_type
        FROM clearing_decision cd
        INNER JOIN $uploadTreeTable ut ON ut.uploadtree_pk = cd.uploadtree_fk
        WHERE cd.decision_type != \$1
          AND cd.group_fk = \$2
          AND ut.upload_fk = \$3
        ORDER BY ut.uploadtree_pk, cd.clearing_decision_pk DESC
    )";
  }

  /**
   * @brief Return Common params array for the query
   * @param int $groupId
   * @param int $uploadId
   * @param string $ackText
   * @return array()
   */
  private function cteParams($groupId, $uploadId, $ackText)
  {
    return array(
      DecisionTypes::WIP,
      $groupId,
      $uploadId,
      DecisionTypes::IRRELEVANT,
      $ackText
    );
  }

  /**
   * @brief Return files containing a given acknowledgement text for an upload.
   * @param int $uploadId
   * @param int $groupId
   * @return JsonResponse
   */
  private function doGetFiles($uploadId, $groupId)
  {
    $ackText = GetParm("ack", PARM_RAW);
    $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
    $cte = $this->latestValidDecisionCte($uploadTreeTable);

    $sql = "$cte
            SELECT DISTINCT lvd.uploadtree_pk, lvd.ufile_name
            FROM latest_valid_decision lvd
            INNER JOIN clearing_decision_event cde
              ON cde.clearing_decision_fk = lvd.decision_id
            INNER JOIN clearing_event ce
              ON ce.clearing_event_pk = cde.clearing_event_fk
            WHERE lvd.decision_type != \$4
              AND ce.acknowledgement = \$5
              AND (ce.removed IS NULL OR ce.removed = FALSE)
            ORDER BY lvd.ufile_name";

    $rows = $this->dbManager->getRows($sql,
      $this->cteParams($groupId, $uploadId, $ackText),
      __METHOD__ . $uploadTreeTable);

    $tracebackUri = Traceback_uri();
    $files = array();
    foreach ($rows as $row) {
      $files[] = array(
        'uploadtree_pk' => (int) $row['uploadtree_pk'],
        'name' => $row['ufile_name'],
        'url' => $tracebackUri . '?mod=view-license&upload=' . $uploadId
              . '&item=' . $row['uploadtree_pk']
      );
    }
    return new JsonResponse($files);
  }

  /**
   * @brief Update acknowledgement text on the clearing_event records
   * @param int $uploadId
   * @param int $groupId
   * @return JsonResponse
   */
  private function doUpdateAcknowledgement($uploadId, $groupId)
  {
    $oldAck = GetParm("oldAck", PARM_RAW);
    $newAck = GetParm("newAck", PARM_RAW);
    $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
    $cte = $this->latestValidDecisionCte($uploadTreeTable);

    $sql = "$cte
            UPDATE clearing_event SET acknowledgement = \$6
            WHERE clearing_event_pk IN (
                SELECT ce.clearing_event_pk
                FROM latest_valid_decision lvd
                INNER JOIN clearing_decision_event cde
                  ON cde.clearing_decision_fk = lvd.decision_id
                INNER JOIN clearing_event ce
                  ON ce.clearing_event_pk = cde.clearing_event_fk
                WHERE lvd.decision_type != \$4
                  AND ce.acknowledgement = \$5
                  AND (ce.removed IS NULL OR ce.removed = FALSE)
            )";

    $params = $this->cteParams($groupId, $uploadId, $oldAck);
    $params[] = $newAck;

    $stmt = __METHOD__ . $uploadTreeTable;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, $params);
    $this->dbManager->freeResult($res);

    $countSql = "$cte
            SELECT COUNT(DISTINCT lvd.uploadtree_pk) AS cnt
            FROM latest_valid_decision lvd
            INNER JOIN clearing_decision_event cde
              ON cde.clearing_decision_fk = lvd.decision_id
            INNER JOIN clearing_event ce
              ON ce.clearing_event_pk = cde.clearing_event_fk
            WHERE lvd.decision_type != \$4
              AND ce.acknowledgement = \$5
              AND (ce.removed IS NULL OR ce.removed = FALSE)";
    $countRow = $this->dbManager->getSingleRow($countSql,
      $this->cteParams($groupId, $uploadId, $newAck),
      __METHOD__ . 'Count' . $uploadTreeTable);

    return new JsonResponse(array('status' => 'success', 'count' => (int) $countRow['cnt']));
  }

  /**
   * @brief Clear acknowledgement from the clearing_event records
   * @param int $uploadId
   * @param int $groupId
   * @return JsonResponse
   */
  private function doDeleteAcknowledgement($uploadId, $groupId)
  {
    $ackText = GetParm("ack", PARM_RAW);
    $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
    $cte = $this->latestValidDecisionCte($uploadTreeTable);

    $sql = "$cte
            UPDATE clearing_event SET acknowledgement = ''
            WHERE clearing_event_pk IN (
                SELECT ce.clearing_event_pk
                FROM latest_valid_decision lvd
                INNER JOIN clearing_decision_event cde
                  ON cde.clearing_decision_fk = lvd.decision_id
                INNER JOIN clearing_event ce
                  ON ce.clearing_event_pk = cde.clearing_event_fk
                WHERE lvd.decision_type != \$4
                  AND ce.acknowledgement = \$5
                  AND (ce.removed IS NULL OR ce.removed = FALSE)
            )";

    $stmt = __METHOD__ . $uploadTreeTable;
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,
      $this->cteParams($groupId, $uploadId, $ackText));
    $this->dbManager->freeResult($res);

    return new JsonResponse(array('status' => 'success'));
  }
}

$NewPlugin = new AjaxAcknowledgementConf();
$NewPlugin->Initialize();
