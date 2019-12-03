<?php
/**
 * *************************************************************
 * Copyright (C) 2019 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * *************************************************************
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
    return $this->handleUpload($request);
  }
}