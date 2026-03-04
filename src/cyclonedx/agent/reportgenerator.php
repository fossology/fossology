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
    return [
      'bomFormat' => 'CycloneDX',
      '$schema' => 'https://cyclonedx.org/schema/bom-1.4.schema.json',
      'specVersion' => '1.4',
      'version' => 1.0,
      'serialNumber' => 'urn:uuid:'. uuid_create(UUID_TYPE_TIME),
      'metadata' => [
        'timestamp' => date('c'),
        'tools' => [
          [
            'vendor' => 'FOSSology',
            'name' => 'FOSSology',
            'version' => $bomdata['tool-version']
          ]
        ],
        'component' => $bomdata['maincomponent']
      ],
      'components' => $bomdata['components']
    ];
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

    if (array_key_exists('description', $componentData) && !empty($componentData['description'])) {
      $component['description'] = $componentData['description'];
    }

    return $component;
  }

  /**
   * Generates a license.
   *
   * @param array $licenseData The license data.
   * @return array The generated license.
   */
  private function generateLicense(array $licenseData): array
  {
    $license = [];

    // Check license ID is a LicenseRef
    if (array_key_exists('id', $licenseData) && !empty($licenseData['id']) &&
      stripos($licenseData['id'], LicenseRef::SPDXREF_PREFIX) === 0) {
      $license['expression'] = $licenseData['id'];
      return $license;
    }

    if (array_key_exists('id', $licenseData) && !empty($licenseData['id'])) {
      $license['license']['id'] = $licenseData['id'];
    } else if (array_key_exists('name', $licenseData) && !empty($licenseData['name'])) {
      $license['license']['name'] = $licenseData['name'];
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
