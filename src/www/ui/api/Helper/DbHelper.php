<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief DB helper for REST api
 */
namespace Fossology\UI\Api\Helper;

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  "/lib/php/common-db.php";

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Folder\Folder;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Db\ModernDbManager;
use Fossology\Lib\Exceptions\DuplicateTokenKeyException;
use Fossology\Lib\Exceptions\DuplicateTokenNameException;
use Fossology\Lib\Proxy\LicenseViewProxy;
use Fossology\Lib\Proxy\UploadBrowseProxy;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Hash;
use Fossology\UI\Api\Models\Job;
use Fossology\UI\Api\Models\Upload;
use Fossology\UI\Api\Models\User;

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
   * @var FolderDao $folderDao
   * FolderDao object
   */
  private $folderDao;

  /**
   * @var UploadDao $uploadDao
   * UploadDao object
   */
  private $uploadDao;

  /**
   * DbHelper constructor.
   *
   * @param DbManager $dbManager DB manager in use
   * @param FolderDao $folderDao Folder Dao to use
   * @param UploadDao $uploadDao Upload Dao to use
   */
  public function __construct(DbManager $dbManager, FolderDao $folderDao,
    UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->folderDao = $folderDao;
    $this->uploadDao = $uploadDao;
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
   * @param integer $groupId  Group trying to access
   * @param integer $limit    Max number of results
   * @param integer $page     Page to get
   * @param integer $uploadId Pass the upload id to check for single upload.
   * @param integer $options  Filter options
   * @param bool $recursive   True to recursive listing of uploads
   * @return array Total pages as first value, uploads as an array in second
   *         value
   */
  public function getUploads($userId, $groupId, $limit, $page = 1,
    $uploadId = null, $options = null, $recursive = true, $apiVersion=ApiVersion::V1)
  {
    $uploadProxy = new UploadBrowseProxy($groupId, 0, $this->dbManager);
    $folderId = $options["folderId"];
    if ($folderId === null) {
      $users = $this->getUsers($userId);
      $folderId = $users[0]->getRootFolderId();
    }
    $folders = [$folderId];

    if ($uploadId !== null) {
      $recursive = true;
      $users = $this->getUsers($userId);
      $folderId = $users[0]->getRootFolderId();
      $folders = [$folderId];
    }

    if ($recursive) {
      $tree = $this->folderDao->getFolderStructure($folderId);
      $folders = array_map(function ($folder) {
        return $folder[$this->folderDao::FOLDER_KEY]->getId();
      }, $tree);
    }

    $params = [$folders];
    $partialQuery = $uploadProxy->getFolderPartialQuery($params);

    $where = "";
    $statementCount = __METHOD__ . ".countAllUploads";
    $statementGet = __METHOD__ . ".getAllUploads.$limit";
    if ($uploadId !== null) {
      $params[] = $uploadId;
      $where .= " AND upload.upload_pk = $" . count($params);
      $statementGet .= ".upload";
      $statementCount .= ".upload";
    }
    if (! empty($options["name"])) {
      $params[] = strtolower("%" . $options["name"] . "%");
      $where .= " AND (LOWER(upload_desc) LIKE $" . count($params) .
        " OR LOWER(ufile_name) LIKE $" . count($params) .
        " OR LOWER(upload_filename) LIKE $" . count($params) . ")";
      $statementGet .= ".name";
      $statementCount .= ".name";
    }
    if (! empty($options["status"])) {
      $params[] = $options["status"];
      $where .= " AND status_fk = $" . count($params);
      $statementGet .= ".stat";
      $statementCount .= ".stat";
    }
    if (! empty($options["assignee"])) {
      $params[] = $options["assignee"];
      $where .= " AND assignee = $" . count($params);
      $statementGet .= ".assi";
      $statementCount .= ".assi";
    }
    if (! empty($options["since"])) {
      $params[] = $options["since"];
      $where .= " AND upload_ts >= to_timestamp($" . count($params) . ")";
      $statementGet .= ".since";
      $statementCount .= ".since";
    }
    $sql = "SELECT count(*) AS cnt FROM $partialQuery $where;";
    $totalResult = $this->dbManager->getSingleRow($sql, $params, $statementCount);
    $totalResult = intval($totalResult['cnt']);
    $totalResult = intval(ceil($totalResult / $limit));

    $params[] = ($page - 1) * $limit;

    $sql = "SELECT
upload.upload_pk, upload.upload_desc, upload.upload_ts, upload.upload_filename, upload_clearing.assignee
FROM $partialQuery $where ORDER BY upload_pk ASC LIMIT $limit OFFSET $" .
      count($params) . ";";
    $results = $this->dbManager->getRows($sql, $params, $statementGet);
    $uploads = [];
    foreach ($results as $row) {
      $uploadId = $row["upload_pk"];
      $pfile_size = null;
      $pfile_sha1 = null;
      $pfile_md5 = null;
      $pfile_sha256 = null;
      $pfile = $this->getPfileInfoForUpload($uploadId);
      if ($pfile !== null) {
        $pfile_size = $pfile['pfile_size'];
        $pfile_sha1 = $pfile['pfile_sha1'];
        $pfile_md5 = $pfile['pfile_md5'];
        $pfile_sha256 = $pfile['pfile_sha256'];
      }

      $folder = $this->getFolderForUpload($uploadId);
      if ($folder === null) {
        continue;
      }
      $folderId = $folder->getId();
      $folderName = $folder->getName();

      $hash = new Hash($pfile_sha1, $pfile_md5, $pfile_sha256, $pfile_size);
      $upload = new Upload($folderId, $folderName, $uploadId,
        $row["upload_desc"], $row["upload_filename"], $row["upload_ts"], $row["assignee"], $hash);
      if (! empty($row["assignee"]) && $row["assignee"] != 1) {
        $upload->setAssigneeDate($this->uploadDao->getAssigneeDate($uploadId));
      }
      $upload->setClosingDate($this->uploadDao->getClosedDate($uploadId));
      $uploads[] = $upload->getArray($apiVersion);
    }
    return [$totalResult, $uploads];
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
      "SELECT DISTINCT ufile_name FROM uploadtree
WHERE uploadtree_pk=$1", [$uploadTreePk])["ufile_name"];
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
FROM $tableName WHERE $idRowName = $1", [$id],
      __METHOD__ . $tableName . $idRowName)["count"])));
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
   * @return User[] Users as an associative array
   */
  public function getUsers($id = null)
  {
    if ($id == null) {
      $usersSQL = "SELECT user_pk, user_name, user_desc, user_email,
                  email_notify, root_folder_fk, group_fk, user_perm, user_agent_list, default_bucketpool_fk FROM users;";
      $statement = __METHOD__ . ".getAllUsers";
    } else {
      $usersSQL = "SELECT user_pk, user_name, user_desc, user_email,
                email_notify, root_folder_fk, group_fk, user_perm, user_agent_list, default_bucketpool_fk FROM users
                WHERE user_pk = $1;";
      $statement = __METHOD__ . ".getSpecificUser";
    }
    $users = [];
    if ($id === null) {
      $result = $this->dbManager->getRows($usersSQL, [], $statement);
    } else {
      $result = $this->dbManager->getRows($usersSQL, [$id], $statement);
    }
    $currentUser = Auth::getUserId();
    $userIsAdmin = Auth::isAdmin();
    foreach ($result as $row) {
      $user = null;
      if ($userIsAdmin ||
        ($row["user_pk"] == $currentUser)) {
        $user = new User($row["user_pk"], $row["user_name"], $row["user_desc"],
          $row["user_email"], $row["user_perm"], $row["root_folder_fk"],
          $row["email_notify"], $row["user_agent_list"], $row["group_fk"], $row["default_bucketpool_fk"]);
      } else {
        $user = new User($row["user_pk"], $row["user_name"], $row["user_desc"],
          null, null, null, null, null, null);
      }
      $users[] = $user;
    }

    return $users;
  }


  /**
   * Generates the SQL statement for the Common Table Expression (CTE)
   * that retrieves the jobs including their status received by the jobqueue table.
   *
   * @return string The SQL statement for the job status CTE.
   */
  private function getJobStatusCteSQLStatement()
  {
    return "WITH job_with_status_cte AS (
      SELECT j.job_pk, j.job_queued, j.job_name, j.job_upload_fk, j.job_user_fk, j.job_group_fk,
        CASE
          WHEN COUNT(CASE WHEN jq.jq_endtext = 'Failed' THEN 1 END) > 0 THEN 'Failed'
          WHEN COUNT(CASE WHEN jq.jq_endtext in ('Started', 'Restarted', 'Paused') THEN 1 END) > 0 THEN 'Processing'
          WHEN COUNT(CASE WHEN jq.jq_endtext IS NULL THEN 1 END) > 0 THEN 'Queued'
          ELSE 'Completed'
        END AS job_status
      FROM job j
      RIGHT JOIN jobqueue jq ON j.job_pk=jq.jq_job_fk
      GROUP BY j.job_pk, j.job_queued, j.job_name, j.job_upload_fk, j.job_user_fk, j.job_group_fk
    )";
  }

  /**
   * @brief Get the recent jobs.
   *
   * If a limit is passed, the results are trimmed. If an ID is passed, the
   * information for the given id is only retrieved.
   *
   * @param integer $id       Set to get information of only given job id
   * @param integer $status   Set to get information of only jobs with given status
   * @param string  $sort     Set to sort the results asc or desc
   * @param integer $limit    Set to limit the result length
   * @param integer $page     Page number required
   * @param integer $uploadId Upload ID to be filtered
   * @param integer $userId      Set to get information of only given user's ID
   * @return array[] List of jobs at first index and total number of pages at
   *         second.
   */
  public function getJobs($id = null, $status = null, $sort = "ASC", $limit = 0, $page = 1, $uploadId = null, $userId = null)
  {
    $jobsWithStatusCteSQL = $this->getJobStatusCteSQLStatement();
    $jobSQL = "$jobsWithStatusCteSQL SELECT * FROM job_with_status_cte";
    $totalJobSql = "$jobsWithStatusCteSQL SELECT count(*) AS cnt FROM job_with_status_cte";

    $pagination = "";
    $params = [];
    $filter = [];
    $statement = $userId !== null ? __METHOD__ . ".getUserJobs" : __METHOD__ . ".getJobs";
    $countStatement = __METHOD__ . ".getJobCount";

    if ($id !== null) {
      $params[] = $id;
      $filter[] = "job_pk = $" . count($params);
      $statement .= ".withJobFilter";
      $countStatement .= ".withJobFilter";
    } elseif ($uploadId !== null) {
      $params[] = $uploadId;
      $filter[] = "job_upload_fk = $" . count($params);
      $statement .= ".withUploadFilter";
      $countStatement .= ".withUploadFilter";
    }

    // if userId was given, add it to the where filter
    if ($userId !== null) {
      $params[] = $userId;
      $filter[] = "job_user_fk = $" . count($params);
    }

    // if status was given, add it to the where filter
    if ($status !== null && in_array($status, ["Failed", "Processing", "Queued", "Completed"])) {
      $params[] = $status;
      $filter[] = "job_status = $" . count($params);
    }
    // build where filter query
    $filterSQL = $filter ? "WHERE " . implode(" AND ", $filter) : "";

    // get result for total count
    $result = $this->dbManager->getSingleRow("$totalJobSql $filterSQL;", $params,
      $countStatement);
    $totalResult = $result['cnt'];

    // sort results in given order, make sure only "ASC" and "DESC" are accepted
    $sort = strtoupper($sort);
    $orderBy = in_array($sort, ["ASC", "DESC"]) ? "ORDER BY job_queued $sort" : "";

    $offset = ($page - 1) * $limit;
    if ($limit > 0) {
      $params[] = $limit;
      $pagination = "LIMIT $" . count($params);
      $params[] = $offset;
      $pagination .= " OFFSET $" . count($params);
      $statement .= ".withLimit";
      $totalResult = ceil($totalResult / $limit);
    } else {
      $totalResult = 1;
    }

    // get result for jobs
    $jobs = [];
    $result = $this->dbManager->getRows("$jobSQL $filterSQL $orderBy $pagination;", $params,
      $statement);
    foreach ($result as $row) {
      $job = new Job($row["job_pk"]);
      $job->setName($row["job_name"]);
      $job->setQueueDate($row["job_queued"]);
      $job->setUploadId($row["job_upload_fk"]);
      $job->setUserId($row["job_user_fk"]);
      $job->setGroupId($row["job_group_fk"]);
      $job->setStatus($row["job_status"]);
      $jobs[] = $job;
    }
    return [$jobs, $totalResult];
  }

  /**
   * @brief Get the recent jobs created by an user.
   *
   * If a limit is passed, the results are trimmed.
   *
   * @param integer $userId      Set to get information of only given user's ID
   * @param integer $status   Set to get information of only jobs with given status
   * @param string  $sort     Set to sort the results asc or desc
   * @param integer $limit    Set to limit the result length
   * @param integer $page     Page number required
   * @return array[] List of jobs at first index and total number of pages at
   *         second.
   */
  public function getUserJobs($userId = null, $status = null, $sort = "ASC", $limit = 0, $page = 1)
  {
    return $this->getJobs(null, $status, $sort, $limit, $page, null, $userId);
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
    $sql = "SELECT token_key, client_id, created_on, expire_on, user_fk, active, token_scope " .
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
   * Adds new oauth client to the user.
   *
   * @param string  $name     Name of the new client
   * @param integer $userId   User PK
   * @param string  $clientId New client ID
   * @param string  $scope    Token scope
   */
  public function addNewClient($name, $userId, $clientId, $scope)
  {
    $sql = "INSERT INTO personal_access_tokens" .
      "(user_fk, created_on, token_scope, token_name, client_id, active)" .
      "VALUES ($1, NOW(), $2, $3, $4, true);";
    $this->dbManager->getSingleRow($sql, [
      $userId, $scope, $name, $clientId
    ], __METHOD__);
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

  /**
   * Get all info from pfile for given upload
   * @param integer $uploadId Upload to get info for
   * @return array|NULL Array of pfile if upload found, null otherwise
   */
  public function getPfileInfoForUpload($uploadId)
  {
    $sql = "SELECT pfile.* FROM upload INNER JOIN pfile " .
      "ON pfile_fk = pfile_pk WHERE upload_pk = $1;";
    $result = $this->dbManager->getSingleRow($sql, [$uploadId],
      __METHOD__ . ".getPfileFromUpload");
    if (! empty($result)) {
      return $result;
    }
    return null;
  }

  /**
   * Get the folder for given upload
   * @param integer $uploadId Upload to get folder for
   * @return Folder|null Folder object if found, null otherwise
   */
  private function getFolderForUpload($uploadId)
  {
    $contentId = $this->folderDao->getFolderContentsId($uploadId,
      $this->folderDao::MODE_UPLOAD);
    $content = $this->folderDao->getContent($contentId);
    return $this->folderDao->getFolder($content['parent_fk']);
  }

  /**
   * Get the licenses from database in paginated way
   *
   * @param integer $page    Which page number to fetch
   * @param integer $limit   Limit of results
   * @param string  $kind    Which kind of licenses to fetch
   * @param integer $groupId Group of the user
   * @param boolean $active  True to get only active licenses
   * @return array
   */
  public function getLicensesPaginated($page, $limit, $kind, $groupId, $active)
  {
    $statementName = __METHOD__;
    $rfTable = 'license_all';
    $options = ['columns' => ['rf_pk', 'rf_shortname', 'rf_fullname', 'rf_text',
      'rf_url', 'rf_risk', 'group_fk']];
    if ($active) {
      $options['extraCondition'] = "rf_active = '" .
        $this->dbManager->booleanToDb($active) . "'";
    }
    if ($kind == "candidate") {
      $options['diff'] = true;
    } elseif ($kind == "main") {
      $groupId = 0;
    }
    $licenseViewDao = new LicenseViewProxy($groupId, $options, $rfTable);
    $withCte = $licenseViewDao->asCTE();

    return $this->dbManager->getRows($withCte .
      " SELECT * FROM $rfTable ORDER BY LOWER(rf_shortname) " .
      "LIMIT $1 OFFSET $2;",
      [$limit, ($page - 1) * $limit], $statementName);
  }

  /**
   * Get the count of licenses accessible by user based on group ID
   *
   * @param string  $kind    Which kind of licenses to look for
   * @param integer $groupId Group of the user
   * @return int Count of licenses
   */
  public function getLicenseCount($kind, $groupId)
  {
    $sql = "SELECT sum(cnt) AS total FROM (";
    $mainLicSql = " SELECT count(*) AS cnt FROM ONLY license_ref ";
    $candidateLicSql = " SELECT count(*) AS cnt FROM license_candidate WHERE group_fk = $1";
    $params = [];

    if ($kind == "main") {
      $sql .= $mainLicSql;
    } elseif ($kind == "candidate") {
      $sql .= $candidateLicSql;
      $params[] = $groupId;
    } else {
      $sql .= $mainLicSql . " UNION ALL " . $candidateLicSql;
      $params[] = $groupId;
    }
    $sql .= ") as all_lic;";

    $statement = __METHOD__ . ".getLicenseCount.$kind";
    $result = $this->dbManager->getSingleRow($sql, $params, $statement);
    return intval($result['total']);
  }

  /*
   * Get the OAuth token ID from a client id
   *
   * @param string $clientId Client ID to get info for
   * @return integer Token ID
   */
  public function getTokenIdFromClientId($clientId)
  {
    $sql = "SELECT pat_pk FROM personal_access_tokens " .
      "WHERE client_id = $1;";
    $result = $this->dbManager->getSingleRow($sql, [$clientId], __METHOD__);
    if (!empty($result)) {
      return $result['pat_pk'];
    }
    return null;
  }
}
