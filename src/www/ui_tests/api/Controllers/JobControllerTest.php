<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Unit tests for JobController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Dao\JobDao;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\UI\Api\Controllers\JobController;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Job;
use Fossology\UI\Api\Models\JobQueue;
use Fossology\UI\Api\Models\User;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

require_once dirname(__DIR__, 4) . "/lib/php/Plugin/FO_Plugin.php";

/**
 * @class JobControllerTest
 * @brief Tests for JobController
 */
class JobControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var DbHelper $dbHelper
   * DB Helper mock
   */
  private $dbHelper;

  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var JobDao $jobDao
   * JobDao mock
   */
  private $jobDao;

  /**
   * @var ShowJobsDao $showJobsDao
   * ShowJobsDao mock
   */
  private $showJobsDao;

  /**
   * @var JobController $jobController
   * JobController object to test
   */
  private $jobController;

  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var StreamFactory $streamFactory
   * Stream factory to create body streams.
   */
  private $streamFactory;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->jobDao = M::mock(JobDao::class);
    $this->showJobsDao = M::mock(ShowJobsDao::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getJobDao')->andReturn($this->jobDao);
    $this->restHelper->shouldReceive('getShowJobDao')->andReturn($this->showJobsDao);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->jobController = new JobController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->streamFactory = new StreamFactory();
  }

  /**
   * @brief Remove test objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }


  /**
   * Helper function to get JSON array from response
   *
   * @param Response $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * Generate array of users
   * @param array $userIds User ids to be generated
   * @return array[]
   */
  private function getUsers($userIds)
  {
    $userArray = array();
    foreach ($userIds as $userId) {
      if ($userId == 2) {
        $accessLevel = PLUGIN_DB_ADMIN;
      } elseif ($userId > 2 && $userId <= 4) {
        $accessLevel = PLUGIN_DB_WRITE;
      } elseif ($userId == 5) {
        $accessLevel = PLUGIN_DB_READ;
      } else {
        continue;
      }
      $user = new User($userId, "user$userId", "User $userId",
        "user$userId@example.com", $accessLevel, 2, 4, "");
      $userArray[] = $user->getArray();
    }
    return $userArray;
  }

  /**
   * @test
   * -# Test JobController::getJobs() for all jobs
   * -# Check if response is 200
   */
  public function testGetJobs()
  {
    $job = new Job(11, "job_name", "01-01-2020", 4, 2, 2, 0, "Completed");
    $jobQueue = new JobQueue(44, 'readmeoss', '2020-01-01 20:41:49', '2020-01-01 20:41:50',
      'Completed', 0, null, [], 0, true, false, true,
      ['text' => 'ReadMeOss', 'link' => 'http://localhost/repo/api/v1/report/16']);
    $this->jobDao->shouldReceive('getChlidJobStatus')->withArgs(array(11))
      ->andReturn(['44' => 0]);
    $this->showJobsDao->shouldReceive('getEstimatedTime')
      ->withArgs(array(11, '', 0, 4))->andReturn("0");
    $this->showJobsDao->shouldReceive('getDataForASingleJob')
      ->withArgs(array(44))->andReturn(["jq_endtext"=>'Completed']);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $userId = 2;
    $user = $this->getUsers([$userId]);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->dbHelper->shouldReceive('getUserJobs')->withArgs(array(null, 2, 0, 1))
      ->andReturn([[$job], 1]);
    $actualResponse = $this->jobController->getJobs($request, $response, []);
    $expectedResponse = $job->getArray(ApiVersion::V1);
    $this->assertEquals(200, $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse,
      $this->getResponseJson($actualResponse)[0]);
    $this->assertEquals('1',
      $actualResponse->getHeaderLine('X-Total-Pages'));
  }

  /**
   * @test
   * -# Test JobController::getJobs() with limit and page set
   * -# Check if response is 200 and have correct total pages header
   */
  public function testGetJobsLimitPage()
  {
    $jobTwo = new Job(12, "job_two", "01-01-2020", 5, 2, 2, 0, "Completed");
    $jobTwoQueue = new JobQueue(45, 'readmeoss', '2020-01-01 20:41:49', '2020-01-01 20:41:50',
    'Completed', 0, null, [], 0, true, false, true,
    ['text' => 'ReadMeOss', 'link' => 'http://localhost/repo/api/v1/report/16']);
    $this->jobDao->shouldReceive('getChlidJobStatus')->withArgs(array(11))
      ->andReturn(['44' => 0]);
    $this->jobDao->shouldReceive('getChlidJobStatus')->withArgs(array(12))
      ->andReturn(['45' => 0]);
    $this->showJobsDao->shouldReceive('getEstimatedTime')
      ->withArgs(array(M::anyOf(11, 12), '', 0, M::anyOf(4, 5)))->andReturn("0");
    $this->showJobsDao->shouldReceive('getDataForASingleJob')
      ->withArgs([M::anyOf(44, 45)])->andReturn(["jq_endtext"=>'Completed']);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', '1');
    $requestHeaders->setHeader('page', '2');
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $userId = 2;
    $user = $this->getUsers([$userId]);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->dbHelper->shouldReceive('getUserJobs')->withArgs(array(null, 2, 1, 2))
    ->andReturn([[$jobTwo], 2]);
    $actualResponse = $this->jobController->getJobs($request, $response, []);
    $expectedResponse = $jobTwo->getArray(ApiVersion::V1);
    $this->assertEquals(200, $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse,
      $this->getResponseJson($actualResponse)[0]);
    $this->assertEquals('2',
      $actualResponse->getHeaderLine('X-Total-Pages'));
  }

  /**
   * @test
   * -# Test JobController::getJobs() with invalid job id
   * -# Check if response is 404
   */
  public function testGetInvalidJob()
  {
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["job", "job_pk", 2])->andReturn(false);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', '1');
    $requestHeaders->setHeader('page', '2');
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $userId = 2;
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $this->expectException(HttpNotFoundException::class);

    $this->jobController->getJobs($request, $response, ["id" => 2]);
  }

  /**
   * @test
   * -# Test JobController::getJobs() with single job id
   * -# Check if response is 200
   */
  public function testGetJobFromId()
  {
    $job = new Job(12, "job_two", "01-01-2020", 5, 2, 2, 0, "Completed");
    $jobTwoQueue = new JobQueue(45, 'readmeoss', '2020-01-01 20:41:49', '2020-01-01 20:41:50',
    'Completed', 0, null, [], 0, true, false, true,
    ['text' => 'ReadMeOss', 'link' => 'http://localhost/repo/api/v1/report/16']);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["job", "job_pk", 12])->andReturn(true);
    $this->dbHelper->shouldReceive('getJobs')->withArgs(array(12, 0, 1))
      ->andReturn([[$job], 1]);
    $this->jobDao->shouldReceive('getChlidJobStatus')->withArgs(array(12))
      ->andReturn(['45' => 0]);
    $this->showJobsDao->shouldReceive('getEstimatedTime')
      ->withArgs(array(12, '', 0, 5))->andReturn("0");
    $this->showJobsDao->shouldReceive('getDataForASingleJob')
      ->withArgs([45])->andReturn(["jq_endtext"=>'Completed']);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $userId = 2;
    $user = $this->getUsers([$userId]);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $actualResponse = $this->jobController->getJobs($request, $response, [
      "id" => 12]);
    $expectedResponse = $job->getArray(ApiVersion::V1);
    $this->assertEquals(200, $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse,
      $this->getResponseJson($actualResponse));
    $this->assertEquals('1',
      $actualResponse->getHeaderLine('X-Total-Pages'));
  }

  /**
   * @test
   * -# Test JobController::getJobs() with single upload
   * -# Check if response is 200
   */
  public function testGetJobsFromUpload()
  {
    $job = new Job(12, "job_two", "01-01-2020", 5, 2, 2, 0, "Completed");
    $jobTwoQueue = new JobQueue(45, 'readmeoss', '2020-01-01 20:41:49', '2020-01-01 20:41:50',
    'Completed', 0, null, [], 0, true, false, true,
    ['text' => 'ReadMeOss', 'link' => 'http://localhost/repo/api/v1/report/16']);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["upload", "upload_pk", 5])->andReturn(true);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['job', 'job_pk', 12])->andReturn(true);
    $this->dbHelper->shouldReceive('getJobs')->withArgs(array(null, 0, 1, 5))
      ->andReturn([[$job], 1]);
    $this->jobDao->shouldReceive('getChlidJobStatus')->withArgs(array(12))
      ->andReturn(['45' => 0]);
    $this->showJobsDao->shouldReceive('getEstimatedTime')
      ->withArgs(array(12, '', 0, 5))->andReturn("0");
    $this->showJobsDao->shouldReceive('getDataForASingleJob')
      ->withArgs([45])->andReturn(["jq_endtext"=>'Completed']);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams([JobController::UPLOAD_PARAM => 5]);
    $response = new ResponseHelper();
    $userId = 2;
    $user = $this->getUsers([$userId]);
    $this->restHelper->shouldReceive('getUserId')->andReturn($userId);
    $actualResponse = $this->jobController->getJobs($request, $response, []);
    $expectedResponse = $job->getArray(ApiVersion::V1);
    $this->assertEquals(200, $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse,
      $this->getResponseJson($actualResponse)[0]);
    $this->assertEquals('1',
      $actualResponse->getHeaderLine('X-Total-Pages'));
  }

  /**
   * @test
   * -# Test JobController::getUploadEtaInSeconds()
   * -# Test if HH:MM:SS can be translated to seconds
   * -# Test if empty response results in 0
   */
  public function testGetUploadEtaInSeconds()
  {
    $jobId = 11;
    $uploadId = 5;
    $completedJob = 5;
    $completedUpload = 3;
    $this->showJobsDao->shouldReceive('getEstimatedTime')
      ->withArgs([$jobId, '', 0, $uploadId])
      ->andReturn("3:10:23");
    $this->showJobsDao->shouldReceive('getEstimatedTime')
      ->withArgs([$completedJob, '', 0, $completedUpload])
      ->andReturn("0");
    $reflection = new \ReflectionClass(get_class($this->jobController));
    $method = $reflection->getMethod('getUploadEtaInSeconds');
    $method->setAccessible(true);

    $result = $method->invokeArgs($this->jobController, [$jobId, $uploadId]);
    $this->assertEquals((3 * 3600) + (10 * 60) + 23, $result);

    $result = $method->invokeArgs($this->jobController,
      [$completedJob, $completedUpload]);
    $this->assertEquals(0, $result);
  }

  /**
   * @test
   * -# Test JobController::getJobStatus()
   * -# Setup one job with two complete children => result Completed
   * -# Setup one job with one child processing and other in queue => result
   *    Processing
   * -# Setup one job with one child completed and one failed => result Failed
   */
  public function testGetJobStatus()
  {
    $jobCompleted = [1, 2];
    $jobQueued = [3, 4];
    $jobFailed = [5, 6];
    $this->showJobsDao->shouldReceive('getDataForASingleJob')
      ->withArgs([M::anyof(1, 2, 5)])
      ->andReturn(["jq_endtext" => "Completed"]);
    $this->showJobsDao->shouldReceive('getDataForASingleJob')
      ->withArgs([3])->andReturn(["jq_endtext" => "Started"]);
    $this->showJobsDao->shouldReceive('getDataForASingleJob')
      ->withArgs([4])->andReturn(["jq_endtext" => "Processing",
        "jq_endtime" => ""]);
    $this->showJobsDao->shouldReceive('getDataForASingleJob')
      ->withArgs([6])->andReturn(["jq_endtext" => "Failed",
        "jq_endtime" => "01-01-2020 00:00:00"]);

    $reflection = new \ReflectionClass(get_class($this->jobController));
    $method = $reflection->getMethod('getJobStatus');
    $method->setAccessible(true);

    $result = $method->invokeArgs($this->jobController, [$jobCompleted]);
    $this->assertEquals("Completed", $result);

    $result = $method->invokeArgs($this->jobController, [$jobQueued]);
    $this->assertEquals("Processing", $result);

    $result = $method->invokeArgs($this->jobController, [$jobFailed]);
    $this->assertEquals("Failed", $result);
  }
}
