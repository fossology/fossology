<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for BulkHistory model
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Slim\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Mockery as M;


/**
 * @class ApiVersion
 * @brief Tests for ApiVersion model
 */
class ApiVersionTest extends TestCase
{
  /**
   * @test
   * -# Test for ApiVersion::getVersion() when $version is V1
   * -# Check if the ApiVersion return of the same as the one passed.
   */
  public function testGetVersionV1()
  {
    $this->testGetVersion(ApiVersion::V1);
  }
  /**
   * @test
   * -# Test for ApiVersion::getVersion() when $version is V2
   * -# Check if the ApiVersion return of the same as the one passed.
   */
  public function testGetVersionV2()
  {
    $this->testGetVersion();
  }

  /**
   * @param $version version to test
   * @return void
   */
  private function testGetVersion($version = ApiVersion::V2)
  {
    $request = M::mock(Request::class);
    $request->shouldReceive('getAttribute')->andReturn($version);
    $this->assertSame($version, ApiVersion::getVersion($request));
  }
}
