<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief  Helper to handle server uploads via REST API
 */

namespace Fossology\UI\Api\Helper\UploadHelper;

use Fossology\UI\Page\UploadSrvPage;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class HelperToUploadSrvPage
 * Child class helper to access protected methods of UploadSrvPage
 */
class HelperToUploadSrvPage extends UploadSrvPage
{

  /**
   * Handles the Symfony Request object and pass it to handleUpload() of
   * UploadVcsPage.
   *
   * @param Request $request Symfony Request object holding information about
   *        the upload
   */
  public function handleRequest(Request $request)
  {
    return $this->handleUpload($request);
  }
}