<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac;

/**
 * @class SracValidator
 * @brief Validates the structural schema, internal references, and constraints of SRAC documents.
 *
 * Implements validation against the Draft S-CORE Safety Relevance Assertion Capability profile (0.2-draft).
 * Note: As indicated in the upstream Eclipse S-CORE prototype, this schema is developmental/proposal
 * material and does not claim released normative support.
 */
class SracValidator
{
  const SCHEMA_VERSION = '0.2-draft';

  const FUNCTIONAL_SAFETY_RELEVANCE_VALUES = [
    'safety-related',
    'not-safety-related',
    'undetermined'
  ];

  const PORTABLE_SAFETY_CLASSIFICATIONS = [
    'QM',
    'ASIL-A',
    'ASIL-B',
    'ASIL-C',
    'ASIL-D',
    'not-assigned'
  ];

  const ASSERTION_STATUS_VALUES = [
    'draft',
    'under-review',
    'reviewed',
    'approved',
    'superseded',
    'withdrawn'
  ];

  const IMPACT_ANALYSIS_STATUS_VALUES = [
    'new',
    'inProgress',
    'complete',
    'stopped',
    'duplicate',
    'other'
  ];

  const IMPACT_LEVEL_VALUES = [
    'noCriticalImpact',
    'safetyImpact',
    'securityImpact',
    'safetyAndSecurityImpact',
    'qualityImpact',
    'availabilityImpact',
    'customerSatisfactionImpact',
    'other'
  ];

  const SPDX_SAFETY_INTEGRITY_LEVEL_VALUES = [
    'qm',
    'asilA',
    'asilB',
    'asilC',
    'asilD',
    'sil1',
    'sil2',
    'sil3',
    'sil4',
    'dalA',
    'dalB',
    'dalC',
    'dalD',
    'dalE',
    'other',
    'noAssertion'
  ];

  const PORTABLE_TO_SPDX_SIL = [
    'QM' => 'qm',
    'ASIL-A' => 'asilA',
    'ASIL-B' => 'asilB',
    'ASIL-C' => 'asilC',
    'ASIL-D' => 'asilD',
    'not-assigned' => 'noAssertion'
  ];

  const DECISION_TYPE_VALUES = [
    'approve',
    'approveConditionally',
    'reject',
    'defer',
    'delegate',
    'requestChange',
    'requestInformation',
    'noAction',
    'close',
    'duplicate',
    'other'
  ];

  const DECISION_STATUS_VALUES = [
    'proposed',
    'requested',
    'inProgress',
    'recorded',
    'superseded',
    'withdrawn',
    'enteredInError',
    'other'
  ];

  /**
   * Validate an SRAC assertion document.
   *
   * @param array $document Parsed SRAC document array
   * @return array List of validation error strings (empty if valid)
   */
  public function validateAssertion(array $document)
  {
    $errors = [];

    // Check schemaVersion
    if (!isset($document['schemaVersion']) || $document['schemaVersion'] !== self::SCHEMA_VERSION) {
      $errors[] = "schemaVersion must be '" . self::SCHEMA_VERSION . "'";
    }

    // Check assertionId
    if (empty($document['assertionId']) || !is_string($document['assertionId']) || trim($document['assertionId']) === '') {
      $errors[] = "assertionId must be a non-empty string";
    }

    // Check subject
    $this->validateSubject($document, $errors);

    // Check safetyRelevance
    $classification = null;
    $this->validateSafetyRelevance($document, $errors, $classification);

    // Check rationale
    if (empty($document['rationale']) || !is_string($document['rationale']) || trim($document['rationale']) === '') {
      $errors[] = "rationale must be a non-empty string";
    }

    // Collect resolvable element IDs and check duplicate IDs
    $resolvableIds = $this->collectAssertionElementIds($document, $errors);

    // Check requirements
    if (array_key_exists('requirements', $document)) {
      $this->validateReferences($document['requirements'], 'requirements', $errors, false);
    }

    // Check evidence
    if (!isset($document['evidence'])) {
      $errors[] = "evidence is required";
    } else {
      $this->validateReferences($document['evidence'], 'evidence', $errors, true);
    }

    // Check impactAnalysis
    if (array_key_exists('impactAnalysis', $document)) {
      $this->validateImpactAnalysis($document['impactAnalysis'], 'impactAnalysis', $errors, $classification, $resolvableIds);
    }

    // Check sourceOfTruth
    if (array_key_exists('sourceOfTruth', $document) && $document['sourceOfTruth'] !== null) {
      $this->validateSourceOfTruth($document['sourceOfTruth'], 'sourceOfTruth', $errors);
    }

    // Check assertion metadata
    $this->validateAssertionMetadata($document, $errors);

    return $errors;
  }

  /**
   * @param array $document
   * @param array &$errors
   */
  private function validateSubject(array $document, array &$errors)
  {
    if (!isset($document['subject']) || !is_array($document['subject'])) {
      $errors[] = "subject must be an object";
      return;
    }

    $subject = $document['subject'];
    foreach (['name', 'purl', 'version'] as $field) {
      if (empty($subject[$field]) || !is_string($subject[$field]) || trim($subject[$field]) === '') {
        $errors[] = "subject.$field must be a non-empty string";
      }
    }

    if (!empty($subject['purl']) && is_string($subject['purl']) && strpos($subject['purl'], 'pkg:') !== 0) {
      $errors[] = "subject.purl must start with 'pkg:'";
    }

    if (!empty($subject['repository']) && !filter_var($subject['repository'], FILTER_VALIDATE_URL)) {
      $errors[] = "subject.repository must be a valid URI";
    }
  }

  /**
   * @param array $document
   * @param array &$errors
   * @param string|null &$classification
   */
  private function validateSafetyRelevance(array $document, array &$errors, &$classification)
  {
    if (!isset($document['safetyRelevance']) || !is_array($document['safetyRelevance'])) {
      $errors[] = "safetyRelevance must be an object";
      return;
    }

    $relevance = $document['safetyRelevance'];
    if (!isset($relevance['status']) || !in_array($relevance['status'], self::FUNCTIONAL_SAFETY_RELEVANCE_VALUES, true)) {
      $errors[] = "safetyRelevance.status must be one of [" . implode(', ', self::FUNCTIONAL_SAFETY_RELEVANCE_VALUES) . "]";
    }

    if (!isset($relevance['classification']) || !in_array($relevance['classification'], self::PORTABLE_SAFETY_CLASSIFICATIONS, true)) {
      $errors[] = "safetyRelevance.classification must be one of [" . implode(', ', self::PORTABLE_SAFETY_CLASSIFICATIONS) . "]";
    } else {
      $classification = $relevance['classification'];
    }
  }

  /**
   * @param mixed $value
   * @param string $path
   * @param array &$errors
   * @param bool $requireOne
   */
  private function validateReferences($value, $path, array &$errors, $requireOne)
  {
    if (!is_array($value)) {
      $errors[] = "$path must be an array";
      return;
    }

    if ($requireOne && empty($value)) {
      $errors[] = "$path must contain at least one item";
    }

    foreach ($value as $index => $item) {
      $itemPath = "$path" . "[$index]";
      if (!is_array($item)) {
        $errors[] = "$itemPath must be an object";
        continue;
      }

      foreach (['id', 'type', 'uri'] as $field) {
        if (empty($item[$field]) || !is_string($item[$field]) || trim($item[$field]) === '') {
          $errors[] = "$itemPath.$field must be a non-empty string";
        }
      }

      if (!empty($item['sha256'])) {
        if (!is_string($item['sha256']) || !preg_match('/^[0-9a-fA-F]{64}$/', $item['sha256'])) {
          $errors[] = "$itemPath.sha256 must be a 64-character hex digest";
        }
      }
    }
  }

  /**
   * @param mixed $value
   * @param string $path
   * @param array &$errors
   * @param string|null $classification
   * @param array $resolvableIds
   */
  private function validateImpactAnalysis($value, $path, array &$errors, $classification, array $resolvableIds)
  {
    if (!is_array($value)) {
      $errors[] = "$path must be an array";
      return;
    }

    foreach ($value as $index => $item) {
      $itemPath = "$path" . "[$index]";
      if (!is_array($item)) {
        $errors[] = "$itemPath must be an object";
        continue;
      }

      if (empty($item['id']) || !is_string($item['id']) || trim($item['id']) === '') {
        $errors[] = "$itemPath.id must be a non-empty string";
      }

      if (!isset($item['trigger']) || !is_array($item['trigger'])) {
        $errors[] = "$itemPath.trigger must be an object";
      } else {
        foreach (['type', 'uri'] as $key) {
          if (empty($item['trigger'][$key]) || !is_string($item['trigger'][$key]) || trim($item['trigger'][$key]) === '') {
            $errors[] = "$itemPath.trigger.$key must be a non-empty string";
          }
        }
      }

      if (!isset($item['impactAnalysisStatus']) || !in_array($item['impactAnalysisStatus'], self::IMPACT_ANALYSIS_STATUS_VALUES, true)) {
        $errors[] = "$itemPath.impactAnalysisStatus must be one of [" . implode(', ', self::IMPACT_ANALYSIS_STATUS_VALUES) . "]";
      }

      if (isset($item['impactLevel']) && !in_array($item['impactLevel'], self::IMPACT_LEVEL_VALUES, true)) {
        $errors[] = "$itemPath.impactLevel must be one of [" . implode(', ', self::IMPACT_LEVEL_VALUES) . "]";
      }

      if (isset($item['safetyIntegrityLevel'])) {
        $sil = $item['safetyIntegrityLevel'];
        if (!in_array($sil, self::SPDX_SAFETY_INTEGRITY_LEVEL_VALUES, true)) {
          $errors[] = "$itemPath.safetyIntegrityLevel must be one of [" . implode(', ', self::SPDX_SAFETY_INTEGRITY_LEVEL_VALUES) . "]";
        }
        if ($classification !== null && isset(self::PORTABLE_TO_SPDX_SIL[$classification])) {
          $expectedSil = self::PORTABLE_TO_SPDX_SIL[$classification];
          if ($sil !== $expectedSil) {
            $errors[] = "$itemPath.safetyIntegrityLevel must be '$expectedSil' when safety relevance classification is '$classification'";
          }
        }
      }

      foreach (['impactedElement', 'addedElement', 'modifiedElement', 'removedElement'] as $elemKey) {
        if (isset($item[$elemKey])) {
          $this->validateIdentifierList($item[$elemKey], "$itemPath.$elemKey", $errors);
          $this->validateResolvedIdentifiers($item[$elemKey], "$itemPath.$elemKey", $errors, $resolvableIds);
        }
      }

      if (!isset($item['decisions']) || !is_array($item['decisions'])) {
        $errors[] = "$itemPath.decisions must be an array";
      } else {
        foreach ($item['decisions'] as $dIdx => $decision) {
          $dPath = "$itemPath.decisions[$dIdx]";
          if (!is_array($decision)) {
            $errors[] = "$dPath must be an object";
            continue;
          }
          if (!isset($decision['decisionType']) || !in_array($decision['decisionType'], self::DECISION_TYPE_VALUES, true)) {
            $errors[] = "$dPath.decisionType must be one of [" . implode(', ', self::DECISION_TYPE_VALUES) . "]";
          }
          if (!isset($decision['decisionStatus']) || !in_array($decision['decisionStatus'], self::DECISION_STATUS_VALUES, true)) {
            $errors[] = "$dPath.decisionStatus must be one of [" . implode(', ', self::DECISION_STATUS_VALUES) . "]";
          }
          if (!isset($decision['appliesTo']) || !is_array($decision['appliesTo']) || empty($decision['appliesTo'])) {
            $errors[] = "$dPath.appliesTo must contain at least one item";
          } else {
            $this->validateIdentifierList($decision['appliesTo'], "$dPath.appliesTo", $errors);
            $this->validateResolvedIdentifiers($decision['appliesTo'], "$dPath.appliesTo", $errors, $resolvableIds);
          }
          if (empty($decision['rationale']) || !is_string($decision['rationale']) || trim($decision['rationale']) === '') {
            $errors[] = "$dPath.rationale must be a non-empty string";
          }
        }
      }

      if (isset($item['requirementVerification'])) {
        if (!is_array($item['requirementVerification'])) {
          $errors[] = "$itemPath.requirementVerification must be an array";
        } else {
          foreach ($item['requirementVerification'] as $vIdx => $vItem) {
            $vPath = "$itemPath.requirementVerification[$vIdx]";
            if (!is_array($vItem)) {
              $errors[] = "$vPath must be an object";
              continue;
            }
            if (empty($vItem['id']) || !is_string($vItem['id']) || trim($vItem['id']) === '') {
              $errors[] = "$vPath.id must be a non-empty string";
            }
            if (!isset($vItem['verifies']) || !is_array($vItem['verifies']) || empty($vItem['verifies'])) {
              $errors[] = "$vPath.verifies must contain at least one item";
            } else {
              $this->validateIdentifierList($vItem['verifies'], "$vPath.verifies", $errors);
              $this->validateResolvedIdentifiers($vItem['verifies'], "$vPath.verifies", $errors, $resolvableIds);
            }
            if (isset($vItem['evidence'])) {
              $this->validateIdentifierList($vItem['evidence'], "$vPath.evidence", $errors);
              $this->validateResolvedIdentifiers($vItem['evidence'], "$vPath.evidence", $errors, $resolvableIds);
            }
          }
        }
      }

      if (isset($item['bundle'])) {
        $bPath = "$itemPath.bundle";
        if (!is_array($item['bundle'])) {
          $errors[] = "$bPath must be an object";
        } else {
          if (empty($item['bundle']['id']) || !is_string($item['bundle']['id']) || trim($item['bundle']['id']) === '') {
            $errors[] = "$bPath.id must be a non-empty string";
          }
          if (!isset($item['bundle']['rootElement']) || !is_array($item['bundle']['rootElement']) || empty($item['bundle']['rootElement'])) {
            $errors[] = "$bPath.rootElement must contain at least one item";
          } else {
            $this->validateIdentifierList($item['bundle']['rootElement'], "$bPath.rootElement", $errors);
            $this->validateResolvedIdentifiers($item['bundle']['rootElement'], "$bPath.rootElement", $errors, $resolvableIds);
          }
        }
      }
    }
  }

  /**
   * @param mixed $value
   * @param string $path
   * @param array &$errors
   */
  private function validateSourceOfTruth($value, $path, array &$errors)
  {
    if (!is_array($value)) {
      $errors[] = "$path must be an object";
      return;
    }

    if (!isset($value['system']) || $value['system'] !== 'sphinx-needs') {
      $errors[] = "$path.system must be 'sphinx-needs'";
    }

    foreach (['documentUri', 'version', 'rootElement', 'rootElementType', 'rootElementStatus'] as $key) {
      if (empty($value[$key]) || !is_string($value[$key]) || trim($value[$key]) === '') {
        $errors[] = "$path.$key must be a non-empty string";
      }
    }

    if (isset($value['includedElements'])) {
      $this->validateIdentifierList($value['includedElements'], "$path.includedElements", $errors);
    }

    if (!empty($value['sha256'])) {
      if (!is_string($value['sha256']) || !preg_match('/^[0-9a-f]{64}$/', $value['sha256'])) {
        $errors[] = "$path.sha256 must be a lowercase 64-character SHA-256 digest";
      }
    }
  }

  /**
   * @param array $document
   * @param array &$errors
   */
  private function validateAssertionMetadata(array $document, array &$errors)
  {
    if (!isset($document['assertion']) || !is_array($document['assertion'])) {
      $errors[] = "assertion must be an object";
      return;
    }

    $assertion = $document['assertion'];
    if (!isset($assertion['status']) || !in_array($assertion['status'], self::ASSERTION_STATUS_VALUES, true)) {
      $errors[] = "assertion.status must be one of [" . implode(', ', self::ASSERTION_STATUS_VALUES) . "]";
    }

    if (!isset($assertion['author']) || !is_array($assertion['author']) || empty($assertion['author']['name']) || !is_string($assertion['author']['name'])) {
      $errors[] = "assertion.author.name must be a non-empty string";
    }

    if (empty($assertion['created']) || !is_string($assertion['created']) || trim($assertion['created']) === '') {
      $errors[] = "assertion.created must be a non-empty date-time string";
    } else {
      // Validate date-time format (ISO 8601)
      if (strtotime($assertion['created']) === false) {
        $errors[] = "assertion.created must be a valid ISO 8601 date-time string";
      }
    }

    if (isset($assertion['reviewer']) && $assertion['reviewer'] !== null) {
      if (!is_array($assertion['reviewer']) || empty($assertion['reviewer']['name']) || !is_string($assertion['reviewer']['name'])) {
        $errors[] = "assertion.reviewer.name must be a non-empty string or null";
      }
    }
  }

  /**
   * Collect all element IDs defined in the assertion and detect duplicates.
   *
   * @param array $document
   * @param array &$errors
   * @return array Map of defined IDs [id => true]
   */
  private function collectAssertionElementIds(array $document, array &$errors)
  {
    $ids = [];
    $allIdsList = [];

    // Collect from requirements and evidence
    foreach (['requirements', 'evidence'] as $key) {
      if (isset($document[$key]) && is_array($document[$key])) {
        foreach ($document[$key] as $ref) {
          if (is_array($ref) && !empty($ref['id']) && is_string($ref['id'])) {
            $allIdsList[] = $ref['id'];
          }
        }
      }
    }

    // Collect from impactAnalysis
    if (isset($document['impactAnalysis']) && is_array($document['impactAnalysis'])) {
      foreach ($document['impactAnalysis'] as $analysis) {
        if (!is_array($analysis)) {
          continue;
        }
        if (!empty($analysis['id']) && is_string($analysis['id'])) {
          $allIdsList[] = $analysis['id'];
        }
        foreach (['decisions', 'requirementVerification'] as $subKey) {
          if (isset($analysis[$subKey]) && is_array($analysis[$subKey])) {
            foreach ($analysis[$subKey] as $subItem) {
              if (is_array($subItem) && !empty($subItem['id']) && is_string($subItem['id'])) {
                $allIdsList[] = $subItem['id'];
              }
            }
          }
        }
      }
    }

    // Check duplicates
    $counts = array_count_values($allIdsList);
    foreach ($counts as $id => $count) {
      if ($count > 1) {
        $errors[] = "assertion element id '$id' is defined more than once";
      }
      $ids[$id] = true;
    }

    return $ids;
  }

  /**
   * @param mixed $value
   * @param string $path
   * @param array &$errors
   */
  private function validateIdentifierList($value, $path, array &$errors)
  {
    if (!is_array($value)) {
      $errors[] = "$path must be an array";
      return;
    }

    foreach ($value as $idx => $id) {
      if (empty($id) || !is_string($id) || trim($id) === '') {
        $errors[] = "$path" . "[$idx] must be a non-empty string";
      }
    }
  }

  /**
   * @param mixed $value
   * @param string $path
   * @param array &$errors
   * @param array $resolvableIds
   */
  private function validateResolvedIdentifiers($value, $path, array &$errors, array $resolvableIds)
  {
    if (!is_array($value)) {
      return;
    }

    foreach ($value as $idx => $id) {
      if (is_string($id) && trim($id) !== '' && !isset($resolvableIds[$id])) {
        $errors[] = "$path" . "[$idx] references '$id', which does not resolve to an assertion element";
      }
    }
  }
}
