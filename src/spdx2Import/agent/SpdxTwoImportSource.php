<?php
/*
 * Copyright (C) 2015-2017, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace Fossology\SpdxTwoImport;

use Fossology\Lib\Data\License;
use EasyRdf_Graph;

class SpdxTwoImportSource
{
  const TERMS = 'http://spdx.org/rdf/terms#';
  const SPDX_URL = 'http://spdx.org/licenses/';
  const SYNTAX_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

  /** @var EasyRdf_Graph */
  private $graph;
  /** @var array */
  private $index;
  /** @var string */
  private $licenseRefPrefix = "LicenseRef-";

  function __construct($filename, $uri = null)
  {
    $this->graph = $this->loadGraph($filename, $uri);
    $this->index = $this->loadIndex($this->graph);
    // $resources = $this->graph->resources(); // TODO it might be also worth to look at $graph->resources();
  }

  private function loadGraph($filename, $uri = null)
  {
    /** @var EasyRdf_Graph */
    $graph = new EasyRdf_Graph();
    $graph->parseFile($filename, 'rdfxml', $uri);
    return $graph;
  }

  private function loadIndex($graph)
  {
    return $graph->toRdfPhp();
  }

  public function getAllFileIds()
  {
    $fileIds = array();
    foreach ($this->index as $subject => $property){
      if ($this->isPropertyAFile($property))
      {
        $fileIds[] = $subject;
      }
    }
    return $fileIds;
  }

  private function isPropertyOfType(&$property, $type)
  {
    $key = self::SYNTAX_NS . 'type';
    $target = self::TERMS . $type;

    return is_array ($property) &&
      array_key_exists($key, $property) &&
      $property[$key][0]['type'] === "uri" &&
      $property[$key][0]['value'] === $target;
  }

  private function isPropertyAFile(&$property)
  {
    return $this->isPropertyOfType($property, 'File');
  }

  public function getHashesMap($propertyId)
  {
    if ($this->isPropertyAFile($property))
    {
      return array();
    }

    $hashItems = $this->getValues($propertyId, 'checksum');

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

  private function getValue($propertyOrId, $key)
  {
    $values = $this->getValues($propertyOrId, $key);
    if(sizeof($values) === 1)
    {
      return $values[0];
    }
    return false;
  }

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
          echo "ERROR: can not handle entry=[".$entry."] of type=[" . $entry['type'] . "]\n"; // TODO
        }
      }
      return $values;
    }
    return false;
  }

  public function getConcludedLicenseInfoForFile($propertyId)
  {
    return $this->getLicenseInfoForFile($propertyId, 'licenseConcluded');
  }

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

  private function parseLicenseIds($licenseIds, &$output)
  {
    foreach($licenseIds as $licenseId)
    {
      if (strtolower($licenseId) === self::TERMS."noassertion" ||
          strtolower($licenseId) === "http://spdx.org/licenses/noassertion")
      {
        continue;
      }

      $license = $this->index[$licenseId];

      if ($license)
      {
        $this->parseLicense($license, $output);
      }
      else
      {
        if(substr($licenseId, 0, strlen(self::SPDX_URL)) === self::SPDX_URL)
        {
          $spdxId = urldecode(substr($licenseId, strlen(self::SPDX_URL)));
          $output[$spdxId] = null;
        }
      }
    }
  }

  private function parseLicense($license, &$output)
  {
    if(!$license)
    {
      return;
    }
    if (is_string($license))
    {
      $this->parseLicenseIds([$license], $output);
    }
    elseif ($this->isPropertyOfType($license, 'ExtractedLicensingInfo'))
    {
      $licenseId = $this->stripLicenseRefPrefix($this->getValue($license,'licenseId'));
      $output[$licenseId] = new License(
        $licenseId,
        $licenseId,
        $this->getValue($license,'name'),
        "",
        $this->getValue($license,'extractedText'),
        $this->getValues($license,'seeAlso')[0],
        "", // TODO
        strpos($this->getValue($license,'licenseId'), $this->licenseRefPrefix));
    }
    elseif ($this->isPropertyOfType($license, 'License'))
    {
      $licenseId = $this->stripLicenseRefPrefix($this->getValue($license,'licenseId'));
      $output[$licenseId] = new License(
        $licenseId,
        $licenseId,
        $this->getValue($license,'name'),
        "",
        $this->getValue($license,'licenseText'),
        $this->getValues($license,'seeAlso')[0],
        "", // TODO
        strpos($this->getValue($license,'licenseId'), $this->licenseRefPrefix));
    }
    elseif ($this->isPropertyOfType($license, 'DisjunctiveLicenseSet'))
    {
      $subLicenseIds = $this->getValues($license, 'member');
      if (sizeof($subLicenseIds) > 1)
      {
        $output["Dual-license"] = null;
      }
      $this->parseLicenseIds($subLicenseIds, $output);
    }
    elseif ($this->isPropertyOfType($license, 'ConjunctiveLicenseSet'))
    {
      $subLicenseIds = $this->getValues($license, 'member');
      $this->parseLicenseIds($subLicenseIds, $output);
    }
    else
    {
      echo "ERROR: can not handle license=[".$license."] of type=[".gettype($license)."]\n"; // TODO
    }
  }

  private function getLicenseInfoForFile($propertyId, $kind)
  {
    $property = $this->index[$propertyId];
    $licenses = $this->getValues($property, $kind);

    $output = array();
    foreach ($licenses as $license)
    {
      $this->parseLicense($license, $output);
    }
    return $output;
  }

  public function getCopyrightTextsForFile($propertyId)
  {
    return array_map('trim', $this->getValues($propertyId, "copyrightText"));
  }

}
