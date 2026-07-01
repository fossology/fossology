<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page
{
  function register_plugin($plugin)
  {
    return null;
  }
}

namespace Fossology\UI\Api\Test\Controllers
{
  use Fossology\Lib\Data\LicenseRef;
  use Fossology\Lib\Test\Reflectory;
  use Fossology\UI\Page\AdviceLicense;

  $GLOBALS['SysConf'] = [
    'DIRECTORIES' => [
      'LOGDIR' => sys_get_temp_dir(),
    ],
  ];
  $GLOBALS['container'] = new class
  {
    public function get($name)
    {
      return new \stdClass();
    }
  };

  require_once dirname(__DIR__, 3) . '/ui/page/AdviceLicense.php';

  /**
   * @class AdviceLicenseTest
   * @brief Tests for candidate-license SPDX-id handling.
   */
  class AdviceLicenseTest extends \PHPUnit\Framework\TestCase
  {
    protected function setUp() : void
    {
      global $container;
      global $SysConf;

      $SysConf = [
        'DIRECTORIES' => [
          'LOGDIR' => sys_get_temp_dir(),
        ],
      ];

      $container = new class
      {
        public function get($name)
        {
          return new \stdClass();
        }
      };
    }

    /**
     * @test
     * -# Save a candidate with the same stored and submitted SPDX id.
     * -# Check that an invalid existing id is not rewritten.
     */
    public function testNormalizeSpdxIdKeepsUnchangedExistingId()
    {
      $adviceLicense = new AdviceLicense();

      $spdxId = Reflectory::invokeObjectsMethodnameWith(
        $adviceLicense,
        'normalizeSpdxId',
        ['Existing Bad SPDX', 'Existing Bad SPDX']
      );

      $this->assertSame('Existing Bad SPDX', $spdxId);
    }

    /**
     * @test
     * -# Save a candidate with a newly entered invalid SPDX id.
     * -# Check that the new value is still converted to a LicenseRef id.
     */
    public function testNormalizeSpdxIdConvertsNewInvalidId()
    {
      $adviceLicense = new AdviceLicense();

      $spdxId = Reflectory::invokeObjectsMethodnameWith(
        $adviceLicense,
        'normalizeSpdxId',
        ['legacy custom id', 'ExistingBadSpdx']
      );

      $this->assertSame(LicenseRef::convertToSpdxId('legacy custom id', null),
        $spdxId);
    }

    /**
     * @test
     * -# Save a candidate with an empty SPDX id.
     * -# Check that the stored value is cleared.
     */
    public function testNormalizeSpdxIdClearsEmptyId()
    {
      $adviceLicense = new AdviceLicense();

      $spdxId = Reflectory::invokeObjectsMethodnameWith(
        $adviceLicense,
        'normalizeSpdxId',
        ['', 'ExistingBadSpdx']
      );

      $this->assertNull($spdxId);
    }
  }
}
