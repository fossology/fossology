<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Data;

use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Util\StringOperation;

class LicenseRef
{
  /** @var int */
  private $id;

  /** @var string */
  private $shortName;

  /** @var string */
  private $fullName;

  /** @var string */
  private $spdxId;

  /**
   * @var string
   * SPDX license ref prefix
   */
  const SPDXREF_PREFIX = "LicenseRef-";
  /**
   * @var string
   * SPDX license ref prefix to use
   */
  const SPDXREF_PREFIX_FOSSOLOGY = "LicenseRef-fossology-";

  /**
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseName
   * @param string $spdxId
   */
  function __construct($licenseId, $licenseShortName, $licenseName, $spdxId)
  {
    $this->id = $licenseId;
    $this->shortName = $licenseShortName;
    $this->fullName = $licenseName ? : $licenseShortName;
    $this->spdxId = self::convertToSpdxId($this->shortName, $spdxId);
  }

  /**
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @return string
   */
  public function getFullName()
  {
    return $this->fullName;
  }

  /**
   * @return string
   */
  public function getShortName()
  {
    return $this->shortName;
  }

  /**
   * @return string
   */
  public function getSpdxId()
  {
    return $this->spdxId;
  }

  public function __toString()
  {
    return 'LicenseRef('
      .$this->id
      .", ".$this->spdxId
      .", ".$this->shortName
      .", ".$this->fullName
    .')';
  }

  public function getArray()
  {
    return array(
      'id' => $this->id,
      'spdxId' => $this->spdxId,
      'shortName' => $this->shortName,
      'fullName' => $this->fullName
    );
  }

  /**
   * @brief Given a license's shortname and spdx id, give out spdx id to use in
   *        reports.
   *
   * - In case, the shortname is special, return as is.
   * - In case spdx id is empty, return shortname with spdx prefix.
   * - Otherwise use the provided spdx id
   * @param string $shortname   License's shortname from DB
   * @param string|null $spdxId License's spdx id from DB
   * @return string
   */
  public static function convertToSpdxId($shortname, $spdxId): string
  {
    if (strcasecmp($shortname, LicenseDao::NO_LICENSE_FOUND) === 0 ||
        strcasecmp($shortname, LicenseDao::VOID_LICENSE) === 0) {
      $spdxLicense = $shortname;
    } elseif (empty($spdxId)) {
      $spdxLicense = $shortname;
      if (! StringOperation::stringStartsWith($shortname, self::SPDXREF_PREFIX)) {
        $spdxLicense = self::SPDXREF_PREFIX_FOSSOLOGY . $shortname;
      }
    } else {
      $spdxLicense = $spdxId;
    }
    if (StringOperation::stringStartsWith($spdxLicense, self::SPDXREF_PREFIX)) {
      // License ref can not end with a '+'
      $spdxLicense = preg_replace('/\+$/', '-or-later', $spdxLicense);
    }
    return self::replaceSpaces($spdxLicense);
  }

  /**
   * Replace all spaces with '-' if they are not surrounding 'AND', 'WITH' or
   * 'OR'
   * @param string $licenseName SPDX expression
   * @return string SPDX expression with space replaced with dash
   */
  public static function replaceSpaces($licenseName): string
  {
    $licenseName = str_replace(' ', '-', $licenseName);
    return preg_replace('/-(OR|AND|WITH)-(?!later)/i', ' $1 ', $licenseName);
  }

  /**
   * Combine two license expressions
   * @param $expression License Expression to be combined
   */
  public function combineExpression($expression)
  {
    $left = [];
    if ($this->spdxId === 'LicenseRef-fossology-License-Expression') {
      $left = json_decode($this->fullName, true);
    } else {
      $left = array(
        'type' => 'License',
        'value' => $this->id
      );
    }
    $right = [];
    if ($expression->getSpdxId() === 'LicenseRef-fossology-License-Expression') {
      $right = json_decode($expression->getFullName(), true);
    } else {
      $right = array(
        'type' => 'License',
        'value' => $expression->getId()
      );
    }
    if ($left !== $right) {
      $combinedExpression = array(
        'type' => 'Expression',
        'value' => 'AND',
        'left' => $left,
        'right' => $right
      );
      $this->id = -1;
      $this->shortName = 'License Expression';
      $this->fullName = json_encode($combinedExpression);
      $this->spdxId = self::convertToSpdxId($this->shortName, '');
    }
  }

  /**
   * @param $licenseDao
   * @param $groupId
   * @return string License Expression
   */
  public function getExpression($licenseDao, $groupId)
  {
    $ast = json_decode($this->fullName, true);
    return $this->buildExpression($ast, $licenseDao, $groupId);
  }

  /**
   * @param $node
   * @param $licenseDao
   * @param $groupId
   * @return string License Expression
   */
  protected function buildExpression($node, $licenseDao, $groupId)
  {
    if ($node['type'] === 'License') {
      $licenseNode = $licenseDao->getLicenseById($node['value'], $groupId);
      if (StringOperation::stringStartsWith($licenseNode->getShortName(),
        LicenseRef::SPDXREF_PREFIX)) {
        return $licenseNode->getShortName();
      }
      return $licenseNode->getSpdxId();
    }
    $left = $this->buildExpression($node['left'], $licenseDao, $groupId);
    $right = $this->buildExpression($node['right'], $licenseDao, $groupId);
    $operator = $node['value'];
    return "($left $operator $right)";
  }
}
