<?php
/*
 SPDX-FileCopyrightText: © 2026 Krrish Biswas

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\ReportImport\Test;

use Fossology\ReportImport\CycloneDxImportSource;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../agent/CycloneDxImportSource.php';

class CycloneDxImportSourceTest extends \PHPUnit\Framework\TestCase
{
    private $testFile;

    protected function setUp(): void
    {
        $this->testFile = tempnam(sys_get_temp_dir(), 'cdx_test_');
        $json = '{
          "bomFormat": "CycloneDX",
          "specVersion": "1.5",
          "version": 1,
          "components": [
            {
              "type": "file",
              "bom-ref": "item-123",
              "name": "test.c",
              "hashes": [
                {
                  "alg": "SHA-1",
                  "content": "a9993e364706816aba3e25717850c26c9cd0d89d"
                }
              ],
              "licenses": [
                {
                  "license": {
                    "id": "GPL-2.0-only",
                    "acknowledgement": "concluded"
                  }
                },
                {
                  "license": {
                    "id": "MIT",
                    "acknowledgement": "declared"
                  }
                }
              ],
              "evidence": {
                "licenses": [
                  {
                    "license": {
                      "id": "Apache-2.0"
                    }
                  }
                ],
                "copyright": [
                  {
                    "text": "Copyright 2024 Acme Corp.\nCopyright 2023 John Doe"
                  }
                ]
              }
            }
          ]
        }';
        file_put_contents($this->testFile, $json);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    public function testParseValidCycloneDx()
    {
        $source = new CycloneDxImportSource($this->testFile);
        $this->assertTrue($source->parse());
        $this->assertEquals("1.5", $source->getVersion());
        $this->assertEquals(["item-123" => "test.c"], $source->getAllFiles());
    }

    public function testGetHashesMap()
    {
        $source = new CycloneDxImportSource($this->testFile);
        $source->parse();
        $hashes = $source->getHashesMap("item-123");
        
        $this->assertArrayHasKey('sha1', $hashes);
        $this->assertEquals("a9993e364706816aba3e25717850c26c9cd0d89d", $hashes['sha1']);
    }

    public function testGetDataForFile()
    {
        $source = new CycloneDxImportSource($this->testFile);
        $source->parse();
        
        $data = $source->getDataForFile("item-123");
        
        // concluded licenses should only contain GPL-2.0-only since its acknowledgement is concluded
        $concluded = $data->getLicensesConcluded();
        $this->assertCount(1, $concluded);
        $this->assertEquals("GPL-2.0-only", $concluded[0]->getLicenseId());
        
        // info in file should contain MIT (declared) and Apache-2.0 (evidence)
        $infoInFile = $data->getLicenseInfosInFile();
        $this->assertCount(2, $infoInFile);
        
        $ids = [];
        foreach ($infoInFile as $item) {
            $ids[] = $item->getLicenseId();
        }
        $this->assertContains("MIT", $ids);
        $this->assertContains("Apache-2.0", $ids);
        
        // Copyrights
        $copyrights = $data->getCopyrightTexts();
        $this->assertCount(2, $copyrights);
        $this->assertContains("Copyright 2024 Acme Corp.", $copyrights);
        $this->assertContains("Copyright 2023 John Doe", $copyrights);
    }
}
