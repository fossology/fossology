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
      'parent entry not widened to root' => ['/tmp/..', '/etc/hosts', false],
      'parent entry with trailing slash' => ['/tmp/../', '/etc/hosts', false],
      'parent entry skipped, next works' => ['/tmp/..:/srv/data', '/srv/data/x', true],
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

  public function testRelativeWhitelistEntryIsIgnored()
  {
    $this->tmpDir = sys_get_temp_dir() . '/fo-whitelist-' . getmypid();
    mkdir($this->tmpDir . '/real', 0777, true);
    $resolved = realpath($this->tmpDir);
    $cwd = getcwd();
    // realpath() would resolve a relative entry against the working directory
    chdir($this->tmpDir);
    $this->setWhitelist('.');
    $dotAllowed = $this->page->check_by_whitelist($resolved . '/file.tar');
    $this->setWhitelist('real');
    $nameAllowed = $this->page->check_by_whitelist($resolved . '/real/file.tar');
    chdir($cwd);

    $this->assertFalse($dotAllowed);
    $this->assertFalse($nameAllowed);
  }

  /**
   * @return array [path, expected result]
   */
  public static function traversalProvider()
  {
    return [
      'parent segment in the middle'  => ['/tmp/../etc/hosts', false],
      'parent segment at the end'     => ['/tmp/a/..', false],
      'nested parent segments'        => ['/tmp/a/../../etc/hosts', false],
      'dots inside a name are fine'   => ['/tmp/a..b/file.tar', true],
    ];
  }

  /**
   * @dataProvider traversalProvider
   */
  public function testTraversalRejectedForRemoteHost($path, $expected)
  {
    $this->setWhitelist('/tmp');
    $this->assertSame($expected,
      $this->page->check_by_whitelist($path, 'remotehost'));
  }

  /**
   * @return array [path, host, expected result]
   */
  public static function wildcardProvider()
  {
    return [
      'remote wildcard below symlink'  => ['/tmp/link/*', 'remotehost', false],
      'remote wildcard question mark'  => ['/tmp/file?.tar', 'remotehost', false],
      'remote wildcard bracket'        => ['/tmp/[ab].tar', 'remotehost', false],
      'remote wildcard brace'          => ['/tmp/{a,b}.tar', 'remotehost', false],
      'remote escaped star is literal' => ['/tmp/a\\*b.tar', 'remotehost', true],
      'remote plain path still allowed' => ['/tmp/a/b.tar', 'remotehost', true],
      'local wildcard still allowed'   => ['/tmp/a/*', 'localhost', true],
      'local wildcard parent denied'   => ['/tmp/*/file', 'localhost', false],
      'local nested wildcard parent'   => ['/tmp/a*/b/*', 'localhost', false],
    ];
  }

  /**
   * Wildcard paths of a remote host are expanded on that host without being
   * resolved, so a symlink in the path could leave the whitelist there.
   * @dataProvider wildcardProvider
   */
  public function testWildcardRejectedForRemoteHost($path, $host, $expected)
  {
    $this->setWhitelist('/tmp');
    $this->assertSame($expected,
      $this->page->check_by_whitelist($path, $host));
  }

  public function testExplicitRootAllowsRemoteWildcard()
  {
    $this->setWhitelist('/');
    $this->assertTrue($this->page->check_by_whitelist('/tmp/link/*', 'remotehost'));
    $this->setWhitelist('/tmp: / ');
    $this->assertTrue($this->page->check_by_whitelist('/etc/*', 'remotehost'));
  }

  public function testWhitelistEntrySymlinkNotResolvedForRemoteHost()
  {
    $this->tmpDir = sys_get_temp_dir() . '/fo-whitelist-' . getmypid();
    mkdir($this->tmpDir . '/real', 0777, true);
    symlink($this->tmpDir . '/real', $this->tmpDir . '/link');
    $resolved = realpath($this->tmpDir . '/real') . '/file.tar';

    $this->setWhitelist($this->tmpDir . '/link');
    // A local symlink must not widen the whitelist of a remote host
    $this->assertFalse($this->page->check_by_whitelist($resolved, 'remotehost'));
    $this->assertTrue($this->page->check_by_whitelist(
      $this->tmpDir . '/link/file.tar', 'remotehost'));
  }

  public function testWhitelistEntrySymlinkToRoot()
  {
    $this->tmpDir = sys_get_temp_dir() . '/fo-whitelist-' . getmypid();
    mkdir($this->tmpDir, 0777, true);
    symlink('/', $this->tmpDir . '/rootlink');

    $this->setWhitelist($this->tmpDir . '/rootlink');
    $this->assertTrue($this->page->check_by_whitelist('/etc/hosts'));
    $this->assertFalse($this->page->check_by_whitelist('/etc/hosts', 'remotehost'));
  }
}
