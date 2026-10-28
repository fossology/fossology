<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\Reflectory;
use Mockery as M;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-menu.php');

/**
 * @class AdminUploadEditTest
 * @brief Test for upload_properties::UpdateUploadProperties()
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AdminUploadEditTest extends \PHPUnit\Framework\TestCase
{
  /** @var upload_properties */
  private $plugin;

  /** @var DbManager|M\MockInterface */
  private $dbManager;

  protected function setUp(): void
  {
    if (!class_exists('upload_properties', false)) {
      $GLOBALS['SysConf'] = [];
      $GLOBALS['container'] = new class {
        public function get($name)
        {
          return new \stdClass();
        }
      };
      require_once(__DIR__ . '/../../ui/admin-upload-edit.php');
    }

    global $MenuList, $Plugins;
    $MenuList = array();
    $Plugins = array();

    $this->dbManager = M::mock(DbManager::class);
    $this->plugin = (new \ReflectionClass('upload_properties'))->newInstanceWithoutConstructor();
    Reflectory::setObjectsProperty($this->plugin, 'dbManager', $this->dbManager);
  }

  protected function tearDown(): void
  {
    M::close();
  }

  /**
   * @test
   * -# Call UpdateUploadProperties() when both new name and description are empty/null.
   * -# Check that 2 (no inputs) is returned and no DB queries are executed.
   */
  public function testUpdateReturnsTwoWhenBothInputsAreNullOrEmpty(): void
  {
    $this->dbManager->shouldNotReceive('getSingleRow');

    $this->assertEquals(2, $this->plugin->UpdateUploadProperties(1, null, null));
    $this->assertEquals(2, $this->plugin->UpdateUploadProperties(1, '', null));
    $this->assertEquals(2, $this->plugin->UpdateUploadProperties(1, '   ', null));
  }

  /**
   * @test
   * -# Call UpdateUploadProperties() with empty description string to clear it.
   * -# Check that upload description is updated to empty string and returns 1.
   */
  public function testUpdateCanClearDescription(): void
  {
    $uploadId = 5;
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([
        'UPDATE upload SET upload_desc=$2 WHERE upload_pk=$1',
        [$uploadId, ''],
        M::any()
      ])
      ->once();

    $result = $this->plugin->UpdateUploadProperties($uploadId, null, '');
    $this->assertEquals(1, $result);
  }

  /**
   * @test
   * -# Call UpdateUploadProperties() with new description text.
   * -# Check that upload description is trimmed and updated.
   */
  public function testUpdateWithNewDescription(): void
  {
    $uploadId = 5;
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([
        'UPDATE upload SET upload_desc=$2 WHERE upload_pk=$1',
        [$uploadId, 'My new description'],
        M::any()
      ])
      ->once();

    $result = $this->plugin->UpdateUploadProperties($uploadId, null, '  My new description  ');
    $this->assertEquals(1, $result);
  }

  /**
   * @test
   * -# Call UpdateUploadProperties() with new name and new description.
   * -# Check that uploadtree and upload table rows are updated.
   */
  public function testUpdateWithNewNameAndDescription(): void
  {
    $uploadId = 5;
    $pfileFk = 42;
    $newName = 'new_archive.tar.gz';
    $newDesc = 'Updated description';

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([
        'SELECT pfile_fk FROM upload WHERE upload_pk=$1',
        [$uploadId],
        M::any()
      ])
      ->once()
      ->andReturn(['pfile_fk' => $pfileFk]);

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([
        'UPDATE uploadtree SET ufile_name=$3 WHERE upload_fk=$1 AND pfile_fk=$2',
        [$uploadId, $pfileFk, $newName],
        M::any()
      ])
      ->once();

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([
        'UPDATE upload SET upload_filename=$3 WHERE upload_pk=$1 AND pfile_fk=$2',
        [$uploadId, $pfileFk, $newName],
        M::any()
      ])
      ->once();

    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([
        'UPDATE upload SET upload_desc=$2 WHERE upload_pk=$1',
        [$uploadId, $newDesc],
        M::any()
      ])
      ->once();

    $result = $this->plugin->UpdateUploadProperties($uploadId, $newName, $newDesc);
    $this->assertEquals(1, $result);
  }

  /**
   * @test
   * -# Call UpdateUploadProperties() when upload row has no pfile_fk.
   * -# Check that 0 is returned.
   */
  public function testUpdateReturnsZeroWhenUploadRowNotFound(): void
  {
    $uploadId = 999;
    $this->dbManager->shouldReceive('getSingleRow')
      ->withArgs([
        'SELECT pfile_fk FROM upload WHERE upload_pk=$1',
        [$uploadId],
        M::any()
      ])
      ->once()
      ->andReturn([]);

    $result = $this->plugin->UpdateUploadProperties($uploadId, 'valid-name.tar', null);
    $this->assertEquals(0, $result);
  }
}
