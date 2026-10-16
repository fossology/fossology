<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac;

/**
 * @class SracIntegrityChecker
 * @brief Computes and validates cryptographic digests for SRAC sidecars and SBOM external references.
 *
 * Prevents tampering and ensures all processed artifacts strictly match their declared digests.
 */
class SracIntegrityChecker
{
  const HASH_ALGORITHM = 'SHA-256';
  const SPDX_COMMENT_REGEX = '/^SRAC sidecar; SHA-256: ([0-9a-fA-F]{64})$/';
  const SHA256_REGEX = '/^[0-9a-fA-F]{64}$/';

  /**
   * Calculate SHA-256 hex digest of a file.
   *
   * @param string $filePath
   * @return string Lowercase hex digest
   * @throws \InvalidArgumentException If file cannot be read
   */
  public function calculateFileSha256($filePath)
  {
    if (!file_exists($filePath) || !is_readable($filePath)) {
      throw new \InvalidArgumentException("Cannot read file for hash calculation: $filePath");
    }

    $hash = hash_file('sha256', $filePath);
    if ($hash === false) {
      throw new \InvalidArgumentException("Failed to compute SHA-256 for file: $filePath");
    }

    return strtolower($hash);
  }

  /**
   * Calculate SHA-256 hex digest of a string.
   *
   * @param string $content
   * @return string Lowercase hex digest
   */
  public function calculateStringSha256($content)
  {
    return strtolower(hash('sha256', $content));
  }

  /**
   * Compare two digests in a timing-safe, case-insensitive manner.
   *
   * @param string $actual
   * @param string $expected
   * @return bool
   */
  public function verifyHash($actual, $expected)
  {
    return hash_equals(strtolower($expected), strtolower($actual));
  }

  /**
   * Discover integrity-protected SRAC external references from an SPDX or CycloneDX SBOM.
   *
   * @param array $sbom Parsed SBOM document
   * @return array List of discovered reference objects:
   *               [['format' => 'SPDX'|'CycloneDX', 'componentIdentifier' => string, 'uri' => string, 'sha256' => string]]
   * @throws \InvalidArgumentException If SBOM format is unsupported or reference is malformed
   */
  public function discoverSracReferences(array $sbom)
  {
    if (isset($sbom['spdxVersion']) && strpos($sbom['spdxVersion'], 'SPDX-2.') === 0) {
      return $this->discoverSpdxReferences($sbom);
    }

    if (isset($sbom['bomFormat']) && $sbom['bomFormat'] === 'CycloneDX') {
      return $this->discoverCycloneDxReferences($sbom);
    }

    throw new \InvalidArgumentException(
      "Unsupported SBOM format; expected SPDX 2.x or CycloneDX"
    );
  }

  /**
   * @param array $sbom
   * @return array
   * @throws \InvalidArgumentException
   */
  private function discoverSpdxReferences(array $sbom)
  {
    $references = [];
    $packages = $sbom['packages'] ?? [];
    if (!is_array($packages)) {
      return $references;
    }

    foreach ($packages as $pkg) {
      if (!is_array($pkg)) {
        continue;
      }
      $externalRefs = $pkg['externalRefs'] ?? [];
      if (!is_array($externalRefs)) {
        continue;
      }

      foreach ($externalRefs as $ref) {
        if (!is_array($ref)) {
          continue;
        }

        $category = $ref['referenceCategory'] ?? '';
        $type = strtolower($ref['referenceType'] ?? '');

        if ($category !== 'OTHER' || $type !== 'srac') {
          continue;
        }

        $comment = $ref['comment'] ?? '';
        if (!preg_match(self::SPDX_COMMENT_REGEX, $comment, $matches)) {
          throw new \InvalidArgumentException(
            "SPDX SRAC external reference must carry a SHA-256 digest in its comment: " . json_encode($ref)
          );
        }

        $locator = $ref['referenceLocator'] ?? '';
        if (empty($locator) || !is_string($locator)) {
          throw new \InvalidArgumentException(
            "SPDX SRAC external reference must have a non-empty referenceLocator"
          );
        }

        $references[] = [
          'format' => 'SPDX',
          'componentIdentifier' => (string)($pkg['SPDXID'] ?? ''),
          'uri' => $locator,
          'sha256' => strtolower($matches[1])
        ];
      }
    }

    return $references;
  }

  /**
   * @param array $sbom
   * @return array
   * @throws \InvalidArgumentException
   */
  private function discoverCycloneDxReferences(array $sbom)
  {
    $references = [];
    $components = [];

    if (isset($sbom['metadata']['component']) && is_array($sbom['metadata']['component'])) {
      $components[] = $sbom['metadata']['component'];
    }

    $rawComponents = $sbom['components'] ?? [];
    if (is_array($rawComponents)) {
      $this->walkCycloneDxComponents($rawComponents, $components);
    }

    foreach ($components as $component) {
      $externalRefs = $component['externalReferences'] ?? [];
      if (!is_array($externalRefs)) {
        continue;
      }

      foreach ($externalRefs as $ref) {
        if (!is_array($ref)) {
          continue;
        }

        $type = $ref['type'] ?? '';
        $comment = $ref['comment'] ?? '';

        if ($type !== 'other' || $comment !== 'SRAC sidecar') {
          continue;
        }

        $digest = null;
        $hashes = $ref['hashes'] ?? [];
        if (is_array($hashes)) {
          foreach ($hashes as $hashItem) {
            if (is_array($hashItem) && strtoupper($hashItem['alg'] ?? '') === 'SHA-256') {
              $digest = $hashItem['content'] ?? null;
              break;
            }
          }
        }

        if (empty($digest) || !is_string($digest) || !preg_match(self::SHA256_REGEX, $digest)) {
          throw new \InvalidArgumentException(
            "CycloneDX SRAC external reference must carry a SHA-256 hash: " . json_encode($ref)
          );
        }

        $url = $ref['url'] ?? '';
        if (empty($url) || !is_string($url)) {
          throw new \InvalidArgumentException(
            "CycloneDX SRAC external reference must have a non-empty URL"
          );
        }

        $references[] = [
          'format' => 'CycloneDX',
          'componentIdentifier' => (string)($component['bom-ref'] ?? ''),
          'uri' => $url,
          'sha256' => strtolower($digest)
        ];
      }
    }

    return $references;
  }

  /**
   * @param array $components
   * @param array &$accumulator
   */
  private function walkCycloneDxComponents(array $components, array &$accumulator)
  {
    foreach ($components as $item) {
      if (!is_array($item)) {
        continue;
      }
      $accumulator[] = $item;
      if (isset($item['components']) && is_array($item['components'])) {
        $this->walkCycloneDxComponents($item['components'], $accumulator);
      }
    }
  }

  /**
   * Resolve an SRAC external reference against an artifact directory, verifying integrity before parsing.
   *
   * @param array $reference Discovered reference array
   * @param string $artifactRoot Path to directory containing referenced files
   * @return string Validated local file path
   * @throws \InvalidArgumentException If file escapes root, does not exist, or digest mismatches
   */
  public function resolveAndVerifyArtifact(array $reference, $artifactRoot)
  {
    $uriPath = parse_url($reference['uri'], PHP_URL_PATH);
    $filename = basename(urldecode($uriPath));

    if (empty($filename) || $filename === '.' || $filename === '/') {
      throw new \InvalidArgumentException(
        "SRAC external reference URI has no valid artifact filename: " . $reference['uri']
      );
    }

    $cleanRoot = rtrim(realpath($artifactRoot) ?: $artifactRoot, '/');
    $targetPath = $cleanRoot . '/' . $filename;

    if (!file_exists($targetPath)) {
      throw new \InvalidArgumentException(
        "Referenced SRAC artifact is unavailable at path: $targetPath"
      );
    }

    // Verify path does not escape root
    $realTarget = realpath($targetPath);
    if ($realTarget === false || strpos($realTarget, $cleanRoot) !== 0) {
      throw new \InvalidArgumentException(
        "Resolved SRAC artifact escapes artifact root directory: $targetPath"
      );
    }

    $actualDigest = $this->calculateFileSha256($targetPath);
    if (!$this->verifyHash($actualDigest, $reference['sha256'])) {
      throw new \InvalidArgumentException(
        "SRAC artifact SHA-256 mismatch: expected {$reference['sha256']}, got $actualDigest"
      );
    }

    return $targetPath;
  }
}
