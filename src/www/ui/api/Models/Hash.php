<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Hash model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Hash
 * @brief Hash model holding information about file like checksums and size
 */
class Hash
{

  /**
   * @var array $ALLOWED_KEYS
   * Allowed keys from user to parse
   */
  const ALLOWED_KEYS = ['sha1', 'sha256', 'md5', 'size'];

  /**
   * @var string $id
   * SHA1 checksum
   */
  private $sha1;

  /**
   * @var string $sha256
   * SHA256 checksum
   */
  private $sha256;

  /**
   * @var string $md5
   * MD5 checksum
   */
  private $md5;

  /**
   * @var int $size
   * Size in bytes
   */
  private $size;

  /**
   * Hash constructor.
   *
   * @param string $sha1    SHA1 checksum
   * @param string $md5     MD5 checksum
   * @param string $sha256  SHA256 checksum
   * @param integer $size   Size of the file in bytes
   */
  public function __construct($sha1 = null, $md5 = null, $sha256 = null,
    $size = null)
  {
    $this->sha1 = $sha1;
    $this->md5 = $md5;
    $this->sha256 = $sha256;
    if ($size === null) {
      $this->size = $size;
    } else {
      $this->size = intval($size);
    }
  }

  /**
   * @return string
   */
  public function getSha1()
  {
    return $this->sha1;
  }

  /**
   * @return string
   */
  public function getSha256()
  {
    return $this->sha256;
  }

  /**
   * @return string
   */
  public function getMd5()
  {
    return $this->md5;
  }

  /**
   * @return number
   */
  public function getSize()
  {
    return $this->size;
  }

  /**
   * Get the object as associative array
   *
   * @return array
   */
  public function getArray()
  {
    return [
      'sha1'    => $this->getSha1(),
      'md5'     => $this->getMd5(),
      'sha256'  => $this->getSha256(),
      'size'    => $this->getSize()
    ];
  }

  /**
   * Creates Hash object from given array. If the input array contains
   * additional keys, return NULL.
   *
   * @param array $inputArray Array to parse
   * @return NULL|Hash NULL if input contains additional keys, Hash otherwise
   */
  public static function createFromArray($inputArray)
  {
    $sha1 = null;
    $md5 = null;
    $sha256 = null;
    $size = null;
    $inputKeys = array_keys($inputArray);
    $intersectKeys = array_intersect($inputKeys, self::ALLOWED_KEYS);
    if (count($inputKeys) > 0 && count($intersectKeys) != count($inputKeys)) {
      return null;
    }
    if (array_key_exists('sha1', $inputArray)) {
      $sha1 = $inputArray['sha1'];
    }
    if (array_key_exists('md5', $inputArray)) {
      $md5 = $inputArray['md5'];
    }
    if (array_key_exists('sha256', $inputArray)) {
      $sha256 = $inputArray['sha256'];
    }
    if (array_key_exists('size', $inputArray)) {
      $size = $inputArray['size'];
    }
    return new Hash($sha1, $md5, $sha256, $size);
  }
}
