<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseDao;
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
        self::TITLE => "Add Custom Text",
        self::MENU_LIST => "Admin::Text Management::Add",
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

    // Handle AJAX requests
    if ($action == 'check_duplicate' && $request->getMethod() == 'POST') {
      return $this->checkDuplicateAjax($request);
    }

    // Handle form submissions
    if ($request->get('updateit') || $request->get('addit')) {
      $result = $this->savePhrase($request, $userId, $groupId);
      if (!$result['success']) {
        $vars = $this->getEditFormVarsFromRequest($request);
        $vars['message'] = $result['message'];
        return $this->render('admin_custom_text_edit.html.twig', $this->mergeWithDefault($vars));
      }
      // Redirect to list view after successful save
      $redirectUrl = Traceback_uri() . '?mod=admin_custom_text_list';
      return new RedirectResponse($redirectUrl);
    }

    // Handle edit form display
    if ($request->get('edit') !== null) {
      $cp_pk = intval($request->get('edit'));
      $vars = $this->getEditFormVars($cp_pk);
      return $this->render('admin_custom_text_edit.html.twig', $this->mergeWithDefault($vars));
    }

    // Default to add form (edit with cp_pk=0)
    $vars = $this->getEditFormVars(0);
    return $this->render('admin_custom_text_edit.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * Get variables for the edit form
   */
  private function getEditFormVars($cp_pk)
  {
    $vars = array();

    $phraseData = $cp_pk > 0 ? $this->getPhraseData($cp_pk) : false;

    if ($phraseData) {
      // Edit existing phrase
      $vars = array_merge($vars, $phraseData);
      $vars['isEdit'] = true;
      // Get associated licenses for this phrase
      $vars['selectedLicenses'] = $this->getAssociatedLicenses($cp_pk);
    } else {
      // Add new phrase, or an edit whose phrase has since been deleted
      $vars['isEdit'] = false;
      $vars['cp_pk'] = 0;
      $vars['text'] = '';
      $vars['selectedLicenses'] = array();
    }

    $vars['formAction'] = Traceback_uri() . '?mod=' . self::NAME;
    $vars['updateParam'] = $vars['isEdit'] ? 'updateit' : 'addit';
    $vars['textParam'] = 'text';
    $vars['isActiveParam'] = 'is_active';

    // Get license options for dropdown
    $vars['licenseOptions'] = $this->getLicenseOptions();

    // Get users for bulk data filter dropdown
    /** @var UserDao */
    $userDao = $this->getObject('dao.user');
    $vars['bulkDataUsers'] = $userDao->getUsersByGroup();

    return $vars;
  }

  /**
   * Rebuild the form from the post, so a rejected save keeps what was entered.
   */
  private function getEditFormVarsFromRequest(Request $request)
  {
    $vars = $this->getEditFormVars(intval($request->get('cp_pk', 0)));
    $vars['text'] = $this->stringValue($request->get('text'));
    $vars['is_active'] = $request->get('is_active') == 'on';

    $selectedLicenses = array();
    foreach ($this->parseLicenseData($request->get('license_data')) as $mapping) {
      $shortname = $mapping['rf_shortname'];
      if ($shortname === '') {
        $shortname = isset($vars['licenseOptions'][$mapping['rf_pk']]) ?
          $vars['licenseOptions'][$mapping['rf_pk']] : '';
      }
      $mapping['rf_shortname'] = $shortname;
      $selectedLicenses[] = $mapping;
    }
    $vars['selectedLicenses'] = $selectedLicenses;

    return $vars;
  }

  /**
   * Decode the license_data JSON posted by the form into license map entries.
   */
  private function parseLicenseData($licenseData)
  {
    $mappings = array();
    if (empty($licenseData) || !is_string($licenseData)) {
      return $mappings;
    }
    $decodedData = json_decode($licenseData, true);
    if (!is_array($decodedData)) {
      return $mappings;
    }

    foreach ($decodedData as $item) {
      if (!is_array($item) || empty($item['licenseId'])) {
        continue;
      }
      $mappings[] = array(
        'rf_pk' => intval($item['licenseId']),
        'rf_shortname' => $this->stringValue($item['licenseName'] ?? null),
        'removing' => ($this->stringValue($item['action'] ?? null) === 'Remove'),
        'comment' => $this->stringValue($item['comment'] ?? null),
        'reportinfo' => $this->stringValue($item['reportinfo'] ?? null),
        'acknowledgement' => $this->stringValue($item['acknowledgement'] ?? null)
      );
    }
    return $mappings;
  }

  /**
   * Trimmed string, or '' for non-string input that trim() would fatal on.
   */
  private function stringValue($value)
  {
    return is_string($value) ? trim($value) : '';
  }

  /**
   * AJAX endpoint to check for duplicate text
   */
  private function checkDuplicateAjax(Request $request)
  {
    $text = StringOperation::replaceUnicodeControlChar(trim($request->get('text')));
    $currentCpPk = intval($request->get('cp_pk'));

    if (empty($text)) {
      return new JsonResponse(array('duplicate' => false));
    }

    $isDuplicate = $this->checkDuplicateTextMd5(md5($text), $currentCpPk > 0 ? $currentCpPk : null);

    return new JsonResponse(array('duplicate' => $isDuplicate));
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
   * Get associated licenses for a custom phrase
   */
  private function getAssociatedLicenses($cp_pk)
  {
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    $sql = "SELECT lr.rf_pk, lr.rf_shortname, cplm.removing,
                   cplm.comment, cplm.reportinfo, cplm.acknowledgement
            FROM custom_phrase_license_map cplm
            JOIN license_ref lr ON cplm.rf_fk = lr.rf_pk
            WHERE cplm.cp_fk = $1
            ORDER BY lr.rf_shortname";

    $result = $dbManager->getRows($sql, array($cp_pk));

    $licenses = array();
    foreach ($result as $row) {
      $licenses[] = array(
        'rf_pk'           => $row['rf_pk'],
        'rf_shortname'    => $row['rf_shortname'],
        'removing'        => $dbManager->booleanFromDb($row['removing']),
        'comment'         => $row['comment'] ?? '',
        'reportinfo'      => $row['reportinfo'] ?? '',
        'acknowledgement' => $row['acknowledgement'] ?? ''
      );
    }

    return $licenses;
  }

  /**
   * Save phrase data (add or update)
   *
   * @return array success flag and a message to show the user
   */
  private function savePhrase(Request $request, $userId, $groupId)
  {
    $cp_pk = intval($request->get('cp_pk'));
    $isUpdate = $cp_pk > 0;
    $text = StringOperation::replaceUnicodeControlChar($this->stringValue($request->get('text')));
    $user_fk = intval($request->get('user_fk'));
    $group_fk = intval($request->get('group_fk'));
    $is_active = $request->get('is_active') == 'on' ? 'true' : 'false';

    if (empty($text)) {
      return array('success' => false,
        'message' => _("ERROR: The text field cannot be empty."));
    }

    $licenseMappings = $this->parseLicenseData($request->get('license_data'));

    // Validate that at least one license is associated
    if (empty($licenseMappings)) {
      return array('success' => false,
        'message' => _("ERROR: At least one license must be associated with the custom text."));
    }

    // Generate MD5 hash of the text
    $textMd5 = md5($text);

    // Check for duplicate text (exclude current record when updating)
    if ($this->checkDuplicateTextMd5($textMd5, $isUpdate ? $cp_pk : null)) {
      return array('success' => false,
        'message' => _("ERROR: A custom text with the same content already exists in the database. Please modify the text or use the existing entry."));
    }

    // Set defaults for user and group if not provided
    if (empty($user_fk)) {
      $user_fk = $userId;
    }
    if (empty($group_fk)) {
      $group_fk = $groupId;
    }

    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');

    try {
      // Start transaction
      $dbManager->begin();

      if ($isUpdate) {
        // Update existing phrase. acknowledgement/comments are not set here:
        // metadata lives on the license map now, not the phrase.
        $sql = "UPDATE custom_phrase SET
                text = $2, text_md5 = $3, user_fk = $4, group_fk = $5, is_active = $6
                WHERE cp_pk = $1";
        $params = array($cp_pk, $text, $textMd5, $user_fk, $group_fk, $is_active);
        $dbManager->prepare($stmt = __METHOD__ . ".update", $sql);
        $dbManager->freeResult($dbManager->execute($stmt, $params));

        // Delete existing license associations
        $deleteSql = "DELETE FROM custom_phrase_license_map WHERE cp_fk = $1";
        $dbManager->prepare($deleteStmt = __METHOD__ . ".delete_licenses", $deleteSql);
        $dbManager->freeResult($dbManager->execute($deleteStmt, array($cp_pk)));

      } else {
        // Insert new phrase. acknowledgement/comments stay NULL by omission.
        $sql = "INSERT INTO custom_phrase
                (text, text_md5, user_fk, group_fk, is_active, created_date)
                VALUES ($1, $2, $3, $4, $5, CURRENT_TIMESTAMP) RETURNING cp_pk";
        $params = array($text, $textMd5, $user_fk, $group_fk, $is_active);
        $dbManager->prepare($stmt = __METHOD__ . ".insert", $sql);
        $result = $dbManager->execute($stmt, $params);
        $row = $dbManager->fetchArray($result);
        $cp_pk = $row['cp_pk'];
        $dbManager->freeResult($result);
      }

      if (!empty($licenseMappings)) {
        $insertLicenseSql = "INSERT INTO custom_phrase_license_map
                             (cp_fk, rf_fk, removing, comment, reportinfo, acknowledgement)
                             VALUES ($1, $2, $3, $4, $5, $6)";
        $dbManager->prepare($insertLicenseStmt = __METHOD__ . ".insert_license", $insertLicenseSql);

        foreach ($licenseMappings as $mapping) {
          if (!empty($mapping['rf_pk'])) {
            $dbManager->freeResult($dbManager->execute($insertLicenseStmt, array(
              $cp_pk,
              $mapping['rf_pk'],
              $mapping['removing'] ? 'true' : 'false',
              $mapping['comment'] ?: null,
              $mapping['reportinfo'] ?: null,
              $mapping['acknowledgement'] ?: null
            )));
          }
        }
      }

      // Commit transaction
      $dbManager->commit();

      return array('success' => true,
        'message' => $isUpdate ? _("Custom text updated successfully.") :
        _("Custom text added successfully."));

    } catch (\Throwable $e) {
      $dbManager->rollback();
      return array('success' => false,
        'message' => _("ERROR: Failed to save custom text: ") . $e->getMessage());
    }
  }

  private function getLicenseOptions()
  {
    /** @var LicenseDao */
    $licenseDao = $this->getObject('dao.license');

    return $licenseDao->getActiveLicensesForGroup(Auth::getGroupId());
  }
}

register_plugin(new AdminCustomTextManagement());

