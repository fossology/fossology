<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for bucket pool queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\BucketPool;
use Psr\Http\Message\ServerRequestInterface;

class BucketPoolController extends RestController
{
  /**
   * Get list of active bucket pools, latest version first for each name
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getBucketpools($request, $response, $args)
  {
    $this->throwNotAdminException();
    $dbManager = $this->dbHelper->getDbManager();
    $rows = $dbManager->getRows(
      "SELECT bucketpool_pk, bucketpool_name, version, active, description " .
      "FROM bucketpool WHERE active = 'Y' " .
      "ORDER BY bucketpool_name ASC, version DESC",
      [], __METHOD__ . ".getBucketpools");

    $bucketpools = [];
    foreach ($rows as $row) {
      $bucketpools[] = (new BucketPool($row['bucketpool_pk'],
        $row['bucketpool_name'], $row['version'], $row['active'] === 'Y',
        $row['description']))->getArray();
    }
    return $response->withJson($bucketpools, 200);
  }

  /**
   * Duplicate a bucket pool together with its bucket definitions.
   *
   * The new bucket pool gets the same name with its version incremented by
   * one. Optionally, the requesting user's default bucket pool is updated
   * to the newly created one.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  public function duplicateBucketpool($request, $response, $args)
  {
    $this->throwNotAdminException();
    $bucketpoolId = intval($args['id']);
    if (! $this->dbHelper->doesIdExist("bucketpool", "bucketpool_pk", $bucketpoolId)) {
      throw new HttpNotFoundException("Bucketpool does not exist");
    }

    $requestBody = $this->getParsedBody($request);
    $updateDefault = false;
    if (is_array($requestBody) && array_key_exists('updateDefault', $requestBody)) {
      $updateDefault = filter_var($requestBody['updateDefault'], FILTER_VALIDATE_BOOLEAN);
    }

    $dbManager = $this->dbHelper->getDbManager();
    $oldPool = $dbManager->getSingleRow(
      "SELECT bucketpool_name, active, description FROM bucketpool " .
      "WHERE bucketpool_pk = $1", [$bucketpoolId], __METHOD__ . ".getBucketpool");

    $versionRow = $dbManager->getSingleRow(
      "SELECT max(version) AS version FROM bucketpool WHERE bucketpool_name = $1",
      [$oldPool['bucketpool_name']], __METHOD__ . ".getMaxVersion");
    $newVersion = intval($versionRow['version']) + 1;

    try {
      $newBucketpoolId = $dbManager->insertTableRow("bucketpool", [
        "bucketpool_name" => $oldPool['bucketpool_name'],
        "version" => $newVersion,
        "active" => $oldPool['active'],
        "description" => $oldPool['description']
      ], __METHOD__ . ".newBucketpool", "bucketpool_pk");
    } catch (\Exception $e) {
      throw new HttpConflictException(
        "Bucketpool with same name and version already exists! " .
        "Please try again.", $e);
    }
    $newBucketpoolId = intval($newBucketpoolId);

    $dbManager->getSingleRow(
      "INSERT INTO bucket_def (bucket_name, bucket_color, bucket_reportorder, " .
      "bucket_evalorder, bucketpool_fk, bucket_type, bucket_regex, " .
      "bucket_filename, stopon, applies_to) " .
      "SELECT bucket_name, bucket_color, bucket_reportorder, bucket_evalorder, " .
      "$1, bucket_type, bucket_regex, bucket_filename, stopon, applies_to " .
      "FROM bucket_def WHERE bucketpool_fk = $2",
      [$newBucketpoolId, $bucketpoolId], __METHOD__ . ".copyBucketDefs");

    if ($updateDefault) {
      $dbManager->getSingleRow(
        "UPDATE users SET default_bucketpool_fk = $1 WHERE user_pk = $2",
        [$newBucketpoolId, $this->restHelper->getUserId()],
        __METHOD__ . ".updateDefault");
    }

    return $response->withJson([
      "id" => $newBucketpoolId,
      "version" => $newVersion
    ], 201);
  }
}
