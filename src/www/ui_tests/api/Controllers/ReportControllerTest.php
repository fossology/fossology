<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Tests for ReportController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Mockery as M;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\ReportController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Slim\Http\Body;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Uri;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @class ReportControllerTest
 * @brief Tests for ReportController
 */
class ReportControllerTest extends \PHPUnit\Framework\TestCase
{

  /**
   * @var array $reportsAllowed
   * Allowed agent names to create report
   */
  private $reportsAllowed = array(
    'dep5',
    'spdx2',
    'spdx2tv',
    'readmeoss',
    'unifiedreport'
  );

  /**
   * @var ReportController $reportController
   * ReportController object to test
   */
  private $reportController;

  /**
   * @var UploadDao $uploadDao
   * UploadDao mock
   */
  private $uploadDao;

  /**
   * @var integer $userId
   * User id
   */
  private $userId;

  /**
   * @var integer $groupId
   * Group id
   */
  private $groupId;

  /**
   * @var SpdxTwoGeneratorUi $spdxPlugin
   * SPDX generator mock
   */
  private $spdxPlugin;

  /**
   * @var M\MockInterface $readmeossPlugin
   * ReadMeOssPlugin mock
   */
  private $readmeossPlugin;

  /**
   * @var M\MockInterface $unifiedPlugin
   * FoUnifiedReportGenerator mock
   */
  private $unifiedPlugin;

  /**
   * @var M\MockInterface $downloadPlugin
   * ui_download mock
   */
  private $downloadPlugin;

  /**
   * @var DbManager $dbManager
   * DbManager mock
   */
  private $dbManager;

  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp()
  {
    global $container;
    $this->userId = 2;
    $this->groupId = 2;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->dbManager = M::mock(DbManager::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->spdxPlugin = M::mock(SpdxTwoGeneratorUi::class);
    $this->readmeossPlugin = M::mock('ReadMeOssPlugin');
    $this->unifiedPlugin = M::mock('FoUnifiedReportGenerator');
    $this->downloadPlugin = M::mock('ui_download');

    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getUploadDao')
      ->andReturn($this->uploadDao);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('ui_spdx2'))->andReturn($this->spdxPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('ui_readmeoss'))->andReturn($this->readmeossPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('download'))->andReturn($this->downloadPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('agent_founifiedreport'))
      ->andReturn($this->unifiedPlugin);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->reportController = new ReportController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  /**
   * @brief Remove test objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown()
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
   * Helper function to generate uploads
   * @param integer $id Upload id (if > 4, return NULL)
   * @return NULL|\Fossology\Lib\Data\Upload\Upload
   */
  private function getUpload($id)
  {
    $filename = "";
    $description = "";
    $treeTableName = "uploadtree_a";
    $timestamp = "";
    switch ($id) {
      case 2:
        $filename = "top$id";
        $timestamp = "01-01-2020";
        break;
      case 3:
        $filename = "child$id";
        $timestamp = "02-01-2020";
        break;
      case 4:
        $filename = "child$id";
        $timestamp = "03-01-2020";
        break;
      default:
        return null;
    }
    return new Upload($id, $filename, $description, $treeTableName, $timestamp);
  }

  /**
   * Helper function to call ReportController::getReport() and return response
   * @param integer $uploadId
   * @param string  $reportFormat
   * @return Response
   */
  private function getResponseForReport($uploadId, $reportFormat)
  {
    $requestHeaders = new Headers();
    $requestHeaders->set('uploadId', $uploadId);
    $requestHeaders->set('reportFormat', $reportFormat);
    $body = new Body(fopen('php://temp', 'r+'));
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/api/v1/report"), $requestHeaders, [], [], $body);
    $response = new Response();
    return $this->reportController->getReport($request, $response, []);
  }

  /**
   * @test
   * -# Test ReportController::getReport() for all available report types
   * -# Check the response for 201
   */
  public function testGetReportAllFormats()
  {
    $uploadId = 3;
    $upload = $this->getUpload($uploadId);

    $this->uploadDao->shouldReceive('isAccessible')->withArgs([$uploadId,
      $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getUpload')->withArgs([$uploadId])
      ->andReturn($upload);
    $this->spdxPlugin->shouldReceive('scheduleAgent')
      ->withArgs([$this->groupId, $upload, M::anyOf($this->reportsAllowed[0],
        $this->reportsAllowed[1], $this->reportsAllowed[2])])
      ->andReturn([32, 33, ""]);
    $this->readmeossPlugin->shouldReceive('scheduleAgent')
      ->withArgs([$this->groupId, $upload])->andReturn([32, 33, ""]);
    $this->unifiedPlugin->shouldReceive('scheduleAgent')
      ->withArgs([$this->groupId, $upload])->andReturn([32, 33, ""]);

    $expectedResponse = new Info(201, "localhost/api/v1/report/32",
      InfoType::INFO);

    foreach ($this->reportsAllowed as $reportFormat) {
      $actualResponse = $this->getResponseForReport($uploadId, $reportFormat);
      $this->assertEquals($expectedResponse->getArray(),
        $this->getResponseJson($actualResponse));
      $this->assertEquals($expectedResponse->getCode(),
        $actualResponse->getStatusCode());
    }
  }

  /**
   * @test
   * -# Test ReportController::getReport() for invalid report type
   * -# Test for 400 response
   */
  public function testGetReportInvalidFormat()
  {
    $uploadId = 3;
    $reportFormat = 'report';

    $expectedResponse = new Info(400,
      "reportFormat must be from [" . implode(",", $this->reportsAllowed) . "]",
      InfoType::ERROR);

    $actualResponse = $this->getResponseForReport($uploadId, $reportFormat);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test ReportController::getReport() for inaccessible upload
   * -# Check for 403 response
   */
  public function testGetReportInaccessibleUpload()
  {
    $uploadId = 4;
    $reportFormat = $this->reportsAllowed[1];

    $this->uploadDao->shouldReceive('isAccessible')->withArgs([$uploadId,
      $this->groupId])->andReturn(false);

    $expectedResponse = new Info(403, "Upload is not accessible!",
      InfoType::ERROR);

    $actualResponse = $this->getResponseForReport($uploadId, $reportFormat);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test ReportController::getReport() for invalid upload
   * -# Check for 404 response
   */
  public function testGetReportInvalidUpload()
  {
    $uploadId = 10;
    $reportFormat = $this->reportsAllowed[1];
    $upload = $this->getUpload($uploadId);

    $this->uploadDao->shouldReceive('isAccessible')->withArgs([$uploadId,
      $this->groupId])->andReturn(true);
    $this->uploadDao->shouldReceive('getUpload')->withArgs([$uploadId])
      ->andReturn($upload);

    $expectedResponse = new Info(404, "Upload does not exists!",
      InfoType::ERROR);

    $actualResponse = $this->getResponseForReport($uploadId, $reportFormat);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test ReportController::downloadReport()
   * -# Generate all mock objects
   * -# Generate a temporary file to be downloaded
   * -# Replicate expected headers
   * -# Check for acutal headers
   * -# Check for actual file content
   */
  public function testDownloadReport()
  {
    $reportId = 43;
    $uploadId = 3;

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT jq_type FROM jobqueue WHERE jq_job_fk = $1',
        [$reportId], "reportValidity"])
      ->andReturn(["jq_type" => $this->reportsAllowed[1]]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT job_upload_fk FROM job WHERE job_pk = $1',
        [$reportId], "reportFileUpload"])
      ->andReturn(["job_upload_fk" => $uploadId]);
    $this->uploadDao->shouldReceive('isAccessible')->withArgs([$uploadId,
      $this->groupId])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT * FROM reportgen WHERE job_fk = $1',
        [$reportId], "reportFileName"])
      ->andReturn(["job_upload_fk" => $uploadId]);

    $tmpfile = tempnam(sys_get_temp_dir(), "FOO");

    $handle = fopen($tmpfile, "w");
    fwrite($handle, "writing to tempfile");
    fclose($handle);

    $fileResponse = new BinaryFileResponse($tmpfile);
    $fileResponse->headers->set('Content-Type', 'text/plain');
    $fileResponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    $fileContent = $fileResponse->getFile();
    $this->downloadPlugin->shouldReceive('getReport')->andReturn($fileResponse);

    $expectedResponse = new Response();
    $expectedResponse = $expectedResponse->withHeader('Content-Description',
        'File Transfer')
      ->withHeader('Content-Type', $fileResponse->headers->get('Content-Type'))
      ->withHeader('Content-Disposition',
        $fileResponse->headers->get('Content-Disposition'))
      ->withHeader('Cache-Control', 'must-revalidate')
      ->withHeader('Pragma', 'private')
      ->withHeader('Content-Length', filesize($fileContent));

    ob_start();
    $actualResponse = $this->reportController->downloadReport(null,
      new Response(), ["id" => $reportId]);
    $output = ob_get_clean();

    $expectedResponse->getBody()->seek(0);
    $this->assertEquals($expectedResponse->getBody()->getContents(),
      $actualResponse->getBody()->getContents());
    $this->assertEquals($expectedResponse->getHeaders(),
      $actualResponse->getHeaders());
    $this->assertEquals(file_get_contents($tmpfile), $output);
    unlink($tmpfile);
  }

  /**
   * @test
   * -# Test ReportController::downloadReport() for inaccessible upload
   * -# Check for 403 response
   */
  public function testDownloadReportInAccessibleUpload()
  {
    $reportId = 43;
    $uploadId = 3;

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT jq_type FROM jobqueue WHERE jq_job_fk = $1',
        [$reportId], "reportValidity"])
      ->andReturn(["jq_type" => $this->reportsAllowed[1]]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT job_upload_fk FROM job WHERE job_pk = $1',
        [$reportId], "reportFileUpload"])
      ->andReturn(["job_upload_fk" => $uploadId]);
    $this->uploadDao->shouldReceive('isAccessible')->withArgs([$uploadId,
      $this->groupId])->andReturn(false);

    $expectedResponse = new Info(403, "Report is not accessible.",
      InfoType::INFO);

    $actualResponse = $this->reportController->downloadReport(null,
      new Response(), ["id" => $reportId]);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test ReportController::downloadReport() for invalid upload
   * -# Check for 404 response
   */
  public function testDownloadReportInvalidUpload()
  {
    $reportId = 43;

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT jq_type FROM jobqueue WHERE jq_job_fk = $1',
        [$reportId], "reportValidity"])
      ->andReturn(["jq_type" => ""]);

    $expectedResponse = new Info(404, "No report scheduled with given job id.",
            InfoType::ERROR);

    $actualResponse = $this->reportController->downloadReport(null,
      new Response(), ["id" => $reportId]);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test ReportController::downloadReport() for early download
   * -# Check for 503 response with `Retry-After` headers.
   */
  public function testDownloadReportTryLater()
  {
    $reportId = 43;
    $uploadId = 3;

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT jq_type FROM jobqueue WHERE jq_job_fk = $1',
        [$reportId], "reportValidity"])
      ->andReturn(["jq_type" => $this->reportsAllowed[1]]);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT job_upload_fk FROM job WHERE job_pk = $1',
        [$reportId], "reportFileUpload"])
      ->andReturn(["job_upload_fk" => $uploadId]);
    $this->uploadDao->shouldReceive('isAccessible')->withArgs([$uploadId,
      $this->groupId])->andReturn(true);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs(['SELECT * FROM reportgen WHERE job_fk = $1',
        [$reportId], "reportFileName"])
      ->andReturn(false);

    $expectedResponse = new Info(503, "Report is not ready. Retry after 10s.",
      InfoType::INFO);

    $actualResponse = $this->reportController->downloadReport(null,
      new Response(), ["id" => $reportId]);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
    $this->assertEquals('10', $actualResponse->getHeaderLine('Retry-After'));
  }
}
