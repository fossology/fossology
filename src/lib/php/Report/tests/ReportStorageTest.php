<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Db\DbManager;
use PHPUnit\Framework\TestCase;

class ReportStorageTest extends TestCase
{
  private $reportRoot;
  private $savedSysConf;

  protected function setUp(): void
  {
    $this->savedSysConf = $GLOBALS['SysConf'] ?? null;
    $this->reportRoot = sys_get_temp_dir() . '/fossology-report-' . bin2hex(random_bytes(8));
    $GLOBALS['SysConf']['FOSSOLOGY']['path'] = $this->reportRoot;
  }

  protected function tearDown(): void
  {
    $GLOBALS['SysConf'] = $this->savedSysConf;
    if (is_dir($this->reportRoot)) {
      $entries = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($this->reportRoot, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($entries as $entry) {
        if ($entry->isDir()) {
          rmdir($entry->getPathname());
        } else {
          unlink($entry->getPathname());
        }
      }
      rmdir($this->reportRoot);
    }
  }

  private function makeReportUtils(DbManager $db): ReportUtils
  {
    return new class($db) extends ReportUtils {
      public function __construct(DbManager $db)
      {
        $this->dbManager = $db;
      }
    };
  }

  public function testReportScopesSeparateSameNamedExports(): void
  {
    $first = ReportUtils::getReportDirectory(7, 1);
    $second = ReportUtils::getReportDirectory(8, 1);
    $otherGroup = ReportUtils::getReportDirectory(7, 2);
    $bulk = ReportUtils::getReportDirectory(7, 1, [8, 9]);

    self::assertSame($first, ReportUtils::getReportDirectory('7', '1'));
    self::assertSame($bulk, ReportUtils::getReportDirectory(7, 1, ['8', '9']));
    self::assertCount(6, array_unique([
      $first, $second, $otherGroup, $bulk,
      ReportUtils::getReportDirectory(7, 1, [8, 10]),
      ReportUtils::getReportDirectory(7, 1, [9, 8]),
    ]));

    $db = $this->createMock(DbManager::class);
    $db->expects($this->exactly(5))->method('begin');
    $db->expects($this->exactly(5))->method('commit');
    $db->expects($this->never())->method('rollback');
    $jobsByPath = [];
    $db->method('getSingleRow')->willReturnCallback(static function ($sql, $params) use (&$jobsByPath) {
      if (strpos($sql, 'FOR NO KEY UPDATE') !== false) {
        return ['upload_pk' => $params[0]];
      }
      if (strpos($sql, 'SELECT 1 FROM reportgen') === 0) {
        return isset($jobsByPath[$params[1]]) ? [1] : false;
      }
      [$jobId, $uploadId, $filepath] = $params;
      $jobsByPath[$filepath] = $jobId;
      return false;
    });
    $db->expects($this->exactly(4))->method('insertTableRow')
      ->willReturnCallback(static function ($table, $row) use (&$jobsByPath) {
        self::assertSame('reportgen', $table);
        $jobsByPath[$row['filepath']] = $row['job_fk'];
      });
    $reports = $this->makeReportUtils($db);

    $basename = 'SPDX2_release.tar.gz.spdx.rdf';
    $reports->saveTextReport(7, 41, $first . $basename, 'first upload');
    $reports->saveTextReport(8, 42, $second . $basename, 'second upload');
    $reports->saveTextReport(7, 43, $otherGroup . $basename, 'other group');
    $reports->saveTextReport(7, 44, $bulk . $basename, 'bulk selection');
    self::assertSame('first upload', file_get_contents($first . $basename));

    $reports->saveTextReport(7, 45, $first . $basename, 'first upload regenerated');
    self::assertSame('first upload regenerated', file_get_contents($first . $basename));
    self::assertSame('second upload', file_get_contents($second . $basename));
    self::assertSame('other group', file_get_contents($otherGroup . $basename));
    self::assertSame('bulk selection', file_get_contents($bulk . $basename));
    self::assertSame([
      $first . $basename => 45,
      $second . $basename => 42,
      $otherGroup . $basename => 43,
      $bulk . $basename => 44,
    ], $jobsByPath);
  }

  public function testFailedRenderKeepsPublishedReport(): void
  {
    $filepath = ReportUtils::getReportDirectory(7, 1) . 'report.docx';
    mkdir(dirname($filepath), 0777, true);
    file_put_contents($filepath, 'complete report');

    $db = $this->createMock(DbManager::class);
    $db->expects($this->never())->method('begin');
    $reports = $this->makeReportUtils($db);

    try {
      $reports->saveReport(7, 43, $filepath, static function ($temporaryFile) use ($filepath) {
        file_put_contents($temporaryFile, 'partial report');
        self::assertSame('complete report', file_get_contents($filepath));
        throw new \RuntimeException('Render failed');
      });
      self::fail('Expected the render error to propagate');
    } catch (\RuntimeException $error) {
      self::assertSame('Render failed', $error->getMessage());
    }

    self::assertSame('complete report', file_get_contents($filepath));
    self::assertSame([], glob(dirname($filepath) . '/.report-*'));
  }
}
