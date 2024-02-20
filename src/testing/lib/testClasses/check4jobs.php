<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Are there any jobs running?
 *
 * usage: $ck4j = new check4jobs();
 *        $count = $ck4j->getJobCount;
 *        $count  = $ck4j->Check;
 *
 * NOTE: this program depends on the UI testing infrastructure at this
 * point.
 * @return boolean (TRUE or FALSE)
 *
 * @version "$Id: $"
 *
 * @TODO add method documentation
 * @TODO create a subclass for doing longer waits (CheckandWait?)
 *
 * Created on Jan. 15, 2009
 */

require_once (TESTROOT . '/testClasses/db.php');

define('SQL', "SELECT *
          FROM jobqueue
          INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
          LEFT OUTER JOIN upload ON upload_pk = job.job_upload_fk
          LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
          WHERE (jobqueue.jq_starttime IS NULL OR jobqueue.jq_endtime IS
          NULL OR jobqueue.jq_end_bits > 1)
          ORDER BY upload_filename,upload.upload_pk,job.job_pk,jobqueue.jq_pk," .
          "jobdepends.jdep_jq_fk;");

class check4jobs {

  protected $jobCount=NULL;
  private $Db;

  function __construct() {
    /*
     * always use the installed root user for the db.
     */
    if(file_exists('/etc/fossology/Db.conf')) {
      $options = file_get_contents('/etc/fossology/Db.conf');
    }
    else if (file_exists('/usr/local/etc/fossology/Db.conf')) {
      $options = file_get_contents('/usr/local/etc/fossology/Db.conf');
    }
    else {
      return(FALSE);
    }
    $this->Db = new db($options);
    $connection = $this->Db->connect();
    if (!(is_resource($connection))) {
      print "check4jobs:FATAL ERROR!, could not connect to the data-base\n";
      return(FALSE);
    }
    $this->_ck4j();
    return;
  }

  public function Check() {
    $this->_ck4j();
    return($this->jobCount);
  }
  private function _ck4j() {
    $results = $this->Db->dbQuery(SQL);
    $howMany = count($results);
    $this->jobCount = $howMany;
    return;
  }

  public function getJobCount() {
    return($this->jobCount);
  }
} // check4jobs
