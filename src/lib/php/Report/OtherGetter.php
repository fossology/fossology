<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG
 Author: Anuapm Ghosh

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\UploadDao;

class OtherGetter
{
  /** @var UploadDao */
  private $uploadDao;

  public function __construct()
  {
    global $container;
    $this->uploadDao = $container->get('dao.upload');
  }

  public function getReportData($uploadId)
  {
    return $this->uploadDao->getReportInfo($uploadId);
  }
}
