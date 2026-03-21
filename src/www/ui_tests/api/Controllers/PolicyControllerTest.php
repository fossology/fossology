<?php
/*
 SPDX-FileCopyrightText: © 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Test\Controllers;

require_once dirname(__DIR__, 4) . '/lib/php/Plugin/FO_Plugin.php';

use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Mockery as M;
use Fossology\UI\Api\Controllers\PolicyController;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\Lib\Dao\PolicyDao;
use Slim\Psr7\Request;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @class PolicyControllerTest
 * @brief Test cases for PolicyController
 */
class PolicyControllerTest extends \PHPUnit\Framework\TestCase
{
  private $assertCountBefore;
  private $restHelper;
  private $policyDao;
  private $policyController;

  protected function setUp() : void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->restHelper = M::mock(RestHelper::class);
    $this->policyDao = M::mock(PolicyDao::class);

    $container->shouldReceive('get')->withArgs(['helper.restHelper'])->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(['dao.policy'])->andReturn($this->policyDao);
    
    $this->policyController = new PolicyController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    
    // Default to admin for most tests
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_CADMIN;
    $GLOBALS['container'] = $container;
  }

  protected function tearDown() : void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
    unset($_SESSION[Auth::USER_LEVEL]);
  }

  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  public function testGetPolicies()
  {
    $request = M::mock(ServerRequestInterface::class);
    $request->shouldReceive('getQueryParams')->andReturn([]);
    
    $expectedPolicies = [['rf_fk' => 100, 'policy_rank' => 0]];
    $this->policyDao->shouldReceive('getAllPolicies')->once()->andReturn($expectedPolicies);
    
    $actualResponse = $this->policyController->getPolicies($request, new ResponseHelper(), []);
    $this->assertEquals(200, $actualResponse->getStatusCode());
    
    $json = $this->getResponseJson($actualResponse);
    $this->assertEquals($expectedPolicies, $json);
  }

  public function testSetPolicySuccess()
  {
    $request = M::mock(ServerRequestInterface::class);
    $request->shouldReceive('getParsedBody')->andReturn(['licenseId' => 100, 'policy_rank' => 1]);
    $request->shouldReceive('getServerParams')->andReturn(['REMOTE_ADDR' => '127.0.0.1']);
    
    $this->restHelper->shouldReceive('getUserId')->andReturn(1);
    
    $this->policyDao->shouldReceive('setLicensePolicy')->once()
        ->withArgs([100, 1, 1, 'API', '127.0.0.1']);

    $actualResponse = $this->policyController->setPolicy($request, new ResponseHelper(), []);
    $this->assertEquals(201, $actualResponse->getStatusCode());
    $this->assertEquals("License policy updated successfully", $this->getResponseJson($actualResponse)['message']);
  }

  public function testSetPolicyInvalidRank()
  {
    $request = M::mock(ServerRequestInterface::class);
    $request->shouldReceive('getParsedBody')->andReturn(['licenseId' => 100, 'policy_rank' => 5]);
    
    $this->expectException(HttpBadRequestException::class);
    $this->policyController->setPolicy($request, new ResponseHelper(), []);
  }

  public function testSetPolicyUnauthorized()
  {
    unset($_SESSION[Auth::USER_LEVEL]); // No CADMIN
    $request = M::mock(ServerRequestInterface::class);
    $request->shouldReceive('getParsedBody')->andReturn(['licenseId' => 100, 'policy_rank' => 0]);
    
    $this->expectException(HttpForbiddenException::class);
    $this->policyController->setPolicy($request, new ResponseHelper(), []);
  }

  public function testDeletePolicy()
  {
    $request = M::mock(ServerRequestInterface::class);
    $request->shouldReceive('getParsedBody')->andReturn(['licenseId' => 100]);
    $request->shouldReceive('getServerParams')->andReturn(['REMOTE_ADDR' => '127.0.0.1']);
    
    $this->restHelper->shouldReceive('getUserId')->andReturn(1);
    
    $this->policyDao->shouldReceive('deleteLicensePolicy')->once()
        ->withArgs([100, 1, 'API', '127.0.0.1']);

    $actualResponse = $this->policyController->deletePolicy($request, new ResponseHelper(), []);
    $this->assertEquals(200, $actualResponse->getStatusCode());
  }

  public function testDeletePolicyUnauthorized()
  {
    unset($_SESSION[Auth::USER_LEVEL]);
    $request = M::mock(ServerRequestInterface::class);
    
    $this->expectException(HttpForbiddenException::class);
    $this->policyController->deletePolicy($request, new ResponseHelper(), []);
  }
}
