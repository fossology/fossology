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
    
    if ($action == 'delete' && $request->getMethod() == 'POST') {
      return $this->deletePhraseAjax($request);
    }
    
    if ($action == 'toggle' && $request->getMethod() == 'POST') {
      return $this->togglePhraseStatusAjax($request);
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
      'aaData' => json_encode($this->getPhrasesTableData()),
      'formAction' => Traceback_uri() . '?mod=' . self::NAME
    );
    return $this->render('admin_custom_text_management.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * Get variables for the edit form
   */
  private function getEditFormVars($cp_pk = 0)
  {
    $vars = array();
    
    if ($cp_pk > 0) {
      // Edit existing phrase
      $phraseData = $this->getPhraseData($cp_pk);
      if ($phraseData) {
        $vars = array_merge($vars, $phraseData);
        $vars['isEdit'] = true;
      }
    } else {
      // Add new phrase
      $vars['isEdit'] = false;
      $vars['cp_pk'] = 0;
    }

    $vars['formAction'] = Traceback_uri() . '?mod=' . self::NAME;
    $vars['updateParam'] = $vars['isEdit'] ? 'updateit' : 'addit';
    $vars['textParam'] = 'text';
    $vars['acknowledgementParam'] = 'acknowledgement';
    $vars['commentsParam'] = 'comments';
    $vars['userFkParam'] = 'user_fk';
    $vars['groupFkParam'] = 'group_fk';
    $vars['rfFkParam'] = 'rf_fk';
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
    
    $sql = "SELECT cp.cp_pk, cp.text, cp.acknowledgement, cp.comments, 
                   cp.user_fk, cp.group_fk, cp.rf_fk, cp.created_date, cp.is_active,
                   u.user_name, lr.rf_shortname
            FROM custom_phrase cp
            LEFT JOIN users u ON cp.user_fk = u.user_pk
            LEFT JOIN license_ref lr ON cp.rf_fk = lr.rf_pk
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
      
      $deleteBtn = '<a href="javascript:void(0)" onclick="deletePhrase(' . $row['cp_pk'] . ')"><img border="0" src="images/button_delete.png"></a>';
      
      $aaData[] = array(
        $editLink,
        '<div style="overflow-y:scroll;max-height:100px;margin:0;">' . nl2br(htmlentities($text)) . '</div>',
        htmlentities($acknowledgement),
        htmlentities($comments),
        htmlentities($row['rf_shortname'] ?: 'N/A'),
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
      
      $sql = "DELETE FROM custom_phrase WHERE cp_pk = $1";
      $dbManager->prepare($stmt = __METHOD__ . ".delete", $sql);
      $dbManager->freeResult($dbManager->execute($stmt, array($phraseId)));
      
      return new JsonResponse(array(
        'success' => true,
        'message' => 'Custom text deleted successfully'
      ));
    } catch (\Exception $e) {
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
    $rf_fk = intval($request->get('rf_fk'));
    $is_active = $request->get('is_active') == 'on' ? 'true' : 'false';
    
    if (empty($text)) {
      return _("ERROR: The text field cannot be empty.");
    }
    
    // Set defaults for user and group if not provided
    if (empty($user_fk)) {
      $user_fk = $userId;
    }
    if (empty($group_fk)) {
      $group_fk = $groupId;
    }
    
    // Convert empty rf_fk to null
    if (empty($rf_fk)) {
      $rf_fk = null;
    }
    
    try {
      /** @var DbManager */
      $dbManager = $this->getObject('db.manager');
      
      if ($cp_pk > 0) {
        // Update existing phrase
        $sql = "UPDATE custom_phrase SET 
                text = $2, acknowledgement = $3, comments = $4, 
                user_fk = $5, group_fk = $6, rf_fk = $7, is_active = $8
                WHERE cp_pk = $1";
        $params = array($cp_pk, $text, $acknowledgement, $comments, 
                       $user_fk, $group_fk, $rf_fk, $is_active);
      } else {
        // Insert new phrase
        $sql = "INSERT INTO custom_phrase 
                (text, acknowledgement, comments, user_fk, group_fk, rf_fk, is_active, created_date)
                VALUES ($1, $2, $3, $4, $5, $6, $7, CURRENT_TIMESTAMP)";
        $params = array($text, $acknowledgement, $comments, 
                       $user_fk, $group_fk, $rf_fk, $is_active);
      }
      
      $dbManager->prepare($stmt = __METHOD__ . ($cp_pk > 0 ? ".update" : ".insert"), $sql);
      $dbManager->freeResult($dbManager->execute($stmt, $params));
      
      return $cp_pk > 0 ? _("Custom text updated successfully.") : 
                         _("Custom text added successfully.");
      
    } catch (\Exception $e) {
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
}

register_plugin(new AdminCustomTextManagement()); 