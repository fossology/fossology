<?php
/*
 SPDX-FileCopyrightText: © 2026 anshika-006

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Test\Reflectory;
use Symfony\Component\HttpFoundation\Request;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-menu.php');

/**
 * @class UserEditPageTest
 * @brief Test for UserEditPage::CreateUserRec(), covering the default
 *        bucket pool handling
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * Regression coverage for: when no bucket pool is selected (for example
 * because none is active, so the dropdown is empty), the form posts no value
 * for default_bucketpool_fk. It used to be cast to 0, which violates the
 * users_default_bucketpool_pk_fkey foreign key and made saving the account
 * fail. It must be stored as NULL instead.
 */
class UserEditPageTest extends \PHPUnit\Framework\TestCase
{
  /** @var UserEditPage */
  private $page;

  protected function setUp(): void
  {
    if (!class_exists('UserEditPage', false)) {
      $GLOBALS['SysConf'] = [];
      $GLOBALS['container'] = new class {
        public function get($name)
        {
          return new \stdClass();
        }
      };
      require_once(__DIR__ . '/../../ui/user-edit.php');
    }

    global $MenuList, $Plugins;
    $MenuList = array();
    $Plugins = array();

    // Bypass the constructor: CreateUserRec() only needs dbManager, which
    // is injected below. No optional settings columns exist in this stub.
    $this->page = (new \ReflectionClass('UserEditPage'))->newInstanceWithoutConstructor();
    $dbManager = new class {
      public function existsColumn($table, $column)
      {
        return false;
      }
    };
    Reflectory::setObjectsProperty($this->page, 'dbManager', $dbManager);
  }

  /**
   * Build a request like the edit form posts it.
   * @param array $extra Additional form values
   * @return Request
   */
  private function formRequest(array $extra = [])
  {
    return new Request([], array_merge([
      'user_pk' => '3',
      'user_name' => 'fossy',
      'public' => '',
      'root_folder_fk' => '1',
      'default_folder_fk' => '1',
      'user_desc' => 'Default Administrator',
      '_pass1' => '',
      '_pass2' => '',
      '_blank_pass' => 'on',
      'user_perm' => '10',
      'user_status' => 'active',
      'user_email' => 'fossy@example.com',
      'email_notify' => 'y',
      'user_agent_list' => 'agent_unpack',
    ], $extra));
  }

  /**
   * @test
   * -# Create a user record from a form without default_bucketpool_fk
   *    (empty dropdown).
   * -# Check that the bucket pool is NULL and not an invalid foreign key of 0.
   */
  public function testMissingBucketPoolIsStoredAsNull()
  {
    $userRec = $this->page->CreateUserRec($this->formRequest());

    $this->assertArrayHasKey('default_bucketpool_fk', $userRec);
    $this->assertNull($userRec['default_bucketpool_fk']);
  }

  /**
   * @test
   * -# Create a user record with an empty and a zero default_bucketpool_fk.
   * -# Check that both are stored as NULL.
   */
  public function testEmptyOrZeroBucketPoolIsStoredAsNull()
  {
    $empty = $this->page->CreateUserRec(
      $this->formRequest(['default_bucketpool_fk' => '']));
    $zero = $this->page->CreateUserRec(
      $this->formRequest(['default_bucketpool_fk' => '0']));

    $this->assertNull($empty['default_bucketpool_fk']);
    $this->assertNull($zero['default_bucketpool_fk']);
  }

  /**
   * @test
   * -# Create a user record with a selected bucket pool.
   * -# Check that the selected id is kept unchanged.
   */
  public function testSelectedBucketPoolIsKept()
  {
    $userRec = $this->page->CreateUserRec(
      $this->formRequest(['default_bucketpool_fk' => '2']));

    $this->assertSame(2, $userRec['default_bucketpool_fk']);
  }
}
