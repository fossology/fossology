<?php
/*
 SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for MaintenanceController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\MaintenanceController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Uri;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;

require_once dirname(__DIR__, 4) . '/lib/php/Plugin/FO_Plugin.php';

/**
 * @class MaintenanceControllerTest
 * @brief Tests for MaintenanceController
 */
class MaintenanceControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var string YAML_LOC
   * Location of openapi.yaml file
   */
  const YAML_LOC = __DIR__ . '/../../../ui/api/documentation/openapi.yaml';

  /**
   * @var MaintenanceController $maintenanceController
   * MaintenanceController object to test
   */
  private $maintenanceController;

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
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;

  /**
   * @var Auth $auth
   * Auth mock
   */
  private $auth;

  /**
   * @var M\MockInterface $maintagentPlugin
   * maintagentPlugin mock
   */
  private $maintagentPlugin;

  /**
   * @var $rq
   */
  private $rq;

  /**
   * @var array $OPTIONS
   */
  private $OPTIONS =[];
  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  protected function setUp() : void
  {
    global $container;
    $this->rq = [
      "options" => ["A","F","g","l","o"],
      "logsDate"=>"2021-08-19",
      "goldDate"=>"2022-07-16"
    ];

    $this->OPTIONS =[
      "A"=>"Run all maintenance operations.",
      "F"=>"Validate folder contents.",
      "g"=>"Remove orphaned gold files.",
      "o"=>"Remove older gold files from repository.",
      "l"=>"Remove older log files from repository."
    ];
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->userDao = M::mock(UserDao::class);
    $this->auth = M::mock(Auth::class);

    $this->maintagentPlugin = M::mock('maintagent');

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getUserDao')
      ->andReturn($this->userDao);

    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(array('maintagent'))
      ->andReturn($this->maintagentPlugin);

    $this->auth->shouldReceive('isAdmin')->andReturn(true);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $this->maintenanceController = new MaintenanceController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->dbManager = M::mock(DbManager::class);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);
    $this->streamFactory = new StreamFactory();

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
   * @test
   * -# Test MaintenanceController::testCreateMaintenance() for valid create maintenance request
   * -# Check if response status is 201
   */
  public function testCreateMaintenance()
  {
    $_SESSION['UserLevel'] = 10;

    $rq = [
      "options" => ["A","F","g","l","o"],
      "logsDate"=>"2021-08-19",
      "goldDate"=>"2022-07-16"
    ];

     $OPTIONS =[
      "A"=>"Run all maintenance operations.",
      "F"=>"Validate folder contents.",
      "g"=>"Remove orphaned gold files.",
      "o"=>"Remove older gold files from repository.",
      "l"=>"Remove older log files from repository."
    ];

    $alteredOptions = array();
    foreach ($rq['options'] as $key) {
      $alteredOptions[$key] = $key;
    }
    $body = $rq;
    $body['options']  = $alteredOptions;

    $this->maintagentPlugin->shouldReceive('getOptions')->andReturn($OPTIONS);

    $mess = _("The maintenance job has been queued");

    $this->maintagentPlugin->shouldReceive('handle')->withArgs([$body])->andReturn($mess);

    $info = new Info(201, $mess, InfoType::INFO);


    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());

    $reqBody = $this->streamFactory->createStream(json_encode(
      $rq
    ));

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);


    $actualResponse = $this->maintenanceController->createMaintenance($request, new ResponseHelper(), null);

    $this->assertEquals($expectedResponse->getStatusCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test MaintenanceController::testCreateMaintenance() for valid create maintenance
   * -# Check if access is denied with HttpForbiddenException  for non admin users
   */
  public function testCreateMaintenanceUserNotAdmin()
  {
    $_SESSION['UserLevel'] = 0;

    $alteredOptions = array();
    foreach ($this->rq['options'] as $key) {
      $alteredOptions[$key] = $key;
    }
    $body = $this->rq;
    $body['options']  = $alteredOptions;

    $this->maintagentPlugin->shouldReceive('getOptions')->andReturn($this->OPTIONS);

    $mess = _("The maintenance job has been queued");

    $this->maintagentPlugin->shouldReceive('handle')->withArgs([$body])->andReturn($mess);
    $reqBody = $this->streamFactory->createStream(json_encode(
      $this->rq
    ));

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);

    $this->expectException(HttpForbiddenException::class);
    $this->maintenanceController->createMaintenance($request, new ResponseHelper(), null);
  }

  /**
   * @test
   * -# Test MaintenanceController::testCreateMaintenance() for valid create maintenance
   * -# Check if access is denied with HttpForbiddenException  for non admin users
   */
  public function testCreateMaintenanceWithBadRequest()
  {
    $_SESSION['UserLevel'] = 10;

    $this->rq["options"] = [];

    $reqBody = $this->streamFactory->createStream(json_encode(
      $this->rq
    ));
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);

    $this->expectException(HttpBadRequestException::class);
    $this->maintenanceController->createMaintenance($request, new ResponseHelper(), null);
  }


  /**
   * @test
   * -# Test MaintenanceController::testCreateMaintenance() for valid create maintenance
   * -# Check if  HttpNotFoundException is thrown when unknown key is encountered
   */
  public function testCreateMaintenanceOptionKeyNotFound()
  {
    $_SESSION['UserLevel'] = 10;

    array_push($this->rq["options"],"M");
    $alteredOptions = array();
    foreach ($this->rq['options'] as $key) {
      $alteredOptions[$key] = $key;
    }
    $body = $this->rq;
    $body['options']  = $alteredOptions;

    $this->maintagentPlugin->shouldReceive('getOptions')->andReturn($this->OPTIONS);

    $mess = _("The maintenance job has been queued");

    $this->maintagentPlugin->shouldReceive('handle')->withArgs([$body])->andReturn($mess);
    $reqBody = $this->streamFactory->createStream(json_encode(
      $this->rq
    ));

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);

    $this->expectException(HttpNotFoundException::class);
    $this->maintenanceController->createMaintenance($request, new ResponseHelper(), null);
  }

  /**
   * @test
   * -# Test MaintenanceController::testCreateMaintenance() for valid create maintenance
   * -# Check if  HttBadRequestException is thrown when GoldDate is not provided
   */
  public function testCreateMaintenanceInvalidGoldDate()
  {
    $_SESSION['UserLevel'] = 10;

    $req = $this->rq;
    $req["goldDate"] = "";


    $alteredOptions = array();
    foreach ($req['options'] as $key) {
      $alteredOptions[$key] = $key;
    }
    $body = $req;
    $body['options']  = $alteredOptions;

    $this->maintagentPlugin->shouldReceive('getOptions')->andReturn($this->OPTIONS);

    $mess = _("The maintenance job has been queued");

    $this->maintagentPlugin->shouldReceive('handle')->withArgs([$body])->andReturn($mess);
    $reqBody = $this->streamFactory->createStream(json_encode(
      $req
    ));

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);

    $this->expectException(HttpBadRequestException::class);
    $this->maintenanceController->createMaintenance($request, new ResponseHelper(), null);
  }


  /**
   * @test
   * -# Test MaintenanceController::testCreateMaintenance() for valid create maintenance
   * -# Check if  HttBadRequestException is thrown when LogsDate is not provided
   */
  public function testCreateMaintenanceInvalidLogsDate()
  {
    $_SESSION['UserLevel'] = 10;

    $req = $this->rq;
    $req["logsDate"] = "";
    $alteredOptions = array();
    foreach ($req['options'] as $key) {
      $alteredOptions[$key] = $key;
    }
    $body = $req;
    $body['options']  = $alteredOptions;

    $this->maintagentPlugin->shouldReceive('getOptions')->andReturn($this->OPTIONS);

    $mess = _("The maintenance job has been queued");

    $this->maintagentPlugin->shouldReceive('handle')->withArgs([$body])->andReturn($mess);
    $reqBody = $this->streamFactory->createStream(json_encode(
      $req
    ));

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    $request = new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $reqBody);

    $this->expectException(HttpBadRequestException::class);
    $this->maintenanceController->createMaintenance($request, new ResponseHelper(), null);
  }

}
