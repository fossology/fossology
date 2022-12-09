<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief  Helper to handle VCS uploads via REST API
 */

/**
 * @namespace Fossology::UI::Api::Helper::UploadHelper
 *            Holds various helpers to handle child classes of UploadPageBase
 */
namespace Fossology\UI\Api\Helper\UploadHelper;

use Fossology\UI\Page\UploadVcsPage;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class HelperToUploadVcsPage
 * Child class helper to access protected methods of UploadVcsPage
 */
class HelperToUploadVcsPage extends UploadVcsPage
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