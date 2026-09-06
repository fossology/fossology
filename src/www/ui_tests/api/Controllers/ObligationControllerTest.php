<?php
/*
 SPDX-FileCopyrightText: © 2026 Bhuvan Somisetty <somisettybhuvan5@gmail.com>

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
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;

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
   * ObligationController object
   */
  private $obligationController;

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
    $this->obligationMap = M::mock(ObligationMap::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);

    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array(
      'businessrules.obligationmap'))->andReturn($this->obligationMap);

    $this->obligationController = new ObligationController($container);
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
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
   * @param ResponseHelper $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * @test
   * -# Test ObligationController::deleteObligation() as an admin for an
   *    existing obligation
   * -# Check if the obligation is deleted and response status is 200
   */
  public function testDeleteObligationAsAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $obligationId = 7;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["obligation_ref", "ob_pk", $obligationId])->andReturn(true);
    $this->obligationMap->shouldReceive('deleteObligation')
      ->withArgs([$obligationId])->once();

    $expectedResponse = new Info(200, "Successfully removed Obligation.",
      InfoType::INFO);
    $actualResponse = $this->obligationController->deleteObligation(null,
      new ResponseHelper(), ["id" => $obligationId]);

    $this->assertEquals($expectedResponse->getCode(),
      $actualResponse->getStatusCode());
    $this->assertEquals($expectedResponse->getArray(),
      $this->getResponseJson($actualResponse));
  }

  /**
   * @test
   * -# Test ObligationController::deleteObligation() as an admin for an
   *    obligation that does not exist
   * -# Check if HttpNotFoundException is thrown
   */
  public function testDeleteObligationNotFound()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(["obligation_ref", "ob_pk", 8])->andReturn(false);
    $this->expectException(HttpNotFoundException::class);

    $this->obligationController->deleteObligation(null, new ResponseHelper(),
      ["id" => 8]);
  }

  /**
   * @test
   * -# Regression test for a non-admin, write-scoped user calling
   *    ObligationController::deleteObligation() for an obligation that exists
   * -# Check if HttpForbiddenException is thrown and the obligation is not
   *    deleted
   */
  public function testDeleteObligationNotAdmin()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->obligationMap->shouldNotReceive('deleteObligation');
    $this->expectException(HttpForbiddenException::class);

    $this->obligationController->deleteObligation(null, new ResponseHelper(),
      ["id" => 7]);
  }

  /**
   * @test
   * -# Regression test for a non-admin user calling
   *    ObligationController::deleteObligation() for an obligation id that
   *    does not even exist
   * -# Check if HttpForbiddenException is thrown before the existence check,
   *    so a non-admin can not use the endpoint to probe which ids exist
   */
  public function testDeleteObligationNotAdminNonexistentId()
  {
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_WRITE;
    $this->dbHelper->shouldNotReceive('doesIdExist');
    $this->obligationMap->shouldNotReceive('deleteObligation');
    $this->expectException(HttpForbiddenException::class);

    $this->obligationController->deleteObligation(null, new ResponseHelper(),
      ["id" => 999999]);
  }
}
