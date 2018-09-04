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

use Fossology\Lib\Db\Driver\Postgres;
use Fossology\Lib\Db\ModernDbManager;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\User;
use Fossology\UI\Api\Models\Job;
use Fossology\UI\Api\Models\Upload;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

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
   * @var resource $PG_CONN
   * Postgres connection resource
   */
  private $PG_CONN;
  /**
   * @var string $sysconfdir
   * SysConfDir location
   */
  private $sysconfdir;

  /**
   * DbHelper constructor.
   */
  public function __construct()
  {
    $logLevel = Logger::DEBUG;
    $logger = new Logger(__FILE__);
    $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $logLevel));
    $rcfile = "fossology.rc";

    $this->sysconfdir = getenv('SYSCONFDIR');
    if ($this->sysconfdir === false)
    {
      if (file_exists($rcfile)) $this->sysconfdir = file_get_contents($rcfile);
      if ($this->sysconfdir === false)
      {
        $this->sysconfdir = "/usr/local/etc/fossology";
      }
    }

    $this->sysconfdir = trim($this->sysconfdir);
    $this->PG_CONN = DBconnect($this->sysconfdir);
    $GLOBALS["PG_CONN"] = $this->PG_CONN;
    $this->dbManager = new ModernDbManager($logger);

    $pgDriver = new Postgres($this->PG_CONN);
    $this->dbManager->setDriver($pgDriver);
  }

  /**
   * Get the DB manager
   * @return ModernDbManager
   */
  public function getDbManager()
  {
    return $this->dbManager;
  }

  /**
   * Get the Postgres connection resource
   * @return resource
   */
  public function getPGCONN()
  {
    return $this->PG_CONN;
  }

  /**
   * Get the uploads under the given user id if not upload id is provided.
   *
   * Get a single upload information under the given user and upload id.
   * @param integer $userId   User to check
   * @param integer $uploadId Pass the upload id to check for single upload.
   * @return Upload[][] Uploads as an associative array
   */
  public function getUploads($userId, $uploadId = NULL)
  {
    if($uploadId == NULL)
    {
      $sql = "SELECT DISTINCT upload.upload_pk, upload.upload_ts, upload.upload_filename,
upload.upload_desc, folder.folder_pk, folder.folder_name, pfile.pfile_size
FROM upload, folderlist, folder, pfile
  WHERE upload.user_fk=".pg_escape_string($userId)."
  AND folderlist.upload_pk=upload.upload_pk
  AND pfile.pfile_pk=folderlist.pfile_fk
";
    }
    else
    {
      $sql = "SELECT DISTINCT upload.upload_pk, upload.upload_ts, upload.upload_filename,
upload.upload_desc, folder.folder_pk, folder.folder_name, pfile.pfile_size
FROM upload, folderlist, folder, pfile
  WHERE upload.user_fk=".pg_escape_string($userId)."
  AND folderlist.upload_pk=upload.upload_pk
  AND folderlist.upload_pk=".pg_escape_string($uploadId)."
  AND pfile.pfile_pk=folderlist.pfile_fk
";
    }

    $result = $this->dbManager->getRows($sql);
    $uploads = [];
    foreach ($result as $row)
    {
      $upload = new Upload($row["folder_pk"],$row["folder_name"],
        $row["upload_pk"], $row["upload_desc"], $row["upload_filename"],
        $row["upload_ts"],$row["pfile_size"]);
      array_push($uploads, $upload->getArray());
    }
    return $uploads;
  }

  /**
   * Get first upload name under a given upload tree id
   * @param integer $uploadTreePk Upload tree id to check.
   * @return string
   */
  public function getFilenameFromUploadTree($uploadTreePk)
  {
    return $this->dbManager->
    getSingleRow('SELECT DISTINCT ufile_name FROM uploadtree
WHERE uploadtree_pk='. pg_escape_string($uploadTreePk))["ufile_name"];
  }

  /**
   * Check if a given id exists under given table.
   * @param string $tableName Table name
   * @param string $idRowName ID column name
   * @param string $id        ID to check
   * @return boolean True if id exists, false otherwise
   */
  public function doesIdExist($tableName, $idRowName, $id)
  {
    return (0 < (intval($this->getDbManager()->getSingleRow("SELECT COUNT(*)
FROM $tableName WHERE $idRowName= ".pg_escape_string($id))["count"])));
  }

  /**
   * Delete the given user id
   * @param integer $id User id to be deleted
   */
  public function deleteUser($id)
  {
    require_once dirname(dirname(__DIR__)) . "/user-del-helper.php";
    DeleteUser($id, $this->getDbManager());
  }

  /**
   * Get the user under the given user id or every user from the database.
   * @param integer $id User id of the required user, or NULL to fetch all
   * users.
   * @return User[][] Users as an associative array
   */
  public function getUsers($id = NULL)
  {
    if($id == NULL)
    {
      $usersSQL = "SELECT user_pk, user_name, user_desc, user_email,
                  email_notify, root_folder_fk, user_perm, user_agent_list FROM users";
    }
    else
    {
      $usersSQL = "SELECT user_pk, user_name, user_desc, user_email,
                email_notify, root_folder_fk, user_perm, user_agent_list FROM users
                WHERE user_pk=" . pg_escape_string($id);
    }
    $users = [];
    $result = $result = $this->dbManager->getRows($usersSQL);
    foreach ($result as $row)
    {
      $user = new User($row["user_pk"], $row["user_name"], $row["user_desc"],
        $row["user_email"], $row["user_perm"],
        $row["root_folder_fk"], $row["email_notify"], $row["user_agent_list"]);
      $users[] = $user->getArray();
    }

    return $users;
  }

  /**
   * @brief Get the recent jobs.
   *
   * If a limit is passed, the results are trimmed. If an ID is passed, the
   * information for the given id is only retrieved.
   * @param integer $limit Set to limit the result length
   * @param integer $id    Set to get information of only given job id
   * @return Job[][] Jobs as an associative array
   */
  public function getJobs($limit = 0, $id = NULL)
  {
    if($id == NULL)
    {
      $jobSQL = "SELECT job_pk, job_queued, job_name, job_upload_fk, job_user_fk, job_group_fk FROM job";
    }
    else
    {
      $jobSQL = "SELECT job_pk, job_queued, job_name, job_upload_fk, job_user_fk, job_group_fk
                FROM job WHERE job_pk=". pg_escape_string($id);
    }

    if($limit > 0)
    {
      $jobSQL .= " LIMIT " . pg_escape_string($limit);
    }

    $jobs = [];
    $result = $result = $this->dbManager->getRows($jobSQL);
    foreach ($result as $row)
    {
      $job = new Job($row["job_pk"], $row["job_name"], $row["job_queued"],
        $row["job_upload_fk"], $row["job_user_fk"], $row["job_group_fk"]);
      $jobs[] = $job->getArray();
    }
    return $jobs;
  }

}
