<?php
/*
 SPDX-FileCopyrightText: © 2026 Krrish Biswas

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\ReportImport;

require_once 'ImportSource.php';
require_once 'ReportImportData.php';
require_once 'ReportImportDataItem.php';
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Util\StringOperation;

class CycloneDxImportSource implements ImportSource
{
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
    if ($this->bomData === null) {
      return false;
    }

    if (!isset($this->bomData['bomFormat']) || strtolower($this->bomData['bomFormat']) !== 'cyclonedx') {
      return false;
    }

    if (isset($this->bomData['components']) && is_array($this->bomData['components'])) {
      foreach ($this->bomData['components'] as $component) {
        $bomRef = isset($component['bom-ref']) ? $component['bom-ref'] : (isset($component['name']) ? $component['name'] : uniqid('comp_'));
        $this->componentsByBomRef[$bomRef] = $component;
      }
    }

    return true;
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
      $name = isset($component['name']) ? $component['name'] : $bomRef;
      $files[$bomRef] = $name;
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
        // Map CycloneDX hash algorithm name to FOSSology hash algorithm name if needed
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
        
        // FOSSology CycloneDX exports put concluded licenses in the component
        // licenses array without an acknowledgement field. Default to concluded
        // to match this behavior; only treat as declared if explicitly marked.
        $ack = 'concluded';
        if (isset($licenseEntry['license']['acknowledgement'])) {
          $ack = strtolower($licenseEntry['license']['acknowledgement']);
        } elseif (isset($licenseEntry['expression']['acknowledgement'])) {
          $ack = strtolower($licenseEntry['expression']['acknowledgement']);
        }
        
        foreach ($items as $item) {
          if ($ack === 'concluded') {
            $concludedLicenses[] = $item;
          } else {
            $infoInFileLicenses[] = $item;
          }
        }
      }
    }

    // CycloneDX 1.5+ evidence block
    if (isset($component['evidence']) && is_array($component['evidence'])) {
      if (isset($component['evidence']['licenses']) && is_array($component['evidence']['licenses'])) {
        foreach ($component['evidence']['licenses'] as $licenseEntry) {
          $items = $this->parseLicenseEntry($licenseEntry);
          foreach ($items as $item) {
            $infoInFileLicenses[] = $item;
          }
        }
      }
      if (isset($component['evidence']['copyright']) && is_array($component['evidence']['copyright'])) {
        foreach ($component['evidence']['copyright'] as $cp) {
          if (isset($cp['text'])) {
            // Split multi-line copyright text into array of texts
            $lines = explode("\n", trim($cp['text']));
            foreach ($lines as $line) {
              $line = trim($line);
              if (!empty($line)) {
                $copyrightTexts[] = $line;
              }
            }
          }
        }
      }
    }

    // Fallback to top-level copyright if evidence is not present or empty
    if (empty($copyrightTexts) && isset($component['copyright']) && is_string($component['copyright'])) {
      $lines = explode("\n", trim($component['copyright']));
      foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
          $copyrightTexts[] = $line;
        }
      }
    }

    return new ReportImportData($infoInFileLicenses, $concludedLicenses, $copyrightTexts);
  }

  private function parseLicenseEntry($licenseEntry)
  {
    $items = [];
    if (isset($licenseEntry['license'])) {
      $license = $licenseEntry['license'];
      $id = isset($license['id']) ? $license['id'] : (isset($license['name']) ? $license['name'] : null);
      if ($id) {
        $id = $this->stripLicenseRefPrefix($id);
        $item = new ReportImportDataItem($id);
        $name = isset($license['name']) ? $this->stripLicenseRefPrefix($license['name']) : $id;
        $text = isset($license['text']['content']) ? $license['text']['content'] : '';
        if (isset($license['text']['encoding']) && $license['text']['encoding'] === 'base64') {
          $text = base64_decode($text);
        }
        $url = isset($license['url']) ? $license['url'] : '';
        $item->setLicenseCandidate($name, $text, true, $url);
        $items[] = $item;
      }
    } elseif (isset($licenseEntry['expression'])) {
      $id = $this->stripLicenseRefPrefix($licenseEntry['expression']);
      $item = new ReportImportDataItem($id);
      $item->setLicenseCandidate($id, '', true, '');
      $items[] = $item;
    }
    return $items;
  }

  /**
   * Strip LicenseRef prefix and FOSSology specific hash from license ID
   * @param string $licenseId
   * @return string
   */
  private function stripLicenseRefPrefix($licenseId)
  {
    if (StringOperation::stringStartsWith($licenseId, LicenseRef::SPDXREF_PREFIX)) {
      if (StringOperation::stringStartsWith($licenseId, LicenseRef::SPDXREF_PREFIX_FOSSOLOGY)) {
        $stripped = urldecode(substr($licenseId, strlen(LicenseRef::SPDXREF_PREFIX_FOSSOLOGY)));
      } else {
        $stripped = urldecode(substr($licenseId, strlen(LicenseRef::SPDXREF_PREFIX)));
      }
      
      if (strlen($stripped) > 33 &&
          substr($stripped, -33, 1) === "-" &&
          ctype_alnum(substr($stripped, -32))) {
        $stripped = substr($stripped, 0, -33);
      }
      return $stripped;
    }
    return urldecode($licenseId);
  }
}
