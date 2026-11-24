<?php
/*
 SPDX-FileCopyrightText: © 2026 Rajay Singh <rajaysingh2007@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Test\Reflectory;

require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Test/Reflectory.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/Plugin/FO_Plugin.php');
require_once(dirname(dirname(dirname(__DIR__))) . '/lib/php/common-plugin.php');

/**
 * @class AdminLicenseFileTest
 * @brief Test for admin_license_file::isAgentPlaceholderText()
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * Regression coverage for: agents create stub licenses with a shared
 * placeholder text. Hashing that text into rf_md5 made the second edited
 * stub violate the unique index on rf_md5.
 */
class AdminLicenseFileTest extends \PHPUnit\Framework\TestCase
{
  public static function provideTexts()
  {
    return [
      'nomos' => ['License by Nomos.', true],
      'ninka' => ['License by Ninka.', true],
      'ojo' => ['License by OJO.', true],
      'scancode' => ['License by Scancode.', true],
      'real text' => ['Permission is hereby granted, free of charge, to any person', false],
    ];
  }

  /**
   * @test
   * @dataProvider provideTexts
   * -# Check that only the agent placeholder texts are detected.
   */
  public function testIsAgentPlaceholderText($text, $expected)
  {
    $GLOBALS['container'] = new class {
      public function get($name)
      {
        return new \stdClass();
      }
    };
    require_once(__DIR__ . '/../../ui/admin-license-file.php');
    $page = (new \ReflectionClass('admin_license_file'))->newInstanceWithoutConstructor();

    $this->assertSame($expected,
      Reflectory::invokeObjectsMethodnameWith($page, 'isAgentPlaceholderText', [$text]));
  }
}
