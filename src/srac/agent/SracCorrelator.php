<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac;

/**
 * @class SracCorrelator
 * @brief Performs deterministic, auditable correlation between SRAC assertions and SBOM components.
 *
 * Implements deterministic matching rules in strict order:
 * 1. Artifact Digest (hash)
 * 2. PURL + Version
 * 3. SBOM Identifier (SPDXID / bom-ref)
 *
 * INVARIANT: Component name alone is NEVER used as a match key.
 * If multiple candidates match, the result is explicitly marked ambiguous.
 */
class SracCorrelator
{
  /** @var SracValidator */
  private $validator;

  public function __construct($validator = null)
  {
    $this->validator = $validator ?: new SracValidator();
  }

  /**
   * Correlate an SRAC assertion document with an SBOM document.
   *
   * @param array $assertion Parsed SRAC assertion
   * @param array $sbom Parsed SPDX 2.x or CycloneDX SBOM
   * @param array $integrity Optional integrity metadata (assertionSha256, sbomSha256, etc.)
   * @param array|null $discoveredRef Optional discovered reference from SBOM
   * @return SracResult
   */
  public function correlateAssertion(array $assertion, array $sbom, array $integrity = [], $discoveredRef = null)
  {
    // Step 1: Validate assertion schema and constraints
    $validationErrors = $this->validator->validateAssertion($assertion);
    $assertionId = (string)($assertion['assertionId'] ?? 'unknown-assertion');
    $subject = $assertion['subject'] ?? [];
    $rationale = (string)($assertion['rationale'] ?? '');
    $evidence = $assertion['evidence'] ?? [];
    $impactAnalysis = $assertion['impactAnalysis'] ?? null;
    $sourceOfTruth = $assertion['sourceOfTruth'] ?? null;

    $safetyAssessment = [
      'source' => 'assertion',
      'safetyRelevance' => $assertion['safetyRelevance']['status'] ?? 'undetermined',
      'classification' => $assertion['safetyRelevance']['classification'] ?? 'not-assigned',
      'assertionStatus' => $assertion['assertion']['status'] ?? 'draft',
      'reviewer' => $assertion['assertion']['reviewer'] ?? null,
      'author' => $assertion['assertion']['author'] ?? null,
      'created' => $assertion['assertion']['created'] ?? null,
      'updated' => $assertion['assertion']['updated'] ?? null,
    ];

    if (!empty($validationErrors)) {
      return new SracResult(
        SracResult::STATUS_INVALID,
        SracResult::RULE_NONE,
        $assertionId,
        $subject,
        $safetyAssessment,
        $rationale,
        $evidence,
        [],
        $validationErrors,
        $integrity,
        $discoveredRef,
        $impactAnalysis,
        $sourceOfTruth
      );
    }

    // Step 2: Check for staleness signals if present in assertion
    $assertionStatus = $safetyAssessment['assertionStatus'];
    $isStale = in_array($assertionStatus, ['superseded', 'withdrawn'], true);

    // Step 3: Extract and index normalized SBOM components
    $components = $this->extractSbomComponents($sbom);
    $indexes = $this->buildComponentIndexes($components);

    // Step 4: Perform deterministic matching in strict priority order
    $matchedComponents = [];
    $ruleUsed = SracResult::RULE_NONE;

    // Rule 1: Artifact digest match
    $digestMatches = $this->matchByDigest($subject, $indexes);
    if (!empty($digestMatches)) {
      $matchedComponents = $digestMatches;
      $ruleUsed = SracResult::RULE_ARTIFACT_DIGEST;
    } else {
      // Rule 2: PURL + Version match
      $purlMatches = $this->matchByPurlVersion($subject, $indexes);
      if (!empty($purlMatches)) {
        $matchedComponents = $purlMatches;
        $ruleUsed = SracResult::RULE_PURL_VERSION;
      } else {
        // Rule 3: SBOM Identifier match (SPDXID or bom-ref)
        $idMatches = $this->matchByIdentifier($subject, $indexes);
        if (!empty($idMatches)) {
          $matchedComponents = $idMatches;
          $ruleUsed = SracResult::RULE_SBOM_IDENTIFIER;
        }
      }
    }

    // Step 5: Assign final classification status
    if ($isStale) {
      $status = SracResult::STATUS_STALE;
    } elseif (count($matchedComponents) === 1) {
      $status = SracResult::STATUS_MATCHED;
    } elseif (count($matchedComponents) > 1) {
      $status = SracResult::STATUS_AMBIGUOUS;
    } else {
      $status = SracResult::STATUS_UNMATCHED;
      $ruleUsed = SracResult::RULE_NONE;
    }

    return new SracResult(
      $status,
      $ruleUsed,
      $assertionId,
      $subject,
      $safetyAssessment,
      $rationale,
      $evidence,
      $matchedComponents,
      [],
      $integrity,
      $discoveredRef,
      $impactAnalysis,
      $sourceOfTruth
    );
  }

  /**
   * Extract components from SPDX 2.x or CycloneDX SBOM.
   *
   * @param array $sbom
   * @return array Normalized component list
   */
  public function extractSbomComponents(array $sbom)
  {
    if (isset($sbom['spdxVersion']) && strpos($sbom['spdxVersion'], 'SPDX-2.') === 0) {
      return $this->extractSpdxComponents($sbom);
    }

    if (isset($sbom['bomFormat']) && $sbom['bomFormat'] === 'CycloneDX') {
      return $this->extractCycloneDxComponents($sbom);
    }

    throw new \InvalidArgumentException("Unsupported SBOM format; expected SPDX 2.x or CycloneDX");
  }

  /**
   * @param array $sbom
   * @return array
   */
  private function extractSpdxComponents(array $sbom)
  {
    $components = [];
    $format = (string)($sbom['spdxVersion'] ?? 'SPDX-2.3');
    $packages = $sbom['packages'] ?? [];

    if (!is_array($packages)) {
      return $components;
    }

    foreach ($packages as $pkg) {
      if (!is_array($pkg)) {
        continue;
      }

      $spdxId = (string)($pkg['SPDXID'] ?? '');
      $name = (string)($pkg['name'] ?? '');
      $version = (string)($pkg['versionInfo'] ?? '');

      // Check externalRefs for PURL
      $purl = '';
      $externalRefs = $pkg['externalRefs'] ?? [];
      if (is_array($externalRefs)) {
        foreach ($externalRefs as $ref) {
          if (!is_array($ref)) {
            continue;
          }
          $locator = (string)($ref['referenceLocator'] ?? '');
          $refType = strtolower((string)($ref['referenceType'] ?? ''));
          if (strpos($locator, 'pkg:') === 0 || strpos($refType, 'purl') !== false) {
            $purl = $locator;
            break;
          }
        }
      }

      // Check checksums
      $checksums = [];
      $rawChecksums = $pkg['checksums'] ?? [];
      if (is_array($rawChecksums)) {
        foreach ($rawChecksums as $cs) {
          if (is_array($cs) && !empty($cs['checksumValue'])) {
            $alg = strtolower($cs['algorithm'] ?? '');
            $checksums[$alg] = strtolower($cs['checksumValue']);
          }
        }
      }

      $components[] = [
        'format' => $format,
        'identifier' => $spdxId,
        'name' => $name,
        'version' => $version,
        'purl' => $purl,
        'checksums' => $checksums,
      ];
    }

    return $components;
  }

  /**
   * @param array $sbom
   * @return array
   */
  private function extractCycloneDxComponents(array $sbom)
  {
    $components = [];
    $format = 'CycloneDX-' . ($sbom['specVersion'] ?? '1.6');
    $rawList = [];

    if (isset($sbom['metadata']['component']) && is_array($sbom['metadata']['component'])) {
      $rawList[] = $sbom['metadata']['component'];
    }

    $topComponents = $sbom['components'] ?? [];
    if (is_array($topComponents)) {
      $this->walkCycloneDxComponents($topComponents, $rawList);
    }

    foreach ($rawList as $comp) {
      if (!is_array($comp)) {
        continue;
      }

      $bomRef = (string)($comp['bom-ref'] ?? '');
      $name = (string)($comp['name'] ?? '');
      $version = (string)($comp['version'] ?? '');
      $purl = (string)($comp['purl'] ?? '');

      $checksums = [];
      $hashes = $comp['hashes'] ?? [];
      if (is_array($hashes)) {
        foreach ($hashes as $h) {
          if (is_array($h) && !empty($h['content'])) {
            $alg = strtolower(str_replace('-', '', $h['alg'] ?? ''));
            $checksums[$alg] = strtolower($h['content']);
          }
        }
      }

      $components[] = [
        'format' => $format,
        'identifier' => $bomRef,
        'name' => $name,
        'version' => $version,
        'purl' => $purl,
        'checksums' => $checksums,
      ];
    }

    return $components;
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
   * Build lookup indexes for fast, deterministic matching.
   *
   * @param array $components
   * @return array
   */
  private function buildComponentIndexes(array $components)
  {
    $purlIndex = [];
    $idIndex = [];
    $digestIndex = [];

    foreach ($components as $component) {
      // Index by PURL + Version
      if (!empty($component['purl'])) {
        list($basePurl, $purlVersion) = $this->decomposePurl($component['purl']);
        $version = $purlVersion ?: $component['version'];
        if ($version !== '') {
          $key = strtolower($basePurl . '@' . $version);
          $purlIndex[$key][] = $component;
        }
      }

      // Index by SBOM Identifier
      if (!empty($component['identifier'])) {
        $idKey = strtolower($component['identifier']);
        $idIndex[$idKey][] = $component;
      }

      // Index by Checksums
      foreach ($component['checksums'] as $alg => $hash) {
        $digestIndex[strtolower($hash)][] = $component;
      }
    }

    return [
      'purl' => $purlIndex,
      'id' => $idIndex,
      'digest' => $digestIndex,
    ];
  }

  /**
   * Match by artifact digest.
   *
   * @param array $subject
   * @param array $indexes
   * @return array
   */
  private function matchByDigest(array $subject, array $indexes)
  {
    // Check if subject declares an artifact digest or if version is a 40/64 hex commit/digest
    $version = (string)($subject['version'] ?? '');
    if (preg_match('/^[0-9a-fA-F]{40,64}$/', $version)) {
      $key = strtolower($version);
      if (isset($indexes['digest'][$key])) {
        return $indexes['digest'][$key];
      }
    }

    return [];
  }

  /**
   * Match by PURL and version.
   *
   * @param array $subject
   * @param array $indexes
   * @return array
   */
  private function matchByPurlVersion(array $subject, array $indexes)
  {
    $subjectPurl = (string)($subject['purl'] ?? '');
    $subjectVersion = (string)($subject['version'] ?? '');

    if ($subjectPurl === '') {
      return [];
    }

    list($basePurl, $purlVersion) = $this->decomposePurl($subjectPurl);
    $expectedVersion = $purlVersion ?: $subjectVersion;

    if ($expectedVersion === '') {
      return [];
    }

    $key = strtolower($basePurl . '@' . $expectedVersion);
    if (isset($indexes['purl'][$key])) {
      return $indexes['purl'][$key];
    }

    return [];
  }

  /**
   * Match by SBOM Identifier (SPDXID or bom-ref).
   *
   * @param array $subject
   * @param array $indexes
   * @return array
   */
  private function matchByIdentifier(array $subject, array $indexes)
  {
    $candidates = [];

    // Try componentPath
    if (!empty($subject['componentPath'])) {
      $pathKey = strtolower($subject['componentPath']);
      if (isset($indexes['id'][$pathKey])) {
        $candidates = array_merge($candidates, $indexes['id'][$pathKey]);
      }
    }

    return array_values(array_unique($candidates, SORT_REGULAR));
  }

  /**
   * Decompose PURL into base (without subpath/qualifiers) and version.
   *
   * @param string $purl
   * @return array [basePurl, version]
   */
  public function decomposePurl($purl)
  {
    // Discard subpath (anything after #)
    $withoutSubpath = explode('#', $purl, 2)[0];

    // Discard qualifiers (anything after ?)
    $withoutQualifiers = explode('?', $withoutSubpath, 2)[0];

    if (strpos($withoutQualifiers, '@') === false) {
      return [$withoutQualifiers, null];
    }

    $lastAt = strrpos($withoutQualifiers, '@');
    $base = substr($withoutQualifiers, 0, $lastAt);
    $version = substr($withoutQualifiers, $lastAt + 1);

    return [$base, $version];
  }
}
