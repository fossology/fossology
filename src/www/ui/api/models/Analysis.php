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

namespace www\ui\api\models;


class Analysis
{
  private $bucket;
  private $copyright;
  private $ecc;
  private $keyword;
  private $mimetype;
  private $monk;
  private $nomos;
  private $pkgagent;

  /**
   * Analysis constructor.
   * @param $bucket boolean
   * @param $copyright boolean
   * @param $ecc boolean
   * @param $keyword boolean
   * @param $mime boolean
   * @param $monk boolean
   * @param $nomos boolean
   * @param $package boolean
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
   * @return www\ui\api\models\Analysis Current object
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
   * Get the object as an array
   * @return array
   */
  public function getArray()
  {
    return [
      "bucket"    => $this->bucket,
      "copyright" => $this->copyright,
      "ecc"       => $this->ecc,
      "keyword"   => $this->keyword,
      "mimetype"  => $this->mimetype,
      "monk"      => $this->monk,
      "nomos"     => $this->nomos,
      "pkgagent"  => $this->pkgagent
    ];
  }


}
