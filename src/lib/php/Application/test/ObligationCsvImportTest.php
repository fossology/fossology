<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Application;

use Fossology\Lib\Db\DbManager;
use Mockery as M;

/** Tests for ObligationCsvImport. */
class ObligationCsvImportTest extends \PHPUnit\Framework\TestCase
{
  private $assertCountBefore;
  private $dbManager;
  private $obligationMap;
  private $tempFiles = [];

  protected function setUp() : void
  {
    global $container;

    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
    $this->dbManager = M::mock(DbManager::class);
    $this->obligationMap = M::mock('obligationMap');
    $container = M::mock('ContainerBuilder');
    $container->shouldReceive('get')->with('businessrules.obligationmap')
      ->andReturn($this->obligationMap);
  }

  protected function tearDown() : void
  {
    foreach ($this->tempFiles as $tempFile) {
      if (file_exists($tempFile)) {
        unlink($tempFile);
      }
    }
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    M::close();
  }

  /** @test */
  public function testHandleFileResetsHeaderBetweenImports()
  {
    $stmtInsert = 'Fossology\Lib\Application\ObligationCsvImport::handleCsvObligation.insert';

    $this->dbManager->shouldReceive('getSingleRow')->once()
      ->with(
        'SELECT ob_pk FROM obligation_ref WHERE ob_topic=$1 AND ob_md5=md5($2)',
        ['Topic One', 'Text One']
      )->andReturn(false);
    $this->dbManager->shouldReceive('getSingleRow')->once()
      ->with(
        'SELECT ob_pk FROM obligation_ref WHERE ob_topic=$1 AND ob_md5=md5($2)',
        ['Topic Two', 'Text Two']
      )->andReturn(false);

    $this->dbManager->shouldReceive('prepare')->twice()
      ->with($stmtInsert, M::type('string'));
    $this->dbManager->shouldReceive('execute')->once()
      ->with($stmtInsert, ['Obligation', 'Topic One', 'Text One', 'green', 'yes', 'First comment']);
    $this->dbManager->shouldReceive('execute')->once()
      ->with($stmtInsert, ['Risk', 'Topic Two', 'Text Two', 'red', 'no', 'Second comment']);
    $this->dbManager->shouldReceive('fetchArray')->once()
      ->andReturn(['ob_pk' => 11]);
    $this->dbManager->shouldReceive('fetchArray')->once()
      ->andReturn(['ob_pk' => 12]);
    $this->dbManager->shouldReceive('freeResult')->twice();

    $importer = new ObligationCsvImport($this->dbManager);

    $firstFile = $this->createTempFile(
      "type,topic,text,classification,modifications,comment,licnames,candidatenames\n" .
      "Obligation,Topic One,Text One,green,yes,First comment,,\n"
    );
    $secondFile = $this->createTempFile(
      "text,topic,type,classification,modifications,comment,licnames,candidatenames\n" .
      "Text Two,Topic Two,Risk,red,no,Second comment,,\n"
    );

    $firstResult = $importer->handleFile($firstFile, 'csv');
    $secondResult = $importer->handleFile($secondFile, 'csv');

    $this->assertStringContainsString("Obligation with id=11 was added successfully.", $firstResult);
    $this->assertStringContainsString("Read csv: 1 obligations", $firstResult);
    $this->assertStringContainsString("Obligation with id=12 was added successfully.", $secondResult);
    $this->assertStringContainsString("Read csv: 1 obligations", $secondResult);
  }

  /** @test */
  public function testHandleFileReportsZeroObligationsForEmptyCsv()
  {
    $importer = new ObligationCsvImport($this->dbManager);
    $emptyFile = $this->createTempFile('');

    $result = $importer->handleFile($emptyFile, 'csv');

    $this->assertSame('Read csv: 0 obligations', $result);
  }

  /** @test */
  public function testHandleFileHandlesMalformedJsonGracefully()
  {
    $importer = new ObligationCsvImport($this->dbManager);
    $jsonFile = $this->createTempFile('{bad json');

    $result = $importer->handleFile($jsonFile, 'json');

    $this->assertStringContainsString('Error decoding JSON: Syntax error', $result);
    $this->assertStringContainsString('Read json:0 obligations', $result);
  }

  private function createTempFile($content)
  {
    $tempFile = tempnam(sys_get_temp_dir(), 'fossology-obligation-import-');
    file_put_contents($tempFile, $content);
    $this->tempFiles[] = $tempFile;
    return $tempFile;
  }
}
