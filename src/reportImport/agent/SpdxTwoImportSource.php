<?php
/*
 SPDX-FileCopyrightText: © 2015-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/
namespace Fossology\ReportImport;

use Fossology\Lib\Data\License;
use EasyRdf\Graph;
require_once 'ReportImportData.php';
require_once 'ReportImportDataItem.php';
require_once 'ImportSource.php';

class SpdxTwoImportSource implements ImportSource
{
  const TERMS = 'http://spdx.org/rdf/terms#';
  const SPDX_URL = 'http://spdx.org/licenses/';
  const SYNTAX_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

  /** @var  string */
  private $filename;
  /** @var  string */
  private $uri;
  /** @var Graph $graph */
  private $graph;
  /** @var array */
  private $index;
  /** @var string */
  private $licenseRefPrefix = "LicenseRef-";

  function __construct($filename, $uri = null)
  {
    $this->filename = $filename;
    $this->uri = $uri;
  }

  /**
   * @return bool
   */
  public function parse()
  {
    $this->graph = $this->loadGraph($this->filename, $this->uri);
    $this->index = $this->loadIndex($this->graph);
    return true;
  }

  private function loadGraph($filename, $uri = null)
  {
    /** @var Graph $graph */
    $graph = new Graph();
    $graph->parseFile($filename, 'rdfxml', $uri);
    return $graph;
  }

  private function loadIndex($graph)
  {
    return $graph->toRdfPhp();
  }

  /**
   * @return array
   */
  public function getAllFiles()
  {
    $fileIds = array();
    foreach ($this->index as $subject => $property){
      if ($this->isPropertyAFile($property))
      {
        $fileIds[$subject] = $this->getFileName($property);
      }
    }
    return $fileIds;
  }

  /**
   * @param $property
   * @param $type
   * @return bool
   */
  private function isPropertyOfType(&$property, $type)
  {
    $key = self::SYNTAX_NS . 'type';
    $target = self::TERMS . $type;

    return is_array ($property) &&
      array_key_exists($key, $property) &&
      sizeof($property[$key]) === 1 &&
      $property[$key][0]['type'] === "uri" &&
      $property[$key][0]['value'] === $target;
  }

  /**
   * @param $property
   * @return bool
   */
  private function isPropertyAFile(&$property)
  {
    return $this->isPropertyOfType($property, 'File');
  }

  /**
   * @param $fileid
   * @return array
   */
  public function getHashesMap($fileid)
  {
    if ($this->isPropertyAFile($property))
    {
      return array();
    }

    $hashItems = $this->getValues($fileid, 'checksum');

    $hashes = array();
    $keyAlgo = self::TERMS . 'algorithm';
    $algoKeyPrefix = self::TERMS . 'checksumAlgorithm_';
    $keyAlgoVal = self::TERMS . 'checksumValue';
    foreach ($hashItems as $hashItem)
    {
      $algorithm = $hashItem[$keyAlgo][0]['value'];
      if(substr($algorithm, 0, strlen($algoKeyPrefix)) === $algoKeyPrefix)
      {
        $algorithm = substr($algorithm, strlen($algoKeyPrefix));
      }
      $hashes[$algorithm] = $hashItem[$keyAlgoVal][0]['value'];
    }

    return $hashes;
  }

  /**
   * @param $propertyOrId
   * @param $key
   * @param null $default
   * @return mixed|null
   */
  private function getValue($propertyOrId, $key, $default=null)
  {
    $values = $this->getValues($propertyOrId, $key);
    if(sizeof($values) === 1)
    {
      return $values[0];
    }
    return $default;
  }

  /**
   * @param $propertyOrId
   * @param $key
   * @return array
   */
  private function getValues($propertyOrId, $key)
  {
    if (is_string($propertyOrId))
    {
      $property = $this->index[$propertyOrId];
    }
    else
    {
      $property = $propertyOrId;
    }

    $key = self::TERMS . $key;
    if (is_array($property) && isset($property[$key]))
    {
      $values = array();
      foreach($property[$key] as $entry)
      {
        if($entry['type'] === 'literal')
        {
          $values[] = $entry['value'];
        }
        elseif($entry['type'] === 'uri')
        {
          if(array_key_exists($entry['value'],$this->index))
          {
            $values[$entry['value']] = $this->index[$entry['value']];
          }
          else
          {
            $values[] = $entry['value'];
          }
        }
        elseif($entry['type'] === 'bnode')
        {
          $values[$entry['value']] = $this->index[$entry['value']];
        }
        else
        {
          error_log("ERROR: can not handle entry=[".$entry."] of type=[" . $entry['type'] . "]");
        }
      }
      return $values;
    }
    return array();
  }

  /**
   * @param $propertyOrId
   * @return mixed|null
   */
  private function getFileName($propertyOrId)
  {
    return $this->getValue($propertyOrId, 'fileName');
  }

  /**
   * @param $propertyId
   * @return array
   */
  public function getConcludedLicenseInfoForFile($propertyId)
  {
    return $this->getLicenseInfoForFile($propertyId, 'licenseConcluded');
  }

  /**
   * @param $propertyId
   * @return array
   */
  public function getLicenseInfoInFileForFile($propertyId)
  {
    return $this->getLicenseInfoForFile($propertyId, 'licenseInfoInFile');
  }

  private function stripLicenseRefPrefix($licenseId)
  {
    if(substr($licenseId, 0, strlen($this->licenseRefPrefix)) === $this->licenseRefPrefix)
    {
      return urldecode(substr($licenseId, strlen($this->licenseRefPrefix)));
    }
    else
    {
      return urldecode($licenseId);
    }
  }

  private function isNotNoassertion($str)
  {
    return ! ( strtolower($str) === self::TERMS."noassertion" ||
               strtolower($str) === "http://spdx.org/licenses/noassertion" );
  }

  private function parseLicenseId($licenseId)
  {
    if (!is_string($licenseId))
    {
      error_log("ERROR: Id not a string: ".$licenseId);
      return array();
    }
    if (strtolower($licenseId) === self::TERMS."noassertion" ||
        strtolower($licenseId) === "http://spdx.org/licenses/noassertion")
    {
      return array();
    }

    $license = $this->index[$licenseId];

    if ($license)
    {
      return $this->parseLicense($license);
    }
    elseif(substr($licenseId, 0, strlen(self::SPDX_URL)) === self::SPDX_URL)
    {
      $spdxId = urldecode(substr($licenseId, strlen(self::SPDX_URL)));
      $item = new ReportImportDataItem($spdxId);
      return array($item);
    }
    else
    {
      error_log("ERROR: can not handle license with ID=".$licenseId);
      return array();
    }
  }

  private function parseLicense($license)
  {
    if (is_string($license))
    {
      return $this->parseLicenseId($license);
    }
    elseif ($this->isPropertyOfType($license, 'ExtractedLicensingInfo'))
    {
      $licenseId = $this->stripLicenseRefPrefix($this->getValue($license,'licenseId'));

      if(strlen($licenseId) > 33 &&
         substr($licenseId, -33, 1) === "-" &&
         ctype_alnum(substr($licenseId, -32)))
      {
        $licenseId = substr($licenseId, 0, -33);
        $item = new ReportImportDataItem($licenseId);
        $item->setCustomText($this->getValue($license,'extractedText'));
        return array($item);

      }
      else
      {
        $item = new ReportImportDataItem($licenseId);
        $item->setLicenseCandidate($this->getValue($license,'name', $licenseId),
                                   $this->getValue($license,'extractedText'),
                                   strpos($this->getValue($license,'licenseId'), $this->licenseRefPrefix));
        return array($item);
      }
    }
    elseif ($this->isPropertyOfType($license, 'License') ||
            $this->isPropertyOfType($license, 'ListedLicense'))
    {
      $licenseId = $this->stripLicenseRefPrefix($this->getValue($license,'licenseId'));
      $item = new ReportImportDataItem($licenseId);
      $item->setLicenseCandidate($this->getValue($license,'name', $licenseId),
                                 $this->getValue($license,'licenseText'),
                                 strpos($this->getValue($license,'licenseId'), $this->licenseRefPrefix));
      return array($item);
    }
    elseif ($this->isPropertyOfType($license, 'DisjunctiveLicenseSet') ||
            $this->isPropertyOfType($license, 'ConjunctiveLicenseSet')
    )
    {
      $output = array();
      $subLicenses = $this->getValues($license, 'member');
      if (sizeof($subLicenses) > 1 &&
          $this->isPropertyOfType($license, 'DisjunctiveLicenseSet'))
      {
        $output[] = new ReportImportDataItem("Dual-license");
      }
      foreach($subLicenses as $subLicense)
      {
        $innerOutput = $this->parseLicense($subLicense);
        foreach($innerOutput as $innerItem)
        {
          $output[] = $innerItem;
        }
      }
      return $output;
    }
    elseif ($this->isPropertyOfType($license, 'OrLaterOperator'))
    {
      $output = array();
      $subLicenses = $this->getValues($license, 'member');
      foreach($subLicenses as $subLicense) {
        /** @var ReportImportDataItem[] $innerOutput */
        $innerOutput = $this->parseLicense($subLicense);
        foreach($innerOutput as $innerItem)
        {
          /** @var License $innerLicenseCandidate */
          $item = new ReportImportDataItem($innerItem->getLicenseId() . "+");

          $innerLicenseCandidate = $innerItem->getLicenseCandidate();
          $item->setLicenseCandidate($innerLicenseCandidate->getFullName() . " or later",
            $innerLicenseCandidate->getText(),
            false);
          $output[] = $item;
        }
      }
      return $output;
    }
    else
    {
      error_log("ERROR: can not handle license=[".$license."] of type=[".gettype($license)."]");
      return array();
    }
  }

  /**
   * @param $propertyId
   * @param $kind
   * @return array
   */
  private function getLicenseInfoForFile($propertyId, $kind)
  {
    $property = $this->index[$propertyId];
    $licenses = $this->getValues($property, $kind);

    $output = array();
    foreach ($licenses as $license)
    {
      $innerOutput = $this->parseLicense($license);
      foreach($innerOutput as $innerItem)
      {
        $output[] = $innerItem;
      }
    }
    return $output;
  }

  private function getCopyrightTextsForFile($propertyId)
  {
    return array_filter(
      array_map(
        'trim',
        $this->getValues($propertyId, "copyrightText")) ,
      array($this, "isNotNoassertion"));
  }

  public function getDataForFile($propertyId)
  {
    return new ReportImportData($this->getLicenseInfoInFileForFile($propertyId),
                                 $this->getConcludedLicenseInfoForFile($propertyId),
                                 $this->getCopyrightTextsForFile($propertyId));
  }
}
