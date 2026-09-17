<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for tag queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;

class TagController extends RestController
{
  /**
   * Create a new tag
   *
   * Delegates name validation and the actual insert to the legacy
   * admin_tag plugin (admin-tag.php), which already implements this for
   * the Admin > Tag UI, so the REST API and that UI stay in agreement on
   * what a valid tag looks like.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   * @throws HttpConflictException
   */
  public function createTag($request, $response, $args)
  {
    $this->throwNotAdminException();
    $requestBody = $this->getParsedBody($request);
    if (! is_array($requestBody)) {
      throw new HttpBadRequestException("Invalid request body.");
    }

    $tagName = (isset($requestBody['name']) && is_string($requestBody['name']))
      ? trim($requestBody['name']) : '';
    $tagDesc = (isset($requestBody['description']) && is_string($requestBody['description']))
      ? $requestBody['description'] : '';

    /** @var \admin_tag $tagPlugin */
    $tagPlugin = $this->restHelper->getPlugin('admin_tag');
    if ($tagName !== '' && $tagPlugin->TagExists($tagName)) {
      throw new HttpConflictException("Tag with same name already exists!");
    }

    $error = $tagPlugin->CreateTag(
      ['tag_name' => $tagName, 'tag_desc' => $tagDesc]);
    if (! empty($error)) {
      throw new HttpBadRequestException(strip_tags($error));
    }

    $newInfo = new Info(201, $tagPlugin->GetTagId($tagName), InfoType::INFO);
    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }

  /**
   * Enable or disable the tag display for a given upload
   *
   * Delegates to the legacy admin_tag_manage plugin
   * (admin-tag-manage.php), which already implements this exact
   * enable/disable-on-upload behaviour for the Admin UI.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function setTagDisplayStatus($request, $response, $args)
  {
    $this->throwNotAdminException();
    $id = intval($args['id']);
    $this->uploadAccessible($id);

    $requestBody = $this->getParsedBody($request);
    if (! is_array($requestBody) || ! array_key_exists('enabled', $requestBody)) {
      throw new HttpBadRequestException("Property 'enabled' is required.");
    }
    $enabled = filter_var($requestBody['enabled'], FILTER_VALIDATE_BOOLEAN);

    /** @var \admin_tag_manage $tagManagePlugin */
    $tagManagePlugin = $this->restHelper->getPlugin('admin_tag_manage');
    $tagManagePlugin->ManageTag(null, $id, $enabled ? 'Enable' : 'Disable');

    $message = $enabled ? "Tag display enabled for upload." :
      "Tag display disabled for upload.";
    $newInfo = new Info(200, $message, InfoType::INFO);
    return $response->withJson($newInfo->getArray(), $newInfo->getCode());
  }
}
