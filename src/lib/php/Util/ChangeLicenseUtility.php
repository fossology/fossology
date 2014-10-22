<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: Johannes Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\Util;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\LicenseRef;

class ChangeLicenseUtility extends Object
{
  /** @var UploadDao $uploadDao */
  private $uploadDao;
  /** @var LicenseDao $licenseDao */
  private $licenseDao;

  /**
   * @param UploadDao $uploadDao
   * @param LicenseDao $licenseDao
   */

  function __construct(UploadDao $uploadDao, LicenseDao $licenseDao)
  {
    $this->uploadDao = $uploadDao;
    $this->licenseDao = $licenseDao;
  }

  /**
   * @return array
   */
  public function createChangeLicenseFormContent()
  {
    $outValues = $this->licenseDao->getLicenseArray();
    $uri = Traceback_uri() . "?mod=popup-license&lic=";
    $rendererVars = array('licenseArray'=>$outValues,'licenseUri'=>$uri);
    return $rendererVars;
  }


  public function createBulkFormContent() {
    $rendererVars = array();
    $rendererVars['bulkUri'] = Traceback_uri() . "?mod=popup-license";
    $rendererVars['licenseArray'] = $this->licenseDao->getLicenseArray();
    return $rendererVars;
  }
}