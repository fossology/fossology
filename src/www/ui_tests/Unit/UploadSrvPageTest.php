<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-menu.php');

/**
 * @class UploadSrvPageTest
 * @brief Test for UploadSrvPage::check_by_whitelist()
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * Regression coverage for: the "Upload from Server" whitelist must never
 * fail open. Before the fix, an empty entry (empty setting, trailing or
 * doubled ":") matched every path, and entries were compared as plain string
 * prefixes ("/tmp" also allowed "/tmpevil/x").
 */
class UploadSrvPageTest extends \PHPUnit\Framework\TestCase
{
  /** @var \Fossology\UI\Page\UploadSrvPage */
  private $page;
  /** @var string */
  private $tmpDir;

  protected function setUp(): void
  {
    if (!class_exists('Fossology\UI\Page\UploadSrvPage', false)) {
      $GLOBALS['SysConf'] = [];
      $GLOBALS['container'] = new class {
        public function get($name)
        {
          return new \stdClass();
        }
      };
      global $MenuList, $Plugins;
      $MenuList = array();
      $Plugins = array();
      require_once(__DIR__ . '/../../ui/page/UploadSrvPage.php');
    }
    // Bypass the plugin constructor, check_by_whitelist() needs no state.
    $this->page = (new \ReflectionClass('Fossology\UI\Page\UploadSrvPage'))
      ->newInstanceWithoutConstructor();
    $this->tmpDir = null;
  }

  protected function tearDown(): void
  {
    if ($this->tmpDir !== null) {
      foreach (glob($this->tmpDir . '/*') ?: [] as $entry) {
        is_link($entry) ? unlink($entry) : @rmdir($entry);
      }
      @rmdir($this->tmpDir);
    }
  }

  private function setWhitelist($value)
  {
    $GLOBALS['SysConf'] = ['SYSCONFIG' => []];
    if ($value !== null) {
      $GLOBALS['SysConf']['SYSCONFIG']['UploadFromServerWhitelist'] = $value;
    }
  }

  /**
   * @return array [whitelist setting, path, expected result]
   */
  public static function whitelistProvider()
  {
    return [
      'default allows /tmp subtree'      => [null, '/tmp/a/b.tar', true],
      'default denies other paths'       => [null, '/etc/fossology/Db.conf', false],
      'path equal to entry'              => ['/srv/data', '/srv/data', true],
      'path below entry'                 => ['/srv/data', '/srv/data/x/y', true],
      'entry with trailing slash'        => ['/srv/data/', '/srv/data/x', true],
      'sibling with same prefix denied'  => ['/tmp', '/tmpevil/x', false],
      'sibling with trailing slash'      => ['/srv/data/', '/srv/data-private/x', false],
      'multiple entries, second matches' => ['/tmp:/srv/data', '/srv/data/x', true],
      'multiple entries, none matches'   => ['/tmp:/srv/data', '/etc/passwd', false],
      'spaces around entries'            => ['/tmp : /srv/data', '/srv/data/x', true],
      'escaped space in path'            => ['/data/my files', '/data/my\\ files/a', true],
      'empty setting denies all'         => ['', '/etc/fossology/Db.conf', false],
      'trailing colon denies others'     => ['/tmp:', '/etc/fossology/Db.conf', false],
      'trailing colon keeps entry'       => ['/tmp:', '/tmp/x', true],
      'leading colon denies others'      => [':/tmp', '/etc/fossology/Db.conf', false],
      'doubled colon denies others'      => ['/tmp::/srv/data', '/etc/fossology/Db.conf', false],
      'whitespace only entry denies'     => ['  ', '/etc/fossology/Db.conf', false],
      'explicit root allows everything'  => ['/', '/etc/hosts', true],
    ];
  }

  /**
   * @dataProvider whitelistProvider
   */
  public function testCheckByWhitelist($setting, $path, $expected)
  {
    $this->setWhitelist($setting);
    $this->assertSame($expected, $this->page->check_by_whitelist($path));
  }

  public function testWhitelistEntryGivenAsSymlink()
  {
    $this->tmpDir = sys_get_temp_dir() . '/fo-whitelist-' . getmypid();
    mkdir($this->tmpDir . '/real', 0777, true);
    symlink($this->tmpDir . '/real', $this->tmpDir . '/link');
    // handleUpload() resolves the uploaded path with realpath() first
    $resolved = realpath($this->tmpDir . '/real') . '/file.tar';

    $this->setWhitelist($this->tmpDir . '/link');
    $this->assertTrue($this->page->check_by_whitelist($resolved));
    $this->assertFalse($this->page->check_by_whitelist(
      realpath($this->tmpDir) . '/realother/file.tar'));
  }
}
