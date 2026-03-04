<?php
/*
 SPDX-FileCopyrightText: © 2020-2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for ReportController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\ReportController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Exceptions\HttpServiceUnavailableException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;
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
    'unifiedreport',
    'clixml',
    'decisionexporter',
    'cyclonedx',
    'spdx3json',
    'spdx3rdf',
    'spdx3jsonld'
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
   * @var M\MockInterface $spdxPlugin
   * SPDX generator mock
   */
  private $spdxPlugin;

  /**
   * @var M\MockInterface $readmeossPlugin
   * ReadMeOssPlugin mock
   */
  private $readmeossPlugin;

  /**
   * @var M\MockInterface $clixmlPlugin
   * CLIXMLPlugin mock
   */
  private $clixmlPlugin;

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
   * @var M\MockInterface $decisionExporterPlugin
   * DecisionExporterAgentPlugin mock
   */
  private $decisionExporterPlugin;

  /**
   * @var M\MockInterface $cyclonedxPlugin
   * CycloneDXGeneratorUi mock
   */
  private $cyclonedxPlugin;

  /**
   * @var M\MockInterface $spdx3Plugin
   * SpdxThreeGeneratorUi mock
   */
  private $spdx3Plugin;

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
    $this->userId = 2;
    $this->groupId = 2;
    $container = M::mock('Psr\Container\ContainerInterface');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->dbManager = M::mock(DbManager::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->spdxPlugin = M::mock('SpdxTwoGeneratorUi');
    $this->readmeossPlugin = M::mock('ReadMeOssPlugin');
    $this->clixmlPlugin = M::mock('CliXmlGeneratorUi');
    $this->unifiedPlugin = M::mock('FoUnifiedReportGenerator');
    $this->decisionExporterPlugin = M::mock('DecisionExporterAgentPlugin');
    $this->cyclonedxPlugin = M::mock('CycloneDXGeneratorUi');
    $this->spdx3Plugin = M::mock('SpdxThreeGeneratorUi');
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
      ->withArgs(array('ui_clixml'))->andReturn($this->clixmlPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('download'))->andReturn($this->downloadPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('agent_founifiedreport'))
      ->andReturn($this->unifiedPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(['agent_fodecisionexporter'])->andReturn($this->decisionExporterPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('ui_cyclonedx'))->andReturn($this->cyclonedxPlugin);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('ui_spdx3'))->andReturn($this->spdx3Plugin);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->reportController = new ReportController($container);
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
    $GLOBALS["apiBasePath"] = "/repo/api/v1";
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('uploadId', $uploadId);
    $requestHeaders->setHeader('reportFormat', $reportFormat);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/repo/api/v1/report"), $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
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
    $this->clixmlPlugin->shouldReceive('scheduleAgent')
      ->withArgs([$this->groupId, $upload])->andReturn([32, 33, ""]);
    $this->decisionExporterPlugin->shouldReceive('scheduleAgent')
      ->withArgs([$this->groupId, $upload])->andReturn([32, 33]);
    $this->cyclonedxPlugin->shouldReceive('scheduleAgent')
      ->withArgs([$this->groupId, $upload])->andReturn([32, 33]);
    $this->spdx3Plugin->shouldReceive('scheduleAgent')
      ->withArgs([$this->groupId, $upload, M::anyOf($this->reportsAllowed[8],
        $this->reportsAllowed[9], $this->reportsAllowed[10])])
      ->andReturn([32, 33, ""]);

    $expectedResponse = new Info(201, "http://localhost/repo/api/v1/report/32",
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

    $this->expectException(HttpBadRequestException::class);

    $this->getResponseForReport($uploadId, $reportFormat);
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

    $this->expectException(HttpForbiddenException::class);

    $this->getResponseForReport($uploadId, $reportFormat);
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

    $this->expectException(HttpNotFoundException::class);

    $this->getResponseForReport($uploadId, $reportFormat);
  }

  /**
   * @test
   * -# Test ReportController::downloadReport()
   * -# Generate all mock objects
   * -# Generate a temporary file to be downloaded
   * -# Replicate expected headers
   * -# Check for actual headers
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

    $expectedResponse = new ResponseHelper();
    $expectedResponse = $expectedResponse->withHeader('Content-Description',
        'File Transfer')
      ->withHeader('Content-Type', $fileResponse->headers->get('Content-Type'))
      ->withHeader('Content-Disposition',
        $fileResponse->headers->get('Content-Disposition'))
      ->withHeader('Cache-Control', 'must-revalidate')
      ->withHeader('Pragma', 'private')
      ->withHeader('Content-Length', filesize($fileContent));

    $actualResponse = $this->reportController->downloadReport(null,
      new ResponseHelper(), ["id" => $reportId]);

    $expectedResponse->getBody()->seek(0);
    $this->assertEquals(file_get_contents($tmpfile),
      $actualResponse->getBody()->getContents());
    $this->assertEquals($expectedResponse->getHeaders(),
      $actualResponse->getHeaders());
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

    $this->expectException(HttpForbiddenException::class);

    $this->reportController->downloadReport(null, new ResponseHelper(),
      ["id" => $reportId]);
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

    $this->expectException(HttpNotFoundException::class);

    $this->reportController->downloadReport(null, new ResponseHelper(),
      ["id" => $reportId]);
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

    $this->expectException(HttpServiceUnavailableException::class);

    $this->reportController->downloadReport(null, new ResponseHelper(),
      ["id" => $reportId]);
  }
}
