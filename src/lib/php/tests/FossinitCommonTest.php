<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../install/fossinit-common.php';

class FossinitCommonTest extends TestCase
{
  private $configDirectory;

  protected function setUp(): void
  {
    $this->configDirectory = sys_get_temp_dir() . '/fossology-fossinit-' . uniqid();
    mkdir($this->configDirectory);
  }

  protected function tearDown(): void
  {
    unlink($this->configDirectory . '/Db.conf');
    rmdir($this->configDirectory);
  }

  public function testGetDatabaseNameFromConfig(): void
  {
    file_put_contents(
      $this->configDirectory . '/Db.conf',
      "dbname=custom_fossology;\nhost=localhost;\n"
    );

    $this->assertSame(
      'custom_fossology',
      getDatabaseNameFromConfig($this->configDirectory)
    );
  }

  public function testGetDatabaseNameFromConfigPreservesZero(): void
  {
    file_put_contents(
      $this->configDirectory . '/Db.conf',
      "dbname=0;\nhost=localhost;\n"
    );

    $this->assertSame(
      '0',
      getDatabaseNameFromConfig($this->configDirectory)
    );
  }
}
