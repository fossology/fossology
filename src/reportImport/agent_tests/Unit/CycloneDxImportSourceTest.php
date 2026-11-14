<?php
/*
 SPDX-FileCopyrightText: © 2026 Krrish Biswas

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\ReportImport\Test;

use Fossology\ReportImport\CycloneDxImportSource;
use Fossology\ReportImport\ReportImportDataItem;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../agent/CycloneDxImportSource.php';

class CycloneDxImportSourceTest extends \PHPUnit\Framework\TestCase
{
  private $testFile;

  protected function setUp(): void
  {
    $this->testFile = tempnam(sys_get_temp_dir(), 'cdx_test_');
  }

  protected function tearDown(): void
  {
    if (file_exists($this->testFile)) {
      unlink($this->testFile);
    }
  }

  private function writeBom(array $components, $specVersion = "1.5")
  {
    file_put_contents($this->testFile, json_encode([
      "bomFormat" => "CycloneDX",
      "specVersion" => $specVersion,
      "version" => 1,
      "components" => $components
    ]));
    $source = new CycloneDxImportSource($this->testFile);
    $this->assertTrue($source->parse());
    return $source;
  }

  private function licenseIds(array $items)
  {
    return array_map(function (ReportImportDataItem $item) {
      return $item->getLicenseId();
    }, $items);
  }

  private function sampleComponent()
  {
    return [
      "type" => "file",
      "bom-ref" => "item-123",
      "name" => "test.c",
      "hashes" => [["alg" => "SHA-1", "content" => "a9993e364706816aba3e25717850c26c9cd0d89d"]],
      "licenses" => [
        ["license" => ["id" => "GPL-2.0-only", "acknowledgement" => "concluded"]],
        ["license" => ["id" => "MIT", "acknowledgement" => "declared"]],
        ["license" => ["id" => "BSD-3-Clause"]]
      ],
      "evidence" => [
        "licenses" => [["license" => ["id" => "Apache-2.0"]]],
        "copyright" => [["text" => "Copyright 2024 Acme Corp.\nCopyright 2023 John Doe"]]
      ]
    ];
  }

  public function testParseValidCycloneDx()
  {
    $source = $this->writeBom([$this->sampleComponent()]);
    $this->assertEquals("1.5", $source->getVersion());
    $this->assertEquals(["item-123" => "test.c"], $source->getAllFiles());
  }

  public function testParseRejectsNonCycloneDxJson()
  {
    file_put_contents($this->testFile, '{"spdxVersion": "SPDX-2.3"}');
    $source = new CycloneDxImportSource($this->testFile);
    $this->assertFalse($source->parse());

    file_put_contents($this->testFile, 'not json');
    $source = new CycloneDxImportSource($this->testFile);
    $this->assertFalse($source->parse());
  }

  public function testGetHashesMap()
  {
    $source = $this->writeBom([$this->sampleComponent()]);
    $hashes = $source->getHashesMap("item-123");
    $this->assertEquals("a9993e364706816aba3e25717850c26c9cd0d89d", $hashes['sha1']);
  }

  public function testOnlyExplicitlyConcludedLicensesAreConcluded()
  {
    $source = $this->writeBom([$this->sampleComponent()]);
    $data = $source->getDataForFile("item-123");

    $this->assertEquals(["GPL-2.0-only"],
      $this->licenseIds($data->getLicensesConcluded()));
    // declared, unmarked and evidence licenses are all info-in-file
    $this->assertEquals(["MIT", "BSD-3-Clause", "Apache-2.0"],
      $this->licenseIds($data->getLicenseInfosInFile()));

    $this->assertEquals(["Copyright 2024 Acme Corp.", "Copyright 2023 John Doe"],
      $data->getCopyrightTexts());
  }

  public function testExpressionAcknowledgementAndSplitting()
  {
    $source = $this->writeBom([[
      "bom-ref" => "c1",
      "name" => "a.c",
      "licenses" => [
        ["expression" => "(MIT OR Apache-2.0) AND LicenseRef-fossology-My%20License",
         "acknowledgement" => "concluded"],
        ["expression" => "GPL-2.0-only WITH Classpath-exception-2.0"]
      ]
    ]]);
    $data = $source->getDataForFile("c1");

    $this->assertEquals(["MIT", "Dual-license", "Apache-2.0", "My License"],
      $this->licenseIds($data->getLicensesConcluded()));
    $this->assertEquals(["GPL-2.0-only", "Classpath-exception-2.0"],
      $this->licenseIds($data->getLicenseInfosInFile()));
  }

  public function testCustomTextLicenseRefIsStripped()
  {
    $md5 = md5("some text");
    $source = $this->writeBom([[
      "bom-ref" => "c1",
      "name" => "a.c",
      "licenses" => [[
        "license" => [
          "name" => "LicenseRef-fossology-Custom-$md5",
          "text" => ["content" => base64_encode("some text"), "encoding" => "base64"]
        ]
      ]]
    ]]);
    $items = $source->getDataForFile("c1")->getLicenseInfosInFile();
    $this->assertCount(1, $items);
    $this->assertEquals("Custom", $items[0]->getLicenseId());
    $this->assertTrue($items[0]->isSetCustomText());
    $this->assertEquals("some text", $items[0]->getCustomText());
  }

  public function testTopLevelCopyrightFallbackAndZeroLine()
  {
    $source = $this->writeBom([[
      "bom-ref" => "c1",
      "name" => "a.c",
      "copyright" => "0\n\nCopyright (c) 2020 Foo"
    ]]);
    $this->assertEquals(["0", "Copyright (c) 2020 Foo"],
      $source->getDataForFile("c1")->getCopyrightTexts());
  }

  public function testComponentsWithoutBomRefDoNotCollide()
  {
    $source = $this->writeBom([
      ["name" => "dup.c"],
      ["name" => "dup.c"],
      ["bom-ref" => "component-1", "name" => "x.c"]
    ]);
    $files = $source->getAllFiles();
    $this->assertCount(3, $files);
    $this->assertEquals(2, count(array_keys($files, "dup.c")));
  }

  public function testGeneratedFallbackDoesNotOverwriteAnExistingComponent()
  {
    $source = $this->writeBom([
      ["type" => "file", "name" => "a.c", "bom-ref" => "component-2-2"],
      ["type" => "file", "name" => "b.c", "bom-ref" => "component-2"],
      ["type" => "file", "name" => "c.c"]
    ], "1.7");
    $files = $source->getAllFiles();
    $this->assertCount(3, $files);
    $this->assertSame("a.c", $files["component-2-2"]);
    $this->assertSame("b.c", $files["component-2"]);
    $this->assertContains("c.c", $files);
  }
}
