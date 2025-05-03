<?php

/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for ApiVersion model
 */
namespace Fossology\UI\Api\Test\Models;


use Fossology\UI\Api\Models\ApiVersion;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class ApiVersionTest extends TestCase
{
  /**
   * Test that getVersion returns the correct version when ATTRIBUTE_NAME is set.
   */
  public function testGetVersionReturnsSetVersion()
  {
    $requestMock = $this->createMock(ServerRequestInterface::class);
    $requestMock->method('getAttribute')
      ->with(ApiVersion::ATTRIBUTE_NAME, ApiVersion::V1)
      ->willReturn(ApiVersion::V2);

    $this->assertEquals(ApiVersion::V2, ApiVersion::getVersion($requestMock));
  }

  /**
   * Test that getVersion returns the default version (V1) when ATTRIBUTE_NAME is not set.
   */
  public function testGetVersionReturnsDefaultVersion()
  {
    $requestMock = $this->createMock(ServerRequestInterface::class);
    $requestMock->method('getAttribute')
      ->with(ApiVersion::ATTRIBUTE_NAME, ApiVersion::V1)
      ->willReturn(ApiVersion::V1);

    $this->assertEquals(ApiVersion::V1, ApiVersion::getVersion($requestMock));
  }

  /**
   * Test that getVersionFromUri returns V2 when URI contains /api/v2
   */
  public function testGetVersionFromUriV2()
  {
    $_SERVER['REQUEST_URI'] = '/api/v2/someEndpoint';
    $this->assertEquals(ApiVersion::V2, ApiVersion::getVersionFromUri());
  }

  /**
   * Test that getVersionFromUri returns V1 when URI contains /api/v2
   */
  public function testGetVersionFromUriV1()
  {
    $_SERVER['REQUEST_URI'] = '/api/v1/someEndpoint';
    $this->assertEquals(ApiVersion::V1, ApiVersion::getVersionFromUri());
  }
}
