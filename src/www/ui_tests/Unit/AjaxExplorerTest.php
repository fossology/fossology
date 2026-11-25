<?php
/*
 SPDX-FileCopyrightText: © 2026 Gaurav Kumar <Student-sst@outlook.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Test\Reflectory;
use Fossology\UI\Ajax\AjaxExplorer;
use Symfony\Component\HttpFoundation\Request;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AjaxExplorerTest extends \PHPUnit\Framework\TestCase
{
  private $explorer;

  protected function setUp(): void
  {
    $GLOBALS['SysConf'] = array();
    $GLOBALS['Plugins'] = array();
    $GLOBALS['container'] = new class {
      public function get($name)
      {
        return new \stdClass();
      }
    };
    require_once(__DIR__ . '/../../../lib/php/common-plugin.php');
    require_once(__DIR__ . '/../../ui/async/AjaxExplorer.php');
    $this->explorer = $GLOBALS['Plugins'][AjaxExplorer::NAME];
  }

  /**
   * @dataProvider licenseFilters
   */
  public function testParseLicenseFilter($value, $expected)
  {
    $this->assertSame($expected,
      Reflectory::invokeObjectsMethodnameWith($this->explorer, 'parseLicenseFilter', array($value)));
  }

  public static function licenseFilters()
  {
    return array(
      'absent' => array(null, array()),
      'cleared' => array('', array()),
      'legacy placeholder' => array('0', array()),
      'integer placeholder' => array(0, array()),
      'single selection' => array('201', array(201)),
      'REST integer' => array(201, array(201)),
      'multiple selections' => array('201,202', array(201, 202)),
      'duplicates' => array('201,202,201', array(201, 202))
    );
  }

  /**
   * @dataProvider invalidLicenseFilters
   */
  public function testInvalidLicenseFilterReturnsBadRequest($value, $name)
  {
    $response = $this->explorer->handle(new Request(array($name => $value)));
    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame('License filters must contain positive integer IDs.',
      json_decode($response->getContent(), true)['error']);
  }

  public static function invalidLicenseFilters()
  {
    foreach (array('scanFilter', 'conFilter') as $name) {
      foreach (array('text', '201,,202', '201,0', '-1', '1.5', '1e2',
        '999999999999999999999', '201); DROP TABLE license_file;--', array('201')) as $value) {
        yield array($value, $name);
      }
    }
  }
}
