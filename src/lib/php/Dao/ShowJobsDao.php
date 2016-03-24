<?php
/*
 Copyright (C) 2015, Siemens AG
 Author: Shaheem Azmal<shaheem.azmal@siemens.com>, 
         Anupam Ghosh <anupam.ghosh@siemens.com>

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
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Fossology\Lib\Auth\Auth;
use Monolog\Logger;

class ShowJobsDao extends Object
{
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;
  /** @var Logger */
  private $logger;
  /** @var maxUploadsPerPage */
  private $maxUploadsPerPage = 10; /* max number of uploads to display on a page */
  /** @var nhours */
  private $nhours = 672;  /* 672=24*28 (4 weeks) What is considered a recent number of hours for "My Recent Jobs" */

  function __construct(DbManager $dbManager, UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->uploadDao = $uploadDao;
    $this->logger = new Logger(self::className());
  }

  /**
   * @brief Find all the jobs for a given set of uploads.
   *
   * @param $upload_pks Array of upload_pk's
   * @param $page Get data for this display page.  Starts with zero.
   *
   * @return array of job_pk's for the uploads
   **/
  function uploads2Jobs($upload_pks, $page=0)
  {
    $jobArray = array();
    $jobCount = count($upload_pks);
    if ($jobCount == 0) {
      return $jobArray;
    }

    /* calculate index of starting upload_pk */
    $offset = empty($page) ? 0 : $page * $this->maxUploadsPerPage;

    /* Get the job_pk's for each for each upload_pk */
    $lastOffset = ($jobCount < $this->maxUploadsPerPage) ? $offset+$jobCount : $this->maxUploadsPerPage;
    $statementName = __METHOD__."upload_pkforjob";
    $this->dbManager->prepare($statementName, "SELECT job_pk FROM job WHERE job_upload_fk=$1 ORDER BY job_pk ASC");
    for(; $offset < $lastOffset; $offset++){
      $upload_pk = $upload_pks[$offset];

      $result = $this->dbManager->execute($statementName, array($upload_pk));
      while ($row = $this->dbManager->fetchArray($result)) {
        $jobArray[] = $row['job_pk'];
      }
      $this->dbManager->freeResult($result);
    }
    return $jobArray;
  }  /* uploads2Jobs() */

  /** 
   * @brief Return job name.  Used for deleted jobs
   * @param upload_pk
   * @return Original job name in job record.
   */
  public function getJobName($uploadId)
  {
    $statementName = __METHOD__."forjob_name";
    /* upload has been deleted so try to get the job name from the original upload job record */
    $row = $this->dbManager->getSingleRow(
           "SELECT job_name FROM job WHERE job_upload_fk= $1 ORDER BY job_pk ASC",
           array($uploadId),
           $statementName
    );
    return (empty($row['job_name']) ? $uploadId : $row['job_name']);
  } /* getJobName */

  /**
   * @brief Find all of my jobs submitted within the last n hours.
   *
   * @param $allusers
   *
   * @return array of job_pk's 
   **/
  public function myJobs($allusers)
  {
    $jobArray = array();

    $allusers_str = ($allusers == 0) ? "job_user_fk='".Auth::getUserId()."' and " : $allusers_str = "";
    
    $statementName = __METHOD__."$allusers_str";
    $this->dbManager->prepare($statementName,
    "SELECT job_pk, job_upload_fk FROM job WHERE $allusers_str job_queued >= (now() - interval '".$this->nhours." hours') ORDER BY job_queued DESC");
    $result = $this->dbManager->execute($statementName);
    while ($row = $this->dbManager->fetchArray($result)) {
      if (!empty($row['job_upload_fk'])) {
        $uploadIsAccessible = $this->uploadDao->isAccessible($row['job_upload_fk'], Auth::getGroupId());
        if (!$uploadIsAccessible) {
          continue;
        }
      }
      $jobArray[] = $row['job_pk'];
    }
    $this->dbManager->freeResult($result);

    return $jobArray;
  }  /* myJobs() */

  /**
   * @brief Get job queue data from db.
   *
   * @param $job_pks Array of $job_pk's to display.
   * @param $page Get data for this display page. Starts with zero.
   *
   * @return array of job data
   * \code
   * JobData [job_pk] Array of job records (JobRec)
   *
   * JobRec['jobqueue'][jq_pk] = array of JobQueue records
   * JobRec['jobqueue'][jq_pk]['depends'] = array of jq_pk's for dependencies
   * JobRec['upload'] = array for upload record
   * JobRec['job'] = array for job record
   * JobRec['uploadtree'] = array for parent uploadtree record
   *
   * JobQueue ['jq_pk'] = jq_pk
   * JobQueue ['jq_type'] = jq_type
   * JobQueue ['jq_itemsprocessed'] = jq_itemsprocessed
   * JobQueue ['jq_starttime'] = jq_starttime
   * JobQueue ['jq_endtime'] = jq_endtime
   * JobQueue ['jq_log'] = jq_log
   * JobQueue ['jq_endtext'] = jq_endtext
   * JobQueue ['jq_end_bits'] = jq_end_bits
   * \endcode
  **/
  public function getJobInfo($job_pks, $page=0)
  {
    /* Output data array */
    $jobData = array();
    foreach($job_pks as $job_pk){
      /* Get job table data */
      $statementName = __METHOD__."JobRec";
      $jobRec = $this->dbManager->getSingleRow(
      "SELECT * FROM job WHERE job_pk= $1",
      array($job_pk),
      $statementName
      );
      $jobData[$job_pk]["job"] = $jobRec;
      if(!empty($jobRec["job_upload_fk"])){
        $upload_pk = $jobRec["job_upload_fk"];
        /* Get Upload record for job */
        $statementName = __METHOD__."UploadRec";
        $uploadRec = $this->dbManager->getSingleRow(
        "SELECT * FROM upload WHERE upload_pk= $1",
        array($upload_pk),
        $statementName
        );
        if(!empty($uploadRec)){
          $jobData[$job_pk]["upload"] = $uploadRec;
          /* Get Upload record for uploadtree */
          $uploadtree_tablename = $uploadRec["uploadtree_tablename"];
          $statementName = __METHOD__."uploadtreeRec";
          $uploadtreeRec = $this->dbManager->getSingleRow(
          "SELECT * FROM $uploadtree_tablename where upload_fk = $1 and parent is null",
          array($upload_pk),
          $statementName
          );
          $jobData[$job_pk]["uploadtree"] = $uploadtreeRec;
        }else{
          $statementName = __METHOD__."uploadRecord";
          $uploadRec = $this->dbManager->getSingleRow(
          "SELECT * FROM upload right join job on upload_pk = job_upload_fk where job_upload_fk = $1",
          array($upload_pk),
          $statementName
          );
          /* upload has been deleted so try to get the job name from the original upload job record */
          $jobName = $this->getJobName($uploadRec["job_upload_fk"]);
          $uploadRec["upload_filename"] = "Deleted Upload: " . $uploadRec["job_upload_fk"] . "(" . $jobName . ")";
          $uploadRec["upload_pk"] = $uploadRec["job_upload_fk"];
          $jobData[$job_pk]["upload"] = $uploadRec;
        }
      }
      /* Get jobqueue table data */
      $statementName = __METHOD__."job_pkforjob";
      $this->dbManager->prepare($statementName,
      "SELECT jq.*,jd.jdep_jq_depends_fk FROM jobqueue jq LEFT OUTER JOIN jobdepends jd ON jq.jq_pk=jd.jdep_jq_fk WHERE jq.jq_job_fk=$1 ORDER BY jq_pk ASC");
      $result = $this->dbManager->execute($statementName, array($job_pk));
      $rows = $this->dbManager->fetchAll($result);
      if (!empty($rows)){
        foreach($rows as $jobQueueRec){
          $jq_pk = $jobQueueRec["jq_pk"];
          if (array_key_exists($job_pk,$jobData) && array_key_exists('jobqueue',$jobData[$job_pk]) && array_key_exists($jq_pk,$jobData[$job_pk]['jobqueue'])) {
            $jobData[$job_pk]['jobqueue'][$jq_pk]["depends"][] = $jobQueueRec["jdep_jq_depends_fk"];
          }
          else
          {
            $jobQueueRec["depends"] = array($jobQueueRec["jdep_jq_depends_fk"]);
            $jobData[$job_pk]['jobqueue'][$jq_pk] = $jobQueueRec;
          }
        }
      }else{
        unset($jobData[$job_pk]);
      }  
      $this->dbManager->freeResult($result);
    }
    return $jobData;
  } /* getJobInfo() */

  /**
   * @brief Returns Number of files/items processed per sec
   * @param int $itemsprocessed
   * @param int $numSecs
   * @return double
   **/
  public function getNumItemsPerSec($itemsprocessed, $numSecs)
  {
    $filesPerSec = ($numSecs > 0) ? $itemsprocessed/$numSecs : 0;
    return $filesPerSec;
  }

  /**
   * @brief Returns Estimated time using jobid
   * @param int $job_pk
   * @param string $jq_Type
   * @param float $filesPerSec
   * @param int $uploadId 
   * @return Returns empty if estimated time is 0 else returns time. 
   **/
  public function getEstimatedTime($job_pk, $jq_Type='', $filesPerSec=0, $uploadId=0)
  {
    if(!empty($uploadId)) {
      $itemCount = $this->dbManager->getSingleRow(
          "SELECT jq_itemsprocessed FROM jobqueue INNER JOIN job ON jq_job_fk=job_pk "
                  . " WHERE jq_type LIKE 'ununpack' AND jq_end_bits ='1' AND job_upload_fk=$1",
          array($uploadId),
          __METHOD__.'.ununpack_might_be_in_other_job'
          );
    }
    else {
      $itemCount = $this->dbManager->getSingleRow(
      "SELECT jq_itemsprocessed FROM jobqueue WHERE jq_type LIKE 'ununpack' AND jq_end_bits ='1' AND jq_job_fk =$1",
      array($job_pk),
      __METHOD__.'.ununpack_must_be_in_this_job'
      );
    }

    if(!empty($itemCount['jq_itemsprocessed'])){

      $selectCol = "jq_type, jq_endtime, jq_starttime, jq_itemsprocessed"; 
      if(empty($jq_Type)){ 
        $removeType = "jq_type NOT LIKE 'ununpack' AND jq_type NOT LIKE 'reportgen' AND jq_type NOT LIKE 'decider' AND";
        /* get starttime endtime and jobtype form jobqueue for a jobid except $removeType */
        $statementName = __METHOD__."$selectCol.$removeType";
        $this->dbManager->prepare($statementName,
        "SELECT $selectCol FROM jobqueue WHERE $removeType jq_job_fk =$1 ORDER BY jq_type DESC");
        $result = $this->dbManager->execute($statementName, array($job_pk));
      } else {
        $statementName = __METHOD__."$selectCol.$jq_Type";
        $this->dbManager->prepare($statementName,
        "SELECT $selectCol FROM jobqueue WHERE jq_type LIKE '$jq_Type' AND jq_job_fk =$1");
        $result = $this->dbManager->execute($statementName, array($job_pk));
      }
      $estimatedArray = array(); // estimate time for each agent

      while($row = $this->dbManager->fetchArray($result)){
        $timeOfCompletion = 0;
        if(empty($row['jq_endtime']) && !empty($row['jq_starttime'])) { // for agent started and not ended 
          if(empty($filesPerSec)) {
            $burnTime = time() - strtotime($row['jq_starttime']);
            $filesPerSec = $this->getNumItemsPerSec($row['jq_itemsprocessed'], $burnTime);
          }

          if(!empty($filesPerSec)) {           
            $timeOfCompletion = ($itemCount['jq_itemsprocessed'] - $row['jq_itemsprocessed']) / $filesPerSec;
          }
          array_push($estimatedArray, $timeOfCompletion);
        }
      }
      if(empty($estimatedArray)) {
        return "";
      }
      else {
        $estimatedTime = round(max($estimatedArray)); // collecting max agent time in seconds
        return intval($estimatedTime/3600).gmdate(":i:s", $estimatedTime);  // convert seconds to time and return     
      } 
    } 
  }/* getEstimatedTime() */

  /** 
   * @brief Return total Job data with time elapsed
   * @param $job_pk
   * @return $row
   */
  public function getDataForASingleJob($job_pk)
  {
    $statementName = __METHOD__."getDataForASingleJob";
    $this->dbManager->prepare($statementName,
    "SELECT *, jq_endtime-jq_starttime as elapsed FROM jobqueue LEFT JOIN job ON job.job_pk = jobqueue.jq_job_fk WHERE jobqueue.jq_pk =$1");
    $result = $this->dbManager->execute($statementName, array($job_pk));
    $row = $this->dbManager->fetchArray($result);
    $this->dbManager->freeResult($result);
    return $row;
  } /* getDataForASingleJob */
}
