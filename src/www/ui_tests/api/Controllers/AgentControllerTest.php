<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for AgentController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\UI\Api\Controllers\AgentController;
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
 * @class AgentControllerTest
 * @brief Tests for AgentController
 */
class AgentControllerTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @var AgentController $agentController
   * AgentController object to test
   */
  private $agentController;

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
   * @var M\MockInterface $agentDao
   * AgentDao mock
   */
  private $agentDao;

  /**
   * @var StreamFactory $streamFactory
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
    $this->agentDao = M::mock(AgentDao::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array('dao.agent'))
      ->andReturn($this->agentDao);

    $this->agentController = new AgentController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->streamFactory = new StreamFactory();
  }

  /**
   * @brief Cleanup mockery objects
   * @see PHPUnit_Framework_TestCase::tearDown()
   */
  protected function tearDown() : void
  {
    M::close();
    $newCount = \Hamcrest\MatcherAssert::getCount();
    $this->addToAssertionCount($newCount - $this->assertCountBefore);
  }

  /**
   * Helper function to get JSON array from response
   *
   * @param ResponseHelper $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  private function buildPostRequest()
  {
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('Content-Type', 'application/json');
    return new Request("POST", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $this->streamFactory->createStream());
  }

  /**
   * @test
   * -# Test AgentController::renewMonkAgent() for a valid renewal request
   * -# Check if response status is 200 and AgentDao::renewCurrentAgent() is called
   */
  public function testRenewMonkAgent()
  {
    $_SESSION['UserLevel'] = 10;

    $this->agentDao->shouldReceive('getCurrentAgentRef')
      ->withArgs(['monk'])
      ->andReturn(new AgentRef(1, 'monk', '1.0.0'));
    $this->agentDao->shouldReceive('renewCurrentAgent')
      ->withArgs(['monk'])
      ->once()
      ->andReturn(true);

    $mess = "Monk agent revision has been renewed.";
    $info = new Info(200, $mess, InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($info->getArray(), $info->getCode());

    $actualResponse = $this->agentController->renewMonkAgent(
      $this->buildPostRequest(), new ResponseHelper(), null);

    $this->assertEquals($expectedResponse->getStatusCode(), $actualResponse->getStatusCode());
    $this->assertEquals($this->getResponseJson($expectedResponse),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test AgentController::renewMonkAgent() when the Monk agent has never run
   * -# Check if HttpNotFoundException is thrown and renewal is never attempted
   */
  public function testRenewMonkAgentNotFound()
  {
    $_SESSION['UserLevel'] = 10;

    $this->agentDao->shouldReceive('getCurrentAgentRef')
      ->withArgs(['monk'])
      ->andReturn(new AgentRef(0, null, null));
    $this->agentDao->shouldNotReceive('renewCurrentAgent');

    $this->expectException(HttpNotFoundException::class);
    $this->agentController->renewMonkAgent(
      $this->buildPostRequest(), new ResponseHelper(), null);
  }

  /**
   * @test
   * -# Test AgentController::renewMonkAgent() for non admin users
   * -# Check if access is denied with HttpForbiddenException
   */
  public function testRenewMonkAgentUserNotAdmin()
  {
    $_SESSION['UserLevel'] = 0;

    $this->agentDao->shouldNotReceive('getCurrentAgentRef');
    $this->agentDao->shouldNotReceive('renewCurrentAgent');

    $this->expectException(HttpForbiddenException::class);
    $this->agentController->renewMonkAgent(
      $this->buildPostRequest(), new ResponseHelper(), null);
  }
}
