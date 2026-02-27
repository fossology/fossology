<?php
/*
 SPDX-FileCopyrightText: © 2017 Siemens AG
 SPDX-FileCopyrightText: © 2021 Orange by Piotr Pszczola <piotr.pszczola@orange.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
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
   * @var boolean $ojo
   * Whether to schedule ojo agent or not
   */
  private $ojo;
  /**
   * @var boolean $scanoss
   * Whether to schedule scanoss agent or not
   */
  private $scanoss;
  /**
   * @var boolean $pkgagent
   * Whether to schedule reso agent or not
   */
  private $reso;
  /**
   * @var boolean $pkgagent
   * Whether to schedule package agent or not
   */
  private $pkgagent;
  /**
   * @var boolean $ipra
   * Whether to schedule ipra agent or not
   */
  private $ipra;
  /**
   * @var boolean $softwareHeritage
   * Whether to schedule software heritage agent or not
   */
  private $softwareHeritage;
  /**
   * @var boolean $compatibility
   * Whether to schedule compatibility agent or not
   */
  private $compatibility;
  /**
   * @var boolean $kotoba
   * Whether to schedule kotoba agent or not
   */
  private $kotoba;

  /**
   * Analysis constructor.
   * @param boolean $bucket
   * @param boolean $copyright
   * @param boolean $ecc
   * @param boolean $keyword
   * @param boolean $mimetype
   * @param boolean $monk
   * @param boolean $nomos
   * @param boolean $pkgagent
   * @param boolean $ojo
   * @param boolean $reso
   * @param boolean $compatibility
   * @param boolean $scanoss
   * @param boolean $ipra
   * @param boolean $softwareHeritage
   * @param boolean $kotoba
   */
  public function __construct($bucket = false, $copyright = false, $ecc = false, $keyword = false,
    $mimetype = false, $monk = false, $nomos = false, $ojo = false, $reso = false, $pkgagent = false, $compatibility = false, $scanoss = false, $ipra = false, $softwareHeritage = false, $kotoba = false)
  {
    $this->bucket = $bucket;
    $this->copyright = $copyright;
    $this->ecc = $ecc;
    $this->keyword = $keyword;
    $this->mimetype = $mimetype;
    $this->monk = $monk;
    $this->nomos = $nomos;
    $this->ojo = $ojo;
    $this->scanoss = $scanoss;
    $this->reso = $reso;
    $this->pkgagent = $pkgagent;
    $this->ipra = $ipra;
    $this->softwareHeritage = $softwareHeritage;
    $this->compatibility = $compatibility;
    $this->kotoba = $kotoba;
  }

  /**
   * Helper function to set boolean properties from array
   * @param array $array Source array containing boolean values
   * @param array $propertyMap Map of array keys to object properties
   */
  private function setBooleanProperties($array, $propertyMap)
  {
    foreach ($propertyMap as $key => $property) {
      if (array_key_exists($key, $array)) {
        $this->$property = filter_var($array[$key], FILTER_VALIDATE_BOOLEAN);
      }
    }
  }

  /**
   * Set the values of Analysis based on associative array
   * @param array $analysisArray Associative boolean array
   * @return Analysis Current object
   */
  public function setUsingArray($analysisArray, $version = ApiVersion::V1)
  {
    $propertyMap = [
      'bucket' => 'bucket',
      ($version == ApiVersion::V2 ? 'copyrightEmailAuthor' : 'copyright_email_author') => 'copyright',
      'ecc' => 'ecc',
      'keyword' => 'keyword',
      'mime' => 'mimetype',
      'monk' => 'monk',
      'nomos' => 'nomos',
      'ojo' => 'ojo',
      'scanoss' => 'scanoss',
      'reso' => 'reso',
      ($version == ApiVersion::V2 ? "pkgagent" : "package") => 'pkgagent',
      ($version == ApiVersion::V2 ? 'ipra' : 'patent') => 'ipra',
      ($version == ApiVersion::V2 ? 'softwareHeritage' : "heritage") => 'softwareHeritage',
      'compatibility' => 'compatibility',
      ($version == ApiVersion::V2 ? 'kotoba' : 'kotoba_bulk') => 'kotoba'
    ];

    $this->setBooleanProperties($analysisArray, $propertyMap);
    return $this;
  }

  /**
   * Set the values of Analysis based on string from DB
   * @param string $analysisString String from DB settings
   * @return Analysis Current object
   */
  public function setUsingString($analysisString)
  {
    $propertyMap = [
      'bucket' => 'bucket',
      'copyright' => 'copyright',
      'ecc' => 'ecc',
      'keyword' => 'keyword',
      'mimetype' => 'mimetype',
      'monk' => 'monk',
      'nomos' => 'nomos',
      'ojo' => 'ojo',
      'scanoss' => 'scanoss',
      'reso' => 'reso',
      'pkgagent' => 'pkgagent',
      'ipra' => 'ipra',
      'softwareHeritage' => 'softwareHeritage',
      'compatibility' => 'compatibility',
      'kotoba' => 'kotoba'
    ];

    foreach ($propertyMap as $key => $property) {
      if (stristr($analysisString, $key)) {
        $this->$property = true;
      }
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
  public function getOjo()
  {
    return $this->ojo;
  }

  /**
   * @return boolean
   */
  public function getScanoss()
  {
    return $this->scanoss;
  }

  /**
   * @return boolean
   */
  public function getReso()
  {
    return $this->reso;
  }

  /**
   * @return boolean
   */
  public function getPkgagent()
  {
    return $this->pkgagent;
  }

  /**
   * @return boolean
   */
  public function getIpra()
  {
    return $this->ipra;
  }

  /**
   * @return boolean
   */
  public function getSoftwareHeritage()
  {
    return $this->softwareHeritage;
  }

  /**
   * @return bool
   */
  public function getCompatibility()
  {
    return $this->compatibility;
  }

  /**
   * @return boolean
   */
  public function getkotoba()
  {
    return $this->kotoba;
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
   * @param boolean $ojo
   */
  public function setOjo($ojo)
  {
    $this->ojo = filter_var($ojo, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $scanoss
   */
  public function setScanoss($scanoss)
  {
    $this->scanoss = filter_var($scanoss, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $reso
   */
  public function setReso($reso)
  {
    $this->reso = filter_var($reso, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $package
   */
  public function setPkgagent($pkgagent)
  {
    $this->pkgagent = filter_var($pkgagent, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $ipra
   */
  public function setIpra($ipra)
  {
    $this->ipra = filter_var($ipra, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $softwareHeritage
   */
  public function setSoftwareHeritage($softwareHeritage)
  {
    $this->softwareHeritage = filter_var($softwareHeritage, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param bool $compatibility
   */
  public function setCompatibility($compatibility)
  {
    $this->compatibility = filter_var($compatibility, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * @param boolean $kotoba
   */
  public function setkotoba($kotoba)
  {
    $this->kotoba = filter_var($kotoba, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * Get the object as an associative array
   * @return array
   */
  public function getArray($version = ApiVersion::V1)
  {
    if ($version == ApiVersion::V2) {
      return [
        "bucket"    => $this->bucket,
        "copyrightEmailAuthor" => $this->copyright,
        "ecc"       => $this->ecc,
        "keyword"   => $this->keyword,
        "mimetype"  => $this->mimetype,
        "monk"      => $this->monk,
        "nomos"     => $this->nomos,
        "ojo"       => $this->ojo,
        "scanoss"   => $this->scanoss,
        "reso"      => $this->reso,
        "pkgagent"   => $this->pkgagent,
        "ipra"    => $this->ipra,
        "softwareHeritage" => $this->softwareHeritage,
        "compatibility" => $this->compatibility,
        "kotoba" => $this->kotoba
      ];
    } else {
      return [
        "bucket"    => $this->bucket,
        "copyright_email_author" => $this->copyright,
        "ecc"       => $this->ecc,
        "keyword"   => $this->keyword,
        "mimetype"  => $this->mimetype,
        "monk"      => $this->monk,
        "nomos"     => $this->nomos,
        "ojo"       => $this->ojo,
        "scanoss"   => $this->scanoss,
        "reso"      => $this->reso,
        "package"   => $this->pkgagent,
        "patent"    => $this->ipra,
        "heritage" => $this->softwareHeritage,
        "compatibility" => $this->compatibility,
        "kotoba_bulk" => $this->kotoba
      ];
    }
  }
}
