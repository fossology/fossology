<?php
/*
 SPDX-FileCopyrightText: © 2026 Krrish Biswas <krrishbiswas175@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Unit tests for BomReportGenerator CycloneDX output
 */

namespace Fossology\Lib\Data {
  class LicenseRef
  {
    const SPDXREF_PREFIX = "LicenseRef-";
  }
}

namespace {

  if (!function_exists('uuid_create')) {
    define('UUID_TYPE_TIME', 1);
    function uuid_create($type = UUID_TYPE_TIME)
    {
      return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
      );
    }
  }

  require_once __DIR__ . '/../agent/reportgenerator.php';

  use Fossology\CycloneDX\BomReportGenerator;

  $generator = new BomReportGenerator();
  $passed = 0;
  $failed = 0;

  function assert_test($name, $condition, $detail = '')
  {
    global $passed, $failed;
    if ($condition) {
      echo "  PASS: $name\n";
      $passed++;
    } else {
      echo "  FAIL: $name" . ($detail ? " -- $detail" : "") . "\n";
      $failed++;
    }
  }

  echo "\n=== CycloneDX BomReportGenerator Tests ===\n\n";

  echo "Test 1: License with text content\n";
  $licenseData = array(
    'id' => 'MIT',
    'name' => 'MIT License',
    'url' => 'https://opensource.org/licenses/MIT',
    'textContent' => base64_encode('Permission is hereby granted, free of charge...'),
    'textContentType' => 'text/plain'
  );
  $result = $generator->createLicense($licenseData);

  assert_test('License has license key', isset($result['license']));
  assert_test('License id is MIT', $result['license']['id'] === 'MIT');
  assert_test('License has url', $result['license']['url'] === 'https://opensource.org/licenses/MIT');
  assert_test('License has text block', isset($result['license']['text']));
  assert_test('License text has content', isset($result['license']['text']['content']));
  assert_test('License text contentType is text/plain', $result['license']['text']['contentType'] === 'text/plain');
  assert_test('License text encoding is base64', $result['license']['text']['encoding'] === 'base64');
  assert_test('License text decodes correctly',
    base64_decode($result['license']['text']['content']) === 'Permission is hereby granted, free of charge...');

  echo "\n";

  echo "Test 2: License without text content\n";
  $licenseDataNoText = array(
    'id' => 'Apache-2.0',
    'name' => 'Apache License 2.0',
    'url' => 'https://www.apache.org/licenses/LICENSE-2.0'
  );
  $result2 = $generator->createLicense($licenseDataNoText);

  assert_test('License has license key', isset($result2['license']));
  assert_test('License id is Apache-2.0', $result2['license']['id'] === 'Apache-2.0');
  assert_test('License has NO text block', !isset($result2['license']['text']));

  echo "\n";

  echo "Test 3: LicenseRef expression\n";
  $licenseRef = array(
    'id' => 'LicenseRef-fossology-custom',
    'name' => 'Custom License'
  );
  $result3 = $generator->createLicense($licenseRef);

  assert_test('LicenseRef returns expression', isset($result3['expression']));
  assert_test('Expression value correct', $result3['expression'] === 'LicenseRef-fossology-custom');
  assert_test('LicenseRef has NO license key', !isset($result3['license']));

  echo "\n";

  echo "Test 4: Component with copyright\n";
  $componentData = array(
    'type' => 'library',
    'name' => 'openssl-3.0.12.tar.gz',
    'bomref' => '42',
    'scope' => 'required',
    'mimeType' => 'application/gzip',
    'copyright' => "Copyright 2000-2023 The OpenSSL Project Authors\nCopyright 1995-2023 Eric A. Young, Tim J. Hudson",
    'hashes' => array(
      $generator->createHash('SHA-1', 'abc123'),
      $generator->createHash('MD5', 'def456')
    ),
    'licenses' => array($result)
  );
  $comp = $generator->createComponent($componentData);

  assert_test('Component has type', $comp['type'] === 'library');
  assert_test('Component has name', $comp['name'] === 'openssl-3.0.12.tar.gz');
  assert_test('Component has bom-ref', $comp['bom-ref'] === '42');
  assert_test('Component has copyright', isset($comp['copyright']));
  assert_test('Copyright contains OpenSSL', strpos($comp['copyright'], 'OpenSSL') !== false);
  assert_test('Copyright contains both entries', strpos($comp['copyright'], 'Eric A. Young') !== false);
  assert_test('Component has hashes', count($comp['hashes']) === 2);
  assert_test('Component has licenses', count($comp['licenses']) === 1);

  echo "\n";

  echo "Test 5: Component without copyright\n";
  $compNoCopyright = $generator->createComponent(array(
    'type' => 'file',
    'name' => 'README.md'
  ));

  assert_test('Component without copyright has no copyright key', !isset($compNoCopyright['copyright']));

  echo "\n";

  echo "Test 6: Full report structure\n";
  $fileComponent = $generator->createComponent(array(
    'type' => 'file',
    'name' => 'src/main.c',
    'bomref' => '42-100',
    'copyright' => 'Copyright 2023 Test Author',
    'hashes' => array($generator->createHash('SHA-1', 'aaa')),
    'licenses' => array($result)
  ));

  $report = $generator->generateReport(array(
    'tool-version' => '4.5.0',
    'maincomponent' => $comp,
    'components' => array($fileComponent)
  ));

  assert_test('Report bomFormat is CycloneDX', $report['bomFormat'] === 'CycloneDX');
  assert_test('Report specVersion is 1.4', $report['specVersion'] === '1.4');
  assert_test('Report version is integer 1', $report['version'] === 1);
  assert_test('Report has serialNumber', !empty($report['serialNumber']));
  assert_test('Report has metadata', isset($report['metadata']));
  assert_test('Report metadata has tools', isset($report['metadata']['tools']));
  assert_test('Tool vendor is FOSSology', $report['metadata']['tools'][0]['vendor'] === 'FOSSology');
  assert_test('Tool version is 4.5.0', $report['metadata']['tools'][0]['version'] === '4.5.0');
  assert_test('Metadata has main component', isset($report['metadata']['component']));
  assert_test('Main component has copyright', isset($report['metadata']['component']['copyright']));
  assert_test('Report has components array', is_array($report['components']));
  assert_test('Components count is 1', count($report['components']) === 1);
  assert_test('File component has copyright', isset($report['components'][0]['copyright']));
  assert_test('Report has no externalReferences when not provided', !isset($report['externalReferences']));

  echo "\n";

  echo "Test 7: JSON validity\n";
  $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  assert_test('JSON encodes without error', json_last_error() === JSON_ERROR_NONE);
  assert_test('JSON is not empty', strlen($json) > 100);

  $decoded = json_decode($json, true);
  assert_test('JSON round-trip preserves bomFormat', $decoded['bomFormat'] === 'CycloneDX');
  assert_test('JSON round-trip preserves license text',
    isset($decoded['metadata']['component']['licenses'][0]['license']['text']['content']));

  echo "\n";

  echo "Test 8: Component with version\n";
  $compWithVersion = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'busybox',
    'version' => '1.36.1'
  ));

  assert_test('Component has version', isset($compWithVersion['version']));
  assert_test('Version value is correct', $compWithVersion['version'] === '1.36.1');

  $compNoVersion = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'busybox',
    'version' => ''
  ));
  assert_test('Empty version is not included', !isset($compNoVersion['version']));

  echo "\n";

  echo "Test 9: Component with purl\n";
  $compWithPurl = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'busybox',
    'purl' => 'pkg:deb/debian/busybox@1.36.1'
  ));

  assert_test('Component has purl', isset($compWithPurl['purl']));
  assert_test('PURL value is correct', $compWithPurl['purl'] === 'pkg:deb/debian/busybox@1.36.1');

  $compNoPurl = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'busybox',
    'purl' => ''
  ));
  assert_test('Empty purl is not included', !isset($compNoPurl['purl']));

  echo "\n";

  echo "Test 10: Component with description\n";
  $compWithDesc = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'openssl',
    'description' => 'Open source SSL/TLS toolkit'
  ));

  assert_test('Component has description', isset($compWithDesc['description']));
  assert_test('Description value is correct', $compWithDesc['description'] === 'Open source SSL/TLS toolkit');

  $compNoDesc = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'openssl',
    'description' => ''
  ));
  assert_test('Empty description is not included', !isset($compNoDesc['description']));

  echo "\n";

  echo "Test 11: Component with properties (acknowledgements + comments)\n";
  $compWithProps = $generator->createComponent(array(
    'type' => 'file',
    'name' => 'src/main.c',
    'acknowledgements' => "Thanks to the OpenSSL team\nThanks to contributors",
    'comments' => 'Reviewed and approved by legal team'
  ));

  assert_test('Component has properties', isset($compWithProps['properties']));
  assert_test('Properties has 2 entries', count($compWithProps['properties']) === 2);
  assert_test('First property is acknowledgement',
    $compWithProps['properties'][0]['name'] === 'fossology:acknowledgement');
  assert_test('Acknowledgement value is correct',
    strpos($compWithProps['properties'][0]['value'], 'OpenSSL') !== false);
  assert_test('Second property is comment',
    $compWithProps['properties'][1]['name'] === 'fossology:comment');
  assert_test('Comment value is correct',
    $compWithProps['properties'][1]['value'] === 'Reviewed and approved by legal team');

  $compOnlyAck = $generator->createComponent(array(
    'type' => 'file',
    'name' => 'src/util.c',
    'acknowledgements' => 'Thanks to maintainers',
    'comments' => ''
  ));
  assert_test('Only acknowledgement property when comments empty', count($compOnlyAck['properties']) === 1);
  assert_test('Single property is acknowledgement',
    $compOnlyAck['properties'][0]['name'] === 'fossology:acknowledgement');

  $compNoProps = $generator->createComponent(array(
    'type' => 'file',
    'name' => 'src/test.c',
    'acknowledgements' => '',
    'comments' => ''
  ));
  assert_test('No properties when both empty', !isset($compNoProps['properties']));

  echo "\n";

  echo "Test 12: Component with externalReferences\n";
  $compWithExtRef = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'openssl',
    'externalReferences' => [
      ['type' => 'distribution', 'url' => 'https://www.openssl.org/source/openssl-3.0.12.tar.gz'],
      ['type' => 'vcs', 'url' => 'https://github.com/openssl/openssl']
    ]
  ));

  assert_test('Component has externalReferences', isset($compWithExtRef['externalReferences']));
  assert_test('ExternalReferences has 2 entries', count($compWithExtRef['externalReferences']) === 2);
  assert_test('First ref type is distribution',
    $compWithExtRef['externalReferences'][0]['type'] === 'distribution');
  assert_test('First ref url is correct',
    $compWithExtRef['externalReferences'][0]['url'] === 'https://www.openssl.org/source/openssl-3.0.12.tar.gz');

  $compNoExtRef = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'openssl',
    'externalReferences' => []
  ));
  assert_test('Empty externalReferences is not included', !isset($compNoExtRef['externalReferences']));

  echo "\n";

  echo "Test 13: Full report with externalReferences\n";
  $reportWithExtRef = $generator->generateReport(array(
    'tool-version' => '4.5.0',
    'maincomponent' => $comp,
    'components' => array($fileComponent),
    'externalReferences' => [
      ['type' => 'website', 'url' => 'https://www.fossology.org']
    ]
  ));

  assert_test('Report has externalReferences', isset($reportWithExtRef['externalReferences']));
  assert_test('Report externalReferences has 1 entry', count($reportWithExtRef['externalReferences']) === 1);
  assert_test('Report externalRef type is website',
    $reportWithExtRef['externalReferences'][0]['type'] === 'website');

  echo "\n";

  echo "Test 14: Full component with all new fields\n";
  $fullComp = $generator->createComponent(array(
    'type' => 'library',
    'name' => 'busybox',
    'version' => '1.36.1',
    'bomref' => '99',
    'scope' => 'required',
    'mimeType' => 'application/x-tar',
    'copyright' => 'Copyright 2023 BusyBox Authors',
    'description' => 'Tiny utilities for small and embedded systems',
    'purl' => 'pkg:generic/busybox@1.36.1',
    'hashes' => array($generator->createHash('SHA-256', 'abc123def456')),
    'licenses' => array($result),
    'externalReferences' => [
      ['type' => 'vcs', 'url' => 'https://git.busybox.net/busybox/']
    ],
    'acknowledgements' => 'Thanks to Denys Vlasenko',
    'comments' => 'Primary build dependency'
  ));

  assert_test('Full component has type', $fullComp['type'] === 'library');
  assert_test('Full component has name', $fullComp['name'] === 'busybox');
  assert_test('Full component has version', $fullComp['version'] === '1.36.1');
  assert_test('Full component has bom-ref', $fullComp['bom-ref'] === '99');
  assert_test('Full component has purl', $fullComp['purl'] === 'pkg:generic/busybox@1.36.1');
  assert_test('Full component has description',
    $fullComp['description'] === 'Tiny utilities for small and embedded systems');
  assert_test('Full component has copyright', isset($fullComp['copyright']));
  assert_test('Full component has hashes', count($fullComp['hashes']) === 1);
  assert_test('Full component has licenses', count($fullComp['licenses']) === 1);
  assert_test('Full component has externalReferences', count($fullComp['externalReferences']) === 1);
  assert_test('Full component has properties', count($fullComp['properties']) === 2);

  echo "\n";

  echo "=== Results ===\n";
  echo "Passed: $passed | Failed: $failed\n";

  if ($failed > 0) {
    echo "\nSome tests FAILED!\n";
    exit(1);
  } else {
    echo "\nAll tests passed!\n";
    exit(0);
  }

} // end namespace
