<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminCustomTextList extends DefaultPlugin
{
  const NAME = "admin_custom_text_list";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Custom Text List",
        self::MENU_LIST => "Admin::Text Management::List",
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_ADMIN
    ));
  }

  /**
   * @param Request $request
   * @throws \Exception
   * @return Response
   */
  protected function handle(Request $request)
  {
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();

    // Check if user is admin
    if (!Auth::isAdmin()) {
      return $this->flushContent(_('Access denied. Admin privileges required.'));
    }

    $action = $request->get('action');

    // Handle AJAX requests
    if ($action == 'get_phrases') {
      return $this->getPhrasesAjax();
    }

    if ($action == 'fetchData') {
      return $this->fetchDataServerSide($request);
    }

    if ($action == 'get_bulk_data') {
      return $this->getBulkDataAjax();
    }

    if ($action == 'delete' && $request->getMethod() == 'POST') {
      return $this->deletePhraseAjax($request);
    }

    if ($action == 'toggle' && $request->getMethod() == 'POST') {
      return $this->togglePhraseStatusAjax($request);
    }

    // Default to table view
    $vars = array(
      'formAction' => Traceback_uri() . '?mod=' . self::NAME,
      'addModuleName' => 'admin_custom_text_management'
    );
    return $this->render('admin_custom_text_list.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * Get phrases data for the table
   */
  private function getPhrasesTableData()
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT cp.cp_pk, cp.text, cp.text_md5, cp.acknowledgement, cp.comments,
                   cp.user_fk, cp.group_fk, cp.created_date, cp.is_active,
                   u.user_name,
                   STRING_AGG(CASE WHEN cplm.removing = false THEN lr.rf_shortname END, ', ' ORDER BY lr.rf_shortname) as licenses_to_add,
                   STRING_AGG(CASE WHEN cplm.removing = true THEN lr.rf_shortname END, ', ' ORDER BY lr.rf_shortname) as licenses_to_remove
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_fk
            LEFT JOIN license_ref lr ON cplm.rf_fk = lr.rf_pk
            GROUP BY cp.cp_pk, cp.text, cp.text_md5, cp.acknowledgement, cp.comments,
                     cp.user_fk, cp.group_fk, cp.created_date, cp.is_active, u.user_name
            ORDER BY cp.created_date DESC";

    $result = $dbManager->getRows($sql);
    $aaData = array();

    foreach ($result as $row) {
      $editLink = '<a href="?mod=admin_custom_text_management&edit=' . $row['cp_pk'] . '"><img border="0" src="images/button_edit.png"></a>';

      $text = strlen($row['text']) > 100 ?
                    substr($row['text'], 0, 100) . '...' :
                    $row['text'];

      $acknowledgement = strlen($row['acknowledgement'] ?: '') > 50 ?
                         substr($row['acknowledgement'], 0, 50) . '...' :
                         ($row['acknowledgement'] ?: 'N/A');

      $comments = strlen($row['comments'] ?: '') > 50 ?
                  substr($row['comments'], 0, 50) . '...' :
                  ($row['comments'] ?: 'N/A');

      $isActiveFlag = $dbManager->booleanFromDb($row['is_active']);
      $statusToggle = '<input type="checkbox" ' . ($isActiveFlag ? 'checked' : '') .
                      ' onchange="togglePhraseStatus(' . $row['cp_pk'] . ', ' . ($isActiveFlag ? 'true' : 'false') . ')"/>';

      $deleteBtn = '<a href="javascript:;" onclick="deletePhrase(' . $row['cp_pk'] . ')"><img class="delete" src="images/space_16.png" alt="Delete"/></a>';

      // Format licenses display with separate lines for add/remove
      $licensesDisplay = '';
      $licensesToAdd = $row['licenses_to_add'] ?: null;
      $licensesToRemove = $row['licenses_to_remove'] ?: null;

      if ($licensesToAdd || $licensesToRemove) {
        if ($licensesToAdd) {
          $licensesDisplay .= '<div><strong>To be added:</strong> ' . htmlentities($licensesToAdd) . '</div>';
        }
        if ($licensesToRemove) {
          $licensesDisplay .= '<div><strong>To be removed:</strong> ' . htmlentities($licensesToRemove) . '</div>';
        }
      } else {
        $licensesDisplay = 'N/A';
      }

      $aaData[] = array(
        $editLink,
        $licensesDisplay,
        '<div style="overflow-y:scroll;max-height:100px;margin:0;">' . nl2br(htmlentities($text)) . '</div>',
        htmlentities($acknowledgement),
        htmlentities($comments),
        htmlentities($row['user_name'] ?: 'N/A'),
        $row['created_date'],
        $statusToggle,
        $deleteBtn
      );
    }

    return $aaData;
  }

  /**
   * AJAX endpoint to get phrases data
   */
  private function getPhrasesAjax()
  {
    $data = $this->getPhrasesTableData();
    return new JsonResponse(array('data' => $data));
  }

  /**
   * AJAX endpoint for server-side pagination
   */
  private function fetchDataServerSide(Request $request)
  {
    $offset = intval($request->query->get('start', 0));
    $limit = intval($request->query->get('length', 10));
    $draw = intval($request->query->get('draw', 1));
    $searchQuery = $_GET['search']['value'] ?? '';

    if (!empty($searchQuery)) {
      $searchQuery = '%' . $searchQuery . '%';
    }

    $totalCount = $this->getTotalPhrasesCount($searchQuery);
    $phraseArray = $this->getPhrasesServerSide($limit, $offset, $searchQuery);

    return new JsonResponse([
      "draw" => $draw,
      "recordsTotal" => $totalCount,
      "recordsFiltered" => $totalCount,
      "data" => $phraseArray,
    ], JsonResponse::HTTP_OK);
  }

  /**
   * AJAX endpoint to delete a phrase
   */
  private function deletePhraseAjax(Request $request)
  {
    $phraseId = intval($request->get('id'));

    if (!$phraseId) {
      return new JsonResponse(array('error' => 'Invalid phrase ID'), 400);
    }

    try {
      /** @var DbManager */
      $dbManager = $this->getObject('db.manager');

      // Start transaction
      $dbManager->begin();

      // Delete license associations first (though CASCADE should handle this)
      $deleteLicensesSql = "DELETE FROM custom_phrase_license_map WHERE cp_fk = $1";
      $dbManager->prepare($deleteLicensesStmt = __METHOD__ . ".delete_licenses", $deleteLicensesSql);
      $dbManager->freeResult($dbManager->execute($deleteLicensesStmt, array($phraseId)));

      // Delete the phrase itself
      $sql = "DELETE FROM custom_phrase WHERE cp_pk = $1";
      $dbManager->prepare($stmt = __METHOD__ . ".delete", $sql);
      $dbManager->freeResult($dbManager->execute($stmt, array($phraseId)));

      // Commit transaction
      $dbManager->commit();

      return new JsonResponse(array(
        'success' => true,
        'message' => 'Custom text deleted successfully'
      ));
    } catch (\Exception $e) {
      $dbManager->rollback();
      return new JsonResponse(array('error' => 'Failed to delete phrase: ' . $e->getMessage()), 500);
    }
  }

  /**
   * AJAX endpoint to toggle phrase status
   */
  private function togglePhraseStatusAjax(Request $request)
  {
    $phraseId = intval($request->get('id'));
    $status = intval($request->get('status'));

    if (!$phraseId) {
      return new JsonResponse(array('error' => 'Invalid phrase ID'), 400);
    }

    try {
      /** @var DbManager */
      $dbManager = $this->getObject('db.manager');

      $sql = "UPDATE custom_phrase SET is_active = $1 WHERE cp_pk = $2";
      $dbManager->prepare($stmt = __METHOD__ . ".toggle", $sql);
      $dbManager->freeResult($dbManager->execute($stmt, array($status ? 'true' : 'false', $phraseId)));

      return new JsonResponse(array(
        'success' => true,
        'message' => $status ? 'Custom text activated' : 'Custom text deactivated'
      ));
    } catch (\Exception $e) {
      return new JsonResponse(array('error' => 'Failed to toggle status: ' . $e->getMessage()), 500);
    }
  }

  /**
   * Get total count of custom phrases for server-side pagination
   */
  private function getTotalPhrasesCount($searchQuery = '')
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT COUNT(*) as count FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_fk
            LEFT JOIN license_ref lr ON cplm.rf_fk = lr.rf_pk";

    $params = array();

    if (!empty($searchQuery)) {
      $sql .= " WHERE (cp.text ILIKE $1 OR cp.acknowledgement ILIKE $1 OR cp.comments ILIKE $1 OR u.user_name ILIKE $1 OR lr.rf_shortname ILIKE $1)";
      $params[] = $searchQuery;
    }

    $sql .= " GROUP BY cp.cp_pk";

    $countSql = "SELECT COUNT(*) as count FROM (" . $sql . ") as subquery";

    $result = $dbManager->getSingleRow($countSql, $params, __METHOD__);
    return $result ? intval($result['count']) : 0;
  }

  /**
   * Get phrases data for server-side pagination
   */
  private function getPhrasesServerSide($limit, $offset, $searchQuery = '')
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT cp.cp_pk, cp.text, cp.text_md5, cp.acknowledgement, cp.comments,
                   cp.user_fk, cp.group_fk, cp.created_date, cp.is_active,
                   u.user_name,
                   STRING_AGG(CASE WHEN cplm.removing = false THEN lr.rf_shortname END, ', ' ORDER BY lr.rf_shortname) as licenses_to_add,
                   STRING_AGG(CASE WHEN cplm.removing = true THEN lr.rf_shortname END, ', ' ORDER BY lr.rf_shortname) as licenses_to_remove
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_fk
            LEFT JOIN license_ref lr ON cplm.rf_fk = lr.rf_pk";

    $params = array();

    if (!empty($searchQuery)) {
      $sql .= " WHERE (cp.text ILIKE $1 OR cp.acknowledgement ILIKE $1 OR cp.comments ILIKE $1 OR u.user_name ILIKE $1 OR lr.rf_shortname ILIKE $1)";
      $params[] = $searchQuery;
    }

    $sql .= " GROUP BY cp.cp_pk, cp.text, cp.text_md5, cp.acknowledgement, cp.comments, 
                      cp.user_fk, cp.group_fk, cp.created_date, cp.is_active, u.user_name
              ORDER BY cp.created_date DESC";

    $sql .= " LIMIT $" . (count($params) + 1) . " OFFSET $" . (count($params) + 2);
    $params[] = $limit;
    $params[] = $offset;

    $result = $dbManager->getRows($sql, $params, __METHOD__);
    $aaData = array();

    foreach ($result as $row) {
      $editLink = '<a href="?mod=admin_custom_text_management&edit=' . $row['cp_pk'] . '"><img border="0" src="images/button_edit.png"></a>';

      $text = strlen($row['text']) > 100 ?
                    substr($row['text'], 0, 100) . '...' :
                    $row['text'];

      $acknowledgement = strlen($row['acknowledgement'] ?: '') > 50 ?
                         substr($row['acknowledgement'], 0, 50) . '...' :
                         ($row['acknowledgement'] ?: 'N/A');

      $comments = strlen($row['comments'] ?: '') > 50 ?
                  substr($row['comments'], 0, 50) . '...' :
                  ($row['comments'] ?: 'N/A');

      $isActiveFlag = $dbManager->booleanFromDb($row['is_active']);
      $statusToggle = '<input type="checkbox" ' . ($isActiveFlag ? 'checked' : '') .
                      ' onchange="togglePhraseStatus(' . $row['cp_pk'] . ', ' . ($isActiveFlag ? 'true' : 'false') . ')"/>';

      $deleteBtn = '<a href="javascript:;" onclick="deletePhrase(' . $row['cp_pk'] . ')"><img class="delete" src="images/space_16.png" alt="Delete"/></a>';

      // Format licenses display with separate lines for add/remove
      $licensesDisplay = '';
      $licensesToAdd = $row['licenses_to_add'] ?: null;
      $licensesToRemove = $row['licenses_to_remove'] ?: null;

      if ($licensesToAdd || $licensesToRemove) {
        if ($licensesToAdd) {
          $licensesDisplay .= '<div><strong>To be added:</strong> ' . htmlentities($licensesToAdd) . '</div>';
        }
        if ($licensesToRemove) {
          $licensesDisplay .= '<div><strong>To be removed:</strong> ' . htmlentities($licensesToRemove) . '</div>';
        }
      } else {
        $licensesDisplay = 'N/A';
      }

      $aaData[] = array(
        $editLink,
        $licensesDisplay,
        '<div style="overflow-y:scroll;max-height:100px;margin:0;">' . nl2br(htmlentities($text)) . '</div>',
        htmlentities($acknowledgement),
        htmlentities($comments),
        htmlentities($row['user_name'] ?: 'N/A'),
        $row['created_date'],
        $statusToggle,
        $deleteBtn
      );
    }

    return $aaData;
  }

  /**
   * AJAX endpoint to get bulk data from license_ref_bulk table with associated licenses
   */
  private function getBulkDataAjax()
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT 
                lrb.lrb_pk,
                lrb.rf_text as bulk_reference_text,
                lrb.ignore_irrelevant,
                lrb.bulk_delimiters,
                lrb.scan_findings,
                
                lr.rf_pk as license_id,
                lr.rf_shortname as license_shortname,
                lr.rf_fullname as license_fullname,
                lr.rf_spdx_id as license_spdx_id,
                
                lsb.removing as is_removing_license,
                lsb.comment as license_comment,
                lsb.reportinfo as license_reportinfo,
                lsb.acknowledgement as license_acknowledgement,
                
                u.user_name as created_by_user,
                up.upload_filename as upload_file,
                
                lrb.user_fk,
                lrb.group_fk,
                lrb.upload_fk,
                lrb.uploadtree_fk

            FROM license_ref_bulk lrb
                INNER JOIN license_set_bulk lsb ON lrb.lrb_pk = lsb.lrb_fk
                INNER JOIN license_ref lr ON lsb.rf_fk = lr.rf_pk
                LEFT JOIN users u ON lrb.user_fk = u.user_pk
                LEFT JOIN upload up ON lrb.upload_fk = up.upload_pk
            WHERE lrb.rf_text IS NOT NULL AND lrb.rf_text != ''
            ORDER BY lrb.lrb_pk DESC, lr.rf_shortname
            LIMIT 200";

    $result = $dbManager->getRows($sql);
    $bulkData = array();

    // Group results by lrb_pk to handle multiple licenses per bulk entry
    $groupedData = array();
    foreach ($result as $row) {
      $lrbPk = $row['lrb_pk'];

      if (!isset($groupedData[$lrbPk])) {
        $text = strlen($row['bulk_reference_text']) > 200 ?
                      substr($row['bulk_reference_text'], 0, 200) . '...' :
                      $row['bulk_reference_text'];

        $ignoreIrrelevant = $dbManager->booleanFromDb($row['ignore_irrelevant']);
        $scanFindings = $dbManager->booleanFromDb($row['scan_findings']);
        $isRemoving = $dbManager->booleanFromDb($row['is_removing_license']);

        $groupedData[$lrbPk] = array(
          'lrb_pk' => $lrbPk,
          'user_fk' => $row['user_fk'],
          'group_fk' => $row['group_fk'],
          'bulk_reference_text' => $row['bulk_reference_text'],
          'text_preview' => $text,
          'upload_fk' => $row['upload_fk'],
          'uploadtree_fk' => $row['uploadtree_fk'],
          'ignore_irrelevant' => $ignoreIrrelevant,
          'bulk_delimiters' => $row['bulk_delimiters'],
          'scan_findings' => $scanFindings,
          'created_by_user' => $row['created_by_user'] ?: 'Unknown',
          'upload_file' => $row['upload_file'] ?: 'Unknown',
          'licenses' => array(),
          'all_acknowledgements' => array(),
          'all_comments' => array()
        );
      }

      // Add license information
      $isRemoving = $dbManager->booleanFromDb($row['is_removing_license']);
      $licenseInfo = array(
        'license_id' => $row['license_id'],
        'license_shortname' => $row['license_shortname'],
        'license_fullname' => $row['license_fullname'],
        'license_spdx_id' => $row['license_spdx_id'],
        'is_removing_license' => $isRemoving,
        'license_comment' => $row['license_comment'],
        'license_reportinfo' => $row['license_reportinfo'],
        'license_acknowledgement' => $row['license_acknowledgement']
      );

      $groupedData[$lrbPk]['licenses'][] = $licenseInfo;

      // Collect unique acknowledgements and comments
      if (!empty($row['license_acknowledgement'])) {
        $groupedData[$lrbPk]['all_acknowledgements'][] = $row['license_acknowledgement'];
      }
      if (!empty($row['license_comment'])) {
        $groupedData[$lrbPk]['all_comments'][] = $row['license_comment'];
      }
    }

    // Convert grouped data to final format
    foreach ($groupedData as $entry) {
      $licenseNames = array();
      $addedLicenses = array();
      $removedLicenses = array();

      foreach ($entry['licenses'] as $license) {
        $licenseNames[] = $license['license_shortname'];
        if ($license['is_removing_license']) {
          $removedLicenses[] = $license['license_shortname'];
        } else {
          $addedLicenses[] = $license['license_shortname'];
        }
      }

      // Create summary strings
      $entry['license_summary'] = implode(', ', $licenseNames);
      $entry['added_licenses'] = implode(', ', $addedLicenses);
      $entry['removed_licenses'] = implode(', ', $removedLicenses);
      $entry['acknowledgement_summary'] = implode('; ', array_unique($entry['all_acknowledgements']));
      $entry['comment_summary'] = implode('; ', array_unique($entry['all_comments']));

      $bulkData[] = $entry;
    }

    return new JsonResponse(array('data' => $bulkData));
  }
}

register_plugin(new AdminCustomTextList());
