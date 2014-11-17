<?php
/*
 Copyright (C) 2014, Siemens AG

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
use Fossology\Lib\Dao\LicenseDao;

define("TITLE_PopupLicense", _("Show Reference License"));

class PopupLicense extends FO_Plugin
{
  /** @var LicenseDao */
  private $licenseDao;


  function __construct()
  {
    $this->Name = "popup-license";
    $this->Title = TITLE_PopupLicense;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    parent::__construct();

    global $container;
    $this->licenseDao = $container->get('dao.license');
  }


  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }
    $licenseShortname = GetParm("lic", PARM_TEXT);
    $licenseId = GetParm("rf", PARM_NUMBER);
    if (empty($licenseShortname) && empty($licenseId))
    {
      return;
    }
    if ($licenseId)
    {
      $license = $this->licenseDao->getLicenseById($licenseId);
    }
    else
    {
      $license = $this->licenseDao->getLicenseByShortName($licenseShortname);
    }
    if ($license === null)
    {
      return;
    }
    $this->vars['shortName'] = $license->getShortName();
    $this->vars['fullName'] = $license->getFullName();
    $licenseUrl = $license->getUrl();
    if (strtolower($licenseUrl) == 'none')
    {
      $licenseUrl = NULL;
    }
    $this->vars['url'] = $licenseUrl;
    $this->vars['text'] = $license->getText();
    return $this->renderTemplate($templateName = 'popup_license.html.twig');
  }
}

$NewPlugin = new PopupLicense();