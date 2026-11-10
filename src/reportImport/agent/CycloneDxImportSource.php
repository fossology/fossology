<?php
/*
 SPDX-FileCopyrightText: © 2026 Krrish Biswas

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\ReportImport;

require_once 'ImportSource.php';
require_once 'ReportImportData.php';
require_once 'ReportImportDataItem.php';
require_once 'ReportImportHelper.php';

/**
 * Import source for CycloneDX JSON SBOMs (specVersion 1.4 and later).
 *
 * Only licenses explicitly marked with `"acknowledgement": "concluded"` are
 * treated as concluded licenses. Every other license (declared, unmarked or
 * from the `evidence` block) is imported as a license info in file.
 */
class CycloneDxImportSource implements ImportSource
{
  const ACK_CONCLUDED = 'concluded';

  private $filename;
  private $bomData;
  private $componentsByBomRef = [];

  function __construct($filename)
  {
    $this->filename = $filename;
  }

  /**
   * @return bool
   */
  public function parse()
  {
    $content = file_get_contents($this->filename);
    if ($content === false) {
      return false;
    }

    $this->bomData = json_decode($content, true);
    if (!is_array($this->bomData)) {
      return false;
    }

    if (!isset($this->bomData['bomFormat']) ||
        strtolower($this->bomData['bomFormat']) !== 'cyclonedx') {
      return false;
    }

    if (isset($this->bomData['components']) && is_array($this->bomData['components'])) {
      foreach ($this->bomData['components'] as $index => $component) {
        if (!is_array($component)) {
          continue;
        }
        $bomRef = $this->getComponentKey($component, $index);
        $this->componentsByBomRef[$bomRef] = $component;
      }
    }

    return true;
  }

  /**
   * Build a unique, deterministic key for a component. Prefer the bom-ref,
   * fall back to the component index so components sharing a name do not
   * overwrite each other.
   * @param array $component
   * @param int $index
   * @return string
   */
  private function getComponentKey($component, $index)
  {
    $bomRef = isset($component['bom-ref']) && $component['bom-ref'] !== ''
      ? (string) $component['bom-ref'] : "component-$index";
    if (array_key_exists($bomRef, $this->componentsByBomRef)) {
      $bomRef = "$bomRef-$index";
    }
    return $bomRef;
  }

  /**
   * @return string|null
   */
  public function getVersion()
  {
    return isset($this->bomData['specVersion']) ? $this->bomData['specVersion'] : null;
  }

  /**
   * @return array
   */
  public function getAllFiles()
  {
    $files = [];
    foreach ($this->componentsByBomRef as $bomRef => $component) {
      $files[$bomRef] = isset($component['name']) ? $component['name'] : $bomRef;
    }
    return $files;
  }

  /**
   * @param string $fileId
   * @return array
   */
  public function getHashesMap($fileId)
  {
    if (!isset($this->componentsByBomRef[$fileId])) {
      return [];
    }

    $component = $this->componentsByBomRef[$fileId];
    if (!isset($component['hashes']) || !is_array($component['hashes'])) {
      return [];
    }

    $hashesMap = [];
    foreach ($component['hashes'] as $hash) {
      if (isset($hash['alg']) && isset($hash['content'])) {
        // CycloneDX 'SHA-1' -> FOSSology 'sha1'
        $alg = strtolower(str_replace('-', '', $hash['alg']));
        $hashesMap[$alg] = $hash['content'];
      }
    }
    return $hashesMap;
  }

  /**
   * @param string $fileid
   * @return ReportImportData
   */
  public function getDataForFile($fileid)
  {
    if (!isset($this->componentsByBomRef[$fileid])) {
      return new ReportImportData();
    }

    $component = $this->componentsByBomRef[$fileid];

    $concludedLicenses = [];
    $infoInFileLicenses = [];
    $copyrightTexts = [];

    if (isset($component['licenses']) && is_array($component['licenses'])) {
      foreach ($component['licenses'] as $licenseEntry) {
        $items = $this->parseLicenseEntry($licenseEntry);
        if ($this->getAcknowledgement($licenseEntry) === self::ACK_CONCLUDED) {
          $concludedLicenses = array_merge($concludedLicenses, $items);
        } else {
          $infoInFileLicenses = array_merge($infoInFileLicenses, $items);
        }
      }
    }

    // CycloneDX 1.5+ evidence block
    if (isset($component['evidence']) && is_array($component['evidence'])) {
      $evidence = $component['evidence'];
      if (isset($evidence['licenses']) && is_array($evidence['licenses'])) {
        foreach ($evidence['licenses'] as $licenseEntry) {
          $infoInFileLicenses = array_merge($infoInFileLicenses,
            $this->parseLicenseEntry($licenseEntry));
        }
      }
      if (isset($evidence['copyright']) && is_array($evidence['copyright'])) {
        foreach ($evidence['copyright'] as $cp) {
          if (isset($cp['text']) && is_string($cp['text'])) {
            $copyrightTexts = array_merge($copyrightTexts,
              $this->splitCopyrightText($cp['text']));
          }
        }
      }
    }

    // Fallback to top-level copyright if evidence is not present or empty
    if (empty($copyrightTexts) && isset($component['copyright']) &&
        is_string($component['copyright'])) {
      $copyrightTexts = $this->splitCopyrightText($component['copyright']);
    }

    return new ReportImportData($infoInFileLicenses, $concludedLicenses, $copyrightTexts);
  }

  /**
   * The acknowledgement is a property of the `license` object, or a sibling
   * of `expression` for expression entries.
   * @param array $licenseEntry
   * @return string|null lower-cased acknowledgement or null if not set
   */
  private function getAcknowledgement($licenseEntry)
  {
    if (!is_array($licenseEntry)) {
      return null;
    }
    if (isset($licenseEntry['license']['acknowledgement'])) {
      return strtolower($licenseEntry['license']['acknowledgement']);
    }
    if (isset($licenseEntry['expression']) && isset($licenseEntry['acknowledgement'])) {
      return strtolower($licenseEntry['acknowledgement']);
    }
    return null;
  }

  /**
   * @param string $text
   * @return string[]
   */
  private function splitCopyrightText($text)
  {
    $result = [];
    foreach (explode("\n", trim($text)) as $line) {
      $line = trim($line);
      if ($line !== '') {
        $result[] = $line;
      }
    }
    return $result;
  }

  /**
   * @param array $licenseEntry
   * @return ReportImportDataItem[]
   */
  private function parseLicenseEntry($licenseEntry)
  {
    if (!is_array($licenseEntry)) {
      return [];
    }
    if (isset($licenseEntry['license']) && is_array($licenseEntry['license'])) {
      return $this->parseLicenseObject($licenseEntry['license']);
    }
    if (isset($licenseEntry['expression']) && is_string($licenseEntry['expression'])) {
      return $this->parseLicenseExpression($licenseEntry['expression']);
    }
    return [];
  }

  /**
   * @param array $license
   * @return ReportImportDataItem[]
   */
  private function parseLicenseObject($license)
  {
    $rawId = isset($license['id']) ? $license['id']
      : (isset($license['name']) ? $license['name'] : null);
    if ($rawId === null || $rawId === '') {
      return [];
    }

    $text = isset($license['text']['content']) ? $license['text']['content'] : '';
    if ($text !== '' && isset($license['text']['encoding']) &&
        strtolower($license['text']['encoding']) === 'base64') {
      $decoded = base64_decode($text, true);
      $text = ($decoded === false) ? '' : $decoded;
    }
    $url = isset($license['url']) ? $license['url'] : '';
    $name = isset($license['name']) ? $this->stripLicenseRefPrefix($license['name']) : null;

    return [$this->createItem($rawId, $name, $text, $url)];
  }

  /**
   * Split an SPDX license expression into its license identifiers. A
   * disjunction is additionally flagged with the "Dual-license" marker, in
   * line with the SPDX importer.
   * @param string $expression
   * @return ReportImportDataItem[]
   */
  private function parseLicenseExpression($expression)
  {
    $tokens = array_filter(preg_split('/\s+/',
      str_replace(['(', ')'], ' ', trim($expression))));
    $items = [];
    foreach ($tokens as $token) {
      $upper = strtoupper($token);
      if ($upper === 'OR') {
        $items[] = new ReportImportDataItem("Dual-license");
      } elseif ($upper === 'AND' || $upper === 'WITH') {
        continue;
      } else {
        $items[] = $this->createItem($token);
      }
    }
    return $items;
  }

  /**
   * @param string $rawId
   * @param string|null $name
   * @param string $text
   * @param string $url
   * @return ReportImportDataItem
   */
  private function createItem($rawId, $name = null, $text = '', $url = '')
  {
    $isLicenseRef = strpos($rawId, \Fossology\Lib\Data\LicenseRef::SPDXREF_PREFIX) === 0;
    $id = $this->stripLicenseRefPrefix($rawId);

    // FOSSology appends "-<md5>" to LicenseRefs carrying a custom text
    $isCustomText = false;
    if ($isLicenseRef && strlen($id) > 33 &&
        substr($id, -33, 1) === "-" && ctype_alnum(substr($id, -32))) {
      $id = substr($id, 0, -33);
      $isCustomText = true;
    }

    $item = new ReportImportDataItem($id);
    if ($isCustomText && $text !== '') {
      $item->setCustomText($text);
    }
    $item->setLicenseCandidate($name !== null ? $name : $id, $text, !$isLicenseRef, $url);
    return $item;
  }

  /**
   * @param string $licenseId
   * @return string
   */
  private function stripLicenseRefPrefix($licenseId)
  {
    return ReportImportHelper::stripLicenseRefPrefix($licenseId);
  }
}
