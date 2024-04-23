<?php
/*
 SPDX-FileCopyrightText: © 2015-2017,2023-2024 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\ReportImport;

use EasyRdf\Graph;
use EasyRdf\Literal;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Util\StringOperation;

require_once 'ReportImportData.php';
require_once 'ReportImportDataItem.php';
require_once 'ImportSource.php';

class SpdxTwoImportSource implements ImportSource
{
  const TERMS = 'http://spdx.org/rdf/terms#';
  const SPDX_URL = 'http://spdx.org/licenses/';
  const SPDX_FILE = 'spdx:File';

  /** @var string */
  private $filename;
  /** @var string */
  private $uri;
  /** @var Graph $graph */
  private $graph;
  /** @var Resource */
  private $spdxDoc;

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
    RdfNamespace::set('spdx', self::TERMS);
    $this->graph = $this->loadGraph($this->filename, $this->uri);
    $this->spdxDoc = $this->getSpdxDoc();
    return $this->graph !== null && $this->spdxDoc !== null;
  }

  private function loadGraph($filename, $uri = null)
  {
    /** @var Graph $graph */
    $graph = new Graph();
    if (StringOperation::stringEndsWith($filename, ".rdf") || StringOperation::stringEndsWith($filename, ".rdf.xml")) {
      $graph->parseFile($filename, 'rdfxml', $uri);
    } elseif (StringOperation::stringEndsWith($filename, ".ttl")) {
      $graph->parseFile($filename, 'turtle', $uri);
    } else {
      $graph->parseFile($filename, 'guess', $uri);
    }
    return $graph;
  }

  private function getSpdxDoc()
  {
    $docs = $this->graph->allOfType("spdx:SpdxDocument");
    if (count($docs) == 1) {
      return $docs[0];
    } else {
      error_log("ERROR: Expected exactly one SPDX document, found " . count($docs));
      return null;
    }
  }

  /**
   * @return array
   */
  public function getAllFiles()
  {
    $relationship = $this->spdxDoc->getResource("spdx:relationship");
    $element = $relationship->getResource("spdx:relatedSpdxElement");
    /* @var $files Resource[] */
    $files = $element->allResources("spdx:hasFile");
    if (count($files) < 1) {
      $files = $this->getNestedFiles($element);
    }
    $fileIds = array();
    foreach ($files as $file) {
      $fileIds[$file->getUri()] = trim($file->getLiteral("spdx:fileName")->getValue());
    }
    return $fileIds;
  }

  /**
   * @param Resource $element Package element
   * @return Resource[] Nested files
   */
  private function getNestedFiles($element): array
  {
    $nestedFiles = [];
    /** @var Resource[] $nestedRelations */
    $nestedRelations = $element->allResources("spdx:relationship");
    foreach ($nestedRelations as $nestedRelation) {
      $relationType = $nestedRelation->getResource("spdx:relationshipType");
      if (StringOperation::stringEndsWith($relationType->getUri(), "contains")) {
        $fileResource = $nestedRelation->getResource("spdx:relatedSpdxElement");
        if ($fileResource !== null) {
          $nestedFiles[] = $fileResource;
        }
      }
    }
    return $nestedFiles;
  }

  /**
   * @param string $fileId File URI
   * @return array
   */
  public function getHashesMap($fileId)
  {
    $fileNode = $this->graph->resource($fileId, self::SPDX_FILE);
    if ($fileNode->getLiteral("spdx:fileName") == null) {
      return [];
    }

    $hashes = [];

    $algoKeyPrefix = self::TERMS . 'checksumAlgorithm_';

    /* @var $checksums Resource[] */
    $checksums = $fileNode->allResources("spdx:checksum");
    foreach ($checksums as $checksum) {
      $algorithm = $checksum->getResource("spdx:algorithm");
      $value = $checksum->getLiteral("spdx:checksumValue");
      if ($algorithm != null && $value != null) {
        if (StringOperation::stringStartsWith($algorithm->getUri(), $algoKeyPrefix)) {
          $algo = substr($algorithm->getUri(), strlen($algoKeyPrefix));
        } else {
          $algo = $algorithm->getUri();
        }
        $hashes[$algo] = trim($value->getValue());
      }
    }
    return $hashes;
  }

  /**
   * @param string $fileid File URI
   * @return ReportImportData
   */
  public function getDataForFile($fileid): ReportImportData
  {
    return new ReportImportData($this->getLicenseInfoInFileForFile($fileid),
      $this->getConcludedLicenseInfoForFile($fileid),
      $this->getCopyrightTextsForFile($fileid));
  }

  /**
   * @param $propertyId
   * @return array
   */
  public function getLicenseInfoInFileForFile($propertyId)
  {
    return $this->getLicenseInfoForFile($propertyId, 'licenseInfoInFile');
  }

  /**
   * @param string $fileId File URI
   * @param string $kind licenseConcluded or licenseInfoInFile
   * @return array
   */
  private function getLicenseInfoForFile($fileId, $kind)
  {
    $fileNode = $this->graph->resource($fileId, self::SPDX_FILE);
    /* @var $licenses Resource[] */
    $licenses = $fileNode->allResources("spdx:$kind");

    $output = [];
    foreach ($licenses as $license) {
      if (!$this->isNotNoassertion($license->getUri())) {
        continue;
      }
      $innerOutput = $this->parseLicense($license);
      foreach ($innerOutput as $innerItem) {
        $output[] = $innerItem;
      }
    }
    return $output;
  }

  private function isNotNoassertion($str)
  {
    return !(strtolower($str) === self::TERMS . "noassertion" ||
      strtolower($str) === "http://spdx.org/licenses/noassertion");
  }

  /**
   * Parse license info. Element can be:
   * -# License ID (string)
   * -# License resource (ExtractedLicensingInfo, License, ListedLicense)
   * -# License set (DisjunctiveLicenseSet, ConjunctiveLicenseSet)
   * -# OrLaterOperator
   * -# Old-style license ID (Resource)
   * @param Resource|string $license
   * @return array|ReportImportDataItem[]
   */
  private function parseLicense($license)
  {
    if (is_string($license)) {
      return $this->parseLicenseId($license);
    } elseif ($license->isA('spdx:ExtractedLicensingInfo') ||
      $license->isA('spdx:License') ||
      $license->isA('spdx:ListedLicense')) {
      return $this->handleLicenseInfo($license);
    } elseif ($license->isA('spdx:DisjunctiveLicenseSet') ||
      $license->isA('spdx:ConjunctiveLicenseSet')) {
      return $this->handleLicenseSet($license);
    } elseif ($license->isA('spdx:OrLaterOperator')) {
      return $this->handleOrLaterOperator($license);
    }
    if ($license instanceof Resource || $license instanceof Graph) {
      return $this->parseLicenseId($license->getUri());
    } else {
      error_log("ERROR: can not handle license=[" . $license . "] of class=[" .
        get_class($license) . "]");
      return [];
    }
  }

  private function parseLicenseId($licenseId)
  {
    if (!is_string($licenseId)) {
      error_log("ERROR: Id not a string: " . $licenseId);
      return [];
    }
    if (!$this->isNotNoassertion($licenseId)) {
      return [];
    }

    if (StringOperation::stringStartsWith($licenseId, self::SPDX_URL)) {
      $spdxId = urldecode(substr($licenseId, strlen(self::SPDX_URL)));
      $item = new ReportImportDataItem($spdxId);
      return [$item];
    } else {
      error_log("ERROR: can not handle license with ID=" . $licenseId);
      return [];
    }
  }

  /**
   * From License resource, create ReportImportDataItem.
   * If the resource is an ExtractedLicensingInfo, the license is generally a
   * candidate license.
   * @param Resource $license License resource
   * @return ReportImportDataItem[]
   */
  private function handleLicenseInfo($license)
  {
    $licenseIdLiteral = $license->getLiteral("spdx:licenseId");
    $licenseNameLiteral = $license->getLiteral("spdx:name");
    if ($license->isA('spdx:ExtractedLicensingInfo')) {
      $licenseTextLiteral = $license->getLiteral("spdx:extractedText");
    } else {
      $licenseTextLiteral = $license->getLiteral("spdx:licenseText");
    }
    if ($licenseIdLiteral != null && $licenseNameLiteral != null &&
      $licenseTextLiteral != null) {
      $seeAlsoLiteral = $license->getLiteral("rdfs:seeAlso");
      $rawLicenseId = $licenseIdLiteral->getValue();
      $licenseId = $this->stripLicenseRefPrefix($rawLicenseId);

      if ($license->isA('spdx:ExtractedLicensingInfo') &&
        (strlen($licenseId) > 33 &&
          substr($licenseId, -33, 1) === "-" &&
          ctype_alnum(substr($licenseId, -32))
        )) {
        $licenseId = substr($licenseId, 0, -33);
        $item = new ReportImportDataItem($licenseId);
        $item->setCustomText($licenseTextLiteral->getValue());
      } else {
        $item = new ReportImportDataItem($licenseId);
        $item->setLicenseCandidate($licenseNameLiteral->getValue(),
          $licenseTextLiteral->getValue(),
          strpos($rawLicenseId, LicenseRef::SPDXREF_PREFIX),
          ($seeAlsoLiteral != null) ? $seeAlsoLiteral->getValue() : ""
        );
      }
      return [$item];
    }
    return [];
  }

  private function stripLicenseRefPrefix($licenseId)
  {
    if (StringOperation::stringStartsWith($licenseId, LicenseRef::SPDXREF_PREFIX)) {
      if (StringOperation::stringStartsWith($licenseId, LicenseRef::SPDXREF_PREFIX_FOSSOLOGY)) {
        return urldecode(substr($licenseId, strlen(LicenseRef::SPDXREF_PREFIX_FOSSOLOGY)));
      }
      return urldecode(substr($licenseId, strlen(LicenseRef::SPDXREF_PREFIX)));
    } else {
      return urldecode($licenseId);
    }
  }

  private function handleLicenseSet($license)
  {
    $output = [];
    $subLicenses = $license->allResources("spdx:member");
    if (sizeof($subLicenses) > 1 && $license->isA('spdx:DisjunctiveLicenseSet')) {
      $output[] = new ReportImportDataItem("Dual-license");
    }
    foreach ($subLicenses as $subLicense) {
      $innerOutput = $this->parseLicense($subLicense);
      $output = array_merge($output, $innerOutput);
    }
    return $output;
  }

  private function handleOrLaterOperator($license)
  {
    $output = [];
    $subLicenses = $license->allResources("spdx:member");
    foreach ($subLicenses as $subLicense) {
      /** @var ReportImportDataItem[] $innerOutput */
      $innerOutput = $this->parseLicense($subLicense);
      foreach ($innerOutput as $innerItem) {
        /** @var License $innerLicenseCandidate */
        $item = new ReportImportDataItem($innerItem->getLicenseId() . "-or-later");

        $innerLicenseCandidate = $innerItem->getLicenseCandidate();
        $item->setLicenseCandidate($innerLicenseCandidate->getFullName() . " or later",
          $innerLicenseCandidate->getText(), false,
          $innerLicenseCandidate->getUrl()
        );
        $output[] = $item;
      }
    }
    return $output;
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
   * @param string $fileId File URI
   * @return array Copyrights from the file or empty array
   */
  private function getCopyrightTextsForFile($fileId): array
  {
    $fileNode = $this->graph->resource($fileId, self::SPDX_FILE);
    /* @var $licenses Literal[] */
    $copyrights = $fileNode->allLiterals("spdx:copyrightText");
    if (count($copyrights) == 1 && $copyrights[0] instanceof Literal) {
      # There should be only 1 copyright element containing 1 copyright per line
      $copyrights = explode("\n", trim($copyrights[0]->getValue()));
      return array_map('trim', $copyrights);
    }
    # There is only 1 copyright literal or noAssertion resource
    return [];
  }
}
