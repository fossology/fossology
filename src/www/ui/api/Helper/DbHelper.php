<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/

/**
 * @file
 * @brief DB helper for REST api
 */
namespace Fossology\UI\Api\Helper;

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  "/lib/php/common-db.php";

use Fossology\Lib\Db\ModernDbManager;
use Fossology\Lib\Exceptions\DuplicateTokenKeyException;
use Fossology\Lib\Exceptions\DuplicateTokenNameException;
use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\Job;
use Fossology\UI\Api\Models\Upload;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\Info;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Auth\Auth;

/**
 * @class DbHelper
 * @brief Provides helper methods to access database for REST api.
 */
class DbHelper
{
  /**
   * @var ModernDbManager $dbManager
   * DB manager in use
   */
  private $dbManager;

  /**
   * DbHelper constructor.
   *
   * @param DbManager $dbManager DB manager in use
   */
  public function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
  }

  /**
   * Get the DB manager
   *
   * @return ModernDbManager
   */
  public function getDbManager()
  {
    return $this->dbManager;
  }

  /**
   * Get the uploads under the given user id if not upload id is provided.
   *
   * Get a single upload information under the given user and upload id.
   *
   * @param integer $userId   User to check
   * @param integer $uploadId Pass the upload id to check for single upload.
   * @return Upload[][] Uploads as an associative array
   */
  public function getUploads($userId, $uploadId = null)
  {
    if ($uploadId == null) {
      $sql = "SELECT
upload.upload_pk, upload.upload_desc, upload.upload_ts, upload.upload_filename,
folder.folder_pk, folder.folder_name, pfile.pfile_size, pfile.pfile_sha1
FROM upload
INNER JOIN folderlist ON folderlist.upload_pk = upload.upload_pk
INNER JOIN folder ON folder.folder_pk = folderlist.parent
INNER JOIN pfile ON pfile.pfile_pk = upload.pfile_fk
WHERE upload.user_fk = $1
ORDER BY upload.upload_pk;";
      $statementName = __METHOD__ . ".getAllUploads";
      $params = [$userId];
    } else {
      $sql = "SELECT
upload.upload_pk, upload.upload_desc, upload.upload_ts, upload.upload_filename,
folder.folder_pk, folder.folder_name, pfile.pfile_size, pfile.pfile_sha1
FROM upload
INNER JOIN folderlist ON folderlist.upload_pk = upload.upload_pk
INNER JOIN folder ON folder.folder_pk = folderlist.parent
INNER JOIN pfile ON pfile.pfile_pk = upload.pfile_fk
WHERE upload.user_fk = $1
AND upload.upload_pk = $2
ORDER BY upload.upload_pk;";
      $statementName = __METHOD__ . ".getSpecificUpload";
      $params = [$userId,$uploadId];
    }
    $result = $this->dbManager->getRows($sql, $params, $statementName);
    $uploads = [];
    foreach ($result as $row) {
      $upload = new Upload($row["folder_pk"], $row["folder_name"],
        $row["upload_pk"], $row["upload_desc"], $row["upload_filename"],
        $row["upload_ts"], $row["pfile_size"], $row["pfile_sha1"]);
      array_push($uploads, $upload->getArray());
    }
    return $uploads;
  }

  /**
   * Get first upload name under a given upload tree id
   *
   * @param integer $uploadTreePk Upload tree id to check.
   * @return string
   */
  public function getFilenameFromUploadTree($uploadTreePk)
  {
    return $this->dbManager->getSingleRow(
      'SELECT DISTINCT ufile_name FROM uploadtree
WHERE uploadtree_pk=' . pg_escape_string($uploadTreePk))["ufile_name"];
  }

  /**
   * Check if a given id exists under given table.
   *
   * @param string $tableName Table name
   * @param string $idRowName ID column name
   * @param string $id ID to check
   * @return boolean True if id exists, false otherwise
   */
  public function doesIdExist($tableName, $idRowName, $id)
  {
    return (0 < (intval($this->getDbManager()->getSingleRow("SELECT COUNT(*)
FROM $tableName WHERE $idRowName= " . pg_escape_string($id))["count"])));
  }

  /**
   * Delete the given user id
   *
   * @param integer $id User id to be deleted
   */
  public function deleteUser($id)
  {
    require_once dirname(dirname(__DIR__)) . "/user-del-helper.php";
    deleteUser($id, $this->getDbManager());
  }

  /**
   * Get the user under the given user id or every user from the database.
   *
   * @param integer $id User id of the required user, or NULL to fetch all
   *        users.
   * @return User[][] Users as an associative array
   */
  public function getUsers($id = null)
  {
    if ($id == null) {
      $usersSQL = "SELECT user_pk, user_name, user_desc, user_email,
                  email_notify, root_folder_fk, user_perm, user_agent_list FROM users;";
      $statement = __METHOD__ . ".getAllUsers";
    } else {
      $usersSQL = "SELECT user_pk, user_name, user_desc, user_email,
                email_notify, root_folder_fk, user_perm, user_agent_list FROM users
                WHERE user_pk = $1;";
      $statement = __METHOD__ . ".getSpecificUser";
    }
    $users = [];
    if ($id === null) {
      $result = $result = $this->dbManager->getRows($usersSQL, [], $statement);
    } else {
      $result = $result = $this->dbManager->getRows($usersSQL, [$id],
        $statement);
    }
    $currentUser = Auth::getUserId();
    $userIsAdmin = Auth::isAdmin();
    foreach ($result as $row) {
      $user = null;
      if ($userIsAdmin ||
        ($row["user_pk"] == $currentUser)) {
        $user = new User($row["user_pk"], $row["user_name"], $row["user_desc"],
          $row["user_email"], $row["user_perm"], $row["root_folder_fk"],
          $row["email_notify"], $row["user_agent_list"]);
      } else {
        $user = new User($row["user_pk"], $row["user_name"], $row["user_desc"],
          null, null, null, null, null);
      }
      $users[] = $user->getArray();
    }

    return $users;
  }

  /**
   * @brief Get the recent jobs.
   *
   * If a limit is passed, the results are trimmed. If an ID is passed, the
   * information for the given id is only retrieved.
   *
   * @param integer $id       Set to get information of only given job id
   * @param integer $limit    Set to limit the result length
   * @param integer $page     Page number required
   * @param integer $uploadId Upload ID to be filtered
   * @return array[] List of jobs at first index and total number of pages at
   *         second.
   */
  public function getJobs($id = null, $limit = 0, $page = 1, $uploadId = null)
  {
    $jobSQL = "SELECT job_pk, job_queued, job_name, job_upload_fk," .
      " job_user_fk, job_group_fk FROM job";
    $totalJobSql = "SELECT count(*) AS cnt FROM job";

    $filter = "";
    $pagination = "";

    $params = [];
    $statement = __METHOD__ . ".getJobs";
    $countStatement = __METHOD__ . ".getJobCount";
    if ($id == null) {
      if ($uploadId !== null) {
        $params[] = $uploadId;
        $filter = "WHERE job_upload_fk = $" . count($params);
        $statement .= ".withUploadFilter";
        $countStatement .= ".withUploadFilter";
      }
    } else {
      $params[] = $id;
      $filter = "WHERE job_pk = $" . count($params);
      $statement .= ".withJobFilter";
      $countStatement .= ".withJobFilter";
    }

    $result = $this->dbManager->getSingleRow("$totalJobSql $filter;", $params,
      $countStatement);

    $totalResult = $result['cnt'];

    $offset = ($page - 1) * $limit;
    if ($limit > 0) {
      $params[] = $limit;
      $pagination = "LIMIT $" . count($params);
      $params[] = $offset;
      $pagination .= " OFFSET $" . count($params);
      $statement .= ".withLimit";
      $totalResult = floor($totalResult / $limit) + 1;
    } else {
      $totalResult = 1;
    }

    $jobs = [];
    $result = $this->dbManager->getRows("$jobSQL $filter $pagination;", $params,
      $statement);
    foreach ($result as $row) {
      $job = new Job($row["job_pk"]);
      $job->setName($row["job_name"]);
      $job->setQueueDate($row["job_queued"]);
      $job->setUploadId($row["job_upload_fk"]);
      $job->setUserId($row["job_user_fk"]);
      $job->setGroupId($row["job_group_fk"]);
      $jobs[] = $job;
    }
    return [$jobs, $totalResult];
  }

  /**
   * Get the required information to validate a token based on token id.
   *
   * @param int $tokenId Token id (primary key of the table).
   * @return array Returns the `token_key`, `created_on, `expire_on` and
   *         `user_fk` for the given token id.
   */
  public function getTokenKey($tokenId)
  {
    $sql = "SELECT token_key, created_on, expire_on, user_fk, active, token_scope " .
      "FROM personal_access_tokens WHERE pat_pk = $1;";
    return $this->dbManager->getSingleRow($sql, [$tokenId],
      __METHOD__ . ".getTokenSecret");
  }

  /**
   * Mark a token as invalid/inactive.
   *
   * @param int $tokenId The token to be marked as invalid
   */
  public function invalidateToken($tokenId)
  {
    $sql = "UPDATE personal_access_tokens SET active = false WHERE pat_pk = $1;";
    $this->dbManager->getSingleRow($sql, [$tokenId], __METHOD__ . ".invalidateToken");
  }

  /**
   * Insert a new token in the DB.
   *
   * @param int    $userId User of the new token
   * @param string $expire When the token will expire
   * @param string $scope  Scope of the token
   * @param string $name   Name of the token
   * @param string $key    Secret key of the token
   * @return array|Fossology::UI::Api::Models::Info New token id and created_on
   *         or Info on error.
   * @throws DuplicateTokenNameException If user already have a token with same
   *         name.
   * @throws DuplicateTokenKeyException  If user already have a token with same
   *         key.
   */
  public function insertNewTokenKey($userId, $expire, $scope, $name, $key)
  {
    if (! $this->checkTokenNameUnique($userId, $name)) {
      throw new DuplicateTokenNameException(
        "Already have a token with same name.", 409);
    }
    if (! $this->checkTokenKeyUnique($userId, $name)) {
      throw new DuplicateTokenKeyException();
    }
    $sql = "INSERT INTO personal_access_tokens " .
      "(user_fk, created_on, expire_on, token_scope, token_name, token_key, active) " .
      "VALUES ($1, NOW(), $2, $3, $4, $5, true) " .
      "RETURNING pat_pk || '.' || user_fk AS jti, created_on";
    return $this->dbManager->getSingleRow($sql, [
      $userId, $expire, $scope, $name, $key
    ], __METHOD__ . ".insertNewToken");
  }

  /**
   * Checks if the `personal_access_tokens_token_name_ukey` constraint is
   * followed by this token.
   *
   * @param int    $userId    User id
   * @param string $tokenName Name of the token
   * @return boolean True if the constraint is followed, false otherwise.
   */
  private function checkTokenNameUnique($userId, $tokenName)
  {
    $tokenIsUnique = true;
    $sql = "SELECT count(*) AS cnt FROM personal_access_tokens " .
      "WHERE user_fk = $1 AND token_name = $2;";
    $result = $this->dbManager->getSingleRow($sql, [$userId, $tokenName],
      __METHOD__ . ".checkTokenNameUnique");
    if ($result['cnt'] != 0) {
      $tokenIsUnique = false;
    }
    return $tokenIsUnique;
  }

  /**
   * Checks if the `personal_access_tokens_token_key_ukey` constraint is
   * followed by this token.
   *
   * @param int    $userId   User id
   * @param string $tokenKey Token secret key
   * @return boolean True if the constraint is followed, false otherwise.
   */
  private function checkTokenKeyUnique($userId, $tokenKey)
  {
    $tokenIsUnique = true;
    $sql = "SELECT count(*) AS cnt FROM personal_access_tokens " .
      "WHERE user_fk = $1 AND token_key = $2;";
    $result = $this->dbManager->getSingleRow($sql, [$userId, $tokenKey],
      __METHOD__ . ".checkTokenKeyUnique");
    if ($result['cnt'] != 0) {
      $tokenIsUnique = false;
    }
    return $tokenIsUnique;
  }

  /**
   * Get the value for maximum API token validity from sysconfig table.
   *
   * @return integer The value stored in DB or 30 (default).
   */
  public function getMaxTokenValidity()
  {
    $sql = "SELECT conf_value FROM sysconfig WHERE variablename = $1;";
    $result = $this->dbManager->getSingleRow($sql, ["PATMaxExipre"],
      __METHOD__ . ".tokenMaxValidFromSysconfig");
    $validity = 30;
    if (! empty($result['conf_value'])) {
      $validity = intval($result['conf_value']);
    }
    return $validity;
  }
}
