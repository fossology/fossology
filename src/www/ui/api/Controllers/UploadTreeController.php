<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for uploadtree queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;


/**
 * @class UploadTreeController
 * @brief Controller for UploadTree model
 */
class UploadTreeController extends RestController
{
  /**
   * @var ContainerInterface $container
   * Slim container
   */
  protected $container;

  /** @var ClearingDao */
  private $clearingDao;

  /**
   * @var LicenseDao $licenseDao
   * License Dao object
   */
  private $licenseDao;

  public function __construct($container)
  {
    parent::__construct($container);
    $this->container = $container;
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->licenseDao = $this->container->get('dao.license');
  }

  /**
   * Set clearing info for a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function updateClearingInfo($request, $response, $args)
  {
    try {
      $uploadTreeId = intval($args['itemId']);
      $licenseId = intval($args['licenseId']);
      $uploadPk = intval($args['id']);
      $body = $this->getParsedBody($request);
      $column = $body['column'];
      $text = $body['text'];
      $columnIdMap = [
        'TEXT' => 'reportinfo',
        'ACK' => 'acknowledgement',
        'COMMENT' => 'comment',
      ];

      $returnVal = null;
      $uploadDao = $this->restHelper->getUploadDao();

      if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadPk)) {
        $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadPk), "uploadtree_pk", $uploadTreeId)) {
        $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
      } else if (!isset($body['column'])) {
        $returnVal =  new Info(400, "The property 'column' is required", InfoType::ERROR);
      } else if (!array_key_exists($column, $columnIdMap)) {
        $returnVal = new Info(400, "Invalid columnKey. Allowed values are 'TEXT', 'ACK', 'COMMENT'", InfoType::ERROR);
      } else if (!$this->dbHelper->doesIdExist("license_ref", "rf_pk", $licenseId)) {
        $returnVal = new Info(404, "License does not exist", InfoType::ERROR);
      }

      if ($returnVal !== null) {
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }

      $concludeLicensePlugin = $this->restHelper->getPlugin('conclude-license');

      // Get the existing licenseIds and check if the given license is among them
      $res = $concludeLicensePlugin->doClearings(true, $this->restHelper->getGroupId(), $uploadPk, $uploadTreeId);
      $existingLicenseIds = array();

      foreach ($res['aaData'] as $license) {
        $currId = $license['DT_RowId'];
        $currId = explode(',', $currId)[1];
        $existingLicenseIds[] = intval($currId);
      }

      if (!in_array($licenseId, $existingLicenseIds)) {
        $returnVal = new Info(404, "Given License does not exist on this item", InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }

      $this->clearingDao->updateClearingEvent($uploadTreeId, $this->restHelper->getUserId(), $this->restHelper->getGroupId(), $licenseId, $columnIdMap[$column], $text);
      $returnVal = new Info(200, "Successfully updated " . ($column == "TEXT" ? "License text": ($column == "ACK" ? "Acknowledgement": "Comment")). ".", InfoType::INFO);
      return $response->withJson($returnVal->getArray(), 200);
    } catch (\Exception $e) {
      $returnVal = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }
}
