<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac;

/**
 * @class SracReportGenerator
 * @brief Generates auditable SRAC correlation reports in JSON and text formats.
 *
 * Adheres to S-CORE srac-report.schema.json for interoperability, while also
 * generating comprehensive summary and audit records for compliance logs.
 */
class SracReportGenerator
{
  const TOOL_NAME = 'fossology.srac';
  const TOOL_VERSION = '0.2.0-draft';
  const SCHEMA_VERSION = '0.2-draft';

  /**
   * Build a single-assertion enrichment report conforming to srac-report.schema.json.
   *
   * @param SracResult $result
   * @param string $sourceFormat "SPDX" or "CycloneDX"
   * @param array $integrity
   * @return array
   */
  public function generateSingleReport(SracResult $result, $sourceFormat, array $integrity = [])
  {
    $safety = $result->getSafetyAssessment();

    $report = [
      'schemaVersion' => self::SCHEMA_VERSION,
      'sourceFormat' => $sourceFormat,
      'assertionId' => $result->getAssertionId(),
      'subject' => $result->getSubject(),
      'safetyAssessment' => [
        'source' => 'assertion',
        'safetyRelevance' => $safety['safetyRelevance'] ?? 'undetermined',
        'classification' => $safety['classification'] ?? 'not-assigned',
        'assertionStatus' => $safety['assertionStatus'] ?? 'draft',
        'reviewer' => $safety['reviewer'] ?? null,
      ],
      'matchedComponents' => $this->sanitizeMatchedComponents($result->getMatchedComponents()),
      'matchStatus' => ($result->getStatus() === SracResult::STATUS_MATCHED) ? 'matched' : 'unmatched',
      'generator' => [
        'name' => self::TOOL_NAME,
        'version' => self::TOOL_VERSION,
      ],
      'integrity' => array_merge([
        'algorithm' => 'SHA-256',
        'assertionSha256' => $integrity['assertionSha256'] ?? '',
        'sbomSha256' => $integrity['sbomSha256'] ?? '',
      ], $integrity),
    ];

    if ($result->getDiscoveredReference() !== null) {
      $report['discoveredReference'] = $result->getDiscoveredReference();
    }

    if ($result->getImpactAnalysis() !== null) {
      $report['impactAnalysis'] = $result->getImpactAnalysis();
    }

    if ($result->getSourceOfTruth() !== null) {
      $report['sourceOfTruth'] = $result->getSourceOfTruth();
    }

    return $report;
  }

  /**
   * Build a comprehensive multi-assertion auditable correlation report.
   *
   * @param SracResult[] $results
   * @param string $sourceFormat "SPDX" or "CycloneDX"
   * @param array $metadata Extra report metadata (e.g. uploadId, filenames, digests)
   * @return array
   */
  public function generateComprehensiveReport(array $results, $sourceFormat, array $metadata = [])
  {
    $summary = [
      'totalAssertions' => count($results),
      'matched' => 0,
      'unmatched' => 0,
      'ambiguous' => 0,
      'invalid' => 0,
      'stale' => 0,
    ];

    $records = [];
    foreach ($results as $result) {
      $status = $result->getStatus();
      if (isset($summary[$status])) {
        $summary[$status]++;
      }

      $records[] = $result->toArray();
    }

    return [
      'schemaVersion' => self::SCHEMA_VERSION,
      'reportType' => 'FOSSology-SRAC-Correlation-Report',
      'sourceFormat' => $sourceFormat,
      'generator' => [
        'name' => self::TOOL_NAME,
        'version' => self::TOOL_VERSION,
        'notice' => 'FOSSology correlates and reports externally authored safety assertions; it does not create, infer, or approve safety classifications.'
      ],
      'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
      'metadata' => $metadata,
      'summary' => $summary,
      'assertions' => $records,
    ];
  }

  /**
   * Generate an auditable plain text summary report.
   *
   * @param array $comprehensiveReport Output of generateComprehensiveReport()
   * @return string
   */
  public function generateTextReport(array $comprehensiveReport)
  {
    $lines = [];
    $lines[] = "================================================================================";
    $lines[] = "               FOSSOLOGY SRAC CORRELATION AUDIT REPORT                          ";
    $lines[] = "================================================================================";
    $lines[] = "Tool:        " . self::TOOL_NAME . " " . self::TOOL_VERSION;
    $lines[] = "Schema:      " . self::SCHEMA_VERSION;
    $lines[] = "Timestamp:   " . ($comprehensiveReport['timestamp'] ?? '');
    $lines[] = "SBOM Format: " . ($comprehensiveReport['sourceFormat'] ?? '');
    $lines[] = "Notice:      " . ($comprehensiveReport['generator']['notice'] ?? '');
    $lines[] = "--------------------------------------------------------------------------------";

    $summary = $comprehensiveReport['summary'] ?? [];
    $lines[] = "SUMMARY COUNTS:";
    $lines[] = "  Total Assertions: " . ($summary['totalAssertions'] ?? 0);
    $lines[] = "  Matched:          " . ($summary['matched'] ?? 0);
    $lines[] = "  Unmatched:        " . ($summary['unmatched'] ?? 0);
    $lines[] = "  Ambiguous:        " . ($summary['ambiguous'] ?? 0);
    $lines[] = "  Invalid:          " . ($summary['invalid'] ?? 0);
    $lines[] = "  Stale:            " . ($summary['stale'] ?? 0);
    $lines[] = "--------------------------------------------------------------------------------";
    $lines[] = "DETAILED ASSERTION RECORDS:";

    $assertions = $comprehensiveReport['assertions'] ?? [];
    foreach ($assertions as $i => $item) {
      $num = $i + 1;
      $lines[] = "";
      $lines[] = "[$num] Assertion ID: " . ($item['assertionId'] ?? 'N/A');
      $lines[] = "     Status:           " . strtoupper($item['status'] ?? 'UNKNOWN');
      $lines[] = "     Correlation Rule: " . ($item['correlationRule'] ?? 'none');

      $subject = $item['subject'] ?? [];
      $lines[] = "     Subject Name:     " . ($subject['name'] ?? 'N/A');
      $lines[] = "     Subject PURL:     " . ($subject['purl'] ?? 'N/A');
      $lines[] = "     Subject Version:  " . ($subject['version'] ?? 'N/A');

      $safety = $item['safetyAssessment'] ?? [];
      $lines[] = "     Safety Relevance: " . ($safety['safetyRelevance'] ?? 'N/A');
      $lines[] = "     Classification:   " . ($safety['classification'] ?? 'N/A');
      $lines[] = "     Review State:     " . ($safety['assertionStatus'] ?? 'N/A');

      if (!empty($safety['author']['name'])) {
        $lines[] = "     Author:           " . $safety['author']['name'];
      }
      if (!empty($safety['reviewer']['name'])) {
        $lines[] = "     Reviewer:         " . $safety['reviewer']['name'];
      }

      $lines[] = "     Rationale:        " . ($item['rationale'] ?? 'N/A');

      $matched = $item['matchedComponents'] ?? [];
      $count = count($matched);
      $lines[] = "     Matched Targets ($count):";
      foreach ($matched as $m) {
        $id = $m['identifier'] ?? $m['name'] ?? 'N/A';
        $purl = $m['purl'] ?? 'N/A';
        $lines[] = "       - ID: $id | PURL: $purl";
      }

      $errs = $item['validationErrors'] ?? [];
      if (!empty($errs)) {
        $lines[] = "     Validation Errors:";
        foreach ($errs as $err) {
          $lines[] = "       ! $err";
        }
      }
    }

    $lines[] = "================================================================================";
    $lines[] = "END OF REPORT";

    return implode("\n", $lines) . "\n";
  }

  /**
   * Ensure matched components match the schema structure required by srac-report.schema.json.
   *
   * @param array $components
   * @return array
   */
  private function sanitizeMatchedComponents(array $components)
  {
    $sanitized = [];
    foreach ($components as $c) {
      $sanitized[] = [
        'format' => $c['format'] ?? '',
        'identifier' => $c['identifier'] ?? null,
        'name' => $c['name'] ?? null,
        'version' => $c['version'] ?? null,
        'purl' => $c['purl'] ?? '',
      ];
    }
    return $sanitized;
  }
}
