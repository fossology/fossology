<?php
/*
 SPDX-FileCopyrightText: © 2026 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\Upload\Upload;
use Fossology\Lib\Db\DbManager;
use Mockery as M;
use PHPUnit\Framework\TestCase;

/**
 * @class ReportReuseTest
 * @brief Tests for the decision to reuse an already generated report
 */
class ReportReuseTest extends TestCase
{
  const UPLOAD_ID = 7;
  const GROUP_ID = 3;
  const JOB_ID = 42;
  const FORMAT = 'spdx2tv';

  /** @var string $repoPath Temporary repository root */
  private $repoPath;

  /** @var string $reportPath Canonical report path of the upload */
  private $reportPath;

  /** @var DbManager|M\MockInterface $dbManager */
  private $dbManager;

  /** @var UploadDao|M\MockInterface $uploadDao */
  private $uploadDao;

  /** @var array $rows Row returned per query, keyed by statement suffix */
  private $rows;

  protected function setUp(): void
  {
    $this->repoPath = sys_get_temp_dir() . '/reportReuseTest' . getmypid();
    mkdir($this->repoPath . '/report', 0777, true);
    $GLOBALS['SysConf']['FOSSOLOGY']['path'] = $this->repoPath;

    $this->reportPath = ReportUtils::canonicalReportPath(self::FORMAT,
      'curl.tar.gz');
    touch($this->reportPath);

    // Registered for this group, nothing wrote it later, upload unchanged
    // since: the report is reusable unless a test says otherwise.
    $this->rows = [
      'registered' => ['job_fk' => self::JOB_ID],
      'otherWriter' => false,
      'latestUploadChange' => ['ts' => filemtime($this->reportPath) - 60],
    ];

    $upload = M::mock(Upload::class);
    $upload->shouldReceive('getFilename')->andReturn('curl.tar.gz');
    $this->uploadDao = M::mock(UploadDao::class);
    $this->uploadDao->shouldReceive('getUpload')->andReturn($upload);
    $this->uploadDao->shouldReceive('getUploadtreeTableName')
      ->andReturn('uploadtree_a');

    $this->dbManager = M::mock(DbManager::class);
    $this->dbManager->shouldReceive('getSingleRow')
      ->andReturnUsing(function ($sql, $params, $statement) {
        foreach ($this->rows as $key => $row) {
          if (strpos($statement, $key) !== false) {
            return $row;
          }
        }
        return false;
      });
  }

  protected function tearDown(): void
  {
    if (is_file($this->reportPath)) {
      unlink($this->reportPath);
    }
    @rmdir($this->repoPath . '/report');
    @rmdir($this->repoPath);
    M::close();
  }

  /**
   * @return ReportReuse
   */
  private function getReportReuse()
  {
    return new ReportReuse($this->dbManager, $this->uploadDao);
  }

  /**
   * @param string &$reason
   * @return string|null
   */
  private function findReusableReport(&$reason = null)
  {
    return $this->getReportReuse()->findReusableReport(self::UPLOAD_ID,
      self::FORMAT, self::GROUP_ID, $reason);
  }

  /**
   * @test
   * -# The group generated the report, nothing changed since
   * -# Check the canonical report path is offered for reuse
   */
  public function testReportIsReused()
  {
    $this->assertEquals($this->reportPath, $this->findReusableReport());
  }

  /**
   * @test
   * -# No reportgen row for this upload, format and group
   * -# Check the report is not reused
   */
  public function testUnregisteredReportIsNotReused()
  {
    $this->rows['registered'] = false;

    $reason = "";
    $this->assertNull($this->findReusableReport($reason));
    $this->assertStringContainsString('spdx2tv', $reason);
  }

  /**
   * @test
   * -# Another upload wrote the same report path afterwards
   * -# Check the report is not reused
   */
  public function testReportWrittenForAnotherUploadIsNotReused()
  {
    $this->rows['otherWriter'] = [1];

    $reason = "";
    $this->assertNull($this->findReusableReport($reason));
    $this->assertStringContainsString('different upload', $reason);
  }

  /**
   * @test
   * -# The registered report file is gone from the repository
   * -# Check the report is not reused
   */
  public function testMissingReportFileIsNotReused()
  {
    unlink($this->reportPath);

    $reason = "";
    $this->assertNull($this->findReusableReport($reason));
    $this->assertStringContainsString('missing', $reason);
  }

  /**
   * @test
   * -# The upload changed after the report was written
   * -# Check the report is not reused
   */
  public function testStaleReportIsNotReused()
  {
    $this->rows['latestUploadChange'] = ['ts' => filemtime($this->reportPath) + 60];

    $reason = "";
    $this->assertNull($this->findReusableReport($reason));
    $this->assertStringContainsString('older', $reason);
  }

  /**
   * @test
   * -# The upload changed after the report was written
   * -# Check the report is still resolvable, as the merge has to read it
   */
  public function testStaleReportIsStillResolvable()
  {
    $this->rows['latestUploadChange'] = ['ts' => filemtime($this->reportPath) + 60];

    $this->assertEquals($this->reportPath,
      $this->getReportReuse()->resolveReportPath(self::UPLOAD_ID, self::FORMAT,
        self::GROUP_ID));
  }

  /**
   * @test
   * -# Nothing was ever recorded for the upload
   * -# Check the change time is zero, so any report counts as up to date
   */
  public function testLatestUploadChangeWithoutHistory()
  {
    $this->rows['latestUploadChange'] = ['ts' => null];

    $this->assertEquals(0, $this->getReportReuse()
      ->latestUploadChange(self::UPLOAD_ID, self::GROUP_ID));
  }
}
