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
}
