<?php
/*
 SPDX-FileCopyrightText: © 2026 Adesh Deshmukh <adeshkd123@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

if (!function_exists('register_plugin')) {
  function register_plugin($plugin) {}
}

$autoloadPath = __DIR__ . '/../../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
  require_once $autoloadPath;
}
require_once __DIR__ . '/../../ui/ReportImportAgentPlugin.php';

class ReportImportAgentPluginTest extends \PHPUnit\Framework\TestCase
{
  private $tmpDir;

  protected function setUp(): void
  {
    global $SysConf;
    $this->tmpDir = sys_get_temp_dir() . '/reportimport_test_' . uniqid();
    $SysConf = ['FOSSOLOGY' => ['path' => $this->tmpDir]];
  }

  protected function tearDown(): void
  {
    global $SysConf;
    $SysConf = null;
    if (is_dir($this->tmpDir . '/ReportImport/')) {
      $this->rmdirRecursive($this->tmpDir . '/ReportImport/');
    }
    @rmdir($this->tmpDir);
  }

  public function testAddReportWithString()
  {
    $plugin = new ReportImportAgentPlugin();
    $result = $plugin->addReport('/some/path/file.spdx');
    $this->assertEquals('--report=' . escapeshellarg('/some/path/file.spdx'), $result);
  }

  public function testAddReportWithNull()
  {
    $plugin = new ReportImportAgentPlugin();
    $this->assertEquals('', $plugin->addReport(null));
  }

  public function testAddReportWithEmptyArray()
  {
    $plugin = new ReportImportAgentPlugin();
    $this->assertEquals('', $plugin->addReport([]));
  }

  public function testAddReportWithUploadError()
  {
    $plugin = new ReportImportAgentPlugin();
    $this->assertEquals('', $plugin->addReport(['error' => UPLOAD_ERR_NO_FILE]));
  }

  public function testAddReportThrowsOnMissingTmpFile()
  {
    $plugin = new ReportImportAgentPlugin();
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Uploaded tmpfile not found');
    $plugin->addReport([
      'error' => UPLOAD_ERR_OK,
      'tmp_name' => '/nonexistent/path/file.spdx',
      'name' => 'test.spdx'
    ]);
  }

  public function testAddReportCreatesDirectoryWithPathTraversalName()
  {
    $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
    file_put_contents($tmpFile, 'content');

    $plugin = new ReportImportAgentPlugin();
    $report = [
      'error' => UPLOAD_ERR_OK,
      'tmp_name' => $tmpFile,
      'name' => '../../etc/passwd'
    ];
    $result = $plugin->addReport($report);

    $this->assertEquals('', $result);
    $this->assertTrue(is_dir($this->tmpDir . '/ReportImport/'));

    $filesInBase = array_diff(scandir($this->tmpDir), ['.', '..']);
    $this->assertEquals(['ReportImport'], $filesInBase);

    unlink($tmpFile);
  }

  /**
   * @dataProvider pathTraversalProvider
   */
  public function testBasenamePreventsPathTraversal($input, $expected)
  {
    $this->assertEquals($expected, basename($input));
  }

  public function pathTraversalProvider()
  {
    return [
      'normal file' => ['test.spdx', 'test.spdx'],
      'simple traversal' => ['../../etc/passwd', 'passwd'],
      'deep traversal' => ['../../../etc/cron.d/malicious', 'malicious'],
      'absolute path' => ['/etc/passwd', 'passwd'],
      'mixed traversal' => ['subdir/../../etc/passwd', 'passwd'],
      'file with spaces' => ['normal file.spdx', 'normal file.spdx'],
      'just filename' => ['file.spdx', 'file.spdx'],
    ];
  }

  private function rmdirRecursive($dir)
  {
    if (!is_dir($dir)) {
      return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      $path = $dir . '/' . $file;
      is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
    }
    rmdir($dir);
  }
}
