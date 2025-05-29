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
