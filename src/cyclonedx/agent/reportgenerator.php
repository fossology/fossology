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

class BomReportGenerator
{
  /** @var string Custom tag namespace prefix for properties */
  private $tagNamespace = 'fossology:';

  /** @var string CycloneDX spec version */
  private $specVersion = '1.7';

  const SUPPORTED_VERSIONS = ['1.4', '1.5', '1.6', '1.7'];

  /**
   * Set the tag namespace prefix for CycloneDX properties.
   *
   * @param string $namespace The namespace prefix (e.g. 'fossology:').
   */
  public function setTagNamespace(string $namespace): void
  {
    $this->tagNamespace = $namespace;
  }

  /**
   * Get the current tag namespace prefix.
   *
   * @return string The namespace prefix.
   */
  public function getTagNamespace(): string
  {
    return $this->tagNamespace;
  }

  /**
   * Set the CycloneDX spec version.
   *
   * @param string $version The spec version (e.g. '1.4', '1.7').
   */
  public function setSpecVersion(string $version): void
  {
    if (in_array($version, self::SUPPORTED_VERSIONS)) {
      $this->specVersion = $version;
    }
  }

  /**
   * Get the current CycloneDX spec version.
   *
   * @return string The spec version.
   */
  public function getSpecVersion(): string
  {
    return $this->specVersion;
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
    $ver = $this->specVersion;

    $metadata = [
      'timestamp' => date('c'),
      'tools' => [
        'components' => [
          [
            'type' => 'application',
            'vendor' => 'FOSSology',
            'name' => 'FOSSology',
            'version' => $bomdata['tool-version'],
            'bom-ref' => 'tool-fossology'
          ],
          [
            'type' => 'application',
            'vendor' => 'FOSSology',
            'name' => 'FOSSology Scanners',
            'version' => $bomdata['tool-version'],
            'bom-ref' => 'tool-fossology-scanners'
          ]
        ]
      ],
      'component' => $bomdata['maincomponent']
    ];

    // metadata.authors was introduced in CycloneDX 1.6
    if (version_compare($ver, '1.6', '>=')) {
      $metadata['authors'] = [
        [
          'name' => 'FOSSology Analyst',
          'bom-ref' => 'person-fossology-analyst'
        ]
      ];
    }

    $report = [
      'bomFormat' => 'CycloneDX',
      '$schema' => 'http://cyclonedx.org/schema/bom-' . $ver . '.schema.json',
      'specVersion' => $ver,
      'version' => 1,
      'serialNumber' => 'urn:uuid:'. uuid_create(UUID_TYPE_TIME),
      'metadata' => $metadata,
      'components' => $bomdata['components']
    ];

    // citations (annotations) introduced in CycloneDX 1.5
    if (version_compare($ver, '1.5', '>=') && !empty($bomdata['citations'])) {
      $report['citations'] = $bomdata['citations'];
    }

    if (!empty($bomdata['externalReferences'])) {
      $report['externalReferences'] = $bomdata['externalReferences'];
    }

    return $report;
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

    // Check license ID is a LicenseRef
    if (array_key_exists('id', $licenseData) && !empty($licenseData['id']) &&
      stripos($licenseData['id'], LicenseRef::SPDXREF_PREFIX) === 0) {
      if (array_key_exists('bom-ref', $licenseData) && !empty($licenseData['bom-ref'])) {
        $license['expressionDetailed'] = [
          'value' => $licenseData['id'],
          'bom-ref' => $licenseData['bom-ref']
        ];
        if (array_key_exists('acknowledgement', $licenseData) && !empty($licenseData['acknowledgement'])) {
          $license['expressionDetailed']['acknowledgement'] = $licenseData['acknowledgement'];
        }
      } else {
        $license['expression'] = $licenseData['id'];
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
