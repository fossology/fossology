<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
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
      "defaultGroup" => 0,
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
        "package"   => false,
        "reso"      => false
      ]
    ];
    $expectedNonAdminUser = [
      "id"           => 8,
      "name"         => 'userii',
      "description"  => 'very useri',
      "defaultGroup" => 0,
    ];

    $actualCurrentUser = new User(2, 'fossy', 'super user', 'fossy@localhost',
      PLUGIN_DB_ADMIN, 2, true, "bucket,copyright,nomos,ojo", 0);
    $actualNonAdminUser = new User(8, 'userii', 'very useri', null, null, null,
      null, null, 0);

    $this->assertEquals($expectedCurrentUser, $actualCurrentUser->getArray());
    $this->assertEquals($expectedNonAdminUser, $actualNonAdminUser->getArray());
  }
}
