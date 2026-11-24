<?php
/*
 SPDX-FileCopyrightText: © 2023 Sushant Kumar <sushantmishra02102002@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @namespace Fossology::CycloneDX
 * @brief Namespace used by Report Generator
 */
namespace Fossology\CycloneDX;

use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Report\CycloneDxSettings;

class BomReportGenerator
{
  /** @var string Prefix of the property names this generator emits */
  private $tagNamespace = CycloneDxSettings::DEFAULT_NAMESPACE;

  /** @var string CycloneDX spec version of the generated document */
  private $specVersion = CycloneDxSettings::DEFAULT_VERSION;

  /**
   * @param CycloneDxSettings $settings
   */
  public function applySettings(CycloneDxSettings $settings): void
  {
    $this->tagNamespace = $settings->getTagNamespace();
    $this->specVersion = $settings->getSpecVersion();
  }

  public function getTagNamespace(): string
  {
    return $this->tagNamespace;
  }

  public function getSpecVersion(): string
  {
    return $this->specVersion;
  }

  /**
   * @param string $version Spec version that introduced a field
   * @return bool True if the document's spec version has that field
   */
  private function supports(string $version): bool
  {
    return version_compare($this->specVersion, $version, '>=');
  }

  /**
   * Creates a component.
   *
   * @param array $componentData The component data.
   * @return array The generated component.
   */
  public function createComponent(array $componentData): array
  {
    return $this->generateComponent($componentData);
  }

  /**
   * Creates a hash.
   *
   * @param string $algorithm The algorithm used for hashing.
   * @param string $content The content to be hashed.
   * @return array The generated hash.
   */
  public function createHash($algorithm, $content): array
  {
    return $this->generateHash($algorithm, $content);
  }

  /**
   * Creates a license.
   *
   * @param array $licenseData The license data.
   * @return array The generated license.
   */
  public function createLicense(array $licenseData): array
  {
    return $this->generateLicense($licenseData);
  }

  /**
   * Generates the report.
   *
   * @param array $bomdata The BOM data.
   * @return array The generated report.
   */
  public function generateReport($bomdata): array
  {
    $author = ['name' => 'FOSSology Analyst'];
    if ($this->supports('1.5')) {
      $author['bom-ref'] = 'person-fossology-analyst';
    }

    $report = [
      'bomFormat' => 'CycloneDX',
      '$schema' => 'http://cyclonedx.org/schema/bom-' . $this->specVersion . '.schema.json',
      'specVersion' => $this->specVersion,
      'version' => 1,
      'serialNumber' => 'urn:uuid:'. uuid_create(UUID_TYPE_TIME),
      'metadata' => [
        'timestamp' => date('c'),
        'tools' => $this->generateTools($bomdata['tool-version']),
        'authors' => [$author],
        'component' => $bomdata['maincomponent']
      ],
      'components' => $bomdata['components']
    ];

    // citations point at bom-refs of the analyst and the scanner tool
    if ($this->supports('1.7') && !empty($bomdata['citations'])) {
      $report['citations'] = $bomdata['citations'];
    }

    if (!empty($bomdata['externalReferences'])) {
      $report['externalReferences'] = $bomdata['externalReferences'];
    }

    return $report;
  }

  /**
   * @param string $toolVersion
   * @return array metadata.tools in the form the spec version expects
   */
  private function generateTools($toolVersion): array
  {
    $tools = [
      ['name' => 'FOSSology', 'bom-ref' => 'tool-fossology'],
      ['name' => 'FOSSology Scanners', 'bom-ref' => 'tool-fossology-scanners']
    ];
    if (!$this->supports('1.5')) {
      return array_map(function ($tool) use ($toolVersion) {
        return ['vendor' => 'FOSSology', 'name' => $tool['name'], 'version' => $toolVersion];
      }, $tools);
    }
    $components = [];
    foreach ($tools as $tool) {
      $component = ['type' => 'application'];
      if ($this->supports('1.6')) {
        $component['manufacturer'] = ['name' => 'FOSSology'];
      }
      $components[] = $component + [
        'name' => $tool['name'],
        'version' => $toolVersion,
        'bom-ref' => $tool['bom-ref']
      ];
    }
    return ['components' => $components];
  }

  /**
   * Generates a component.
   *
   * @param array $componentData The component data.
   * @return array The generated component.
   */
  private function generateComponent(array $componentData): array
  {
    $component = [
      'type' => $componentData['type'],
      'name' => $componentData['name']
    ];

    if (array_key_exists('version', $componentData) && !empty($componentData['version'])) {
      $component['version'] = $componentData['version'];
    }

    if (array_key_exists('mimeType', $componentData) && !empty($componentData['mimeType'])) {
      $component['mime-type'] = $componentData['mimeType'];
    }

    if (array_key_exists('bomref', $componentData) && !empty($componentData['bomref'])) {
      $component['bom-ref'] = $componentData['bomref'];
    }

    /**
     * "Specifies the scope of the component. If scope is not specified,
     *  'required' scope SHOULD be assumed by the consumer of the BOM."
     */
    if (array_key_exists('scope', $componentData) && !empty($componentData['scope'])) {
      $component['scope'] = $componentData['scope'];
    } else {
      $component['scope'] = 'required';
    }

    if (array_key_exists('hashes', $componentData) && !empty($componentData['hashes'])) {
      $component['hashes'] = $componentData['hashes'];
    }

    if (array_key_exists('licenses', $componentData) && !empty($componentData['licenses'])) {
      $component['licenses'] = $componentData['licenses'];
    }

    if (array_key_exists('copyright', $componentData) && !empty($componentData['copyright'])) {
      $component['copyright'] = $componentData['copyright'];
    }

    if (array_key_exists('purl', $componentData) && !empty($componentData['purl'])) {
      $component['purl'] = $componentData['purl'];
    }

    if (array_key_exists('description', $componentData) && !empty($componentData['description'])) {
      $component['description'] = $componentData['description'];
    }

    if (array_key_exists('externalReferences', $componentData) && !empty($componentData['externalReferences'])) {
      $component['externalReferences'] = $componentData['externalReferences'];
    }

    $properties = [];
    if (array_key_exists('acknowledgements', $componentData) && !empty($componentData['acknowledgements'])) {
      $properties[] = [
        'name' => $this->tagNamespace . 'acknowledgement',
        'value' => $componentData['acknowledgements']
      ];
    }
    if (array_key_exists('comments', $componentData) && !empty($componentData['comments'])) {
      $properties[] = [
        'name' => $this->tagNamespace . 'comment',
        'value' => $componentData['comments']
      ];
    }
    if (!empty($properties)) {
      $component['properties'] = $properties;
    }

    return $component;
  }

  private function generateLicense(array $licenseData): array
  {
    $license = [];

    if (!$this->supports('1.5')) {
      unset($licenseData['bom-ref']);
    }
    if (!$this->supports('1.6')) {
      unset($licenseData['acknowledgement']);
    }

    $isLicenseRef = !empty($licenseData['id']) &&
      stripos($licenseData['id'], LicenseRef::SPDXREF_PREFIX) === 0;
    // Before 1.7 an expression cannot share a licenses array with license
    // objects, so a LicenseRef id is written as a license name there.
    if ($isLicenseRef && !$this->supports('1.7')) {
      $licenseData['name'] = $licenseData['id'];
      unset($licenseData['id']);
    } elseif ($isLicenseRef) {
      $license['expression'] = $licenseData['id'];
      if (array_key_exists('bom-ref', $licenseData) && !empty($licenseData['bom-ref'])) {
        $license['bom-ref'] = $licenseData['bom-ref'];
      }
      if (array_key_exists('acknowledgement', $licenseData) && !empty($licenseData['acknowledgement'])) {
        $license['acknowledgement'] = $licenseData['acknowledgement'];
      }
      return $license;
    }

    if (array_key_exists('id', $licenseData) && !empty($licenseData['id'])) {
      $license['license']['id'] = $licenseData['id'];
    } else if (array_key_exists('name', $licenseData) && !empty($licenseData['name'])) {
      $license['license']['name'] = $licenseData['name'];
    }

    if (array_key_exists('bom-ref', $licenseData) && !empty($licenseData['bom-ref'])) {
      $license['license']['bom-ref'] = $licenseData['bom-ref'];
    }

    if (array_key_exists('acknowledgement', $licenseData) && !empty($licenseData['acknowledgement'])) {
      $license['license']['acknowledgement'] = $licenseData['acknowledgement'];
    }

    if (array_key_exists('url', $licenseData) && !empty($licenseData['url'])) {
      $license['license']['url'] = $licenseData['url'];
    }

    if (array_key_exists('textContent', $licenseData) && !empty($licenseData['textContent'])) {
      $license['license']['text'] = [
        'content' => $licenseData['textContent'],
        'contentType' => $licenseData['textContentType'],
        'encoding' => 'base64'
      ];
    }

    return $license;
  }

  /**
   * Generates a hash.
   *
   * @param string $algorithm The algorithm used for hashing.
   * @param string $content The content to be hashed.
   * @return array The generated hash.
   */
  private function generateHash(string $algorithm, string $content): array
  {
    return [
      'alg' => $algorithm,
      'content' => $content
    ];
  }
}
