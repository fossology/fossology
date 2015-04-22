<?php
/*
Copyright (C) 2015, Siemens AG
Author: Johannes Najjar, anupam.ghosh@siemens.com, Shaheem Azmal 

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

use Fossology\Lib\Data\Tree\Item;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

class ShowJobsDaoTest extends \PHPUnit_Framework_TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;
	
	private $showJobDao;

	private $testFilesPerSec;


  private $job_pks = array(2,1);

  public function setUp()
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();

    $this->testDb->createPlainTables(
        array(
            'upload',
            'uploadtree',
            'uploadtree_a',
            'job',
            'perm_upload',
            'jobqueue',
            'jobdepends',
        ));

    $this->dbManager->prepare($stmt = 'insert.upload',
        "INSERT INTO upload (upload_pk, uploadtree_tablename) VALUES ($1, $2)");
		$uploadArray = array(array(1, 'uploadtree'), array(2, 'uploadtree_a'));
    foreach ($uploadArray as $uploadEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

    $this->dbManager->prepare($stmt = 'insert.job',
        "INSERT INTO job (job_pk, job_queued, job_name, job_upload_fk, job_user_fk) VALUES ($1, $2, $3, $4, $5)");
		$jobArray = array(array(1, "2015-04-21 18:29:19.16051+05:30", "FCKeditor_2.6.4.zip", 1,2 ),
		                array(2,"2015-04-21 20:29:19.16051+05:30", "zlib_1.2.8.zip", 2,2));
    foreach ($jobArray as $uploadEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

		$logger = M::mock('Monolog\Logger'); // new Logger("UploadDaoTest");
    $logger->shouldReceive('debug');
    $this->uploadDao = new UploadDao($this->dbManager, $logger);
    $this->showJobDao = new ShowJobsDao($this->dbManager, $this->uploadDao);
    
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  public function tearDown()
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount()-$this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }
  

  public function testUpload2jobs()
  { 
    $upload_pks = 1;
	
    $testUploadJobs = $this->showJobDao->uploads2Jobs($upload_pks, $page=0);
    $this->assertNotNull($testUploadJobs);
		
    $testUploadJobs = $this->showJobDao->uploads2Jobs($upload_pks, $page=1);
    $this->assertNotNull($testUploadJobs);
  }

  public function testgetJobName()
  {
    $uploadId = 1;
    $testJobName = $this->showJobDao->getJobName($uploadId);
    $this->assertEquals("$testJobName", "FCKeditor_2.6.4.zip");

    $testJobName = $this->showJobDao->getJobName($uploadId = 3);
    $this->assertEquals("$testJobName", 3);
  }
  
  public function testMyJobs()
  {
    $this->dbManager->prepare($stmt = 'insert.perm_upload',
      "INSERT INTO perm_upload (perm_upload_pk, perm, upload_fk, group_fk) VALUES ($1, $2, $3, $4)");
    $uploadArrayPerm = array(array(1, 10, 1,2 ),
                             array(2,10, 2,2));
    foreach ($uploadArrayPerm as $uploadEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }
    
    $this->job_pks = array(2,1);
    $testMyJobs = $this->showJobDao->myJobs($allusers=1);
    $this->assertEquals($testMyJobs,$this->job_pks );
  }
  public function testgetNumItemsPerSec()
  {
    $itemsprocessed = 2000;
    $numSecs =30;
    $this->testFilesPerSec = $this->showJobDao->getNumItemsPerSec($itemsprocessed, $numSecs);
    $this->assertNotNull($this->testFilesPerSec);
    
    $itemsprocessed = 15;
    $numSecs =30;
    $this->testFilesPerSec = $this->showJobDao->getNumItemsPerSec($itemsprocessed, $numSecs);
    $this->assertNotNull($this->testFilesPerSec);
    return $this->testFilesPerSec;
  }

  public function testGetJobInfo()
  {
    $this->dbManager->prepare($stmt = 'insert.jobqueue',
       "INSERT INTO jobqueue (jq_pk, jq_job_fk, jq_type, jq_args, jq_starttime, jq_endtime, jq_endtext, jq_end_bits, jq_schedinfo, jq_itemsprocessed)"
	. "VALUES ($1, $2, $3, $4,$5, $6,$7,$8,$9,$10)");
    $uploadArrayQue = array(array(8, 1, "nomos", 1,"2015-04-21 18:29:29.0594+05:30",null ,"Started", 0,"localhost.5963", 547),
	                    array(1, 1, "ununpack", 1, "2015-04-21 18:29:19.23825+05:30", "2015-04-21 18:29:26.396562+05:30", "Completed",1,null,646 ));
    foreach ($uploadArrayQue as $uploadEntry)
    {
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
    foreach ($uploadTreeArray as $uploadEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

    $this->dbManager->prepare($stmt = 'insert.jobdepends',
	"INSERT INTO jobdepends (jdep_jq_fk, jdep_jq_depends_fk) VALUES ($1, $2 )");
    $jobDependsArray = array(array(2,1),
                             array(3,2),
                             array(4,2),
                             array(5,2),
                             array(6,2),
                             array(7,2),
                             array(8,2),
                          );
    foreach ($jobDependsArray as $uploadEntry)
    {
      $this->dbManager->freeResult($this->dbManager->execute($stmt, $uploadEntry));
    }

    $testMyJobInfo = $this->showJobDao->getJobInfo($this->job_pks, $page=0);
    $this->assertNotNull($testMyJobInfo);

    $testGetEstimatedTime = $this->showJobDao->getEstimatedTime($job_pk=1, $jq_Type="nomos", $this->testFilesPerSec); 
    $this->assertNotNull($testGetEstimatedTime);

    $testGetEstimatedTime = $this->showJobDao->getEstimatedTime($job_pk=1, $jq_Type=null, $this->testFilesPerSec);
    $this->assertNotNull($testGetEstimatedTime);
  }
}
