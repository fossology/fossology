<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-FileCopyrightText: © Fossology contributors

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
    if ($action == 'fetchData') {
      return $this->fetchDataServerSide($request);
    }

    if ($action == 'get_bulk_data') {
      return $this->getBulkDataAjax($request);
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
   * AJAX endpoint for server-side pagination
   */
  private function fetchDataServerSide(Request $request)
  {
    $offset = intval($request->query->get('start', 0));
    $limit = intval($request->query->get('length', 10));
    $draw = intval($request->query->get('draw', 1));
    $searchQuery = $request->query->all('search')['value'] ?? '';

    $filteredQuery = '';
    if (!empty($searchQuery)) {
      $filteredQuery = '%' . $searchQuery . '%';
    }

    $totalCount = $this->getTotalPhrasesCount('');
    $filteredCount = empty($filteredQuery) ? $totalCount : $this->getTotalPhrasesCount($filteredQuery);
    $phraseArray = $this->getPhrasesServerSide($limit, $offset, $filteredQuery);

    return new JsonResponse([
      "draw" => $draw,
      "recordsTotal" => $totalCount,
      "recordsFiltered" => $filteredCount,
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

  private function getTotalPhrasesCount($searchQuery = '')
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT COUNT(DISTINCT cp.cp_pk) AS count
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_fk
            LEFT JOIN license_ref lr ON cplm.rf_fk = lr.rf_pk";

    $params = array();

    if (!empty($searchQuery)) {
      $sql .= " WHERE (cp.text ILIKE $1 OR cplm.reportinfo ILIKE $1"
            . " OR cplm.acknowledgement ILIKE $1 OR cplm.comment ILIKE $1"
            . " OR u.user_name ILIKE $1 OR lr.rf_shortname ILIKE $1)";
      $params[] = $searchQuery;
    }

    $stmtName = empty($searchQuery) ? __METHOD__ . '.unfiltered' : __METHOD__ . '.filtered';
    $result = $dbManager->getSingleRow($sql, $params, $stmtName);
    return $result ? intval($result['count']) : 0;
  }

  /**
   * Get phrases data for server-side pagination
   */
  private function getPhrasesServerSide($limit, $offset, $searchQuery = '')
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT cp.cp_pk, cp.text, cp.created_date, cp.is_active,
                   u.user_name,
                   STRING_AGG(CASE WHEN cplm.removing = false THEN lr.rf_shortname END, ', ' ORDER BY lr.rf_shortname) as licenses_to_add,
                   STRING_AGG(CASE WHEN cplm.removing = true THEN lr.rf_shortname END, ', ' ORDER BY lr.rf_shortname) as licenses_to_remove,
                   STRING_AGG(CASE WHEN cplm.reportinfo IS NOT NULL AND cplm.reportinfo != '' THEN cplm.reportinfo END, '; ') as reportinfo,
                   STRING_AGG(CASE WHEN cplm.acknowledgement IS NOT NULL AND cplm.acknowledgement != '' THEN cplm.acknowledgement END, '; ') as acknowledgement,
                   STRING_AGG(CASE WHEN cplm.comment IS NOT NULL AND cplm.comment != '' THEN cplm.comment END, '; ') as comments
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_fk
            LEFT JOIN license_ref lr ON cplm.rf_fk = lr.rf_pk";

    $params = array();

    // Filter by cp_pk via subquery: a WHERE on the outer join would drop
    // non-matching license rows from STRING_AGG too, truncating the list.
    if (!empty($searchQuery)) {
      $sql .= " WHERE cp.cp_pk IN (
                  SELECT cp2.cp_pk FROM custom_phrase cp2
                  LEFT JOIN users u2 ON cp2.user_fk = u2.user_pk
                  LEFT JOIN custom_phrase_license_map cplm2 ON cp2.cp_pk = cplm2.cp_fk
                  LEFT JOIN license_ref lr2 ON cplm2.rf_fk = lr2.rf_pk
                  WHERE (cp2.text ILIKE $1 OR cplm2.reportinfo ILIKE $1"
            . " OR cplm2.acknowledgement ILIKE $1 OR cplm2.comment ILIKE $1"
            . " OR u2.user_name ILIKE $1 OR lr2.rf_shortname ILIKE $1)
                )";
      $params[] = $searchQuery;
    }

    $sql .= " GROUP BY cp.cp_pk, cp.text, cp.created_date, cp.is_active, u.user_name
              ORDER BY cp.created_date DESC";

    $sql .= " LIMIT $" . (count($params) + 1) . " OFFSET $" . (count($params) + 2);
    $params[] = $limit;
    $params[] = $offset;

    $stmtName = empty($searchQuery) ? __METHOD__ . '.unfiltered' : __METHOD__ . '.filtered';
    $result = $dbManager->getRows($sql, $params, $stmtName);
    $aaData = array();

    foreach ($result as $row) {
      $text = mb_strlen($row['text'], 'UTF-8') > 100
        ? mb_substr($row['text'], 0, 100, 'UTF-8') . '...'
        : $row['text'];

      $licensesToAdd = $row['licenses_to_add'] ?: null;
      $licensesToRemove = $row['licenses_to_remove'] ?: null;
      $licensesDisplay = '';
      if ($licensesToAdd || $licensesToRemove) {
        if ($licensesToAdd) {
          $licensesDisplay .= '<div><img src="images/icons/add_16.png" alt="+" title="To be added"/> ' . htmlspecialchars($licensesToAdd, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($licensesToRemove) {
          $licensesDisplay .= '<div><img src="images/icons/remove_16.png" alt="-" title="To be removed"/> ' . htmlspecialchars($licensesToRemove, ENT_QUOTES, 'UTF-8') . '</div>';
        }
      } else {
        $licensesDisplay = 'N/A';
      }

      $reportinfo = trim($row['reportinfo'] ?: '');
      $acknowledgement = trim($row['acknowledgement'] ?: '');
      $comments = trim($row['comments'] ?: '');

      $makeIndicator = function($content, $letter, $label, $typeClass) {
        $has = $content !== '';
        $tip = $has
          ? $label . ': ' . htmlspecialchars(mb_substr($content, 0, 150) . (mb_strlen($content) > 150 ? '…' : ''), ENT_QUOTES)
          : $label . ': not set';
        $class = 'custom-text-indicator ' . ($has ? $typeClass : 'custom-text-indicator-empty');
        return '<span class="' . $class . '" title="' . $tip . '">' . $letter . '</span>';
      };

      $detailsCell = '<div class="custom-text-details-cell">'
        . $makeIndicator($reportinfo, 'T', 'License Text', 'custom-text-indicator-reportinfo')
        . $makeIndicator($acknowledgement, 'A', 'Acknowledgement', 'custom-text-indicator-acknowledgement')
        . $makeIndicator($comments, 'C', 'Comment', 'custom-text-indicator-comment')
        . '</div>';

      $isActiveFlag = $dbManager->booleanFromDb($row['is_active']);
      $statusToggle = '<input type="checkbox" class="phrase-status-toggle"'
        . ' data-phrase-id="' . intval($row['cp_pk']) . '"'
        . ' data-current-status="' . ($isActiveFlag ? '1' : '0') . '"'
        . ' title="' . ($isActiveFlag ? 'Active' : 'Inactive') . '"'
        . ($isActiveFlag ? ' checked' : '') . '/>';

      $editLink = '<a href="?mod=admin_custom_text_management&edit=' . intval($row['cp_pk']) . '"'
        . ' title="Edit"><img border="0" src="images/button_edit.png" alt="Edit"/></a>';

      $deleteBtn = '<button type="button" class="custom-text-delete custom-text-delete-btn btn btn-link btn-sm p-0"'
        . ' data-phrase-id="' . intval($row['cp_pk']) . '" title="Delete">'
        . '&times;</button>';

      $aaData[] = array(
        $licensesDisplay,
        '<div class="custom-text-cell">' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</div>',
        $detailsCell,
        htmlspecialchars($row['user_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8'),
        '<div class="custom-text-actions-cell">'
          . $editLink . $statusToggle . $deleteBtn . '</div>'
      );
    }

    return $aaData;
  }

  /**
   * Get total count of bulk entries (distinct lrb_pk) matching an optional search term.
   *
   * @param string $searchQuery Search term (already wrapped with % wildcards), or empty.
   * @return int
   */
  private function getTotalBulkCount($searchQuery = '', $userFk = 0)
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT COUNT(DISTINCT lrb.lrb_pk) AS count
            FROM license_ref_bulk lrb
                INNER JOIN license_set_bulk lsb ON lrb.lrb_pk = lsb.lrb_fk
                INNER JOIN license_ref lr ON lsb.rf_fk = lr.rf_pk
                LEFT JOIN users u ON lrb.user_fk = u.user_pk
                LEFT JOIN upload up ON lrb.upload_fk = up.upload_pk
            WHERE lrb.rf_text IS NOT NULL AND lrb.rf_text != ''";

    $params = array();
    $paramIdx = 1;

    if (!empty($searchQuery)) {
      $sql .= " AND (lrb.rf_text ILIKE \$$paramIdx OR lr.rf_shortname ILIKE \$$paramIdx OR u.user_name ILIKE \$$paramIdx OR up.upload_filename ILIKE \$$paramIdx)";
      $params[] = $searchQuery;
      $paramIdx++;
    }

    if ($userFk > 0) {
      $sql .= " AND lrb.user_fk = \$$paramIdx";
      $params[] = $userFk;
    }

    $result = $dbManager->getSingleRow($sql, $params, __METHOD__);
    return $result ? intval($result['count']) : 0;
  }

  /**
   * AJAX endpoint to get bulk data from license_ref_bulk table with
   * associated licenses. Supports server-side pagination and search.
   *
   * Query parameters accepted: page, limit, search.
   */
  private function getBulkDataAjax(Request $request)
  {
    $page = max(1, intval($request->query->get('page', 1)));
    $limit = max(1, intval($request->query->get('limit', 10)));
    $searchTerm = trim($request->query->get('search', ''));
    $userFk = max(0, intval($request->query->get('user_fk', 0)));

    $searchQuery = '';
    if (!empty($searchTerm)) {
      $searchQuery = '%' . $searchTerm . '%';
    }

    $totalRecords = $this->getTotalBulkCount($searchQuery, $userFk);
    $totalPages = max(1, intval(ceil($totalRecords / $limit)));

    // Clamp page to valid range
    if ($page > $totalPages) {
      $page = $totalPages;
    }
    $offset = ($page - 1) * $limit;

    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    // Paginate distinct lrb_pk first, then join full row data so every
    // license row for a paginated bulk entry is returned.
    $whereClause = "WHERE lrb.rf_text IS NOT NULL AND lrb.rf_text != ''";
    $params = array();
    $paramIdx = 1;

    if (!empty($searchQuery)) {
      $whereClause .= " AND (lrb.rf_text ILIKE \$$paramIdx OR lr.rf_shortname ILIKE \$$paramIdx OR u.user_name ILIKE \$$paramIdx OR up.upload_filename ILIKE \$$paramIdx)";
      $params[] = $searchQuery;
      $paramIdx++;
    }

    if ($userFk > 0) {
      $whereClause .= " AND lrb.user_fk = \$$paramIdx";
      $params[] = $userFk;
      $paramIdx++;
    }

    // Subquery: get the paginated lrb_pk values
    $subSql = "SELECT DISTINCT lrb.lrb_pk
               FROM license_ref_bulk lrb
                   INNER JOIN license_set_bulk lsb ON lrb.lrb_pk = lsb.lrb_fk
                   INNER JOIN license_ref lr ON lsb.rf_fk = lr.rf_pk
                   LEFT JOIN users u ON lrb.user_fk = u.user_pk
                   LEFT JOIN upload up ON lrb.upload_fk = up.upload_pk
               $whereClause
               ORDER BY lrb.lrb_pk DESC
               LIMIT \$$paramIdx OFFSET \$" . ($paramIdx + 1);
    $params[] = $limit;
    $params[] = $offset;
    $paramIdx += 2;

    // Main query: fetch full data only for the paginated lrb_pk set
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
            WHERE lrb.lrb_pk IN ($subSql)
            ORDER BY lrb.lrb_pk DESC, lr.rf_shortname";

    $result = $dbManager->getRows($sql, $params, __METHOD__);
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

    return new JsonResponse(array(
      'data' => $bulkData,
      'totalRecords' => $totalRecords,
      'totalPages' => $totalPages,
      'currentPage' => $page,
      'pageSize' => $limit
    ));
  }
}

register_plugin(new AdminCustomTextList());
