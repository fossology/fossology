<?php
/***************************************************************
 * Copyright (C) 2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Tests for User model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\User;

require_once dirname(dirname(dirname(dirname(__DIR__)))) .
  '/lib/php/Plugin/FO_Plugin.php';

/**
 * @class UserTest
 * @brief Tests for User model
 */
class UserTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# Test the data format returned by User::getArray() model
   */
  public function testDataFormat()
  {
    $expectedCurrentUser = [
      "id"           => 2,
      "name"         => 'fossy',
      "description"  => 'super user',
      "email"        => 'fossy@localhost',
      "accessLevel"  => 'admin',
      "rootFolderId" => 2,
      "emailNotification" => true,
      "agents"       => [
        "bucket"    => true,
        "copyright_email_author" => true,
        "ecc"       => false,
        "keyword"   => false,
        "mimetype"  => false,
        "monk"      => false,
        "nomos"     => true,
        "ojo"       => true,
        "package"   => false
      ]
    ];
    $expectedNonAdminUser = [
      "id"           => 8,
      "name"         => 'userii',
      "description"  => 'very useri',
    ];

    $actualCurrentUser = new User(2, 'fossy', 'super user', 'fossy@localhost',
      PLUGIN_DB_ADMIN, 2, true, "bucket,copyright,nomos,ojo");
    $actualNonAdminUser = new User(8, 'userii', 'very useri', null, null, null,
      null, null);

    $this->assertEquals($expectedCurrentUser, $actualCurrentUser->getArray());
    $this->assertEquals($expectedNonAdminUser, $actualNonAdminUser->getArray());
  }
}
