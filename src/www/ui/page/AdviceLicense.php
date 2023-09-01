<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Composer\Spdx\SpdxLicenses;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdviceLicense extends DefaultPlugin
{
  const NAME = "advice_license";

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "Candidate Licenses",
        self::MENU_LIST => "Organize::Licenses",
        self::REQUIRES_LOGIN => true
    ));
  }

  /**
   * @param Request $request
   * @throws \Exception
   * @return Response
   */
  protected function handle(Request $request)
  {
    $rf = intval($request->get('rf'));
    $userId = Auth::getUserId();
    $groupId = Auth::getGroupId();
    /** @var UserDao */
    $userDao = $this->getObject('dao.user');
    $username = $userDao->getUserByPk($userId);
    $canEdit = $userDao->isAdvisorOrAdmin($userId, $groupId);
    if (empty($rf) || ! $canEdit) {
      $vars = array(
          'aaData' => json_encode($this->getArrayArrayData($groupId, $canEdit)),
          'canEdit' => $canEdit
      );
      return $this->render('advice_license.html.twig', $this->mergeWithDefault($vars));
    }

    $vars = $this->getDataRow($groupId, $rf);
    if ($vars === false) {
      return $this->flushContent( _('invalid license candidate'));
    }

    if ($request->get('save')) {
      try {
        $vars = $this->saveInput($request, $vars, $userId);
        $vars['message'] = 'Successfully updated.';
      } catch (\Exception $e) {
        $vars = array('rf_spdx_id' => $request->get('spdx_id'),
                      'rf_shortname' => $request->get('shortname'),
                      'rf_fullname' => $request->get('fullname'),
                      'rf_text' => $request->get('rf_text'),
                      'rf_url' => $request->get('url'),
                      'rf_notes' => $request->get('note'),
                      'rf_risk' => intval($request->get('risk'))
                     );
        $vars['message'] = $e->getMessage();
      }
    }

    return $this->render('advice_license-edit.html.twig', $this->mergeWithDefault($vars));
  }


  private function getArrayArrayData($groupId,$canEdit)
  {
    $sql = "SELECT rf_pk,rf_spdx_id,rf_shortname,rf_fullname,rf_text,rf_url,rf_notes,marydone FROM license_candidate WHERE group_fk=$1";
    /** @var DbManager */
    $dbManager = $this->getObject('db.manager');
    $dbManager->prepare($stmt = __METHOD__, $sql);
    $res = $dbManager->execute($stmt, array($groupId));
    $aaData = array();
    while ($row = $dbManager->fetchArray($res)) {
      $aData = array(htmlentities($row['rf_spdx_id']),
        htmlentities($row['rf_shortname']), htmlentities($row['rf_fullname']),
        '<div style="overflow-y:scroll;max-height:150px;margin:0;">' . nl2br(htmlentities($row['rf_text'])) . '</div>',
        htmlentities($row['rf_url']),
        $this->bool2checkbox($dbManager->booleanFromDb($row['marydone']))
      );
      if ($canEdit) {
        $link = Traceback_uri() . '?mod=' . Traceback_parm() . '&rf=' . $row['rf_pk'];
        $edit = '<a href="' . $link . '"><img border="0" src="images/button_edit.png"></a>';
        array_unshift($aData,$edit);
      }
      $aaData[] = $aData;
    }
    $dbManager->freeResult($res);
    return $aaData;
  }


  private function getDataRow($groupId, $licId)
  {
    if ($licId == -1) {
      return array('rf_pk' => -1, 'rf_shortname' => '');
    }
    $sql = "SELECT rf_pk,rf_spdx_id,rf_shortname,rf_fullname,rf_text,rf_url," .
      "rf_notes,rf_lastmodified,rf_user_fk_modified,rf_user_fk_created," .
      "rf_creationdate,marydone,rf_risk FROM license_candidate " .
      "WHERE group_fk=$1 AND rf_pk=$2";
    /* @var $dbManager DbManager */
    $dbManager = $this->getObject('db.manager');
    $row = $dbManager->getSingleRow($sql, array($groupId, $licId), __METHOD__);
    if (false !== $row) {
      $row['marydone'] = $dbManager->booleanFromDb($row['marydone']);
      $row['rf_lastmodified'] = Convert2BrowserTime($row['rf_lastmodified']);
      $row['rf_creationdate'] = Convert2BrowserTime($row['rf_creationdate']);
      $userDao = $this->getObject('dao.user');
      $username = $userDao->getUserByPk($row['rf_user_fk_created']);
      $row['rf_user_fk_created'] = $username['user_name'];
      $username = $userDao->getUserByPk($row['rf_user_fk_modified']);
      $row['rf_user_fk_modified'] = $username['user_name'];
    }
    return $row;
  }


  private function bool2checkbox($bool)
  {
    $check = $bool ? ' checked="checked"' : '';
    return '<input type="checkbox"' . $check . ' disabled="disabled"/>';
  }

  /**
   * @param Request $request
   * @param array $oldRow
   * @param $userId
   * @return array $newRow
   * @throws \Exception
   */
  private function saveInput(Request $request, $oldRow, $userId)
  {
    $spdxLicenses = new SpdxLicenses();

    $spdxId = $request->get('spdx_id');
    $shortname = $request->get('shortname');
    $fullname = $request->get('fullname');
    $rfText = $request->get('rf_text');
    $url = $request->get('url');
    $marydone = $request->get('marydone');
    $note = $request->get('note');
    $riskLvl = intval($request->get('risk'));
    $lastmodified = date(DATE_ATOM);
    $userIdcreated = $userId;
    $userIdmodified = $userId;

    if (empty($shortname) || empty($fullname) || empty($rfText)) {
      throw new \Exception('missing shortname (or) fullname (or) reference text');
    }

    /* @var $licenseDao LicenseDao */
    $licenseDao = $this->getObject('dao.license');
    $ok = ($oldRow['rf_shortname'] == $shortname);
    if (!$ok) {
      $ok = $licenseDao->isNewLicense($shortname, Auth::getGroupId());
    }
    if (!$ok) {
      throw new \Exception('shortname already in use');
    }
    if ($oldRow['rf_pk'] == -1) {
      $oldRow['rf_pk'] = $licenseDao->insertUploadLicense($shortname, $rfText, Auth::getGroupId(), $userId);
    }

    if (! empty($spdxId) &&
      strstr(strtolower($spdxId), strtolower(LicenseRef::SPDXREF_PREFIX)) === false) {
      if (! $spdxLicenses->validate($spdxId)) {
        $spdxId = LicenseRef::convertToSpdxId($spdxId, null);
      }
    } elseif (empty($spdxId)) {
      $spdxId = null;
    }
    if (! empty($spdxId)) {
      $spdxId = LicenseRef::replaceSpaces($spdxId);
    }

    $licenseDao->updateCandidate($oldRow['rf_pk'], $shortname, $fullname,
      $rfText, $url, $note, $lastmodified, $userIdmodified, !empty($marydone),
      $riskLvl, $spdxId);
    return $this->getDataRow(Auth::getGroupId(), $oldRow['rf_pk']);
  }
}

register_plugin(new AdviceLicense());
