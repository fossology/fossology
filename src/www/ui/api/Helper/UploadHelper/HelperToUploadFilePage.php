<?php
/*
 SPDX-FileCopyrightText: © 2019 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Helper to handle file uploads via REST API
 */
namespace Fossology\UI\Api\Helper\UploadHelper;

use Fossology\UI\Page\UploadFilePage;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class HelperToUploadFilePage
 * Child class helper to access protected methods of UploadFilePage
 */
class HelperToUploadFilePage extends UploadFilePage
{

  /**
   * Handles the Symfony Request object and pass it to handleUpload() of
   * UploadFilePage.
   *
   * @param Request $request Symfony Request object holding information about
   *        the upload
   */
  public function handleRequest(Request $request)
  {
    $response = $this->handleUpload($request);
    if ($response[0]) {
      $response[3] = $response[3][0];
    }
    return $response;
  }
}
