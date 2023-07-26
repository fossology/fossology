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
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\LicenseController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\License;
use Fossology\UI\Api\Models\Obligation;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;
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

    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $this->restHelper->shouldReceive('getPlugin')->withArgs(["admin_license_candidate"])->andReturn($this->licenseCandidatePlugin);
    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
    $this->restHelper->shouldReceive('getUserDao')->andReturn($this->userDao);


    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('admin_license_from_csv'))->andReturn($this->adminLicensePlugin);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
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
  private function traslateLicenseToDb($licenses)
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
    $info = new Info(404,
      "No license found with short name '{$licenseShortName}'.",
      InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());

    $actualResponse = $this->licenseController->getLicense($request,
      new ResponseHelper(), ['shortname' => $licenseShortName]);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
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
      ->andReturn($this->traslateLicenseToDb($licenses));

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

    $info = new Info(400, "Can not exceed total pages: 1", InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withHeader("X-Total-Pages", 1)
      ->withJson($info->getArray(), $info->getCode());

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

    $info = new Info(400, "Property 'shortName' is required.",
      InfoType::ERROR);
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

    $info = new Info(403, "Need to be admin to create non-candidate license.",
      InfoType::ERROR);
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

    $info = new Info(409, "License with shortname '" .
      $license->getShortName() . "' already exists!", InfoType::ERROR);
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

    $info = new Info(403, "Operation not permitted for this group.",
      InfoType::ERROR);
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

    $info = new Info(403, "Only admin can edit main licenses.",
      InfoType::ERROR);
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
   * -# Check if reponse-body matches
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
   * -# Check if reponse-body matches
   */
  public function testDeleteAdminLicenseCandidateNotAdmin(){
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $id = 1;
    $this->auth->shouldReceive('isAdmin')->andReturn(false);
    $expectedResponse = new Info(403, "Only admin can perform this operation.", InfoType::ERROR);
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
   * -# User is admin
   * -# License-candidate is doesn't exist
   * -# Check if response is 404
   * -# Check if reponse-body matches
   */
  public function testDeleteAdminLicenseCandidateNotFound(){
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $id = 1;
    $this->auth->shouldReceive('isAdmin')->andReturn(true);
    $this->licenseCandidatePlugin->shouldReceive('getDataRow')->withArgs([$id])->andReturn(false);
    $res = new Response('true',Response::HTTP_OK,array('Content-type'=>'text/plain'));
    $this->licenseCandidatePlugin->shouldReceive("doDeleteCandidate")->withArgs([$id])->andReturn($res);
    $expectedResponse = new Info(404, "License candidate not found.", InfoType::ERROR);
    $actualResponse = $this->licenseController->deleteAdminLicenseCandidate(null,
      new ResponseHelper(), ["id" => $id]);
    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }


  /**
   * @test
   * -# Test for LicenseController::getCandidates()
   * -# Check if status is 200
   * -# Check if response-body matches
   */
  public function testGetCandidates()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->licenseCandidatePlugin->shouldReceive('getCandidateArrayData')->andReturn([]);

    $expectedResponse = (new ResponseHelper())->withJson([], 200);
    $actualResponse = $this->licenseController->getCandidates(null,
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
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_READ;

    $info = new Info(403, "You are not allowed to access the endpoint.", InfoType::ERROR);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());
    $actualResponse = $this->licenseController->getCandidates(null,
      new ResponseHelper(), null);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }
}
