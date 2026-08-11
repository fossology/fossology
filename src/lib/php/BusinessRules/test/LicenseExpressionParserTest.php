<?php
/*
 SPDX-FileCopyrightText: (C) 2026 FOSSology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\BusinessRules;

class LicenseExpressionParserTest extends \PHPUnit\Framework\TestCase
{
  private function getCorpus()
  {
    $path = __DIR__ . '/../../../spdx-expression/contract/test-corpus.json';
    return json_decode(file_get_contents($path), true);
  }

  public function testValidExpressionsMatchSharedContract()
  {
    foreach ($this->getCorpus()['valid'] as $case) {
      $parser = new LicenseExpressionParser($case['input'], 1, 1);

      $this->assertTrue($parser->parse(), $case['name']);
      $this->assertSame($case['canonical'], $parser->getCanonical(), $case['name']);
      $this->assertEquals(
        json_decode($case['ast'], true),
        $parser->getContractAST(),
        $case['name']
      );
    }
  }

  public function testInvalidExpressionsMatchSharedContractErrors()
  {
    foreach ($this->getCorpus()['invalid'] as $case) {
      $parser = new LicenseExpressionParser($case['input'], 1, 1);

      $this->assertFalse($parser->parse(), $case['name']);
      $this->assertSame($case['error'], $parser->getErrorCode(), $case['name']);
    }
  }
}
