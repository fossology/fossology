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

namespace www\ui\api\helper;

require_once dirname(dirname(dirname(__FILE__))) . "/api/models/Upload.php";
require_once dirname(dirname(dirname(__FILE__))) . "/api/models/User.php";
require_once dirname(dirname(dirname(__FILE__))) . "/api/models/Job.php";

use api\models\Info;
use Fossology\Lib\Db\ModernDbManager;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Fossology\Lib\Db\Driver\Postgres;
use api\models\Upload;
use www\ui\api\models\InfoType;
use www\ui\api\models\User;
use www\ui\api\models\Job;


class DbHelper
{
  private $dbManager;
  private $PG_CONN;


  /**
   * DbHelper constructor.
   */
  public function __construct()
  {
    $logLevel = Logger::DEBUG;
    $logger = new Logger(__FILE__);
    $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $logLevel));
    $this->dbManager = new ModernDbManager($logger);
    $this->PG_CONN = pg_connect("host=localhost port=5432 dbname=fossology user=fossy password=fossy")
    or die("Could not connect");
    $pgDriver = new Postgres($this->PG_CONN);
    $this->dbManager->setDriver($pgDriver);
  }

  /**
   * @return ModernDbManager
   */
  public function getDbManager()
  {
    return $this->dbManager;
  }

  /**
   * @return resource
   */
  public function getPGCONN()
  {
    return $this->PG_CONN;
  }

  public function getUploads($userId, $uploadId = NULL)
  {
    if($uploadId == NULL)
    {
      $sql = "SELECT DISTINCT upload.upload_pk, upload.upload_ts, upload.upload_filename, upload.upload_desc,folder.folder_pk, folder.folder_name, pfile.pfile_size
FROM upload, folderlist, folder, pfile
  WHERE upload.user_fk=".pg_escape_string($userId)."
  AND folderlist.upload_pk=upload.upload_pk
  AND pfile.pfile_pk=folderlist.pfile_fk
";
    }
    else
    {
      $sql = "SELECT DISTINCT upload.upload_pk, upload.upload_ts, upload.upload_filename, upload.upload_desc,folder.folder_pk, folder.folder_name, pfile.pfile_size
FROM upload, folderlist, folder, pfile
  WHERE upload.user_fk=".pg_escape_string($userId)."
  AND folderlist.upload_pk=upload.upload_pk
  AND folderlist.upload_pk=".pg_escape_string($uploadId)."
  AND pfile.pfile_pk=folderlist.pfile_fk
";
    }

    $result = pg_query($this->getPGCONN(), $sql);
    $uploads = [];
    while ($row = pg_fetch_assoc($result))
    {
      $upload = new Upload($row["folder_pk"],$row["folder_name"], $row["upload_pk"], $row["upload_desc"],
        $row["upload_filename"], $row["upload_ts"],$row["pfile_size"]);
      array_push($uploads, $upload);
    }
    pg_free_result($result);
    return $uploads;
  }

  /**
   * @param $uploadTreePk integer
   * @return string
   */
  public function getFilenameFromUploadTree($uploadTreePk)
  {
    return $this->dbManager->
    getSingleRow('SELECT DISTINCT ufile_name FROM uploadtree WHERE uploadtree_pk='. pg_escape_string($uploadTreePk))["ufile_name"];
  }

  public function doesIdExist($tableName, $idRowName, $id)
  {
    return (0 < (intval($this->getDbManager()->getSingleRow("SELECT COUNT(*) FROM $tableName WHERE $idRowName= ".pg_escape_string($id))["count"])));
  }

  public function deleteUser($id)
  {
    require_once "/usr/local/share/fossology/www/ui/user-del-helper.php";
    DeleteUser($id, $this->PG_CONN);
  }

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
                email_notify, root_folder_fk, user_perm, user_agent_list FROM users WHERE user_pk=" . pg_escape_string($id);
    }
    $users = [];
    $result = pg_query($this->getPGCONN(), $usersSQL);
    while ($row = pg_fetch_assoc($result))
    {
      $user = new User($row["user_pk"], $row["user_name"], $row["user_desc"],
        $row["user_email"], $row["user_perm"],
        $row["root_folder_fk"], $row["email_notify"], $row["user_agent_list"]);
      $users[] = $user->getJSON();
    }

    return json_encode($users, JSON_PRETTY_PRINT);
  }

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

    $result = pg_query($this->getPGCONN(), $jobSQL);
    $jobs = [];
    while ($row = pg_fetch_assoc($result))
    {
      $job = new Job($row["job_pk"], $row["job_name"], $row["job_queued"],
        $row["job_upload_fk"], $row["job_user_fk"], $row["job_group_fk"]);
      $jobs[] = $job->getJSON();
    }
    pg_free_result($result);
    return json_encode($jobs, JSON_PRETTY_PRINT);
  }

}
