<?php
/*
 SPDX-FileCopyrightText: © 2026 Contributors to FOSSology

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use PHPUnit\Framework\TestCase;

/**
 * @class ReportUtilsExcludeIrrelevantTest
 * @brief Tests for ReportUtils excludeIrrelevant parameter
 */
class ReportUtilsExcludeIrrelevantTest extends TestCase
{
  /**
   * @brief Test that getFilesWithLicensesFromClearings accepts excludeIrrelevant parameter
   */
  public function testMethodSignatureIncludesExcludeIrrelevant()
  {
    $reflection = new \ReflectionMethod(ReportUtils::class, 'getFilesWithLicensesFromClearings');
    $parameters = $reflection->getParameters();

    // Should have 5 parameters now
    $this->assertCount(5, $parameters);

    // The last parameter should be excludeIrrelevant with default true
    $lastParam = $parameters[4];
    $this->assertEquals('excludeIrrelevant', $lastParam->getName());
    $this->assertTrue($lastParam->isDefaultValueAvailable());
    $this->assertTrue($lastParam->getDefaultValue());
  }
}
