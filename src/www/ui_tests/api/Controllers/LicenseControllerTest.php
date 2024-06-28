<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for LicenseController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseAcknowledgementDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\LicenseStdCommentDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\LicenseController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\License;
use Fossology\UI\Api\Models\Obligation;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class LicenseControllerTest
 * @brief Unit tests for LicenseController
 */
class LicenseControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

  /**
   * @var integer $userId
   * User ID to mock
   */
  private $userId;

  /**
   * @var integer $groupId
   * Group ID to mock
   */
  private $groupId;

  /**
   * @var DbHelper $dbHelper
   * DbHelper mock
   */
  private $dbHelper;

  /**
   * @var DbManager $dbManager
   * Dbmanager mock
   */
  private $dbManager;

  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var LicenseController $licenseController
   * LicenseController mock
   */
  private $licenseController;

  /**
   * @var LicenseDao $licenseDao
   * LicenseDao mock
   */
  private $licenseDao;

  /**
   * @var UserDao $userDao
   * UserDao mock
   */
  private $userDao;

  /**
   * @var LicenseAcknowledgementDao $adminLicenseAckDao
   * LicenseAcknowledgementDao mock
   */
  private $adminLicenseAckDao;

  /**
   * @var LicenseStdCommentDao $licenseStdCommentDao
   * LicenseStdCommentDao mock
   */
  private $licenseStdCommentDao;

  /**
   * @var M\MockInterface $adminLicensePlugin
   * admin_license_from_csv mock
   */
  private $adminLicensePlugin;

  /**
   * @var StreamFactory $streamFactory
   * Stream factory to create body streams.
   */
  private $streamFactory;

  /**
   * @var M\MockInterface $licenseCandidatePlugin
   * admin_license_candidate mock
   */
  private $licenseCandidatePlugin;


  /**
   * @var Auth $auth
   * Auth mock
   */
  private $auth;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container;
    $this->userId = 2;
    $this->groupId = 2;
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->auth = M::mock(Auth::class);
    $this->dbManager = M::mock(DbManager::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->licenseDao = M::mock(LicenseDao::class);
    $this->userDao = M::mock(UserDao::class);
    $this->adminLicenseAckDao = M::mock(LicenseAcknowledgementDao::class);
    $this->adminLicensePlugin = M::mock('admin_license_from_csv');
    $this->licenseCandidatePlugin = M::mock('admin_license_candidate');
    $this->licenseStdCommentDao = M::mock(LicenseStdCommentDao::class);

    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $this->restHelper->shouldReceive('getPlugin')->withArgs(["admin_license_candidate"])->andReturn($this->licenseCandidatePlugin);
    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
    $this->restHelper->shouldReceive('getUserDao')->andReturn($this->userDao);

    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('admin_license_from_csv'))->andReturn($this->adminLicensePlugin);
    $container->shouldReceive('get')->withArgs(array(
      'dao.license.stdc'))->andReturn($this->licenseStdCommentDao);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array(
      'dao.license.acknowledgement'))->andReturn($this->adminLicenseAckDao);
    $container->shouldReceive('get')->withArgs(array(
      'dao.license'))->andReturn($this->licenseDao);
    $container->shouldReceive('get')->withArgs(array(
      'dao.license.acknowledgement'))->andReturn($this->adminLicenseAckDao);
    $this->licenseController = new LicenseController($container);
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
   * Helper function to generate obligations
   * @param integer $id
   * @return Obligation
   */
  private function getObligation($id)
  {
    return new Obligation($id, 'My obligation', 'Obligation',
      'This should represent some valid obligation text.', 'yellow');
  }

  /**
   * Helper function to generate obligations for License Dao
   * @param integer $id
   * @return Obligation
   */
  private function getDaoObligation($id)
  {
    return [
      'ob_pk' => $id,
      'ob_topic' => 'My obligation',
      'ob_text' => 'This should represent some valid obligation text.',
      'ob_active' => true,
      'rf_fk' => 2,
      'ob_type' => 'Obligation',
      'ob_classification' => 'yellow',
      'ob_comment' => "",
      'rf_shortname' => null
    ];
  }

  /**
   * Helper function to generate licenses
   * @param string  $shortname
   * @param boolean $obligations
   * @param boolean $emptyObligation
   * @return License
   */
  private function getLicense($shortname, $obligations=false,
                              $emptyObligation=true)
  {
    $obligationList = [
      $this->getObligation(123),
      $this->getObligation(124)
    ];
    $license = null;
    if ($shortname == "MIT") {
      $license = new License(22, "MIT", "MIT License",
        "MIT License Copyright (c) <year> <copyright holders> ...",
        "https://opensource.org/licenses/MIT", null, 2, false);
    } else {
      $license = new License(25, $shortname, "Exotic License",
        "Exotic license for magical codes", "", null, 0, true);
    }
    if ($obligations) {
      if ($emptyObligation) {
        $license->setObligations([]);
      } else {
        $license->setObligations($obligationList);
      }
    }
    return $license;
  }

  /**
   * Helper function to generate licenses from LicenseDao
   * @param string  $shortname
   * @return \Fossology\Lib\Data\License
   */
  private function getDaoLicense($shortname)
  {
    $license = null;
    if ($shortname == "MIT") {
      $license = new \Fossology\Lib\Data\License(22, "MIT", "MIT License",
        2, "MIT License Copyright (c) <year> <copyright holders> ...",
        "https://opensource.org/licenses/MIT", 1, true);
    } else {
      $license = new \Fossology\Lib\Data\License(25, $shortname,
        "Exotic License", 0, "Exotic license for magical codes", "", 1,
        false);
    }
    return $license;
  }

  /**
   * Helper function to translate License to DB array
   * @param array $licenses
   * @return array
   */
  private function translateLicenseToDb($licenses)
  {
    $licenseList = [];
    foreach ($licenses as $license) {
      $licenseList[] = [
        'rf_pk' => $license->getId(),
        'rf_shortname' => $license->getShortName(),
        'rf_fullname' => $license->getFullName(),
        'rf_text' => $license->getText(),
        'rf_url' => $license->getUrl(),
        'rf_risk' => $license->getRisk(),
        'group_fk' => $license->getIsCandidate() ? $this->groupId : 0
      ];
    }
    return $licenseList;
  }

  /**
   * @test
   * -# Test for LicenseController::getLicense() to fetch single license
   * -# Check if response is 200
   */
  public function testGetLicense()
  {
    $licenseShortName = "MIT";
    $license = $this->getLicense($licenseShortName, true);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license/$licenseShortName"), $requestHeaders, [], [], $body);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$licenseShortName, $this->groupId])
      ->andReturn($this->getDaoLicense($licenseShortName));
    $this->licenseDao->shouldReceive('getLicenseObligations')
      ->withArgs([[22], false])->andReturn([]);
    $this->licenseDao->shouldReceive('getLicenseObligations')
      ->withArgs([[22], true])->andReturn([]);
    $expectedResponse = (new ResponseHelper())->withJson($license->getArray(), 200);

    $actualResponse = $this->licenseController->getLicense($request,
      new ResponseHelper(), ['shortname' => $licenseShortName]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for LicenseController::getLicense() to fetch single license
   * -# The license now has obligations
   * -# Check if response is 200
   */
  public function testGetLicenseObligations()
  {
    $licenseShortName = "MIT";
    $license = $this->getLicense($licenseShortName, true, false);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license/$licenseShortName"), $requestHeaders, [], [], $body);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$licenseShortName, $this->groupId])
      ->andReturn($this->getDaoLicense($licenseShortName));
    $this->licenseDao->shouldReceive('getLicenseObligations')
      ->withArgs([[22], false])->andReturn([$this->getDaoObligation(123)]);
    $this->licenseDao->shouldReceive('getLicenseObligations')
      ->withArgs([[22], true])->andReturn([$this->getDaoObligation(124)]);
    $expectedResponse = (new ResponseHelper())->withJson($license->getArray(), 200);

    $actualResponse = $this->licenseController->getLicense($request,
      new ResponseHelper(), ['shortname' => $licenseShortName]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for LicenseController::getLicense(), license not found
   * -# Check if response is 404
   */
  public function testGetLicenseNotFound()
  {
    $licenseShortName = "Bogus";

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license/$licenseShortName"), $requestHeaders, [], [], $body);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$licenseShortName, $this->groupId])
      ->andReturn(null);
    $this->expectException(HttpNotFoundException::class);

    $this->licenseController->getLicense($request, new ResponseHelper(),
      ['shortname' => $licenseShortName]);
  }

  /**
   * @test
   * -# Test for LicenseController::getAllLicenses() to fetch all licenses
   * -# Check if response is 200
   * -# Check if pagination headers are set
   */
  public function testGetAllLicense()
  {
    $licenses = [
      $this->getLicense("MIT"),
      $this->getLicense("Exotic"),
      $this->getLicense("Exotic2"),
      $this->getLicense("Exotic3")
    ];

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getLicenseCount')
      ->withArgs(["all", $this->groupId])->andReturn(4);
    $this->dbHelper->shouldReceive('getLicensesPaginated')
      ->withArgs([1, 100, "all", $this->groupId, false])
      ->andReturn($this->translateLicenseToDb($licenses));

    $responseLicense = [];
    foreach ($licenses as $license) {
      $responseLicense[] = $license->getArray();
    }
    $expectedResponse = (new ResponseHelper())->withHeader("X-Total-Pages", 1)
      ->withJson($responseLicense, 200);

    $actualResponse = $this->licenseController->getAllLicenses($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getHeaders(),
      $actualResponse->getHeaders());
  }

  /**
   * @test
   * -# Test for LicenseController::getAllLicenses() to fetch all licenses
   * -# The page requested is out of bounds
   * -# Check if response is 400
   * -# Check if pagination headers are set
   */
  public function testGetAllLicenseBounds()
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 5);
    $requestHeaders->setHeader('page', 2);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getLicenseCount')
      ->withArgs(["all", $this->groupId])->andReturn(4);
    $this->expectException(HttpBadRequestException::class);

    $this->licenseController->getAllLicenses($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for LicenseController::getAllLicenses() with kind filter
   * -# Check if proper parameters are passed to DbHelper
   */
  public function testGetAllLicenseFilters()
  {
    // All licenses
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license", "kind=all"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getLicenseCount')
      ->withArgs(["all", $this->groupId])->andReturn(4)->once();
    $this->dbHelper->shouldReceive('getLicensesPaginated')
      ->withArgs([1, 100, "all", $this->groupId, false])
      ->andReturn([])->once();

    $this->licenseController->getAllLicenses($request, new ResponseHelper(), []);

    // Main licenses
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license", "kind=main"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getLicenseCount')
      ->withArgs(["main", $this->groupId])->andReturn(4)->once();
    $this->dbHelper->shouldReceive('getLicensesPaginated')
      ->withArgs([1, 100, "main", $this->groupId, false])
      ->andReturn([])->once();

    $this->licenseController->getAllLicenses($request, new ResponseHelper(), []);

    // Candidate licenses
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license", "kind=candidate"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getLicenseCount')
      ->withArgs(["candidate", $this->groupId])->andReturn(4)->once();
    $this->dbHelper->shouldReceive('getLicensesPaginated')
      ->withArgs([1, 100, "candidate", $this->groupId, false])
      ->andReturn([])->once();

    $this->licenseController->getAllLicenses($request, new ResponseHelper(), []);

    // wrong filter
    $request = new Request("GET", new Uri("HTTP", "localhost", 80,
      "/license", "kind=bogus"), $requestHeaders, [], [], $body);
    $this->dbHelper->shouldReceive('getLicenseCount')
      ->withArgs(["all", $this->groupId])->andReturn(4)->once();
    $this->dbHelper->shouldReceive('getLicensesPaginated')
      ->withArgs([1, 100, "all", $this->groupId, false])
      ->andReturn([])->once();

    $this->licenseController->getAllLicenses($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for LicenseController::createLicense() to create new license
   * -# Check if response is 201
   */
  public function testCreateLicense()
  {
    $license = $this->getLicense("MIT");
    $requestBody = $license->getArray();
    $requestBody["isCandidate"] = true;
    unset($requestBody['id']);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    $request = new Request("POST", new Uri("HTTP", "localhost", 80,
      "/license"), $requestHeaders, [], [], $body);

    $tableName = "license_candidate";
    $assocData = [
      "rf_shortname" => $license->getShortName(),
      "rf_fullname" => $license->getFullName(),
      "rf_text" => $license->getText(),
      "rf_md5" => md5($license->getText()),
      "rf_risk" => $license->getRisk(),
      "rf_url" => $license->getUrl(),
      "rf_detector_type" => 1,
      "group_fk" => $this->groupId,
      "rf_user_fk_created" => $this->userId,
      "rf_user_fk_modified" => $this->userId,
      "marydone" => false
    ];

    $sql = "SELECT count(*) cnt FROM ".
      "$tableName WHERE rf_shortname = $1 AND group_fk = $2;";

    $this->dbManager->shouldReceive('insertTableRow')
      ->withArgs([$tableName, $assocData, M::any(), "rf_pk"])->andReturn(4);
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([$sql, [$license->getShortName(), $this->groupId], M::any()])
      ->andReturn(["cnt" => 0]);

    $info = new Info(201, '4', InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());

    $actualResponse = $this->licenseController->createLicense($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for LicenseController::createLicense() to create new license
   * -# The request body is malformed
   * -# Check if response is 400
   */
  public function testCreateLicenseNoShort()
  {
    $license = $this->getLicense("MIT");
    $requestBody = $license->getArray();
    $requestBody["isCandidate"] = true;
    unset($requestBody['id']);
    unset($requestBody['shortName']);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    $request = new Request("POST", new Uri("HTTP", "localhost", 80,
      "/license"), $requestHeaders, [], [], $body);
    $this->expectException(HttpBadRequestException::class);

    $this->licenseController->createLicense($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for LicenseController::createLicense() to create new license
   * -# Non admin user can't create main license
   * -# Check if response is 403
   */
  public function testCreateLicenseNoAdmin()
  {
    $license = $this->getLicense("MIT");
    $requestBody = $license->getArray();
    unset($requestBody['id']);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    $request = new Request("POST", new Uri("HTTP", "localhost", 80,
      "/license"), $requestHeaders, [], [], $body);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);

    $this->licenseController->createLicense($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for LicenseController::createLicense() to create new license
   * -# Simulate duplicate license name
   * -# Check if response is 409
   */
  public function testCreateDuplicateLicense()
  {
    $license = $this->getLicense("MIT");
    $requestBody = $license->getArray();
    $requestBody["isCandidate"] = true;
    unset($requestBody['id']);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    $request = new Request("POST", new Uri("HTTP", "localhost", 80,
      "/license"), $requestHeaders, [], [], $body);

    $tableName = "license_candidate";

    $sql = "SELECT count(*) cnt FROM ".
      "$tableName WHERE rf_shortname = $1 AND group_fk = $2;";

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([$sql, [$license->getShortName(), $this->groupId], M::any()])
      ->andReturn(["cnt" => 1]);
    $this->expectException(HttpConflictException::class);

    $this->licenseController->createLicense($request, new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for LicenseController::updateLicense() to edit a license
   * -# Check if response is 200
   */
  public function testUpdateLicense()
  {
    $license = $this->getDaoLicense("Exotic");
    $requestBody = [
      "fullName" => "Exotic License - style",
      "risk" => 0
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    $request = new Request("PATCH", new Uri("HTTP", "localhost", 80,
      "/license/" . $license->getShortName()), $requestHeaders, [], [], $body);

    $tableName = "license_candidate";
    $assocData = [
      "rf_fullname" => "Exotic License - style",
      "rf_risk" => 0
    ];

    $this->userDao->shouldReceive('isAdvisorOrAdmin')
      ->withArgs([$this->userId, $this->groupId])->andReturn(true);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$license->getShortName(), $this->groupId])
      ->andReturn($license);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["license_candidate", "rf_pk", $license->getId()])
      ->andReturn(true);
    $this->dbManager->shouldReceive('updateTableRow')
      ->withArgs([$tableName, $assocData, "rf_pk", $license->getId(), M::any()]);

    $info = new Info(200, "License " . $license->getShortName() . " updated.",
      InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());

    $actualResponse = $this->licenseController->updateLicense($request,
      new ResponseHelper(), ["shortname" => $license->getShortName()]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for LicenseController::updateLicense() to edit a license
   * -# User is not admin/advisor of the group
   * -# Check if response is 403
   */
  public function testUpdateLicenseNonAdvisor()
  {
    $license = $this->getDaoLicense("Exotic");
    $requestBody = [
      "fullName" => "Exotic License - style",
      "risk" => 0
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    $request = new Request("PATCH", new Uri("HTTP", "localhost", 80,
      "/license/" . $license->getShortName()), $requestHeaders, [], [], $body);

    $this->userDao->shouldReceive('isAdvisorOrAdmin')
      ->withArgs([$this->userId, $this->groupId])->andReturn(false);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$license->getShortName(), $this->groupId])
      ->andReturn($license);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["license_candidate", "rf_pk", $license->getId()])
      ->andReturn(true);
    $this->expectException(HttpForbiddenException::class);

    $this->licenseController->updateLicense($request, new ResponseHelper(),
      ["shortname" => $license->getShortName()]);
  }

  /**
   * @test
   * -# Test for LicenseController::updateLicense() to edit a license
   * -# User is not admin
   * -# Check if response is 403
   */
  public function testUpdateLicenseNonAdmin()
  {
    $license = $this->getDaoLicense("MIT");
    $requestBody = [
      "fullName" => "MIT License - style",
      "risk" => 0
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    $request = new Request("PATCH", new Uri("HTTP", "localhost", 80,
      "/license/" . $license->getShortName()), $requestHeaders, [], [], $body);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;

    $this->userDao->shouldReceive('isAdvisorOrAdmin')
      ->withArgs([$this->userId, $this->groupId])->andReturn(true);
    $this->licenseDao->shouldReceive('getLicenseByShortName')
      ->withArgs([$license->getShortName(), $this->groupId])
      ->andReturn($license);
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["license_candidate", "rf_pk", $license->getId()])
      ->andReturn(false);
    $this->expectException(HttpForbiddenException::class);

    $this->licenseController->updateLicense($request, new ResponseHelper(),
      ["shortname" => $license->getShortName()]);
  }


  /**
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   * @test
   * -# Test for LicenseController::handleImportLicense()
   * -# Check if response status is 200
   * -# Check if response body is matches the expected response body
   */
  public function testImportLicense()
  {

    $delimiter =  ',';
    $enclosure = '"';

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');

    $body = $this->streamFactory->createStream(json_encode([]));

    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $FILE_INPUT_NAME = "file_input";

    $this->adminLicensePlugin->shouldReceive('getFileInputName')
      ->andReturn($FILE_INPUT_NAME);

    $res = array(true,"random_message",200);

    $this->adminLicensePlugin->shouldReceive('handleFileUpload')-> withArgs([NULL,$delimiter,$enclosure])
      ->andReturn($res);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->auth->shouldReceive('isAdmin')->andReturn(true);

    $info = new Info(200, "random_message", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->licenseController->handleImportLicense($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }


  /**
   * @test
   * -# Test for LicenseController::deleteAdminLicenseCandidate() to delete license-candidate.
   * -# User is admin
   * -# License-candidate is does exist
   * -# Check if response is 200
   * -# Check if response-body matches
   */
  public function testDeleteAdminLicenseCandidateIsAdmin(){
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $id = 1;
    $this->auth->shouldReceive('isAdmin')->andReturn(true);
    $this->licenseCandidatePlugin->shouldReceive('getDataRow')->withArgs([$id])->andReturn(true);
    $res = new Response('true',Response::HTTP_OK,array('Content-type'=>'text/plain'));
    $this->licenseCandidatePlugin->shouldReceive("doDeleteCandidate")->withArgs([$id,false])->andReturn($res);
    $expectedResponse = new Info(202,"License candidate will be deleted.",  InfoType::INFO);
    $actualResponse = $this->licenseController->deleteAdminLicenseCandidate(null,
      new ResponseHelper(), ["id" => $id]);
    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for LicenseController::deleteAdminLicenseCandidate() to delete license-candidate.
   * -# User is not-admin
   * -# License-candidate is does exist
   * -# Check if response is 400
   * -# Check if response-body matches
   */
  public function testDeleteAdminLicenseCandidateNotAdmin(){
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $id = 1;
    $this->auth->shouldReceive('isAdmin')->andReturn(false);
    $this->expectException(HttpForbiddenException::class);

    $this->licenseController->deleteAdminLicenseCandidate(null,
      new ResponseHelper(), ["id" => $id]);
  }

  /**
   * @test
   * -# Test for LicenseController::deleteAdminLicenseCandidate() to delete license-candidate.
   * -# User is admin
   * -# License-candidate don't exist
   * -# Check if response is 404
   * -# Check if rseponse-body matches
   */
  public function testDeleteAdminLicenseCandidateNotFound(){
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $id = 1;
    $this->auth->shouldReceive('isAdmin')->andReturn(true);
    $this->licenseCandidatePlugin->shouldReceive('getDataRow')->withArgs([$id])->andReturn(false);
    $res = new Response('true',Response::HTTP_OK,array('Content-type'=>'text/plain'));
    $this->licenseCandidatePlugin->shouldReceive("doDeleteCandidate")->withArgs([$id])->andReturn($res);
    $this->expectException(HttpNotFoundException::class);

    $this->licenseController->deleteAdminLicenseCandidate(null,
      new ResponseHelper(), ["id" => $id]);
  }

  /**
   * @test
   *  - # Test LicenseController::getAllAdminAcknowledgements
   *  - # Check if the response status is 200
   *  - # Check if output acknowledgements match the expected
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testGetAllAdminAcknowledgements()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $dummyLicenseAcknowledgments = [
      array(
        "la_pk" => 1,
        "name" => "MIT license",
        "acknowledgement" => "Permission is hereby granted, free of charge",
        "updated" => "2024-06-16 15:02:22.245855+02",
        "user_fk" =>  2,
        "is_enabled" => false,
      ),
      array(
        "la_pk" => 2,
        "name" => "GPL-2.0-or-later",
        "acknowledgement" => "Permission is hereby granted, free of charge",
        "updated" => "2024-08-16 15:02:22.245855+02",
        "user_fk" =>  4,
        "is_enabled" => true,
      )
    ];

    $expectedInfo = [
      Array (
        'name' => $dummyLicenseAcknowledgments[0]["name"],
        'acknowledgement' => $dummyLicenseAcknowledgments[0]["acknowledgement"],
        'is_enabled' => false,
        'id' => 1,
    ),
     Array (
        'name' => $dummyLicenseAcknowledgments[1]["name"],
        'acknowledgement' => $dummyLicenseAcknowledgments[1]['acknowledgement'],
        'is_enabled' => true,
        'id' => 2
    )
    ];

    $this->adminLicenseAckDao->shouldReceive('getAllAcknowledgements')->andReturn($dummyLicenseAcknowledgments);
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $expectedResponse = (new ResponseHelper())->withJson($expectedInfo, 200);
    $actualResponse = $this->licenseController->getAllAdminAcknowledgements($request,$response,[]);

    $this->assertEquals(200,$actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getBody()->getContents(),$actualResponse->getBody()->getContents());
    $this->assertEquals($this->getResponseJson($expectedResponse),$this->getResponseJson($actualResponse));

  }

  /**
   * @test
   *  - # Test LicenseController::getAllAdminAcknowledgements
   *  - # Check if the response status is 403
   *  - # Check if HttpForbiddenException is thrown for non admin user
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testGetAllAdminAcknowledgementsNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_NONE;

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $this->expectException(HttpForbiddenException::class);
    $this->licenseController->getAllAdminAcknowledgements($request,$response,[]);
  }

  /**
   * @test LicenseController::handleAdminLicenseAcknowledgement
   *  - # Check if the status is 400
   *  - # Check if HttpBadRequestException is thrown with empty body
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleAdminLicenseAcknowledgementBadRequest()
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $this->expectException(HttpBadRequestException::class);

    $this->licenseController->handleAdminLicenseAcknowledgement($request,$response,[]);

  }

  /**
   * @test LicenseController::handleAdminLicenseAcknowledgement
   *  - # Check if the status is 400
   *  - # Check if HttpBadRequestException is thrown with invalid  body
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleAdminLicenseAcknowledgementInvalidBody()
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode([]));
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $this->expectException(HttpBadRequestException::class);

    $this->licenseController->handleAdminLicenseAcknowledgement($request,$response,[]);
  }


  /**
   * @test
   *  - # Test LicenseController::handleAdminLicenseAcknowledgement
   *  - # Check if the response status is 200 after updating an admin license acknowledgement.
   *  - # Check if actual and expected objects match.
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */

  public function testHandleAdminLicenseAcknowledgementWithUpdate()
  {

    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(["license_std_acknowledgement","name", $this->getDummyVars()["bodyContent"][0]["name"]])->andReturn(true);
    $this->dbManager->shouldReceive("getSingleRow")
      ->withAnyArgs()->andReturn($this->getDummyVars()["dummyExistingAck"]);
    $this->adminLicenseAckDao->shouldReceive('updateAcknowledgement')->withArgs([$this->getDummyVars()["bodyContent"][0]["id"], $this->getDummyVars()["bodyContent"][0]["name"], $this->getDummyVars()["bodyContent"][0]["ack"]]);
    $this->adminLicenseAckDao->shouldReceive('toggleAcknowledgement')->withArgs([$this->getDummyVars()["bodyContent"][0]["id"]]);


    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode($this->getDummyVars()["bodyContent"]));
    $request = new Request("PUT", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $expectedInfo = new Info(200, "Successfully updated admin license acknowledgement with name '" . $this->getDummyVars()["dummyExistingAck"]["name"] . "'", InfoType::INFO);
    $success [] =$expectedInfo->getArray();
    $expectedResponse = (new ResponseHelper())->withJson(["success" => $success, "errors" => []], 200);

    $actualResponse = $this->licenseController->handleAdminLicenseAcknowledgement($request,$response,[]);

    $this->assertEquals(200,$actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),$this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getBody()->getContents(),$actualResponse->getBody()->getContents());

  }

  /**
   * @test
   *  - # Test LicenseController::handleAdminLicenseAcknowledgement
   *  - # Check if creation of new admin license acknowledgement when it already exists.
   *  - # Check if the response status is 400
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleAdminLicenseAcknowledgementLicenseExists()
  {

    $bodyContent = $this->getDummyVars()["bodyContent"];
    $bodyContent[0]["update"] = false;

    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(["license_std_acknowledgement","name", $this->getDummyVars()["bodyContent"][0]["name"]])->andReturn(true);
    $this->dbManager->shouldReceive("getSingleRow")
      ->withAnyArgs()->andReturn($this->getDummyVars()["dummyExistingAck"]);
    $this->adminLicenseAckDao->shouldReceive('updateAcknowledgement')->withArgs([$this->getDummyVars()["bodyContent"][0]["id"], $this->getDummyVars()["bodyContent"][0]["name"], $this->getDummyVars()["bodyContent"][0]["ack"]]);
    $this->adminLicenseAckDao->shouldReceive('toggleAcknowledgement')->withArgs([$this->getDummyVars()["bodyContent"][0]["id"]]);


    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode($bodyContent));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();


    $actualResponse = $this->licenseController->handleAdminLicenseAcknowledgement($request,$response,[]);
    $this->assertEquals(400,$this->getResponseJson($actualResponse)["errors"][0]["code"]);
    $this->assertEquals([],$this->getResponseJson($actualResponse)["success"]);
  }

  /**
   * @test
   * - # Test LicenseController::handleAdminLicenseAcknowledgement
   * - # Check if new ackonowledgement is created with response code 2021
   * - # Check of expected and actual object match
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleAdminLicenseAcknowledgementCreateNew()
  {

    $bodyContent = $this->getDummyVars()["bodyContent"];
    $bodyContent[0]["update"] = false;

    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(["license_std_acknowledgement","name", $this->getDummyVars()["bodyContent"][0]["name"]])->andReturn(false);
    $this->dbManager->shouldReceive("getSingleRow")
      ->withAnyArgs()->andReturn($this->getDummyVars()["dummyExistingAck"]);
    $this->adminLicenseAckDao->shouldReceive('updateAcknowledgement')->withArgs([$this->getDummyVars()["bodyContent"][0]["id"], $this->getDummyVars()["bodyContent"][0]["name"], $this->getDummyVars()["bodyContent"][0]["ack"]]);
    $this->adminLicenseAckDao->shouldReceive('toggleAcknowledgement')->withArgs([$this->getDummyVars()["bodyContent"][0]["id"]]);
    $this->adminLicenseAckDao->shouldReceive("insertAcknowledgement")
      ->withArgs([$bodyContent[0]['name'], $bodyContent[0]['ack']])->andReturn(-1);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode($bodyContent));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $info = new Info(201, "Acknowledgement added successfully.", InfoType::INFO);
    $success [] = $info->getArray();

    $expectedResponse = (new ResponseHelper())->withJson(["success" => $success, "errors" => []], 200);
    $actualResponse = $this->licenseController->handleAdminLicenseAcknowledgement($request,$response,[]);
    $this->assertEquals(201,$this->getResponseJson($actualResponse)["success"][0]["code"]);
    $this->assertEmpty($this->getResponseJson($actualResponse)["errors"]);
    $this->assertEquals($this->getResponseJson($expectedResponse),$this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getBody()->getContents(),$actualResponse->getBody()->getContents());
  }

  /**
   * @test
   *  - # Test LicenseController::handleAdminLicenseAcknowledgement()
   *  - # Check the error code is 500 for users without admin permission
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleAdminLicenseAcknowledgementWithNoPermission()
  {

    $bodyContent = $this->getDummyVars()["bodyContent"];
    $bodyContent[0]["update"] = false;

    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(["license_std_acknowledgement","name", $this->getDummyVars()["bodyContent"][0]["name"]])->andReturn(false);
    $this->dbManager->shouldReceive("getSingleRow")
      ->withAnyArgs()->andReturn($this->getDummyVars()["dummyExistingAck"]);
    $this->adminLicenseAckDao->shouldReceive('updateAcknowledgement')->withArgs([$this->getDummyVars()["bodyContent"][0]["id"], $this->getDummyVars()["bodyContent"][0]["name"], $this->getDummyVars()["bodyContent"][0]["ack"]]);
    $this->adminLicenseAckDao->shouldReceive('toggleAcknowledgement')->withArgs([$this->getDummyVars()["bodyContent"][0]["id"]]);
    $this->adminLicenseAckDao->shouldReceive("insertAcknowledgement")
      ->withArgs([$bodyContent[0]['name'], $bodyContent[0]['ack']])->andReturn(-2);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode($bodyContent));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $expectedError = new Info(500, "Error while inserting new acknowledgement.", InfoType::ERROR);
    $errors [] = $expectedError->getArray();

    $expectedResponse = (new ResponseHelper())->withJson(["success" => [], "errors" => $errors], 200);
    $actualResponse = $this->licenseController->handleAdminLicenseAcknowledgement($request,$response,[]);
    $this->assertEquals(500,$this->getResponseJson($actualResponse)["errors"][0]["code"]);
    $this->assertEmpty($this->getResponseJson($actualResponse)["success"]);
    $this->assertEquals($this->getResponseJson($expectedResponse),$this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getBody()->getContents(),$actualResponse->getBody()->getContents());
  }

  /**
   * @test
   *  - # Test LicenseController::handleLicenseStandardComment()
   *  - # Check if the array of licenseStdComments is retrieved
   *  - # Check if the response code is 200
   *  - # Check if no errors along the procedure.
   * @return void
   */
  public function testGetAllLicenseStandardComments()
  {
    $dbLicenseStdComments = [
      array(
        "lsc_pk" => 1,
        "name" => "Test License Standard",
        "comment" => "MIT License Standard",
        "updated" => "2024-06-16 15:04:08.07613+02",
        "user_fk" => 3,
        "is_enabled" => true
      ),
    ];

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $this->licenseStdCommentDao->shouldReceive('getAllComments')
      ->andReturn($dbLicenseStdComments);
    $actualResponse = $this->licenseController->getAllLicenseStandardComments($request, $response,[]);

    $this->assertEquals(200,intval($actualResponse->getStatusCode()));
    $this->assertEquals($dbLicenseStdComments[0]['lsc_pk'], $this->getResponseJson($actualResponse)[0]['id']);
    $this->assertEquals($dbLicenseStdComments[0]['name'], $this->getResponseJson($actualResponse)[0]['name']);
    $this->assertEquals($dbLicenseStdComments[0]['comment'], $this->getResponseJson($actualResponse)[0]['comment']);
    $this->assertEquals($dbLicenseStdComments[0]['is_enabled'], $this->getResponseJson($actualResponse)[0]['is_enabled']);

  }

  /**
   * @test
   *  - # Test LicenseController::handleLicenseStandardComment()
   *  - # Check if HttpForbiddenException for non admin users
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleLicenseStandardComment()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $this->expectException(HttpForbiddenException::class);
    $this->licenseController->handleLicenseStandardComment($request, $response,[]);

  }

  /**
   * @test LicenseController::handleLicenseStandardComment()
   *  - # Check if the status is 400
   *  - # Check if HttpBadRequestException is thrown with invalid  body
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleLicenseStandardCommentWithInvalidBody()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode([]));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();
    $this->expectException(HttpBadRequestException::class);

    $this->licenseController->handleLicenseStandardComment($request,$response,[]);
  }

  /**
   * - # Function helping to store more data and provide a single point of access without repeating ourselves.
   * @return array
   */
  public function getDummyVars()
  {
    $bodyContent = [
      array(
        "update" => true,
        "ack" => "acknowledgement",
        "name" => "MIT license",
        "toggle" => "toggle license",
        "id" => 3
      )
    ];
    $dummyExistingAck = [
      "la_pk" => 1,
      "name" => "MIT license",
      "acknowledgement" => "Permission is hereby granted, free of charge",
      "updated" => "2024-06-16 15:02:22.245855+02",
      "user_fk" =>  2,
      "is_enabled" => false
    ];
    $licenseStdComments = [
      array(
        "id" =>5,
        "name" => "Test License Standard",
        "comment" => "MIT License Standard",
        "update" => true,
        "toggle" => true
      ),
    ];
    $existingLicenseStdComment = [
      "name" => "Test License Standard",
      "comment" => "MIT License Standard",
      "update" => true,
    ];

    $tableName = "license_std_acknowledgement";

    return [
      "bodyContent" => $bodyContent,
      "dummyExistingAck" => $dummyExistingAck,
      "tableName" => $tableName,
      "licenseStdComments" => $licenseStdComments,
      "existingLicenseStdComment" => $existingLicenseStdComment
    ];

  }

  /**
   * @test
   *  - # Test LicenseController::handleLicenseStandardComment()
   *  - # Check if the response is 200 after updating the license standard comment.
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleLicenseStandardCommentWithUpdate()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $bodyContent = $this->getDummyVars()['licenseStdComments'];
    $existingLicenseStdComments = [
        "name" => "Test License Standard",
        "comment" => "MIT License Standard",
        "update" => true,
    ];

    $this->dbManager->shouldReceive("getSingleRow")
      ->withAnyArgs()->andReturn($existingLicenseStdComments);
    $this->licenseStdCommentDao->shouldReceive('updateComment')->withArgs([$bodyContent[0]["id"], $bodyContent[0]["name"], $bodyContent[0]["comment"]]);
    $this->licenseStdCommentDao->shouldReceive('toggleComment')->withArgs([$bodyContent[0]["id"]]);


    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode($bodyContent));
    $request = new Request("PUT", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $expectedInfo = new Info(200, "Successfully updated standard comment", InfoType::INFO);
    $success [] =$expectedInfo->getArray();
    $expectedResponse = (new ResponseHelper())->withJson(["success" => $success, "errors" => []], 200);

    $actualResponse = $this->licenseController->handleLicenseStandardComment($request,$response,[]);

    $this->assertEquals(200,$actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),$this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getBody()->getContents(),$actualResponse->getBody()->getContents());

  }

  /**
   * @test
   *  - # Test LicenseController::handleLicenseStandardComment()
   *  - # Check if creation of new comment fails if there exists the same comment in the database
   *  - # Check if the response code is 400
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleLicenseStandardCommentExists()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $bodyContent = $this->getDummyVars()['licenseStdComments'];
    $bodyContent[0]["update"] = false;

    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(["license_std_comment","name", $bodyContent[0]['name']])->andReturn(true);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode($bodyContent));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $actualResponse = $this->licenseController->handleAdminLicenseAcknowledgement($request,$response,[]);
    $this->assertEquals(400,$this->getResponseJson($actualResponse)["errors"][0]["code"]);
    $this->assertEquals([],$this->getResponseJson($actualResponse)["success"]);
  }


  /**
   * @test
   *  - # Test LicenseController::handleLicenseStandardComment()
   *  - # Check if the status is 201 after creating new LicenseStdComment
   *  - # Check if object matches
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testHandleLicenseStandardCommentCreateNew()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $bodyContent = $this->getDummyVars()['licenseStdComments'];
    $bodyContent[0]["update"] = false;

    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(["license_std_comment","name", $bodyContent[0]["name"]])->andReturn(false);
    $this->licenseStdCommentDao->shouldReceive("insertComment")
      ->withArgs([$bodyContent[0]['name'], $bodyContent[0]['comment']])->andReturn(-1);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode($bodyContent));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $info = new Info(201, "Comment with name '". $bodyContent[0]['name'] ."' added successfully.", InfoType::INFO);
    $success [] = $info->getArray();

    $expectedResponse = (new ResponseHelper())->withJson(["success" => $success, "errors" => []], 200);
    $actualResponse = $this->licenseController->handleLicenseStandardComment($request,$response,[]);

    $this->assertEquals(201,$this->getResponseJson($actualResponse)["success"][0]["code"]);
    $this->assertEmpty($this->getResponseJson($actualResponse)["errors"]);
    $this->assertEquals($this->getResponseJson($expectedResponse),$this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getBody()->getContents(),$actualResponse->getBody()->getContents());
  }

  /**
   * @test
   *  - # Test LicenseController::handleLicenseStandardComment()
   *  - # Check is the status is 500 for users who are not admins
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */

  public function testHandleLicenseStandardCommentNoPermission()
  {

    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $bodyContent = $this->getDummyVars()['licenseStdComments'];
    $bodyContent[0]["update"] = false;

    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(["license_std_comment","name", $bodyContent[0]["name"]])->andReturn(false);
    $this->licenseStdCommentDao->shouldReceive("insertComment")
      ->withArgs([$bodyContent[0]['name'], $bodyContent[0]['comment']])->andReturn(-2);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream(json_encode($bodyContent));
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $response = new ResponseHelper();

    $expectedError = new Info(500, "Error while inserting new comment.", InfoType::ERROR);
    $errors [] = $expectedError->getArray();

    $expectedResponse = (new ResponseHelper())->withJson(["success" => [], "errors" => $errors], 200);
    $actualResponse = $this->licenseController->handleLicenseStandardComment($request,$response,[]);
    $this->assertEquals(500,$this->getResponseJson($actualResponse)["errors"][0]["code"]);
    $this->assertEmpty($this->getResponseJson($actualResponse)["success"]);
    $this->assertEquals($this->getResponseJson($expectedResponse),$this->getResponseJson($actualResponse));
    $this->assertEquals($expectedResponse->getBody()->getContents(),$actualResponse->getBody()->getContents());
  }



  /**
   *  -# Test for LicenseController::exportAdminLicenseToCSV() to export license to CSV.
   *  -# Check if the status code is 200
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testExportAdminLicenseToCSV()
  {
    $id = 1;
    $this->dbHelper->shouldReceive("doesIdExist")->withArgs(array("license_ref","rf_pk",$id))->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")->withArgs(array("license_candidate","rf_pk",$id))->andReturn(true);
    $this->dbManager->shouldReceive('prepare');
    $this->dbManager->shouldReceive('fetchAll')->andReturn([]);;
    $this->dbManager->shouldReceive('freeResult')->andReturn([]);
    $this->dbManager->shouldReceive('execute');
    $this->dbManager->shouldReceive("getSingleRow")->withAnyArgs()->andReturn([]);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream(json_encode(["referenceText" =>"rftext"]));
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);

    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $actualResponse = $this->licenseController->exportAdminLicenseToCSV($request,new ResponseHelper(), []);
    $this->assertEquals(200,$actualResponse->getStatusCode());

  }


  /**
   *  -# Test for LicenseController::exportAdminLicenseToCSV() to export license to CSV.
   *  -# Check if the status code is 403 with HttpForbiddenException
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testExportAdminLicenseToCSVNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $request = $this->getEmptyRequest("GET");
    $this->expectException(HttpForbiddenException::class);
    $actualResponse = $this->licenseController->exportAdminLicenseToCSV($request,new ResponseHelper(), []);
    $this->assertEquals(200,$actualResponse->getStatusCode());

  }

  /**\
   * @test
   * @return Request
   */
  public function getEmptyRequest($method)
  {
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request($method, new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    return $request;
  }

  /**
   * @return Request
   */
  public function getRequestWithBody($method, $body)
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');

    $request = new Request($method, new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    return $request;
  }

  /**
   * @test
   *  - # Test LicenseController::verifyLicense()
   *  - # Check if HttpForbiddenException is thrown for unauthorized users
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testVerifyLicenseNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $request = $this->getEmptyRequest("POST");

    $this->expectException(HttpForbiddenException::class);
    $this->licenseController->verifyLicense($request, new ResponseHelper(),[]);

  }


  /**
   * @test
   *  - # Test LicenseController::verifyLicense()
   *  - # Check if HttpBadRequestException is thrown for invalid body
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testVerifyLicenseWithBadRequest()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;

    $license = $this->getLicenseArgs()["license"];
    $parentLicense = $this->getLicenseArgs()["parentLicense"];
    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));
    $request = $this->getRequestWithBody("POST", $body);
    $this->expectException(HttpBadRequestException::class);
    $this->licenseController->verifyLicense($request, new ResponseHelper(), [
      "shortname" => ""
    ]);
  }



  /**
   * @test
   *  - # Test LicenseController::verifyLicense()
   *  - # Check if status code is 400 for License whose shortName is not unique
   *  - # Check if HttpBadRequestException is thrown
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testVerifyLicenseWithNonUniqueName()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $license = $this->getLicenseArgs()["license"];
    $parentLicense = $this->getLicenseArgs()["parentLicense"];

    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));

    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$license->getShortName(),$this->groupId])->andReturn($license);
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$parentLicense->getShortName(), $this->groupId])->andReturn($parentLicense);

    $this->licenseCandidatePlugin->shouldReceive("verifyCandidate")
      ->withArgs([$license->getId(),$license->getShortName(), $parentLicense->getId()])
      ->andReturn(false);
    $request = $this->getRequestWithBody("POST", $body);

    $this->expectException(HttpBadRequestException::class);
   $this->licenseController->verifyLicense($request, new ResponseHelper(), ["shortname" => $license->getShortName()]);

  }
  /**
   * @test
   *  - # Test LicenseController::verifyLicense()
   *  - # Check if status code is 404 for License which is not found
   *  - # Check if HttpBadRequestException is thrown
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testVerifyNotFoundLicense()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $license = $this->getLicenseArgs()["license"];
    $parentLicense = $this->getLicenseArgs()["parentLicense"];

    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));

    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$license->getShortName(),$this->groupId])->andReturn([]);
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$parentLicense->getShortName(), $this->groupId])->andReturn($parentLicense);

    $request = $this->getRequestWithBody("POST", $body);

    $this->expectException(HttpNotFoundException::class);
    $this->licenseController->verifyLicense($request, new ResponseHelper(), ["shortname" => $license->getShortName()]);

  }

  /**
   * @test
   *  - # Test LicenseController::verifyLicense()
   *  - # Check if status codes match
   *  - # Check of expected and actual response bodies match
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testVerifyLicense()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $license = $this->getLicenseArgs()["license"];
    $parentLicense = $this->getLicenseArgs()["parentLicense"];

    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));

    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$license->getShortName(),$this->groupId])->andReturn($license);
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$parentLicense->getShortName(), $this->groupId])->andReturn($parentLicense);

    $this->licenseCandidatePlugin->shouldReceive("verifyCandidate")
      ->withArgs([$license->getId(),$license->getShortName(), $parentLicense->getId()])
      ->andReturn(true);
    $request = $this->getRequestWithBody("POST", $body);

    $info = new Info(200, 'Successfully verified candidate ('.$license->getShortName().')'.' as variant of ('.$parentLicense->getShortName().').', InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson([
      "code" => $info->getCode(),
      "message" => $info->getMessage(),
      "type" => $info->getType()
    ]);

    $actualResponse = $this->licenseController->verifyLicense($request, new ResponseHelper(), ["shortname" => $license->getShortName()]);

    $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));

  }

  /**
   * @return License[]
   */

  public function getLicenseArgs()
  {
    $license = new License(1,
      "MIT",
      "MIT License",
      "GNU GENERAL PUBLIC LICENSE Copyright (C) 1989 Free Software ",
    );

    $parentLicense = new License(3,
      "MPL-2.0",
      "MPL-2.0 License",
      "GNU GENERAL PUBLIC LICENSE Copyright (C) 1989 Free Software ",
    );

    return [
      "license" => $license,
      "parentLicense" => $parentLicense
    ];
  }

  /**
   * @test
   * Test LicenseController::mergeLicense()
   *  - # Check if HttpForbiddenException is thrown for unauthorized users
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testMergeLicenseNotAdmin()
   {
     $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
     $body = $this->streamFactory->createStream(json_encode([]));
     $request = $this->getRequestWithBody("POST", $body);

     $this->expectException(HttpForbiddenException::class);
     $this->licenseController->mergeLicense($request, new ResponseHelper(), []);

   }

  /**
   * @test
   *  Test LicenseController::mergeLicense()
   *  - # Check if the HttpBadRequestException is thrown when shortName arg is empty
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testMergeLicenseWithBadRequest()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $parentLicense = $this->getLicenseArgs()["parentLicense"];

    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));
    $request = $this->getRequestWithBody("POST", $body);

    $this->expectException(HttpBadRequestException::class);
    $this->licenseController->mergeLicense($request, new ResponseHelper(), ["shortname" => ""]);

  }

  /**
   * @test
   * Test LicenseController::mergeLicense()
   *  - # Check if HttpBadRequestException is thrown for license whose name is the same as it parent.
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testMergeLicenseWithSameName()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $parentLicense = $this->getLicenseArgs()["parentLicense"];

    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));
    $request = $this->getRequestWithBody("POST", $body);

    $this->expectException(HttpBadRequestException::class);
    $this->licenseController->mergeLicense($request, new ResponseHelper(), ["shortname" => $parentLicense->getShortName()]);

  }

  /**
   * @test
   *  Test LicenseController::mergeLicense()
   *  - # Check if HttpNotFoundException is thrown for notfound license.
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testMergeNotFoundLicense()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $parentLicense = $this->getLicenseArgs()["parentLicense"];
    $license = $this->getLicenseArgs()["license"];

    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$license->getShortName(),$this->groupId])->andReturn(null);
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$parentLicense->getShortName(), $this->groupId])->andReturn($parentLicense);

    $request = $this->getRequestWithBody("POST", $body);

    $this->expectException(HttpNotFoundException::class);
    $this->licenseController->mergeLicense($request, new ResponseHelper(), ["shortname" => $license->getShortName()]);

  }

  /**
   * @test
   * Test LicenseController::mergeLicense()
   *  - # Check if HttpNotFoundException is thrown for licenseCandidate that is not found.
   *  - # Check is the response status s 404
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testMergeLicenseWithNoCandidateLicense()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $parentLicense = $this->getLicenseArgs()["parentLicense"];
    $license = $this->getLicenseArgs()["license"];

    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$license->getShortName(),$this->groupId])->andReturn($license);
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$parentLicense->getShortName(), $this->groupId])->andReturn($parentLicense);
    $this->licenseCandidatePlugin->shouldReceive("getDataRow")
      ->withArgs([$license->getId()])->andReturn([]);

    $request = $this->getRequestWithBody("POST", $body);

    $this->expectException(HttpNotFoundException::class);
    $this->licenseController->mergeLicense($request, new ResponseHelper(), ["shortname" => $license->getShortName()]);

  }

  /**
   * @test
   *  Test LicenseController::mergeLicense()
   *  - # Check if HttpInternalServerErrorException is thrown for duplicate license name
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testMergeLicenseInternalServerError()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $parentLicense = $this->getLicenseArgs()["parentLicense"];
    $license = $this->getLicenseArgs()["license"];

    $vars = [
       "rf_shortname" => "AGPL-1.0-or-later",
       "rf_text" => "License by OJO.",
       "shortname" => "AGPL-1.0-or-later",
    ];
    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$license->getShortName(),$this->groupId])->andReturn($license);
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$parentLicense->getShortName(), $this->groupId])->andReturn($parentLicense);
    $this->licenseCandidatePlugin->shouldReceive("getDataRow")
      ->withArgs([$license->getId()])->andReturn($vars);
    $this->licenseCandidatePlugin->shouldReceive("mergeCandidate")
      ->withArgs([$license->getId(), $parentLicense->getId(), $vars ])->andReturn(false);
    $request = $this->getRequestWithBody("POST", $body);

    $this->expectException(HttpInternalServerErrorException::class);
    $this->licenseController->mergeLicense($request, new ResponseHelper(), ["shortname" => $license->getShortName()]);

  }

  /**
   * @test
   *  Test LicenseController::mergeLicense()
   *   - # Check if the status code is 200
   *   - # Check if expected and actual object match
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testMergeLicense()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $parentLicense = $this->getLicenseArgs()["parentLicense"];
    $license = $this->getLicenseArgs()["license"];

    $vars = [
      "rf_shortname" => "AGPL-1.0-or-later",
      "rf_text" => "License by OJO.",
      "shortname" => "AGPL-1.0-or-later",
    ];
    $body = $this->streamFactory->createStream(json_encode([
      "parentShortname" => $parentLicense->getShortName(),
    ]));
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$license->getShortName(),$this->groupId])->andReturn($license);
    $this->licenseDao->shouldReceive("getLicenseByShortName")
      ->withArgs([$parentLicense->getShortName(), $this->groupId])->andReturn($parentLicense);
    $this->licenseCandidatePlugin->shouldReceive("getDataRow")
      ->withArgs([$license->getId()])->andReturn($vars);
    $this->licenseCandidatePlugin->shouldReceive("mergeCandidate")
      ->withArgs([$license->getId(), $parentLicense->getId(), $vars ])->andReturn(true);
    $request = $this->getRequestWithBody("PUT", $body);

    $info = new Info(200, "Successfully merged candidate (". $parentLicense->getShortName() .") into (".$license->getShortName() .").", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());

    $actualResponse = $this->licenseController->mergeLicense($request, new ResponseHelper(), ["shortname" => $license->getShortName()]);
    $this->assertEquals(200, $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));

  }

  /**
   * @test
   *  Test LicenseController::getSuggestedLicense()
   *  - # Check is the HttpForbiddenException is thrown for unauthorized users
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testGetSuggestedLicensesNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_NONE;
    $request = $this->getEmptyRequest("GET");
    $this->expectException(HttpForbiddenException::class);
    $this->licenseController->getSuggestedLicense($request, new ResponseHelper(), []);
  }

  /**
   * @test
   *  Test LicenseController::getSuggestedLicense()
   *  - # Check if the status is 200
   *  - # Check if the actual and expected response bodies match
   * @return void
   * @throws \Fossology\UI\Api\Exceptions\HttpErrorException
   */
  public function testGetSuggestedLicenseWithBadRequest()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $body = $this->streamFactory->createStream(json_encode([
      "referenceText" => ""
    ]));

    $licenseCandidate = [
      "rf_pk" => 2,
      "rf_spdx_id"=> 4,
      "rf_fullname" => "GFDL-1.3-no-invariants-or-later",
      "rf_shortname" => "GFDL-1.1-no-invariants-only",
      "rf_text" => "License by OJO.",
      "rf_url" => "",
      "rf_notes" => "",
      "rf_risk" => "",

    ];

    $this->licenseCandidatePlugin->shouldReceive("suggestLicenseId")
      ->withArgs([$licenseCandidate['rf_text'],true])->andReturn([[2,3,5,4],[]]);
    $this->licenseCandidatePlugin->shouldReceive("getDataRow")
      ->withArgs([2,"ONLY license-ref"])->andReturn($licenseCandidate);
    $expectedResponse = (new ResponseHelper())->withJson([
      'id' => intval($licenseCandidate['rf_pk']),
      'spdxName' => $licenseCandidate['rf_spdx_id'],
      'shortName' => $licenseCandidate['rf_shortname'],
      'fullName' => $licenseCandidate['rf_fullname'],
      'text' => $licenseCandidate['rf_text'],
      'url' => $licenseCandidate['rf_url'],
      'notes' => $licenseCandidate['rf_notes'],
      'risk' => intval($licenseCandidate['rf_risk']),
      "highlights" => []
    ], 200);

    $request = $this->getRequestWithBody("GET", $body);
    $this->expectException(HttpBadRequestException::class);
    $actualResponse = $this->licenseController->getSuggestedLicense($request, new ResponseHelper(), []);
    $this->assertEquals(200,$actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));

  }






  /**
   * @test
   * -# Test for LicenseController::getCandidates()
   * -# Check if status is 200
   * -# Check if response-body matches
   */
  public function testGetCandidates()
  {
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V1);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->licenseCandidatePlugin->shouldReceive('getCandidateArrayData')->andReturn([]);

    $expectedResponse = (new ResponseHelper())->withJson([], 200);
    $actualResponse = $this->licenseController->getCandidates($request,
      new ResponseHelper(), null);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for LicenseController::getCandidates() as a non-admin user
   * -# Check if status is 403
   */
  public function testGetCandidatesNoAdmin()
  {
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn(ApiVersion::V1);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_READ;

    $this->expectException(HttpForbiddenException::class);

    $this->licenseController->getCandidates($request,
      new ResponseHelper(), null);
  }
}
