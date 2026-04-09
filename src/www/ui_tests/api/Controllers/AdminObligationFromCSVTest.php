<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Api\Test\Controllers
{
  use Fossology\UI\Page\AdminObligationFromCSV;
  use Mockery as M;
  use Symfony\Component\HttpFoundation\File\UploadedFile;

  /** Tests for AdminObligationFromCSV. */
  class AdminObligationFromCSVTest extends \PHPUnit\Framework\TestCase
  {
    public static $functions;
    private $assertCountBefore;
    private $obligationCsvImport;
    private $tempFiles = [];

    protected function setUp() : void
    {
      global $container;
      global $SysConf;

      $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
      self::$functions = M::mock(\stdClass::class);
      self::$functions->shouldReceive('register_plugin')->andReturnNull();

      $container = M::mock('ContainerBuilder');
      $this->obligationCsvImport = M::mock('obligationCsvImport');
      $dummyService = M::mock(\stdClass::class);

      $container->shouldReceive('get')->with('app.obligation_csv_import')
        ->andReturn($this->obligationCsvImport);
      $container->shouldReceive('get')->with('session')->andReturn($dummyService);
      $container->shouldReceive('get')->with('twig.environment')->andReturn($dummyService);
      $container->shouldReceive('get')->with('logger')->andReturn($dummyService);
      $container->shouldReceive('get')->with('ui.component.menu')->andReturn($dummyService);
      $container->shouldReceive('get')->with('ui.component.micromenu')->andReturn($dummyService);

      $SysConf = [
        'DIRECTORIES' => [
          'LOGDIR' => sys_get_temp_dir(),
        ],
        'SYSCONFIG' => [
          'LicenseDBBaseURL' => '',
          'LicenseDBContentObligations' => '',
          'LicenseDBHealth' => '',
          'LicenseDBToken' => 'test-token',
        ],
      ];
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
    public function testHandleFileUploadUsesDefaultCsvControlsForEmptyStrings()
    {
      $adminObligationFromCsv = new AdminObligationFromCSV();
      $uploadedFile = $this->getUploadedCsvFile();

      $this->obligationCsvImport->shouldReceive('setDelimiter')->once()->with(',');
      $this->obligationCsvImport->shouldReceive('setEnclosure')->once()->with('"');
      $this->obligationCsvImport->shouldReceive('handleFile')->once()
        ->with($uploadedFile->getRealPath(), 'csv')->andReturn('imported');

      $actual = $adminObligationFromCsv->handleFileUpload($uploadedFile, '', '');

      $this->assertSame([true, 'imported', 200], $actual);
    }

    /** @test */
    public function testHandleFileUploadReturnsRealUploadErrorForRestCalls()
    {
      $adminObligationFromCsv = new AdminObligationFromCSV();
      $uploadedFile = $this->getUploadedFileWithError(UPLOAD_ERR_INI_SIZE);

      $actual = $adminObligationFromCsv->handleFileUpload($uploadedFile, ',', '"', true);

      $this->assertSame([false, $uploadedFile->getErrorMessage(), 400], $actual);
    }

    private function getUploadedCsvFile()
    {
      $tempFile = tempnam(sys_get_temp_dir(), 'fossology-obligation-csv-');
      file_put_contents($tempFile, "type,topic,text\nObligation,Keep notices,Preserve notices\n");
      $this->tempFiles[] = $tempFile;

      return new UploadedFile($tempFile, 'obligations.csv', 'text/csv', null, true);
    }

    private function getUploadedFileWithError($errorCode)
    {
      $tempFile = tempnam(sys_get_temp_dir(), 'fossology-obligation-upload-');
      file_put_contents($tempFile, '');
      $this->tempFiles[] = $tempFile;

      return new UploadedFile($tempFile, 'obligations.csv', 'text/csv', $errorCode, true);
    }
  }
}

namespace Fossology\UI\Page
{
  function register_plugin($plugin)
  {
    return \Fossology\UI\Api\Test\Controllers\AdminObligationFromCSVTest::$functions
      ->register_plugin($plugin);
  }
}
