<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for ObligationController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\BusinessRules\ObligationMap;
use Fossology\UI\Api\Controllers\ObligationController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpConflictException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class ObligationControllerTest
 * @brief Unit tests for ObligationController
 */
class ObligationControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var integer $assertCountBefore
   * Assertions before running tests
   */
  private $assertCountBefore;

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
   * @var ObligationMap $obligationMap
   * ObligationMap mock
   */
  private $obligationMap;

  /**
   * @var ObligationController $obligationController
   * ObligationController object under test
   */
  private $obligationController;

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
    $this->dbManager = M::mock(DbManager::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->obligationMap = M::mock(ObligationMap::class);

    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);
    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array(
      'businessrules.obligationmap'))->andReturn($this->obligationMap);

    $this->obligationController = new ObligationController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->streamFactory = new StreamFactory();
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
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
   * Helper to build a POST /obligations request with the given body.
   * @param array $requestBody
   * @return Request
   */
  private function getCreateRequest($requestBody)
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $body = $this->streamFactory->createStream();
    $body->write(json_encode($requestBody));
    $body->seek(0);
    return new Request("POST", new Uri("HTTP", "localhost", 80,
      "/obligations"), $requestHeaders, [], [], $body);
  }

  /**
   * @test
   * -# Test for ObligationController::createObligation() to create a new
   *    obligation
   * -# Check if response is 201
   */
  public function testCreateObligation()
  {
    $requestBody = [
      "topic" => "Should preserve notice",
      "type" => "Obligation",
      "text" => "All notices from the package should be preserved.",
      "classification" => "yellow",
      "comment" => "Please respect the obligation"
    ];
    $request = $this->getCreateRequest($requestBody);

    $checkSql = "SELECT count(*) AS cnt FROM obligation_ref " .
      "WHERE ob_topic = $1 AND ob_text = $2";
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([$checkSql,
        [$requestBody["topic"], $requestBody["text"]], M::any()])
      ->andReturn(["cnt" => 0]);

    $assocData = [
      "ob_active" => true,
      "ob_type" => "Obligation",
      "ob_modifications" => "No",
      "ob_topic" => $requestBody["topic"],
      "ob_md5" => md5($requestBody["text"]),
      "ob_text" => $requestBody["text"],
      "ob_classification" => "yellow",
      "ob_text_updatable" => true,
      "ob_comment" => "Please respect the obligation"
    ];
    $this->dbManager->shouldReceive('insertTableRow')
      ->withArgs(["obligation_ref", $assocData, M::any(), "ob_pk"])
      ->andReturn(7);

    $info = new Info(201, 7, InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(),
      $info->getCode());

    $actualResponse = $this->obligationController->createObligation($request,
      new ResponseHelper(), []);
    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test for ObligationController::createObligation() associating
   *    licenses and candidate licenses with the new obligation
   * -# Check if response is 201 and licenses get associated
   */
  public function testCreateObligationWithLicenses()
  {
    $requestBody = [
      "topic" => "Should preserve notice",
      "text" => "All notices from the package should be preserved.",
      "licenses" => ["MIT"],
      "candidateLicenses" => ["Exotic"]
    ];
    $request = $this->getCreateRequest($requestBody);

    $checkSql = "SELECT count(*) AS cnt FROM obligation_ref " .
      "WHERE ob_topic = $1 AND ob_text = $2";
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([$checkSql,
        [$requestBody["topic"], $requestBody["text"]], M::any()])
      ->andReturn(["cnt" => 0]);

    $this->dbManager->shouldReceive('insertTableRow')
      ->withArgs(["obligation_ref", M::type('array'), M::any(), "ob_pk"])
      ->andReturn(7);

    $this->obligationMap->shouldReceive('getIdFromShortname')
      ->withArgs(["MIT", false])->andReturn([22]);
    $this->obligationMap->shouldReceive('associateLicenseFromLicenseList')
      ->withArgs([7, [22], false])->once()->andReturn(true);
    $this->obligationMap->shouldReceive('getIdFromShortname')
      ->withArgs(["Exotic", true])->andReturn([25]);
    $this->obligationMap->shouldReceive('associateLicenseFromLicenseList')
      ->withArgs([7, [25], true])->once()->andReturn(true);

    $actualResponse = $this->obligationController->createObligation($request,
      new ResponseHelper(), []);
    $this->assertEquals(201, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test for ObligationController::createObligation()
   * -# The request body has no topic
   * -# Check if response is 400
   */
  public function testCreateObligationNoTopic()
  {
    $requestBody = [
      "text" => "All notices from the package should be preserved."
    ];
    $request = $this->getCreateRequest($requestBody);
    $this->expectException(HttpBadRequestException::class);

    $this->obligationController->createObligation($request,
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for ObligationController::createObligation()
   * -# The request body has no text
   * -# Check if response is 400
   */
  public function testCreateObligationNoText()
  {
    $requestBody = [
      "topic" => "Should preserve notice"
    ];
    $request = $this->getCreateRequest($requestBody);
    $this->expectException(HttpBadRequestException::class);

    $this->obligationController->createObligation($request,
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for ObligationController::createObligation()
   * -# The request body has additional unknown properties
   * -# Check if response is 400
   */
  public function testCreateObligationExtraProperty()
  {
    $requestBody = [
      "topic" => "Should preserve notice",
      "text" => "All notices from the package should be preserved.",
      "bogus" => "not allowed"
    ];
    $request = $this->getCreateRequest($requestBody);
    $this->expectException(HttpBadRequestException::class);

    $this->obligationController->createObligation($request,
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for ObligationController::createObligation()
   * -# Non admin user cannot create an obligation
   * -# Check if response is 403
   */
  public function testCreateObligationNoAdmin()
  {
    $requestBody = [
      "topic" => "Should preserve notice",
      "text" => "All notices from the package should be preserved."
    ];
    $request = $this->getCreateRequest($requestBody);
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->expectException(HttpForbiddenException::class);

    $this->obligationController->createObligation($request,
      new ResponseHelper(), []);
  }

  /**
   * @test
   * -# Test for ObligationController::createObligation()
   * -# Simulate duplicate topic/text
   * -# Check if response is 409
   */
  public function testCreateObligationDuplicate()
  {
    $requestBody = [
      "topic" => "Should preserve notice",
      "text" => "All notices from the package should be preserved."
    ];
    $request = $this->getCreateRequest($requestBody);

    $checkSql = "SELECT count(*) AS cnt FROM obligation_ref " .
      "WHERE ob_topic = $1 AND ob_text = $2";
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([$checkSql,
        [$requestBody["topic"], $requestBody["text"]], M::any()])
      ->andReturn(["cnt" => 1]);
    $this->expectException(HttpConflictException::class);

    $this->obligationController->createObligation($request,
      new ResponseHelper(), []);
  }
}
