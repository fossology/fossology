<?php
/*
 SPDX-FileCopyrightText: © 2026 Rajay Singh <rajaysingh2007@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for OsselotController
 */

namespace Fossology\UI\Api\Test\Controllers;

use Fossology\UI\Api\Controllers\OsselotController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class OsselotControllerTest
 * @brief Test cases for OsselotController
 */
class OsselotControllerTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbHelper $dbHelper */
  private $dbHelper;

  /** @var OsselotController $osselotController */
  private $osselotController;

  protected function setUp() : void
  {
    $container = M::mock('ContainerBuilder');
    $this->dbHelper = M::mock(DbHelper::class);
    $restHelper = M::mock(RestHelper::class);
    $restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($restHelper);
    $this->osselotController = new OsselotController($container);
  }

  protected function tearDown() : void
  {
    M::close();
  }

  /**
   * @param string|null $value EnableOsselotReuse value, null to leave it unset
   * @return Request
   */
  private function prepare($value)
  {
    $GLOBALS['SysConf'] = ['SYSCONFIG' => []];
    if ($value !== null) {
      $GLOBALS['SysConf']['SYSCONFIG']['EnableOsselotReuse'] = $value;
    }
    return new Request("POST", new Uri("HTTP", "localhost"), new Headers(),
      [], [], (new StreamFactory())->createStream());
  }

  private function assertDisabled($response)
  {
    $response->getBody()->seek(0);
    $this->assertEquals(501, $response->getStatusCode());
    $this->assertEquals("OSSelot integration is disabled",
      json_decode($response->getBody()->getContents(), true)['message']);
  }

  /**
   * Values of EnableOsselotReuse which mean OSSelot is disabled
   */
  public static function disabledProvider()
  {
    return [
      'unchecked in admin UI' => ['false'],
      'not configured' => [null]
    ];
  }

  /**
   * @dataProvider disabledProvider
   */
  public function testGetPackageVersionsDisabled($value)
  {
    $request = $this->prepare($value);
    $this->assertDisabled($this->osselotController->getPackageVersions(
      $request, new ResponseHelper(), ['package' => 'zlib']));
  }

  /**
   * @dataProvider disabledProvider
   */
  public function testImportOsselotReportDisabled($value)
  {
    $request = $this->prepare($value);
    $this->dbHelper->shouldNotReceive('doesIdExist');
    $this->assertDisabled($this->osselotController->importOsselotReport(
      $request, new ResponseHelper(), ['id' => 2]));
  }

  public function testGetPackageVersionsEnabled()
  {
    $request = $this->prepare('true');
    $this->expectException(HttpBadRequestException::class);
    $this->osselotController->getPackageVersions($request,
      new ResponseHelper(), ['package' => ' ']);
  }
}
