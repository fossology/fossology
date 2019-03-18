<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @dir
 * @brief Data models/resources supported by REST api
 * @file
 * @brief Analysis model
 */
namespace Fossology\UI\Api\Models;

/**
 * @class Analysis
 * @brief Model to hold analysis settings
 */
class Analysis
{
  /**
   * @var boolean $bucket
   * Whether to schedule bucket agent or not
   */
  private $bucket;
  /**
   * @var boolean $copyright
   * Whether to schedule copyright agent or not
   */
  private $copyright;
  /**
   * @var boolean $ecc
   * Whether to schedule ecc agent or not
   */
  private $ecc;
  /**
   * @var boolean $keyword
   * Whether to schedule keyword agent or not
   */
  private $keyword;
  /**
   * @var boolean $mimetype
   * Whether to schedule mime type agent or not
   */
  private $mimetype;
  /**
   * @var boolean $monk
   * Whether to schedule monk agent or not
   */
  private $monk;
  /**
   * @var boolean $nomos
   * Whether to schedule nomos agent or not
   */
  private $nomos;
  /**
   * @var boolean $pkgagent
   * Whether to schedule package agent or not
   */
  private $pkgagent;

  /**
   * Analysis constructor.
   * @param boolean $bucket
   * @param boolean $copyright
   * @param boolean $ecc
   * @param boolean $keyword
   * @param boolean $mime
   * @param boolean $monk
   * @param boolean $nomos
   * @param boolean $package
   */
  public function __construct($bucket = false, $copyright = false, $ecc = false, $keyword = false,
    $mimetype = false, $monk = false, $nomos = false, $pkgagent = false)
  {
    $this->bucket = $bucket;
    $this->copyright = $copyright;
    $this->ecc = $ecc;
    $this->keyword = $keyword;
    $this->mimetype = $mimetype;
    $this->monk = $monk;
    $this->nomos = $nomos;
    $this->pkgagent = $pkgagent;
  }

  /**
   * Set the values of Analysis based on associative array
   * @param array $analysisArray Associative boolean array
   * @return Analysis Current object
   */
  public function setUsingArray($analysisArray)
  {
    if(array_key_exists("bucket", $analysisArray)) {
      $this->bucket = filter_var($analysisArray["bucket"], FILTER_VALIDATE_BOOLEAN);
    }
    if(array_key_exists("copyright_email_author", $analysisArray)) {
      $this->copyright = filter_var($analysisArray["copyright_email_author"], FILTER_VALIDATE_BOOLEAN);
    }
    if(array_key_exists("ecc", $analysisArray)) {
      $this->ecc = filter_var($analysisArray["ecc"], FILTER_VALIDATE_BOOLEAN);
    }
    if(array_key_exists("keyword", $analysisArray)) {
      $this->keyword = filter_var($analysisArray["keyword"], FILTER_VALIDATE_BOOLEAN);
    }
    if(array_key_exists("mime", $analysisArray)) {
      $this->mimetype = filter_var($analysisArray["mime"], FILTER_VALIDATE_BOOLEAN);
    }
    if(array_key_exists("monk", $analysisArray)) {
      $this->monk = filter_var($analysisArray["monk"], FILTER_VALIDATE_BOOLEAN);
    }
    if(array_key_exists("nomos", $analysisArray)) {
      $this->nomos = filter_var($analysisArray["nomos"], FILTER_VALIDATE_BOOLEAN);
    }
    if(array_key_exists("package", $analysisArray)) {
      $this->pkgagent = filter_var($analysisArray["package"], FILTER_VALIDATE_BOOLEAN);
    }
    return $this;
  }

  /**
   * Set the values of Analysis based on string from DB
   * @param array $analysisString String from DB settings
   * @return Analysis Current object
   */
  public function setUsingString($analysisString)
  {
    if(stristr($analysisString, "bucket")) {
      $this->bucket = true;
    }
    if(stristr($analysisString, "copyright")) {
      $this->copyright = true;
    }
    if(stristr($analysisString, "ecc")) {
      $this->ecc = true;
    }
    if(stristr($analysisString, "keyword")) {
      $this->keyword = true;
    }
    if(stristr($analysisString, "mimetype")) {
      $this->mimetype = true;
    }
    if(stristr($analysisString, "monk")) {
      $this->monk = true;
    }
    if(stristr($analysisString, "nomos")) {
      $this->nomos = true;
    }
    if(stristr($analysisString, "pkgagent")) {
      $this->pkgagent = true;
    }
    return $this;
  }

  ////// Getters //////
  /**
   * @return boolean
   */
  public function getBucket()
  {
    return $this->bucket;
  }

  /**
   * @return boolean
   */
  public function getCopyright()
  {
    return $this->copyright;
  }

  /**
   * @return boolean
   */
  public function getEcc()
  {
    return $this->ecc;
  }

  /**
   * @return boolean
   */
  public function getKeyword()
  {
    return $this->keyword;
  }

  /**
   * @return boolean
   */
  public function getMime()
  {
    return $this->mimetype;
  }

  /**
   * @return boolean
   */
  public function getMonk()
  {
    return $this->monk;
  }

  /**
   * @return boolean
   */
  public function getNomos()
  {
    return $this->nomos;
  }

  /**
   * @return boolean
   */
  public function getPackage()
  {
    return $this->pkgagent;
  }

  ////// Setters //////
  /**
   * @param boolean $bucket
   */
  public function setBucket($bucket)
  {
    $this->bucket = filter_var($bucket, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $copyright
   */
  public function setCopyright($copyright)
  {
    $this->copyright = filter_var($copyright, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $ecc
   */
  public function setEcc($ecc)
  {
    $this->ecc = filter_var($ecc, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $keyword
   */
  public function setKeyword($keyword)
  {
    $this->keyword = filter_var($keyword, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $mime
   */
  public function setMime($mime)
  {
    $this->mimetype = filter_var($mime, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $monk
   */
  public function setMonk($monk)
  {
    $this->monk = filter_var($monk, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $nomos
   */
  public function setNomos($nomos)
  {
    $this->nomos = filter_var($nomos, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $package
   */
  public function setPackage($package)
  {
    $this->pkgagent = filter_var($package, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * Get the object as an associative array
   * @return array
   */
  public function getArray()
  {
    return [
      "bucket"    => $this->bucket,
      "copyright_email_author" => $this->copyright,
      "ecc"       => $this->ecc,
      "keyword"   => $this->keyword,
      "mimetype"  => $this->mimetype,
      "monk"      => $this->monk,
      "nomos"     => $this->nomos,
      "package"   => $this->pkgagent
    ];
  }


}
