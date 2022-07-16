<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Auth\Auth;

define("TITLE_POPUPLICENSE", _("Show Reference License"));

class PopupLicense extends FO_Plugin
{
  /** @var LicenseDao */
  private $licenseDao;


  function __construct()
  {
    $this->Name = "popup-license";
    $this->Title = TITLE_POPUPLICENSE;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    parent::__construct();

    global $container;
    $this->licenseDao = $container->get('dao.license');
  }

  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return 0;
    }
    $licenseShortname = GetParm("lic", PARM_TEXT);
    $licenseId = GetParm("rf", PARM_NUMBER);
    $groupId = $_SESSION[Auth::GROUP_ID];
    if (empty($licenseShortname) && empty($licenseId)) {
      return;
    }
    if ($licenseId) {
      $license = $this->licenseDao->getLicenseById($licenseId, $groupId);
    } else {
      $license = $this->licenseDao->getLicenseByShortName($licenseShortname,
        $groupId);
    }
    if ($license === null) {
      return;
    }
    $this->vars['shortName'] = $license->getShortName();
    $this->vars['fullName'] = $license->getFullName();
    $parent = $this->licenseDao->getLicenseParentById($license->getId());
    if ($parent !== null) {
      $this->vars['parentId'] = $parent->getId();
      $this->vars['parentShortName'] = $parent->getShortName();
    }
    $licenseUrl = $license->getUrl();
    if (strtolower($licenseUrl) == 'none') {
      $licenseUrl = NULL;
    }
    $this->vars['url'] = $licenseUrl;
    $this->vars['text'] = $license->getText();
    $this->vars['risk'] = $license->getRisk() ?: 0;
    return $this->render('popup_license.html.twig');
  }
}

$NewPlugin = new PopupLicense();
