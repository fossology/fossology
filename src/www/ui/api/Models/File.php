<?php
/*
 SPDX-FileCopyrightText: © 2017, 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief File model
 */

namespace Fossology\UI\Api\Models;

/**
 * @class File
 * @brief File model holding information about a single file
 */
class File
{

  /**
   * @var string $NOT_FOUND
   * Message for files not found in DB
   */
  const NOT_FOUND = "Not found";
  /**
   * @var string $INVALID
   * Message for files not found in DB
   */
  const INVALID = "Invalid keys";

  /**
   * @var Hash $hash
   * Hash info of the file
   */
  private $hash;
  /**
   * @var Findings $findings
   * Findings about the file
   */
  private $findings;
  /**
   * @var array $uploads
   * Upload IDs the file belongs to
   */
  private $uploads;
  /**
   * @var string $message
   * Message associated with the file
   */
  private $message;

  /**
   * File constructor.
   * @param Hash $hash
   */
  public function __construct($hash)
  {
    $this->hash = $hash;
    $this->findings = null;
    $this->uploads = null;
    $this->message = "";
  }

  /**
   * @return Hash
   */
  public function getHash()
  {
    return $this->hash;
  }

  /**
   * @return Findings|null If message is `NOT_FOUND` or `INVALID`, returns null,
   * findings otherwise
   */
  public function getFindings()
  {
    if ($this->message == self::NOT_FOUND || $this->message == self::INVALID) {
      return null;
    }
    return $this->findings;
  }

  /**
   * @return array|null
   */
  public function getUploads()
  {
    if (count($this->uploads) == 1 && $this->uploads[0] == 0) {
      return null;
    }
    return $this->uploads;
  }

  /**
   * @return string
   */
  public function getMessage()
  {
    return $this->message;
  }

  /**
   * @param Hash $hash
   */
  public function setHash($hash)
  {
    $this->hash = $hash;
  }

  /**
   * @param Findings $findings
   */
  public function setFindings($findings)
  {
    $this->findings = $findings;
  }

  /**
   * @param array $uploads
   */
  public function setUploads($uploads)
  {
    if (is_array($uploads)) {
      $this->uploads = $uploads;
    } else {
      if ($this->uploads === null) {
        $this->uploads = array();
      }
      $this->uploads[] = intval($uploads);
    }
  }

  /**
   * @param string $message
   */
  public function setMessage($message)
  {
    $this->message = $message;
  }

  /**
   * Get the file element as associative array
   *
   * Do not return findings and uploads if message is `NOT_FOUND` or `INVALID`.
   * @return array
   */
  public function getArray()
  {
    $returnArray = [];
    $returnArray['hash'] = $this->hash->getArray();
    if ($this->message != self::NOT_FOUND && $this->message != self::INVALID) {
      $returnArray['findings'] = $this->getFindings()->getArray();
      $returnArray['uploads'] = $this->getUploads();
    } else {
      $returnArray['message'] = $this->getMessage();
    }
    return $returnArray;
  }

  /**
   * Parse a list of hashes and generate array of File objects.
   *
   * @param array $inputList Array of hashes to parse
   * @return File[] Array of files
   * @sa Fossology::UI::Api::Models::Hash::createFromArray()
   */
  public static function parseFromArray($inputList)
  {
    $fileList = [];
    foreach ($inputList as $fileJson) {
      $hash = Hash::createFromArray($fileJson);
      if ($hash === null) {
        $hash = new Hash();
        $file = new File($hash);
        $file->setMessage(self::INVALID);
      } else {
        $file = new File($hash);
      }
      $fileList[] = $file;
    }
    return $fileList;
  }
}
