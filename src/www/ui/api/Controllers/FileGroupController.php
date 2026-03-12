<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Dao\FileGroupDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\FileGroup;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class FileGroupController
 * @brief REST controller for File Groups (Issue #2847).
 *
 * Endpoints:
 *   GET    /uploads/{id}/filegroups           - list all groups for an upload
 *   POST   /uploads/{id}/filegroups           - create a new group
 *   PATCH  /uploads/{id}/filegroups/{fgId}   - update group metadata
 *   DELETE /uploads/{id}/filegroups/{fgId}   - delete a group
 *   POST   /uploads/{id}/filegroups/{fgId}/members      - add files to a group
 *   DELETE /uploads/{id}/filegroups/{fgId}/members/{ut} - remove a file
 *   GET    /uploads/{id}/filegroups/suggest   - auto-suggest groups
 */
class FileGroupController extends RestController
{
  /** @var FileGroupDao */
  private $fileGroupDao;

  /** @var UploadDao */
  private $uploadDao;

  /**
   * @param object $container DI container
   */
  public function __construct($container)
  {
    parent::__construct($container);
    $this->fileGroupDao = $this->container->get('dao.filegroup');
    $this->uploadDao    = $this->container->get('dao.upload');
  }

  // ─── Helpers ───────────────────────────────────────────────────────────────

  /**
   * Assert that the current user can access the given upload.
   * @param int $uploadId
   * @throws HttpNotFoundException
   */
  private function assertUploadExists(int $uploadId): void
  {
    if (!$this->dbHelper->doesIdExist('upload', 'upload_pk', $uploadId)) {
      throw new HttpNotFoundException("Upload $uploadId not found.");
    }
  }

  /**
   * Assert that a file group exists and belongs to the given upload.
   * @param int $fgPk
   * @param int $uploadId
   * @return array The group row
   * @throws HttpNotFoundException
   */
  private function assertGroupExists(int $fgPk, int $uploadId): array
  {
    $group = $this->fileGroupDao->getGroupById($fgPk);
    if (empty($group) || intval($group['upload_fk']) !== $uploadId) {
      throw new HttpNotFoundException("File group $fgPk not found for upload $uploadId.");
    }
    return $group;
  }

  /**
   * Assert the caller is a member of the required fossology group.
   * @param int $requiredGroupId
   * @throws HttpForbiddenException
   */
  private function assertGroupMembership(int $requiredGroupId): void
  {
    if (intval($this->restHelper->getGroupId()) !== $requiredGroupId) {
      throw new HttpForbiddenException(
        "You do not have permission to modify this file group."
      );
    }
  }

  /**
   * Convert a DB row to a FileGroup model and then to its array representation.
   * @param array $row DB row from file_group
   * @return array
   */
  private function rowToArray(array $row): array
  {
    $fg = new FileGroup(
      intval($row['fg_pk']),
      $row['fg_name'],
      $row['fg_curation_notes'] ?? null,
      $this->dbHelper->getDbManager()->booleanFromDb($row['fg_include_in_report']),
      intval($row['member_count'] ?? 0),
      $row['date_created'],
      $row['date_modified'] ?? null
    );
    return $fg->getArray();
  }

  // ─── Route handlers ────────────────────────────────────────────────────────

  /**
   * GET /uploads/{id}/filegroups
   *
   * List all file groups for the given upload.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args  ['id' => uploadId]
   * @return ResponseHelper
   */
  public function getGroups(
    ServerRequestInterface $request,
    ResponseHelper $response,
    array $args
  ): ResponseHelper {
    $uploadId = intval($args['id']);
    $this->assertUploadExists($uploadId);

    $groupId = $this->restHelper->getGroupId();
    $rows    = $this->fileGroupDao->getGroupsForUpload($uploadId, $groupId);
    $groups  = array_map([$this, 'rowToArray'], $rows);

    return $response->withJson($groups, 200);
  }

  /**
   * POST /uploads/{id}/filegroups
   *
   * Create a new file group for the upload.
   *
   * Expected JSON body:
   * {
   *   "name": "MIT utilities",           // required
   *   "curationNotes": "Verified clean", // optional
   *   "includeInReport": true,           // optional, default true
   *   "members": [12345, 12346]          // optional list of uploadtree_pk
   * }
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function createGroup(
    ServerRequestInterface $request,
    ResponseHelper $response,
    array $args
  ): ResponseHelper {
    $uploadId = intval($args['id']);
    $this->assertUploadExists($uploadId);

    $body = $this->getParsedBody($request);

    if (empty($body['name']) || !is_string($body['name'])) {
      throw new HttpBadRequestException("'name' is required and must be a string.");
    }

    $name            = trim($body['name']);
    $curationNotes   = isset($body['curationNotes']) ? trim($body['curationNotes']) : '';
    $includeInReport = isset($body['includeInReport'])
      ? (bool) $body['includeInReport'] : true;

    $groupId = $this->restHelper->getGroupId();
    $userId  = $this->restHelper->getUserId();

    $fgPk = $this->fileGroupDao->createGroup(
      $uploadId, $groupId, $userId, $name, $curationNotes, $includeInReport
    );

    // Add any initial members if provided
    if (!empty($body['members']) && is_array($body['members'])) {
      $members = array_filter($body['members'], 'is_numeric');
      $this->fileGroupDao->addMembers($fgPk, array_map('intval', $members));
    }

    $info = new Info(201, intval($fgPk), InfoType::INFO);
    return $response->withJson($info->getArray(), 201);
  }

  /**
   * PATCH /uploads/{id}/filegroups/{fgId}
   *
   * Update group name, curation notes or include-in-report flag.
   *
   * Expected JSON body (all fields optional):
   * {
   *   "name":            "New name",
   *   "curationNotes":   "Updated notes",
   *   "includeInReport": false
   * }
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args  ['id' => uploadId, 'fgId' => fgPk]
   * @return ResponseHelper
   */
  public function updateGroup(
    ServerRequestInterface $request,
    ResponseHelper $response,
    array $args
  ): ResponseHelper {
    $uploadId = intval($args['id']);
    $fgPk     = intval($args['fgId']);
    $this->assertUploadExists($uploadId);
    $group = $this->assertGroupExists($fgPk, $uploadId);
    $this->assertGroupMembership(intval($group['group_fk']));

    $body = $this->getParsedBody($request);

    $name            = isset($body['name']) ? trim($body['name']) : null;
    $curationNotes   = isset($body['curationNotes']) ? trim($body['curationNotes']) : null;
    $includeInReport = isset($body['includeInReport'])
      ? (bool) $body['includeInReport'] : null;

    if ($name === '') {
      throw new HttpBadRequestException("'name' must not be empty.");
    }

    $this->fileGroupDao->updateGroup($fgPk, $name, $curationNotes, $includeInReport);

    $info = new Info(200, "File group $fgPk updated successfully.", InfoType::INFO);
    return $response->withJson($info->getArray(), 200);
  }

  /**
   * DELETE /uploads/{id}/filegroups/{fgId}
   *
   * Delete a file group (members are removed automatically via cascade).
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function deleteGroup(
    ServerRequestInterface $request,
    ResponseHelper $response,
    array $args
  ): ResponseHelper {
    $uploadId = intval($args['id']);
    $fgPk     = intval($args['fgId']);
    $this->assertUploadExists($uploadId);
    $group = $this->assertGroupExists($fgPk, $uploadId);
    $this->assertGroupMembership(intval($group['group_fk']));

    $this->fileGroupDao->deleteGroup($fgPk);

    $info = new Info(200, "File group $fgPk deleted.", InfoType::INFO);
    return $response->withJson($info->getArray(), 200);
  }

  /**
   * POST /uploads/{id}/filegroups/{fgId}/members
   *
   * Add files (by uploadtree_pk) to a group.
   *
   * Expected JSON body:
   * {
   *   "members": [12345, 12346, 12347]
   * }
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpBadRequestException
   */
  public function addMembers(
    ServerRequestInterface $request,
    ResponseHelper $response,
    array $args
  ): ResponseHelper {
    $uploadId = intval($args['id']);
    $fgPk     = intval($args['fgId']);
    $this->assertUploadExists($uploadId);
    $group = $this->assertGroupExists($fgPk, $uploadId);
    $this->assertGroupMembership(intval($group['group_fk']));

    $body = $this->getParsedBody($request);

    if (empty($body['members']) || !is_array($body['members'])) {
      throw new HttpBadRequestException(
        "'members' is required and must be a non-empty array of uploadtree_pk integers."
      );
    }

    $members = array_filter($body['members'], 'is_numeric');
    if (empty($members)) {
      throw new HttpBadRequestException("'members' must contain numeric uploadtree_pk values.");
    }

    $this->fileGroupDao->addMembers($fgPk, array_map('intval', $members));

    $info = new Info(200,
      count($members) . " file(s) added to group $fgPk.", InfoType::INFO);
    return $response->withJson($info->getArray(), 200);
  }

  /**
   * DELETE /uploads/{id}/filegroups/{fgId}/members/{utId}
   *
   * Remove a single file from a group.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args  ['id' => uploadId, 'fgId' => fgPk, 'utId' => uploadtreePk]
   * @return ResponseHelper
   */
  public function removeMember(
    ServerRequestInterface $request,
    ResponseHelper $response,
    array $args
  ): ResponseHelper {
    $uploadId   = intval($args['id']);
    $fgPk       = intval($args['fgId']);
    $uploadtreePk = intval($args['utId']);
    $this->assertUploadExists($uploadId);
    $group = $this->assertGroupExists($fgPk, $uploadId);
    $this->assertGroupMembership(intval($group['group_fk']));

    $this->fileGroupDao->removeMember($fgPk, $uploadtreePk);

    $info = new Info(200, "File $uploadtreePk removed from group $fgPk.", InfoType::INFO);
    return $response->withJson($info->getArray(), 200);
  }

  /**
   * GET /uploads/{id}/filegroups/{fgId}/members
   *
   * List all member files in a group.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getMembers(
    ServerRequestInterface $request,
    ResponseHelper $response,
    array $args
  ): ResponseHelper {
    $uploadId = intval($args['id']);
    $fgPk     = intval($args['fgId']);
    $this->assertUploadExists($uploadId);
    $this->assertGroupExists($fgPk, $uploadId);

    $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
    $members = $this->fileGroupDao->getGroupMembers($fgPk, $uploadTreeTable);

    return $response->withJson($members, 200);
  }

  /**
   * GET /uploads/{id}/filegroups/suggest
   *
   * Auto-suggest file groupings based on shared license decisions.
   *
   * Returns an array of candidate groups. Each element:
   * {
   *   "file_list":    [uploadtree_pk, ...],
   *   "license_set":  [rf_fk, ...],
   *   "member_count": N
   * }
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function suggestGroups(
    ServerRequestInterface $request,
    ResponseHelper $response,
    array $args
  ): ResponseHelper {
    $uploadId = intval($args['id']);
    $this->assertUploadExists($uploadId);

    $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
    $suggestions = $this->fileGroupDao->suggestGroups($uploadId, $uploadTreeTable);

    // Convert PostgreSQL array literals to PHP arrays for clean JSON output
    $result = array_map(function (array $row) {
      return [
        'fileList'    => $this->pgArrayToInt($row['file_list']),
        'licenseSet'  => $this->pgArrayToInt($row['license_set']),
        'memberCount' => intval($row['member_count']),
      ];
    }, $suggestions);

    return $response->withJson($result, 200);
  }

  /**
   * Parse a PostgreSQL array literal like "{1,2,3}" into a PHP int array.
   * @param string|null $pgArray
   * @return int[]
   */
  private function pgArrayToInt(?string $pgArray): array
  {
    if (empty($pgArray)) {
      return [];
    }
    $stripped = trim($pgArray, '{}');
    if ($stripped === '') {
      return [];
    }
    return array_map('intval', explode(',', $stripped));
  }
}
