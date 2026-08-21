<?php
/*
 SPDX-FileCopyrightText: © 2021 HH Partners

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief License
 */

namespace Fossology\UI\Api\Models;

/**
 * @class License
 * @package Fossology\UI\Api\Models
 * @brief License model to hold license related info
 */
class License
{
  /**
   * @var array $ALLOWED_KEYS
   * Allowed keys from user to parse
   */
  const ALLOWED_KEYS = ['shortName', 'fullName', 'text', 'url', 'risk',
    'isCandidate', 'mergeRequest'];
  /**
   * @var integer $id
   * License id
   */
  private $id;
  /**
   * @var string $shortName
   * Short name of the license
   */
  private $shortName;
  /**
   * @var string $fullName
   * Full name of the license
   */
  private $fullName;
  /**
   * @var string $text
   * The text of the license
   */
  private $text;
  /**
   * @var string $url
   * License URL
   */
  private $url;
  /**
   * @var array|null $obligations
   * Obligations for the license
   */
  private $obligations;
  /**
   * @var integer|null $license
   * The risk level of the license
   */
  private $risk;
  /**
   * @var boolean $isCandidate
   * Is the license a candidate license?
   */
  private $isCandidate;
  /**
   * @var boolean $mergeRequest
   * Create merge request for candidate license?
   */
  private $mergeRequest;
  /**
   * @var string|null $spdxId
   * SPDX id of the license
   */
  private $spdxId;
  /**
   * @var boolean|null $active
   * Is the license active?
   */
  private $active;
  /**
   * @var string|null $licenseType
   * Type of the license (e.g. Permissive, Copyleft)
   */
  private $licenseType;
  /**
   * @var array|null $parentLicense
   * Parent license this license is mapped to for conclusions, if any
   */
  private $parentLicense;
  /**
   * @var array|null $reportLicense
   * License this license is mapped to for reports, if any
   */
  private $reportLicense;

  /**
   * License constructor.
   *
   * @param integer $id
   * @param string $shortName
   * @param string $fullName
   * @param string $text
   * @param string $url
   * @param array  $obligations
   * @param integer|null $risk
   * @param boolean $isCandidate
   */
  public function __construct(
    $id,
    $shortName = "",
    $fullName = "",
    $text = "",
    $url = "",
    $obligations = null,
    $risk = null,
    $isCandidate = false
  )
  {
    $this->id = intval($id);
    $this->setShortName($shortName);
    $this->setFullName($fullName);
    $this->setText($text);
    $this->setUrl($url);
    $this->setObligations($obligations);
    $this->setRisk($risk);
    $this->setIsCandidate($isCandidate);
    $this->mergeRequest = false;
    $this->spdxId = null;
    $this->active = null;
    $this->licenseType = null;
    $this->parentLicense = null;
    $this->reportLicense = null;
  }

  /**
   * JSON representation of the license
   * @return string
   */
  public function getJSON()
  {
    return json_encode($this->getArray());
  }

  /**
   * Get License element as associative array
   * @return array
   */
  public function getArray()
  {
    $data = [
      'id' => $this->getId(),
      'shortName' => $this->getShortName(),
      'fullName' => $this->getFullName(),
      'text' => $this->getText(),
      'url' => $this->getUrl(),
      'risk' => $this->getRisk(),
      'isCandidate' => $this->getIsCandidate()
    ];
    if ($this->obligations !== null) {
      $data['obligations'] = $this->getObligations();
    }
    if ($this->spdxId !== null) {
      $data['spdxId'] = $this->spdxId;
    }
    if ($this->active !== null) {
      $data['active'] = $this->active;
    }
    if ($this->licenseType !== null) {
      $data['licenseType'] = $this->licenseType;
    }
    if ($this->parentLicense !== null) {
      $data['parentLicense'] = $this->parentLicense;
    }
    if ($this->reportLicense !== null) {
      $data['reportLicense'] = $this->reportLicense;
    }
    return $data;
  }

  /**
   * Get the license ID
   * @return integer License's ID
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Get the license's short name
   * @return string License's short name
   */
  public function getShortName()
  {
    if ($this->shortName === null) {
      return "";
    }
    return $this->shortName;
  }

  /**
   * Get the license's full name
   * @return string License's short name
   */
  public function getFullName()
  {
    if ($this->fullName === null) {
      return "";
    }
    return $this->fullName;
  }

  /**
   * Get the license's text
   * @return string License's text
   */
  public function getText()
  {
    if ($this->text === null) {
      return "";
    }
    return $this->text;
  }

  /**
   * Get the license's URL
   * @return string License's URL
   */
  public function getUrl()
  {
    if ($this->url === null) {
      return "";
    }
    return $this->url;
  }

  /**
   * Get the license's risk level
   * @return int|null License's risk level if set, null if not set
   */
  public function getRisk()
  {
    return $this->risk;
  }

  /**
   * Is the license a candidate?
   * @return boolean
   */
  public function getIsCandidate()
  {
    return $this->isCandidate;
  }

  /**
   * Get the license's associated obligations
   * @return array|null License's associated obligations if set, null if not set
   */
  public function getObligations()
  {
    if ($this->obligations === null) {
      return null;
    }

    $obligationList = [];
    foreach ($this->obligations as $obligation) {
      $obligationList[] = $obligation->getArray();
    }
    return $obligationList;
  }

  /**
   * A new merge request to be made for the license?
   * @return boolean
   */
  public function getMergeRequest()
  {
    return $this->mergeRequest;
  }

  /**
   * Get the license's SPDX id
   * @return string|null License's SPDX id if set, null if not set
   */
  public function getSpdxId()
  {
    return $this->spdxId;
  }

  /**
   * Is the license active?
   * @return boolean|null License's active status if set, null if not set
   */
  public function getActive()
  {
    return $this->active;
  }

  /**
   * Get the license's type (e.g. Permissive, Copyleft)
   * @return string|null License's type if set, null if not set
   */
  public function getLicenseType()
  {
    return $this->licenseType;
  }

  /**
   * Get the parent license this license is mapped to for conclusions
   * @return array|null Parent license reference if set, null if not set
   */
  public function getParentLicense()
  {
    return $this->parentLicense;
  }

  /**
   * Get the license this license is mapped to for reports
   * @return array|null Report license reference if set, null if not set
   */
  public function getReportLicense()
  {
    return $this->reportLicense;
  }

  /**
   * Set the license's short name
   * @param string $shortName License's short name
   */
  public function setShortName($shortName)
  {
    $this->shortName = convertToUTF8($shortName, false);
  }

  /**
   * Set the license's full name
   * @param string $fullName License's full name
   */
  public function setFullName($fullName)
  {
    $this->fullName = convertToUTF8($fullName, false);
  }

  /**
   * Set the license's text
   * @param string $text License's text
   */
  public function setText($text)
  {
    $this->text = convertToUTF8($text, false);
  }

  /**
   * Set the license's URL
   * @param string $url License's URL
   */
  public function setUrl($url)
  {
    $this->url = convertToUTF8($url, false);
  }

  /**
   * Set the license's risk level
   * @param int|null $risk License's risk level or null
   */
  public function setRisk($risk)
  {
    // invtval returns 0 for null, so check for nullness to preserve the
    // difference in the response.
    if (!is_null($risk)) {
      $this->risk = intval($risk);
    } else {
      $this->risk = $risk;
    }
  }

  /**
   * Set if license is candidate.
   * @param boolean $isCandidate
   */
  public function setIsCandidate($isCandidate)
  {
    $this->isCandidate = filter_var($isCandidate, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * Set the license's associated obligations
   * @param array $obligations Obligations to be added
   */
  public function setObligations($obligations)
  {
    if (is_array($obligations)) {
      $this->obligations = [];
    } elseif ($obligations === null) {
      $this->obligations = null;
      return;
    }
    foreach ($obligations as $obligation) {
      $this->addObligation($obligation);
    }
  }

  /**
   * Add obligation to license's associated obligations
   * @param Obligation $obligation A single obligation to be added
   */
  public function addObligation($obligation)
  {
    if ($this->obligations === null) {
      $this->obligations = [];
    }
    $this->obligations[] = $obligation;
  }

  /**
   * Set the license's SPDX id
   * @param string|null $spdxId License's SPDX id
   */
  public function setSpdxId($spdxId)
  {
    $this->spdxId = $spdxId;
  }

  /**
   * Set whether the license is active
   * @param boolean|null $active License's active status
   */
  public function setActive($active)
  {
    if (!is_null($active)) {
      $this->active = filter_var($active, FILTER_VALIDATE_BOOLEAN);
    } else {
      $this->active = null;
    }
  }

  /**
   * Set the license's type (e.g. Permissive, Copyleft)
   * @param string|null $licenseType License's type
   */
  public function setLicenseType($licenseType)
  {
    $this->licenseType = $licenseType;
  }

  /**
   * Set the parent license this license is mapped to for conclusions
   * @param array|null $parentLicense Parent license reference
   */
  public function setParentLicense($parentLicense)
  {
    $this->parentLicense = $parentLicense;
  }

  /**
   * Set the license this license is mapped to for reports
   * @param array|null $reportLicense Report license reference
   */
  public function setReportLicense($reportLicense)
  {
    $this->reportLicense = $reportLicense;
  }

  /**
   * Set the merge request for new license
   * @param boolean $mergeRequest
   */
  public function setMergeRequest($mergeRequest)
  {
    $this->mergeRequest = filter_var($mergeRequest, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * Parse a license from JSON input.
   *
   * @param array $inputLicense Object sent by user
   * @return License
   */
  public static function parseFromArray($inputLicense)
  {
    $inputKeys = array_keys($inputLicense);
    $intersectKeys = array_intersect($inputKeys, self::ALLOWED_KEYS);
    if (count($inputKeys) > 0 && count($intersectKeys) != count($inputKeys)) {
      return -1;
    }
    if (array_search('shortName', $inputKeys) === false) {
      return -2;
    }
    $newLicense = new License(0);
    if (array_key_exists('shortName', $inputLicense)) {
      $newLicense->setShortName($inputLicense['shortName']);
    }
    if (array_key_exists('fullName', $inputLicense)) {
      $newLicense->setFullName($inputLicense['fullName']);
    }
    if (array_key_exists('text', $inputLicense)) {
      $newLicense->setText($inputLicense['text']);
    }
    if (array_key_exists('url', $inputLicense)) {
      $newLicense->setUrl($inputLicense['url']);
    }
    if (array_key_exists('risk', $inputLicense)) {
      $newLicense->setRisk($inputLicense['risk']);
    }
    if (array_key_exists('isCandidate', $inputLicense)) {
      $newLicense->setIsCandidate($inputLicense['isCandidate']);
    }
    if (array_key_exists('mergeRequest', $inputLicense)) {
      $newLicense->setMergeRequest($inputLicense['mergeRequest']);
    }
    return $newLicense;
  }
}
