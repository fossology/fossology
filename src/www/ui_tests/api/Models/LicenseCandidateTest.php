<?php
/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for LicenseCandidate model
 */

namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\LicenseCandidate;
use Monolog\Test\TestCase;

class LicenseCandidateTest extends TestCase
{
  /**
   * Provides test data and an instance of the LicenseCandidate class.
   *
   * @return array An associative array containing:
   *  - `expectedArray`: The expected array structure.
   *  - `obj`: The instance of LicenseCandidate being tested.
   */
  private function getLicenseCandidateInfo($version = ApiVersion::V1)
  {
    $id = 1;
    $shortname = "AGPL-1.0-or-later";
    $spdxid = "MIT";
    $fullname = "AGPL-1.0-or-later License";
    $text = "Permission is hereby granted...";
    $group_name = "TestGroup";
    $group_id = 12;

    if ($version == ApiVersion::V1) {
      $expectedArray = [
        "id"         => $id,
        "shortname"  => $shortname,
        "spdxid"     => $spdxid,
        "fullname"   => $fullname,
        "text"       => $text,
        "group_name" => $group_name,
        "group_id"   => $group_id,
      ];
    } else {
      $expectedArray = [
        "id"         => $id,
        "shortname"  => $shortname,
        "spdxid"     => $spdxid,
        "fullname"   => $fullname,
        "text"       => $text,
        "groupName" => $group_name,
        "groupId"   => $group_id,
      ];
    }

    $obj = new LicenseCandidate($id, $shortname, $spdxid, $fullname, $text, $group_name, $group_id);
    return [
      'expectedArray' => $expectedArray,
      'obj' => $obj
    ];
  }

  /**
   * @test
   * -# Test data model returned by LicenseCandidate::getArray($version) when API version is V1
   */
  public function testDataFormatV1()
  {
    $this->testDataFormat(ApiVersion::V1);
  }

  /**
   * @test
   * -# Test data model returned by LicenseCandidate::getArray($version) when API version is V2
   */
  public function testDataFormatV2()
  {
    $this->testDataFormat(ApiVersion::V2);
  }

  /**
   * -# Test the data format returned by LicenseCandidate::getArray($version) model
   */
  private function testDataFormat($version)
  {
    $info = $this->getLicenseCandidateInfo($version);
    $expectedArray = $info['expectedArray'];
    $licenseCandidate = $info['obj'];
    $this->assertEquals($expectedArray, $licenseCandidate->getArray($version));
  }

  /**
   * Tests LicenseCandidate::getId() method.
   *
   * This method validates that the `getId` method returns the correct license ID value.
   */
  public function testGetId()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $this->assertEquals(1, $licenseCandidate->getId());
  }

  /**
   * Tests LicenseCandidate::getShortname() method.
   *
   * This method validates that the `getShortname` method returns the correct shortname value.
   */
  public function testGetShortname()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $this->assertEquals("AGPL-1.0-or-later", $licenseCandidate->getShortname());
  }

  /**
   * Tests LicenseCandidate::getSpdxid() method.
   */
  public function testGetSpdxid()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $this->assertEquals("MIT", $licenseCandidate->getSpdxid());
  }

  /**
   * Tests LicenseCandidate::setSpdxid() method.
   */
  public function testSetSpdxid()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $licenseCandidate->setSpdxid("Apache-2.0");
    $this->assertEquals("Apache-2.0", $licenseCandidate->getSpdxid());
  }

  /**
   * Tests LicenseCandidate::getFullname() method.
   */
  public function testGetFullname()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $this->assertEquals("AGPL-1.0-or-later License", $licenseCandidate->getFullname());
  }

  /**
   * Tests LicenseCandidate::setFullname() method.
   */
  public function testSetFullname()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $licenseCandidate->setFullname("Apache License 2.0");
    $this->assertEquals("Apache License 2.0", $licenseCandidate->getFullname());
  }

  /**
   * Tests LicenseCandidate::getText() method.
   */
  public function testGetText()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $this->assertEquals("Permission is hereby granted...", $licenseCandidate->getText());
  }

  /**
   * Tests LicenseCandidate::setText() method.
   */
  public function testSetText()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $licenseCandidate->setText("Apache License text...");
    $this->assertEquals("Apache License text...", $licenseCandidate->getText());
  }

  /**
   * Tests LicenseCandidate::getGroupName() method.
   */
  public function testGetGroupName()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $this->assertEquals("TestGroup", $licenseCandidate->getGroupName());
  }

  /**
   * Tests LicenseCandidate::getGroupId() method.
   */
  public function testGetGroupId()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $this->assertEquals(12, $licenseCandidate->getGroupId());
  }

  /**
   * Tests LicenseCandidate::setGroupId() method.
   */
  public function testSetGroupId()
  {
    $licenseCandidate = $this->getLicenseCandidateInfo(ApiVersion::V1)['obj'];
    $licenseCandidate->setGroupId(456);
    $this->assertEquals(456, $licenseCandidate->getGroupId());
  }
}
