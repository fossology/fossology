<?php

/*
 SPDX-FileCopyrightText: © 2024 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Tests for LicenseCandidateTest
 */
namespace Fossology\UI\Api\Test\Models;

use Fossology\UI\Api\Models\LicenseCandidate;
use Fossology\UI\Api\Models\ApiVersion;

class LicenseCandidateTest extends \PHPUnit\Framework\TestCase
{
  /**
   * Provides test data and an instance of the LicenseCandidate class.
   *
   * @return array An associative array containing test data and a LicenseCandidate object.
   */
  public function getLicenseCandidateInfo($version=ApiVersion::V2)
  {
    $id = 9;
    $shortname = "MIT";
    $spdxid = 2;
    $fullname = "MIT License";
    $text = "Permission is hereby granted, free of charge, to any person obtaining a copy...";
    $group_name = "fossy";
    $group_id = 3;

    if ($version == ApiVersion::V2) {
      $candidateInfo = [
        'id' => $id,
        'shortName' => $shortname,
        'spdxId' => $spdxid,
        'fullName' => $fullname,
        'text' => $text,
        'groupName' => $group_name,
        'groupId' => $group_id,
      ];
    } else {
      $candidateInfo = [
        'id' => $id,
        'shortname' => $shortname,
        'spdxid' => $spdxid,
        'fullname' => $fullname,
        'text' => $text,
        'group_name' => $group_name,
        'group_id' => $group_id,
      ];
    }
    return [
      'candidateInfo' => $candidateInfo,
      'obj' => new LicenseCandidate($id, $shortname, $spdxid, $fullname, $text, $group_name, $group_id)
    ];
  }

  /**
   * Test LicenseCandidate:getArray()
   * Tests the getArray method of the LicenseCandidate class with API .
   *  - # Check if the returned array matches the expected data.
   */
  public function testDataFormat()
  {
    $obj = $this->getLicenseCandidateInfo()['obj'];
    $expectedArray = $this->getLicenseCandidateInfo(ApiVersion::V1)['candidateInfo'];
    $this->assertEquals($expectedArray, $obj->getArray(ApiVersion::V1));
  }

  /**
   * Test LicenseCandidate:setId()
   * Tests the setId method of the LicenseCandidate class.
   *  - # Check if the ID has changed to the new value
   */
  public function testSetId()
  {
    $obj = $this->getLicenseCandidateInfo()['obj'];
    $obj->setId(2);
    $this->assertEquals(2, $obj->getId());
  }

  /**
   * Test LicenseCandidate:setShortname()
   * Tests the setShortname method of the LicenseCandidate class.
   *  - # Check if the shortname has changed to the new value
   */
  public function testSetShortname()
  {
    $obj = $this->getLicenseCandidateInfo()['obj'];
    $obj->setShortname("Apache-2.0");
    $this->assertEquals("Apache-2.0", $obj->getShortname());
  }

  /**
   * Test LicenseCandidate:setSpdxid()
   * Tests the setSpdxid method of the LicenseCandidate class.
   *  - # Check if the SPDX ID has changed to the new value
   */
  public function testSetSpdxid()
  {
    $obj = $this->getLicenseCandidateInfo()['obj'];
    $obj->setSpdxid("Apache-2.0");
    $this->assertEquals("Apache-2.0", $obj->getSpdxid());
  }

  /**
   * Test LicenseCandidate:setFullname()
   * Tests the setFullname method of the LicenseCandidate class.
   *  - # Check if the fullname has changed to the new value
   */
  public function testSetFullname()
  {
    $obj = $this->getLicenseCandidateInfo()['obj'];
    $obj->setFullname("Apache License 2.0");
    $this->assertEquals("Apache License 2.0", $obj->getFullname());
  }

  /**
   * Test LicenseCandidate:setText()
   * Tests the setText method of the LicenseCandidate class.
   *  - # Check if the text has changed to the new value
   */
  public function testSetText()
  {
    $obj = $this->getLicenseCandidateInfo()['obj'];
    $newText = "Licensed under the Apache License, Version 2.0 (the 'License');";
    $obj->setText($newText);
    $this->assertEquals($newText, $obj->getText());
  }

  /**
   * Test LicenseCandidate:setGroupName()
   * Tests the setGroupName method of the LicenseCandidate class.
   *  - # Check if the group name has changed to the new value
   */
  public function testSetGroupName()
  {
    $obj = $this->getLicenseCandidateInfo()['obj'];
    $obj->setGroupName("closedsource");
    $this->assertEquals("closedsource", $obj->getGroupName());
  }

  /**
   * Test LicenseCandidate:setGroupId()
   * Tests the setGroupId method of the LicenseCandidate class.
   *  - # Check if the group ID has changed to the new value
   */
  public function testSetGroupId()
  {
    $obj = $this->getLicenseCandidateInfo()['obj'];
    $obj->setGroupId(20);
    $this->assertEquals(20, $obj->getGroupId());
  }

  /**
   * Test LicenseCandidate:createFromArray()
   * Tests the createFromArray method of the LicenseCandidate class.
   *  - # Check if the created LicenseCandidate object matches the expected data
   */
  public function testCreateFromArray()
  {
    $data = $this->getLicenseCandidateInfo()['candidateInfo'];
    $data['rf_pk'] = 9;
    $data['rf_shortname'] = "MIT";
    $data['rf_spdx_id'] = 2;
    $data['rf_fullname'] = "MIT License";
    $data['group_name'] ='fossy';
    $data['rf_text'] = 'Permission is hereby granted, free of charge, to any person obtaining a copy...';
    $data['group_pk'] = 3;

    $expectedObj = $this->getLicenseCandidateInfo(ApiVersion::V1)['candidateInfo'];
    $obj = LicenseCandidate::createFromArray($data);
    $this->assertEquals($expectedObj, $obj->getArray());
  }
}
