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
use Fossology\Lib\Util\StringOperation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AdminCustomTextManagement extends DefaultPlugin
{
  const NAME = "admin_custom_text_management";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Text Management",
        self::MENU_LIST => "Admin::Text Management",
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
    /** @var UserDao */
    $userDao = $this->getObject('dao.user');

    // Check if user is admin
    if (!Auth::isAdmin()) {
      return $this->flushContent(_('Access denied. Admin privileges required.'));
    }

    $action = $request->get('action');
    $view = $request->get('view');

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

    if ($action == 'check_duplicate' && $request->getMethod() == 'POST') {
      return $this->checkDuplicateAjax($request);
    }

    // Handle form submissions
    if ($request->get('updateit') || $request->get('addit')) {
      $resultstr = $this->savePhrase($request, $userId, $groupId);
      if (strpos($resultstr, 'ERROR') !== false) {
        $vars = $this->getEditFormVars($request->get('cp_pk', 0));
        $vars['message'] = $resultstr;
        return $this->render('admin_custom_text_edit.html.twig', $this->mergeWithDefault($vars));
      } else {
        // Implement POST-redirect-GET pattern to prevent duplicate submissions
        $redirectUrl = Traceback_uri() . '?mod=' . self::NAME;
        return new RedirectResponse($redirectUrl);
      }
    }

    // Handle edit form display
    if ($request->get('edit') !== null) {
      $cp_pk = intval($request->get('edit'));
      $vars = $this->getEditFormVars($cp_pk);
      return $this->render('admin_custom_text_edit.html.twig', $this->mergeWithDefault($vars));
    }

    // Default to table view
    $vars = array(
      'formAction' => Traceback_uri() . '?mod=' . self::NAME
    );
    return $this->render('admin_custom_text_management.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * Get variables for the edit form
   */
  private function getEditFormVars($cp_pk)
  {
    $vars = array();

    if ($cp_pk > 0) {
      // Edit existing phrase
      $phraseData = $this->getPhraseData($cp_pk);
      if ($phraseData) {
        $vars = array_merge($vars, $phraseData);
        $vars['isEdit'] = true;
        // Get associated licenses for this phrase
        $vars['selectedLicenses'] = $this->getAssociatedLicenses($cp_pk);
      }
    } else {
      // Add new phrase
      $vars['isEdit'] = false;
      $vars['cp_pk'] = 0;
      $vars['selectedLicenses'] = array();
    }

    $vars['formAction'] = Traceback_uri() . '?mod=' . self::NAME;
    $vars['updateParam'] = $vars['isEdit'] ? 'updateit' : 'addit';
    $vars['textParam'] = 'text';
    $vars['acknowledgementParam'] = 'acknowledgement';
    $vars['commentsParam'] = 'comments';
    $vars['userFkParam'] = 'user_fk';
    $vars['groupFkParam'] = 'group_fk';
    $vars['licensesParam'] = 'licenses';
    $vars['isActiveParam'] = 'is_active';

    // Get license options for dropdown
    $vars['licenseOptions'] = $this->getLicenseOptions();

    return $vars;
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
                   STRING_AGG(lr.rf_shortname, ', ' ORDER BY lr.rf_shortname) as license_names
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_pk
            LEFT JOIN license_ref lr ON cplm.rf_pk = lr.rf_pk
            GROUP BY cp.cp_pk, cp.text, cp.text_md5, cp.acknowledgement, cp.comments, 
                     cp.user_fk, cp.group_fk, cp.created_date, cp.is_active, u.user_name
            ORDER BY cp.created_date DESC";

    $result = $dbManager->getRows($sql);
    $aaData = array();

    foreach ($result as $row) {
      $editLink = '<a href="?mod=' . self::NAME . '&edit=' . $row['cp_pk'] . '"><img border="0" src="images/button_edit.png"></a>';

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

      $licenses = $row['license_names'] ?: 'N/A';

      $aaData[] = array(
        $editLink,
        htmlentities($licenses),
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
      $deleteLicensesSql = "DELETE FROM custom_phrase_license_map WHERE cp_pk = $1";
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
   * AJAX endpoint to check for duplicate text
   */
  private function checkDuplicateAjax(Request $request)
  {
    $textMd5 = trim($request->get('text_md5'));
    $currentCpPk = intval($request->get('cp_pk'));

    if (empty($textMd5)) {
      return new JsonResponse(array('duplicate' => false));
    }

    $isDuplicate = $this->checkDuplicateTextMd5($textMd5, $currentCpPk > 0 ? $currentCpPk : null);

    return new JsonResponse(array('duplicate' => $isDuplicate));
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
   * Check if a text MD5 hash already exists in the database
   */
  private function checkDuplicateTextMd5($textMd5, $excludeCpPk = null)
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT cp_pk FROM custom_phrase WHERE text_md5 = $1";
    $params = array($textMd5);

    if ($excludeCpPk) {
      $sql .= " AND cp_pk != $2";
      $params[] = $excludeCpPk;
    }

    $result = $dbManager->getSingleRow($sql, $params, __METHOD__);

    return $result !== false;
  }

  /**
   * Get data for a specific phrase
   */
  private function getPhraseData($cp_pk)
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT * FROM custom_phrase WHERE cp_pk = $1";
    $row = $dbManager->getSingleRow($sql, array($cp_pk), __METHOD__);

    if ($row) {
      $row['is_active'] = $dbManager->booleanFromDb($row['is_active']);
    }

    return $row;
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
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_pk
            LEFT JOIN license_ref lr ON cplm.rf_pk = lr.rf_pk";

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
                   STRING_AGG(lr.rf_shortname, ', ' ORDER BY lr.rf_shortname) as license_names
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN custom_phrase_license_map cplm ON cp.cp_pk = cplm.cp_pk
            LEFT JOIN license_ref lr ON cplm.rf_pk = lr.rf_pk";

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
      $editLink = '<a href="?mod=' . self::NAME . '&edit=' . $row['cp_pk'] . '"><img border="0" src="images/button_edit.png"></a>';

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

      $licenses = $row['license_names'] ?: 'N/A';

      $aaData[] = array(
        $editLink,
        htmlentities($licenses),
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
   * Get associated licenses for a custom phrase
   */
  private function getAssociatedLicenses($cp_pk)
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT lr.rf_pk, lr.rf_shortname 
            FROM custom_phrase_license_map cplm
            JOIN license_ref lr ON cplm.rf_pk = lr.rf_pk
            WHERE cplm.cp_pk = $1
            ORDER BY lr.rf_shortname";

    $result = $dbManager->getRows($sql, array($cp_pk));

    $licenses = array();
    foreach ($result as $row) {
      $licenses[] = array(
        'rf_pk' => $row['rf_pk'],
        'rf_shortname' => $row['rf_shortname']
      );
    }

    return $licenses;
  }

  /**
   * Save phrase data (add or update)
   */
  private function savePhrase(Request $request, $userId, $groupId)
  {
    $cp_pk = intval($request->get('cp_pk'));
    $text = StringOperation::replaceUnicodeControlChar(trim($request->get('text')));
    $acknowledgement = StringOperation::replaceUnicodeControlChar(trim($request->get('acknowledgement')));
    $comments = StringOperation::replaceUnicodeControlChar(trim($request->get('comments')));
    $user_fk = intval($request->get('user_fk'));
    $group_fk = intval($request->get('group_fk'));
    $selectedLicenses = $request->get('licenses', array());
    $is_active = $request->get('is_active') == 'on' ? 'true' : 'false';

    if (empty($text)) {
      return _("ERROR: The text field cannot be empty.");
    }

    // Generate MD5 hash of the text
    $textMd5 = md5($text);

    // Check for duplicate text (exclude current record when updating)
    if ($this->checkDuplicateTextMd5($textMd5, $cp_pk > 0 ? $cp_pk : null)) {
      return _("ERROR: A custom text with the same content already exists in the database. Please modify the text or use the existing entry.");
    }

    // Set defaults for user and group if not provided
    if (empty($user_fk)) {
      $user_fk = $userId;
    }
    if (empty($group_fk)) {
      $group_fk = $groupId;
    }

    // Convert to array if single value
    if (!is_array($selectedLicenses)) {
      $selectedLicenses = array($selectedLicenses);
    }

    // Filter out empty values
    $selectedLicenses = array_filter($selectedLicenses, function($license) {
      return !empty($license);
    });

    try {
      /** @var DbManager */
      $dbManager = $this->getObject('db.manager');

      // Start transaction
      $dbManager->begin();

      if ($cp_pk > 0) {
        // Update existing phrase
        $sql = "UPDATE custom_phrase SET 
                text = $2, text_md5 = $3, acknowledgement = $4, comments = $5, 
                user_fk = $6, group_fk = $7, is_active = $8
                WHERE cp_pk = $1";
        $params = array($cp_pk, $text, $textMd5, $acknowledgement, $comments,
                       $user_fk, $group_fk, $is_active);
        $dbManager->prepare($stmt = __METHOD__ . ".update", $sql);
        $dbManager->freeResult($dbManager->execute($stmt, $params));

        // Delete existing license associations
        $deleteSql = "DELETE FROM custom_phrase_license_map WHERE cp_pk = $1";
        $dbManager->prepare($deleteStmt = __METHOD__ . ".delete_licenses", $deleteSql);
        $dbManager->freeResult($dbManager->execute($deleteStmt, array($cp_pk)));

      } else {
        // Insert new phrase
        $sql = "INSERT INTO custom_phrase 
                (text, text_md5, acknowledgement, comments, user_fk, group_fk, is_active, created_date)
                VALUES ($1, $2, $3, $4, $5, $6, $7, CURRENT_TIMESTAMP) RETURNING cp_pk";
        $params = array($text, $textMd5, $acknowledgement, $comments,
                       $user_fk, $group_fk, $is_active);
        $dbManager->prepare($stmt = __METHOD__ . ".insert", $sql);
        $result = $dbManager->execute($stmt, $params);
        $row = $dbManager->fetchArray($result);
        $cp_pk = $row['cp_pk'];
        $dbManager->freeResult($result);
      }

      // Insert license associations
      if (!empty($selectedLicenses)) {
        $insertLicenseSql = "INSERT INTO custom_phrase_license_map (cp_pk, rf_pk) VALUES ($1, $2)";
        $dbManager->prepare($insertLicenseStmt = __METHOD__ . ".insert_license", $insertLicenseSql);

        foreach ($selectedLicenses as $licenseId) {
          if (!empty($licenseId)) {
            $dbManager->freeResult($dbManager->execute($insertLicenseStmt, array($cp_pk, intval($licenseId))));
          }
        }
      }

      // Commit transaction
      $dbManager->commit();

      return $cp_pk > 0 ? _("Custom text updated successfully.") :
                         _("Custom text added successfully.");

    } catch (\Exception $e) {
      $dbManager->rollback();
      return _("ERROR: Failed to save custom text: ") . $e->getMessage();
    }
  }

  /**
   * Get license options for dropdown
   */
  private function getLicenseOptions()
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT rf_pk, rf_shortname FROM license_ref ORDER BY rf_shortname";
    $result = $dbManager->getRows($sql);

    $options = array();
    $options[''] = '-- Select License --';

    foreach ($result as $row) {
      $options[$row['rf_pk']] = $row['rf_shortname'];
    }

    return $options;
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

register_plugin(new AdminCustomTextManagement());
