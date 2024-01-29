<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author:  Author: Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-FileCopyrightText: © 2023 Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for copyright queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\FileInfo;
use Fossology\UI\Api\Models\ApiVersion;
use Psr\Http\Message\ServerRequestInterface;


class FileInfoController extends RestController
{
  /**
   * @var \ui_view_info $viewInfo
   * View Info object
   */
  private $viewInfo;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->viewInfo = $this->restHelper->getPlugin('view_info');
  }

  /**
   * File info for a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getItemInfo($request, $response, $args)
  {
    $uploadPk = $args["id"];
    $uploadTreeId = $args["itemId"];
    $apiVersion = ApiVersion::getVersion($request);
    $this->uploadAccessible($uploadPk);
    $this->isItemExists($uploadPk, $uploadTreeId);
    $response_view = $this->viewInfo->ShowView($uploadPk, $uploadTreeId);
    $response_meta = $this->viewInfo->ShowMetaView($uploadPk, $uploadTreeId);
    $response_package_info = $this->viewInfo->ShowPackageInfo($uploadPk, $uploadTreeId);
    $response_tag_info = $this->viewInfo->ShowTagInfo($uploadPk, $uploadTreeId);
    $response_reuse_info = $this->viewInfo->showReuseInfo($uploadPk);
    $finalValue = new FileInfo($response_view, $response_meta, $response_package_info, $response_tag_info, $response_reuse_info);
    return $response->withJson($finalValue->getarray($apiVersion), 200);
  }
}
