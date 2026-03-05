<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file FileGroupDao.php
 * @brief Data Access Object for file groups (Issue #2847).
 *
 * Provides CRUD operations for the `file_group` and `file_group_member`
 * tables, plus a helper to auto-suggest groupings based on matching
 * license / copyright fingerprints.
 */

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

/**
 * @class FileGroupDao
 * @brief DAO for grouping files that share the same license/copyright info.
 */
class FileGroupDao
{
  /** @var DbManager */
  private $dbManager;

  /** @var Logger */
  private $logger;

  /**
   * FileGroupDao constructor.
   * @param DbManager $dbManager
   * @param Logger $logger
   */
  public function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger    = $logger;
  }

  // ─── Group CRUD ────────────────────────────────────────────────────────────

  /**
   * Create a new file group.
   *
   * @param int    $uploadId        Upload the group belongs to
   * @param int    $groupId         Fossology group that owns this file group
   * @param int    $userId          User creating the group
   * @param string $name            Human-readable group name
   * @param string $curationNotes   Optional curation notes / metadata
   * @param bool   $includeInReport Whether to include this group in reports
   * @return int   Primary key (fg_pk) of the newly created group
   */
  public function createGroup(int $uploadId, int $groupId, int $userId,
    string $name, string $curationNotes = '', bool $includeInReport = true): int
  {
    $stmt = __METHOD__;
    $sql  = "INSERT INTO file_group
               (upload_fk, group_fk, user_fk, fg_name, fg_curation_notes,
                fg_include_in_report, date_created, date_modified)
             VALUES ($1, $2, $3, $4, $5, $6, now(), now())
             RETURNING fg_pk";
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, [
      $uploadId,
      $groupId,
      $userId,
      $name,
      $curationNotes,
      $this->dbManager->booleanToDb($includeInReport),
    ]);
    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
    return intval($row['fg_pk']);
  }

  /**
   * Update properties of an existing file group.
   *
   * @param int    $fgPk            Group primary key
   * @param string $name            New name (pass null to keep existing)
   * @param string $curationNotes   New curation notes (pass null to keep existing)
   * @param bool   $includeInReport New include-in-report flag (pass null to keep existing)
   * @return bool  True on success
   */
  public function updateGroup(int $fgPk, ?string $name = null,
    ?string $curationNotes = null, ?bool $includeInReport = null): bool
  {
    $setClauses = ["\"date_modified\" = now()"];
    $params     = [$fgPk];

    if ($name !== null) {
      $params[]     = $name;
      $setClauses[] = "\"fg_name\" = $" . count($params);
    }
    if ($curationNotes !== null) {
      $params[]     = $curationNotes;
      $setClauses[] = "\"fg_curation_notes\" = $" . count($params);
    }
    if ($includeInReport !== null) {
      $params[]     = $this->dbManager->booleanToDb($includeInReport);
      $setClauses[] = "\"fg_include_in_report\" = $" . count($params);
    }

    if (count($params) === 1) {
      // Nothing to update aside from date
    }

    $sql = "UPDATE file_group SET " . implode(', ', $setClauses)
         . " WHERE fg_pk = $1";
    $this->dbManager->getSingleRow($sql, $params, __METHOD__);
    return true;
  }

  /**
   * Delete a file group (members are cascade-deleted via FK).
   *
   * @param int $fgPk Group primary key
   */
  public function deleteGroup(int $fgPk): void
  {
    $this->dbManager->getSingleRow(
      "DELETE FROM file_group WHERE fg_pk = $1",
      [$fgPk],
      __METHOD__
    );
  }

  /**
   * Get all file groups for a given upload and fossology group.
   *
   * @param int $uploadId Upload ID
   * @param int $groupId  Fossology group ID
   * @return array[] Rows from file_group with an extra `member_count` column
   */
  public function getGroupsForUpload(int $uploadId, int $groupId): array
  {
    $sql = "SELECT fg.fg_pk,
                   fg.fg_name,
                   fg.fg_curation_notes,
                   fg.fg_include_in_report,
                   fg.user_fk,
                   fg.date_created,
                   fg.date_modified,
                   COUNT(fgm.fgm_pk) AS member_count
            FROM file_group fg
            LEFT JOIN file_group_member fgm ON fgm.fg_fk = fg.fg_pk
            WHERE fg.upload_fk = $1 AND fg.group_fk = $2
            GROUP BY fg.fg_pk
            ORDER BY fg.date_created ASC";
    return $this->dbManager->getRows($sql, [$uploadId, $groupId], __METHOD__);
  }

  /**
   * Get a single file group by its primary key.
   *
   * @param int $fgPk Group primary key
   * @return array|false Single row or false if not found
   */
  public function getGroupById(int $fgPk)
  {
    return $this->dbManager->getSingleRow(
      "SELECT fg.fg_pk, fg.fg_name, fg.fg_curation_notes,
              fg.fg_include_in_report, fg.upload_fk, fg.group_fk,
              fg.user_fk, fg.date_created, fg.date_modified
       FROM file_group fg
       WHERE fg.fg_pk = $1",
      [$fgPk],
      __METHOD__
    );
  }

  // ─── Member management ─────────────────────────────────────────────────────

  /**
   * Add uploadtree items as members of a group.
   * Silently ignores duplicates (already member).
   *
   * @param int   $fgPk          Group primary key
   * @param int[] $uploadtreePks Array of uploadtree_pk values to add
   */
  public function addMembers(int $fgPk, array $uploadtreePks): void
  {
    if (empty($uploadtreePks)) {
      return;
    }
    $stmt = __METHOD__;
    $sql  = "INSERT INTO file_group_member (fg_fk, uploadtree_fk)
             VALUES ($1, $2)
             ON CONFLICT (fg_fk, uploadtree_fk) DO NOTHING";
    $this->dbManager->prepare($stmt, $sql);
    foreach ($uploadtreePks as $utPk) {
      $res = $this->dbManager->execute($stmt, [$fgPk, intval($utPk)]);
      $this->dbManager->freeResult($res);
    }
  }

  /**
   * Remove a single file from a group.
   *
   * @param int $fgPk          Group primary key
   * @param int $uploadtreePk  uploadtree_pk of the file to remove
   */
  public function removeMember(int $fgPk, int $uploadtreePk): void
  {
    $this->dbManager->getSingleRow(
      "DELETE FROM file_group_member
       WHERE fg_fk = $1 AND uploadtree_fk = $2",
      [$fgPk, $uploadtreePk],
      __METHOD__
    );
  }

  /**
   * Get all member files of a group with their names and pfile IDs.
   *
   * @param int    $fgPk              Group primary key
   * @param string $uploadTreeTable   Name of the upload-specific uploadtree table
   *                                  (e.g. 'uploadtree_a')
   * @return array[] Rows with uploadtree_pk, ufile_name, pfile_fk
   */
  public function getGroupMembers(int $fgPk, string $uploadTreeTable = 'uploadtree_a'): array
  {
    $sql = "SELECT ut.uploadtree_pk, ut.ufile_name, ut.pfile_fk
            FROM file_group_member fgm
            JOIN \"$uploadTreeTable\" ut ON ut.uploadtree_pk = fgm.uploadtree_fk
            WHERE fgm.fg_fk = $1
            ORDER BY ut.ufile_name ASC";
    return $this->dbManager->getRows($sql, [$fgPk], __METHOD__ . $uploadTreeTable);
  }

  /**
   * Check whether an uploadtree item already belongs to any group within this upload.
   *
   * @param int $uploadtreePk  uploadtree_pk to check
   * @param int $uploadId      Upload the item belongs to
   * @return int|false         fg_pk of the existing group, or false
   */
  public function getGroupForItem(int $uploadtreePk, int $uploadId)
  {
    $row = $this->dbManager->getSingleRow(
      "SELECT fg.* FROM file_group_member fgm
       JOIN file_group fg ON fg.fg_pk = fgm.fg_fk
       WHERE fgm.uploadtree_fk = $1 AND fg.upload_fk = $2
       LIMIT 1",
      [$uploadtreePk, $uploadId],
      __METHOD__
    );
    return $row;
  }

  // ─── Auto-grouping suggestions ─────────────────────────────────────────────

  /**
   * Suggest groupings by finding files inside an upload that share
   * identical license fingerprints (sorted array of rf_fk from clearing events).
   *
   * Only files that have at least one non-removed clearing event are considered.
   * Returns groups with more than one member.
   *
   * @param int    $uploadId        Upload to analyse
   * @param string $uploadTreeTable Name of the uploadtree table for this upload
   * @return array[] Each element has keys:
   *                 - `file_list`    (string, PostgreSQL array literal of uploadtree_pk)
   *                 - `license_set`  (string, PostgreSQL array literal of rf_fk)
   *                 - `member_count` (int)
   */
  public function suggestGroups(int $uploadId, string $uploadTreeTable = 'uploadtree_a'): array
  {
    $sql = "
      SELECT
        array_agg(DISTINCT ut.uploadtree_pk ORDER BY ut.uploadtree_pk) AS file_list,
        lr_set.license_set,
        count(DISTINCT ut.uploadtree_pk) AS member_count
      FROM \"$uploadTreeTable\" ut
      CROSS JOIN LATERAL (
        SELECT array_agg(ce.rf_fk ORDER BY ce.rf_fk) AS license_set
        FROM clearing_event ce
          JOIN clearing_decision_event cde
            ON cde.clearing_event_fk = ce.clearing_event_pk
          JOIN clearing_decision cd
            ON cd.clearing_decision_pk = cde.clearing_decision_fk
           AND cd.uploadtree_fk        = ut.uploadtree_pk
        WHERE NOT ce.removed
      ) lr_set
      WHERE ut.upload_fk = \$1
        AND (ut.ufile_mode & (3<<28)) = 0   -- exclude directories and artifacts
        AND lr_set.license_set IS NOT NULL
      GROUP BY lr_set.license_set
      HAVING count(DISTINCT ut.uploadtree_pk) > 1
      ORDER BY member_count DESC";

    return $this->dbManager->getRows($sql, [$uploadId],
      __METHOD__ . $uploadTreeTable);
  }

  /**
   * Get all file-group data for an upload (used by decision import/export).
   *
   * @param int $uploadId Upload ID
   * @return array[] Full join of file_group and file_group_member rows
   */
  public function getAllGroupDataForUpload(int $uploadId): array
  {
    $sql = "SELECT fg.fg_pk, fg.fg_name, fg.fg_curation_notes,
                   fg.fg_include_in_report, fg.group_fk, fg.user_fk,
                   fgm.uploadtree_fk
            FROM file_group fg
            LEFT JOIN file_group_member fgm ON fgm.fg_fk = fg.fg_pk
            WHERE fg.upload_fk = $1
            ORDER BY fg.fg_pk, fgm.uploadtree_fk";
    return $this->dbManager->getRows($sql, [$uploadId], __METHOD__);
  }
}
