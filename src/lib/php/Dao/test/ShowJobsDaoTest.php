<?php
/*
 SPDX-FileCopyrightText: © 2015 Siemens AG
 Author: Johannes Najjar, anupam.ghosh@siemens.com, Shaheem Azmal

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

class ShowJobsDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;
  /** @var ShowJobsDao */
  private $showJobsDao;
  /** @var Mock for UploadPermissionDao */
  private $uploadPermissionDao;

  private $job_pks = array(2,1);

  protected function setUp() : void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->testDb->createPlainTables(
        array(
            'upload',
            'uploadtree',
            'job',
            'perm_upload',
            'jobqueue',
            'jobdepends',
        ));
    $this->testDb->createInheritedTables(array('uploadtree_a'));

    $uploadArray = array(array('upload_pk'=>1, 'uploadtree_tablename'=>'uploadtree'),
        array('upload_pk'=>2, 'uploadtree_tablename'=>'uploadtree_a'));
    foreach ($uploadArray as $uploadEntry) {
      $this->dbManager->insertTableRow('upload', $uploadEntry);
    }

    $this->dbManager->prepare($stmt = 'insert.job',
        "INSERT INTO job (job_pk, job_queued, job_name, job_upload_fk, job_user_fk) VALUES ($1, $2, $3, $4, $5)");
    $jobArray = array(array(1,date('c',time()-5), "FCKeditor_2.6.4.zip", 1,1 ),
                      array(2,date('c'), "zlib_1.2.8.zip", 2,2));
    foreach ($jobArray as $uploadEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

    $logger = M::mock('Monolog\Logger');
    $logger->shouldReceive('debug');
    $this->uploadPermissionDao = M::mock('Fossology\Lib\Dao\UploadPermissionDao');
    $this->uploadDao = new UploadDao($this->dbManager, $logger, $this->uploadPermissionDao);
    $this->showJobsDao = new ShowJobsDao($this->dbManager, $this->uploadDao);

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }



  public function testUploads2Jobs()
  {
    $jobs = array(3=>2, 4=>3, 5=>5, 6=>8%6, 7=>13%6, 8=>21%6);
    foreach ($jobs as $jobId => $jobUpload) {
      $this->dbManager->insertTableRow('job', array('job_pk' => $jobId, 'job_upload_fk' => $jobUpload));
    }
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $showJobDao = new ShowJobsDao($this->dbManager,$uploadDao);
    $jobsWithoutUpload = $showJobDao->uploads2Jobs(array());
    assertThat($jobsWithoutUpload, is(emptyArray()));
    $jobsWithUploadIdOne = $showJobDao->uploads2Jobs(array(1));
    assertThat($jobsWithUploadIdOne, equalTo(array(array(1,7),0)));
    $jobsAtAll = $showJobDao->uploads2Jobs(array(1,2,3,4,5));
    assertThat($jobsAtAll, equalTo(array(array(1,7, 2,3,6, 4,8, 5),0)));
    $jobsWithUploadFour = $showJobDao->uploads2Jobs(array(4));
    assertThat($jobsWithUploadFour[0], is(emptyArray()));
  }

  public function testUploads2JobsPaged()
  {
    $jobs = array_combine(range(3,13),range(3,13));
    foreach ($jobs as $jobId => $jobUpload) {
      $this->dbManager->insertTableRow('job', array('job_pk' => $jobId, 'job_upload_fk' => $jobUpload));
    }
    $uploadDao = M::mock('Fossology\Lib\Dao\UploadDao');
    $showJobDao = new ShowJobsDao($this->dbManager,$uploadDao);

    $jobsPage1 = $showJobDao->uploads2Jobs(range(1,17),0);
    assertThat($jobsPage1[0], arrayWithSize(10));
    assertThat($jobsPage1[1], is(1));
    $jobsPage2 = $showJobDao->uploads2Jobs(array_combine(range(10,16),range(11,17)),1);
    assertThat($jobsPage2[0], arrayWithSize(3));
    assertThat($jobsPage2[1], is(0));
    $jobsPage3 = $showJobDao->uploads2Jobs(array(),2);
    assertThat($jobsPage3, arrayWithSize(0));
  }


  public function testgetJobName()
  {
    $testJobName = $this->showJobsDao->getJobName(1);
    assertThat($testJobName, equalTo("FCKeditor_2.6.4.zip"));

    $testJobNameIfNothingQueued = $this->showJobsDao->getJobName($uploadId = 3);
    assertThat($testJobNameIfNothingQueued, equalTo($uploadId));
  }

  public function testMyJobs()
  {
    $groupId = 2;
    $GLOBALS['SysConf']['auth'][Auth::GROUP_ID] = $groupId;
    $GLOBALS['SysConf']['auth'][Auth::USER_ID] = 1;

    $this->uploadPermissionDao->shouldReceive('isAccessible')->withArgs(array(anything(),$groupId))
            ->andReturnUsing(function($upload,$group)
            {
              return ($upload==1 || $upload==2 || $upload==4);
            });
    $testOurJobs = $this->showJobsDao->myJobs(true);
    assertThat($testOurJobs[0], is(arrayContainingInAnyOrder($this->job_pks)));
    $testMyJobs = $this->showJobsDao->myJobs(false);
    assertThat($testMyJobs, equalTo(array(array(1), 0)));

    $this->dbManager->queryOnce("UPDATE job SET job_queued=job_queued-INTERVAL '30 days' WHERE job_pk=1");
    $this->dbManager->prepare(__METHOD__.'insert.perm_upload',
      "INSERT INTO perm_upload (perm_upload_pk, perm, upload_fk, group_fk) VALUES ($1, $2, $3, $4)");
    $testOutdatedJobs = $this->showJobsDao->myJobs(true);
    assertThat($testOutdatedJobs, equalTo(array(array(2), 0)));
  }

  public function testgetNumItemsPerSec()
  {
    $numSecs = 30;
    $testFilesPerSec = $this->showJobsDao->getNumItemsPerSec(5*$numSecs, $numSecs);
    assertThat($testFilesPerSec,is(greaterThan(1)));

    $testFilesPerSec = $this->showJobsDao->getNumItemsPerSec(0.9*$numSecs, $numSecs);
    assertThat($testFilesPerSec,is(lessThanOrEqualTo(1)));
  }

  public function testGetJobInfo()
  {
    $this->dbManager->prepare($stmt = 'insert.jobqueue',
       "INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed)"
     . "VALUES ($1, $2, $3, $4,$5, $6,$7,$8,$9,$10)");

    $nowTime = time();
    $diffTime = 2345;
    $nomosTime = date('Y-m-d H:i:sO',$nowTime-$diffTime);
    $uploadArrayQue = array(array(8, $jobId=1, "nomos", 1,$nomosTime,null ,"Started", 0,"localhost.5963", $itemNomos=147),
                           array(1, $jobId, "ununpack", 1, "2015-04-21 18:29:19.23825+05:30", "2015-04-21 18:29:26.396562+05:30", "Completed",1,null,$itemCount=646 ));
    foreach ($uploadArrayQue as $uploadEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

    $this->dbManager->prepare($stmt = 'insert.uploadtree_a',
            "INSERT INTO uploadtree_a (uploadtree_pk, parent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name)"
         . "VALUES ($1, $2, $3, $4,$5, $6, $7, $8)");
    $uploadTreeArray = array(array(123, 121, 1, 103, 32768, 542, 543, "fckeditorcode_ie.js"),
                             array(121,120, 1, 0, 536888320, 537, 544, "js"),
                             array(715,651, 2,607 ,33188 ,534 ,535 ,"zconf.h.cmakein"),
                             array(915, 651, 2, 606 ,33188 ,532 ,533 ,"zconf.h"),
                          );
    foreach ($uploadTreeArray as $uploadEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

    $this->dbManager->prepare($stmt = 'insert.jobdepends',
        "INSERT INTO jobdepends (jdep_jq_fk, jdep_jq_depends_fk) VALUES ($1, $2 )");
    $jqWithTwoDependencies = 8;
    $jobDependsArray = array(array(2,1),
                             array(3,2),
                             array(4,2),
                             array(5,2),
                             array(6,2),
                             array($jqWithTwoDependencies,4),
                             array($jqWithTwoDependencies,4),
                          );
    foreach ($jobDependsArray as $uploadEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

    $testMyJobInfo = $this->showJobsDao->getJobInfo($this->job_pks);
    assertThat($testMyJobInfo,hasKey($jobId));
    assertThat($testMyJobInfo[$jobId]['jobqueue'][$jqWithTwoDependencies]['depends'], is(arrayWithSize(2)));

    $testFilesPerSec = 0.23;
    $formattedEstimatedTime = $this->showJobsDao->getEstimatedTime($job_pk=1, $jq_Type="nomos", $testFilesPerSec);
    assertThat($formattedEstimatedTime, matchesPattern ('/\\d+:\\d{2}:\\d{2}/'));
    $hourMinSec = explode(':', $formattedEstimatedTime);
    assertThat($hourMinSec[0]*3600+$hourMinSec[1]*60+$hourMinSec[2],
            is(closeTo(($itemCount-$itemNomos)/$testFilesPerSec,0.5+$testFilesPerSec)));

    $testGetEstimatedTime = $this->showJobsDao->getEstimatedTime($job_pk=1, $jq_Type, 0);
    assertThat($testGetEstimatedTime, matchesPattern ('/\\d+:\\d{2}:\\d{2}/'));
    $hourMinSec = explode(':', $testGetEstimatedTime);
    $tolerance = 0.5+($itemCount-$itemNomos)/$itemNomos+(time()-$nowTime);
    assertThat($hourMinSec[0]*3600+$hourMinSec[1]*60+$hourMinSec[2],
            is(closeTo(($itemCount-$itemNomos)/$itemNomos*$diffTime,$tolerance)));

    $fewFilesPerSec = 0.003;
    $formattedLongTime = $this->showJobsDao->getEstimatedTime($job_pk=1, $jq_Type="nomos", $fewFilesPerSec);
    assertThat($formattedLongTime, matchesPattern ('/\\d+:\\d{2}:\\d{2}/'));
    $hourMinSec = explode(':', $formattedLongTime);
    assertThat($hourMinSec[0]*3600+$hourMinSec[1]*60+$hourMinSec[2],
            is(closeTo(($itemCount-$itemNomos)/$fewFilesPerSec,0.5+$fewFilesPerSec)));
  }

  public function testGetEstimatedTimeShouldNotDivideByZero()
  {
    $this->dbManager->prepare($stmt = 'insert.jobqueue',
       "INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed)"
     . "VALUES ($1, $2, $3, $4,$5, $6,$7,$8,$9,$10)");

    $nowTime = time();
    $diffTime = 2345;
    $nomosTime = date('Y-m-d H:i:sO',$nowTime-$diffTime);
    $uploadArrayQue = array(array(8, $jobId=1, $jqType="nomos", 1,$nomosTime,null ,"Started", 0,"localhost.5963", $itemNomos=147),
                           array(1, $jobId, "ununpack", 1, "2015-04-21 18:29:19.23825+05:30", "2015-04-21 18:29:26.396562+05:30", "Completed",1,null,$itemCount=646 ));
    foreach ($uploadArrayQue as $uploadEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

    $showJobsDaoMock = M::mock('Fossology\\Lib\\Dao\\ShowJobsDao[getNumItemsPerSec]',array($this->dbManager, $this->uploadDao));
    $showJobsDaoMock->shouldReceive('getNumItemsPerSec')->andReturn(0);

    $estimated = $showJobsDaoMock->getEstimatedTime($jobId, $jqType);
    assertThat($estimated,  equalTo('0:00:00'));
  }
  private function insertJobQue($jobQueueEndBits = 2)
  {
    $jobs = array(3=>2, 4=>3, 5=>5, 6=>8%6, 7=>13%6, 8=>21%6);
    foreach ($jobs as $jobId => $jobUpload) {
      $this->dbManager->insertTableRow('job', array('job_pk' => $jobId, 'job_upload_fk' => $jobUpload));
    }
    $this->dbManager->prepare($stmt = 'insert.jobqueue',
      "INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed)"
      . "VALUES ($1, $2, $3, $4,$5, $6,$7,$8,$9,$10)");

    $nowTime = time();
    $diffTime = 2345;
    $nomosTime = date('Y-m-d H:i:sO',$nowTime-$diffTime);
    $uploadArrayQue = array(array(8, $jobId=1, $jqType="nomos", 1,$nomosTime,null ,"Started", 0,"localhost.5963", $itemNomos=147),
      array(1, $jobId, "ununpack", 1, "2015-04-21 18:29:19.23825+05:30", "2015-04-21 18:29:26.396562+05:30", "Completed",$jobQueueEndBits,null,$itemCount=646 ));
    foreach ($uploadArrayQue as $uploadEntry) {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }
  }
  public function testGetJobsForAll()
  {
    $this->insertJobQue();
    $result = $this->showJobsDao->getJobsForAll();
    $this->assertNotNull($result);
    $this->assertEquals(array(array('job' => 'nomos', 'jq_job_fk' => "1","upload_fk" => "1", "status" => "running")),$result);
  }
  public function testGetItemsProcessedForDecider()
  {
    $this->insertJobQue();
    $result = $this->showJobsDao->getItemsProcessedForDecider("nomos",1);
    $expectedArray = array("147","1");
    $this->assertNotNull($result);
    $this->assertEquals($expectedArray,$result);
  }
  public function testGetJobStatusFalse()
  {
    $this->insertJobQue();
    $jobStatus = $this->showJobsDao->getJobStatus(1);
    $this->assertNotNull($jobStatus);
    $this->assertFalse($jobStatus);
  }
  public function testGetJobStatusTrue()
  {
    $this->insertJobQue(4);
    $jobStatus = $this->showJobsDao->getJobStatus(1);
    $this->assertNotNull($jobStatus);
    $this->assertTrue($jobStatus);
  }
  public function testGetDataASingleJob()
  {
    $this->insertJobQue();
    $result = $this->showJobsDao->getDataForASingleJob(1);
    $this->assertNotNull($result);
    $expectedData = array(
      'jq_pk' => "1",
      'jq_job_fk' => "1",
      'jq_type' => "ununpack",
      'jq_args' => "1",
      'jq_starttime' => $result['jq_starttime'],
      'jq_endtime' => $result['jq_endtime'],
      'jq_endtext' => "Completed",
      'jq_end_bits' => "2",
      'jq_schedinfo' => NULL,
      'jq_itemsprocessed' => "646",
      'jq_log' => NULL,
      'jq_runonpfile' => NULL,
      'jq_host' => NULL,
      'jq_cmd_args' => NULL,
      'job_pk' => "1",
      'job_queued' => $result['job_queued'],
      'job_priority' => NULL,
      'job_email_notify' => NULL,
      'job_name' => "FCKeditor_2.6.4.zip",
      'job_upload_fk' => "1",
      'job_folder_fk' => NULL,
      'job_user_fk' => "1",
      'job_group_fk' => NULL,
      'elapsed' => "00:00:07.158312"
    );
    $this->assertEquals($expectedData,$result);
  }
}
