<?php
/*
 SPDX-FileCopyrightText: © 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\Lib\Data\Spasht;

/**
 * @class DefinitionSummary
 * Holds summary of Definition data for quick display
 */
class DefinitionSummary
{
  /**
   * @var string $declaredLicense
   * Declared License
   */
  private $declaredLicense;

  /**
   * @var string $url
   * Source URL
   */
  private $url;

  /**
   * @var string $release
   * Release date
   */
  private $release;

  /**
   * @var int $files
   * No. of files in package
   */
  private $files;

  /**
   * @var string $attribution
   * First 100 characters of copyrights
   */
  private $attribution;

  /**
   * @var string $discoveredLicenses
   * First 100 characters of discovered licenses
   */
  private $discoveredLicenses;

  /**
   * @var integer $score
   * Package score
   */
  private $score;

  /**
   * Set the object based on object returned by API
   * @param array $obj Array containing the data
   */
  public function __construct($result)
  {
    $this->attribution = "";
    $this->declaredLicense = "NOASSERTION";
    $this->discoveredLicenses = "";
    $this->files = 0;
    $this->release = "";
    $this->url = "";
    $this->score = 0;

    if (array_key_exists('licensed', $result)) {
      $licensed = $result["licensed"];
      if (array_key_exists("declared", $licensed)) {
        $this->declaredLicense = $licensed["declared"];
      }
      if (array_key_exists("facets", $licensed) &&
        array_key_exists("core", $licensed["facets"])) {
        $core = $licensed["facets"]["core"];
        if (array_key_exists("files", $core)) {
          $this->files = intval($core["files"]);
        }
        if (array_key_exists("attribution", $core) &&
          array_key_exists("parties", $core["attribution"])) {
          $this->attribution = substr(
            implode(", ", $core['attribution']['parties']), 0, 100);
        }
        if (array_key_exists("discovered", $core) &&
          array_key_exists("expressions", $core["discovered"])) {
          $this->discoveredLicenses = substr(
            implode(", ", $core['discovered']['expressions']), 0, 100);
        }
      }
    }

    if (array_key_exists("described", $result)) {
      $described = $result["described"];
      if (array_key_exists("sourceLocation", $described) &&
        array_key_exists("url", $described["sourceLocation"])) {
        $this->url = $described["sourceLocation"]["url"];
      } else if (array_key_exists("urls", $described) &&
        array_key_exists("version", $described["urls"])) {
        $this->url = $described["urls"]["version"];
      }
      if (array_key_exists("releaseDate", $described)) {
        $this->release = $described["releaseDate"];
      }
    }

    if (array_key_exists("scores", $result) &&
        array_key_exists("effective", $result["scores"])) {
      $this->score = $result["scores"]["effective"];
    }
  }

  /**
   * @return string
   */
  public function getDeclaredLicense()
  {
    return $this->declaredLicense;
  }

  /**
   * @return string
   */
  public function getUrl()
  {
    return $this->url;
  }

  /**
   * @return string
   */
  public function getRelease()
  {
    return $this->release;
  }

  /**
   * @return number
   */
  public function getFiles()
  {
    return $this->files;
  }

  /**
   * @return string
   */
  public function getAttribution()
  {
    return $this->attribution;
  }

  /**
   * @return string
   */
  public function getDiscoveredLicenses()
  {
    return $this->discoveredLicenses;
  }

  /**
   * @return integer
   */
  public function getScore()
  {
    return $this->score;
  }
}
