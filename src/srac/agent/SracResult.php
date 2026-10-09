<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac;

/**
 * @class SracResult
 * @brief Represents the result of validating and correlating an SRAC assertion.
 *
 * Implements strict classification into mutually exclusive states:
 * - matched: successfully and uniquely matched to an SBOM component
 * - unmatched: valid assertion but no matching SBOM component
 * - ambiguous: multiple SBOM candidates satisfied the deterministic matching rule
 * - invalid: assertion failed schema validation, internal reference integrity, or hash verification
 * - stale: valid assertion marked superseded, withdrawn, or expired
 */
class SracResult
{
  const STATUS_MATCHED = 'matched';
  const STATUS_UNMATCHED = 'unmatched';
  const STATUS_AMBIGUOUS = 'ambiguous';
  const STATUS_INVALID = 'invalid';
  const STATUS_STALE = 'stale';

  const RULE_ARTIFACT_DIGEST = 'artifact_digest';
  const RULE_PURL_VERSION = 'purl_version';
  const RULE_SBOM_IDENTIFIER = 'sbom_identifier';
  const RULE_NONE = 'none';

  /** @var string */
  private $status;

  /** @var string */
  private $rule;

  /** @var string */
  private $assertionId;

  /** @var array */
  private $subject;

  /** @var array */
  private $safetyAssessment;

  /** @var string */
  private $rationale;

  /** @var array */
  private $evidence;

  /** @var array|null */
  private $impactAnalysis;

  /** @var array|null */
  private $sourceOfTruth;

  /** @var array */
  private $matchedComponents;

  /** @var array */
  private $validationErrors;

  /** @var array */
  private $integrity;

  /** @var array|null */
  private $discoveredReference;

  /**
   * @param string $status
   * @param string $rule
   * @param string $assertionId
   * @param array $subject
   * @param array $safetyAssessment
   * @param string $rationale
   * @param array $evidence
   * @param array $matchedComponents
   * @param array $validationErrors
   * @param array $integrity
   * @param array|null $discoveredReference
   * @param array|null $impactAnalysis
   * @param array|null $sourceOfTruth
   */
  public function __construct(
    $status = self::STATUS_UNMATCHED,
    $rule = self::RULE_NONE,
    $assertionId = '',
    array $subject = [],
    array $safetyAssessment = [],
    $rationale = '',
    array $evidence = [],
    array $matchedComponents = [],
    array $validationErrors = [],
    array $integrity = [],
    $discoveredReference = null,
    $impactAnalysis = null,
    $sourceOfTruth = null
  )
  {
    $this->status = $status;
    $this->rule = $rule;
    $this->assertionId = $assertionId;
    $this->subject = $subject;
    $this->safetyAssessment = $safetyAssessment;
    $this->rationale = $rationale;
    $this->evidence = $evidence;
    $this->matchedComponents = $matchedComponents;
    $this->validationErrors = $validationErrors;
    $this->integrity = $integrity;
    $this->discoveredReference = $discoveredReference;
    $this->impactAnalysis = $impactAnalysis;
    $this->sourceOfTruth = $sourceOfTruth;
  }

  /**
   * @return string
   */
  public function getStatus()
  {
    return $this->status;
  }

  /**
   * @param string $status
   */
  public function setStatus($status)
  {
    $this->status = $status;
  }

  /**
   * @return string
   */
  public function getRule()
  {
    return $this->rule;
  }

  /**
   * @param string $rule
   */
  public function setRule($rule)
  {
    $this->rule = $rule;
  }

  /**
   * @return string
   */
  public function getAssertionId()
  {
    return $this->assertionId;
  }

  /**
   * @return array
   */
  public function getSubject()
  {
    return $this->subject;
  }

  /**
   * @return array
   */
  public function getSafetyAssessment()
  {
    return $this->safetyAssessment;
  }

  /**
   * @return string
   */
  public function getRationale()
  {
    return $this->rationale;
  }

  /**
   * @return array
   */
  public function getEvidence()
  {
    return $this->evidence;
  }

  /**
   * @return array|null
   */
  public function getImpactAnalysis()
  {
    return $this->impactAnalysis;
  }

  /**
   * @return array|null
   */
  public function getSourceOfTruth()
  {
    return $this->sourceOfTruth;
  }

  /**
   * @return array
   */
  public function getMatchedComponents()
  {
    return $this->matchedComponents;
  }

  /**
   * @param array $matchedComponents
   */
  public function setMatchedComponents(array $matchedComponents)
  {
    $this->matchedComponents = $matchedComponents;
  }

  /**
   * @return array
   */
  public function getValidationErrors()
  {
    return $this->validationErrors;
  }

  /**
   * @param array $validationErrors
   */
  public function setValidationErrors(array $validationErrors)
  {
    $this->validationErrors = $validationErrors;
  }

  /**
   * @return array
   */
  public function getIntegrity()
  {
    return $this->integrity;
  }

  /**
   * @param array $integrity
   */
  public function setIntegrity(array $integrity)
  {
    $this->integrity = $integrity;
  }

  /**
   * @return array|null
   */
  public function getDiscoveredReference()
  {
    return $this->discoveredReference;
  }

  /**
   * @param array|null $discoveredReference
   */
  public function setDiscoveredReference($discoveredReference = null)
  {
    $this->discoveredReference = $discoveredReference;
  }

  /**
   * Export to associative array.
   * @return array
   */
  public function toArray()
  {
    $data = [
      'assertionId' => $this->assertionId,
      'status' => $this->status,
      'correlationRule' => $this->rule,
      'subject' => $this->subject,
      'safetyAssessment' => $this->safetyAssessment,
      'rationale' => $this->rationale,
      'evidence' => $this->evidence,
      'matchedComponents' => $this->matchedComponents,
      'validationErrors' => $this->validationErrors,
      'integrity' => $this->integrity
    ];

    if ($this->discoveredReference !== null) {
      $data['discoveredReference'] = $this->discoveredReference;
    }
    if ($this->impactAnalysis !== null) {
      $data['impactAnalysis'] = $this->impactAnalysis;
    }
    if ($this->sourceOfTruth !== null) {
      $data['sourceOfTruth'] = $this->sourceOfTruth;
    }

    return $data;
  }
}
