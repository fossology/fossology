<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Srac;

use Fossology\Lib\Agent\Agent;

if (file_exists(__DIR__ . '/version.php')) {
  require_once __DIR__ . '/version.php';
} else {
  if (!defined('SRAC_AGENT_NAME')) {
    define('SRAC_AGENT_NAME', 'srac');
  }
  if (!defined('AGENT_VERSION')) {
    define('AGENT_VERSION', '0.2.0-draft');
  }
  if (!defined('AGENT_REV')) {
    define('AGENT_REV', 'dev');
  }
}

require_once __DIR__ . '/SracResult.php';
require_once __DIR__ . '/SracParser.php';
require_once __DIR__ . '/SracValidator.php';
require_once __DIR__ . '/SracIntegrityChecker.php';
$vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
  require_once $vendorAutoload;
}
if (!class_exists('Fossology\Lib\Agent\Agent')) {
  $agentClassFile = dirname(__DIR__, 2) . '/lib/php/Agent/Agent.php';
  if (file_exists($agentClassFile)) {
    require_once $agentClassFile;
  }
}
if (!class_exists('Fossology\Lib\Agent\Agent')) {
  abstract class StandaloneAgentBase
  {
    protected $agentSpecifLongOptions = [];
    protected $agentName = '';
    protected $agentVersion = '';
    protected $agentRev = '';
    protected $args = [];
    protected $userId = 0;
    protected $groupId = 0;
    protected $jobId = 0;
    protected $container = null;

    public function __construct($agentName = '', $version = '', $revision = '')
    {
      $this->agentName = $agentName;
      $this->agentVersion = $version;
      $this->agentRev = $revision;
    }
    public function scheduler_connect()
    {
    }
    public function run_scheduler_event_loop()
    {
    }
    public function scheduler_disconnect($val = 0)
    {
    }
    public function heartbeat($newProcessed = 0)
    {
    }
    abstract protected function processUploadId($uploadId);
  }
  class_alias('Fossology\Srac\StandaloneAgentBase', 'Fossology\Lib\Agent\Agent');
}

/**
 * @class SracAgent
 * @brief FOSSology agent for correlating SRAC safety assertions with SBOM components.
 *
 * Can be executed via the FOSSology scheduler or standalone via CLI.
 * Conforms to FOSSology agent conventions.
 */
class SracAgent extends Agent
{
  const OPT_SRAC = 'srac';
  const OPT_SBOM = 'sbom';
  const OPT_ARTIFACT_ROOT = 'artifact-root';
  const OPT_OUTPUT = 'output';
  const OPT_FORMAT = 'format';

  /** @var SracParser */
  private $parser;

  /** @var SracValidator */
  private $validator;

  /** @var SracIntegrityChecker */
  private $integrityChecker;

  /** @var SracCorrelator */
  private $correlator;

  /** @var SracReportGenerator */
  private $reportGenerator;

  public function __construct(
    $parser = null,
    $validator = null,
    $integrityChecker = null,
    $correlator = null,
    $reportGenerator = null,
    $schedulerMode = false
  )
  {
    // Only initialize DB-backed Agent if scheduler mode is explicitly enabled and cli_Init exists
    if ($schedulerMode && function_exists('cli_Init')) {
      parent::__construct(SRAC_AGENT_NAME, AGENT_VERSION, AGENT_REV);
    }

    $this->agentSpecifLongOptions[] = self::OPT_SRAC . ':';
    $this->agentSpecifLongOptions[] = self::OPT_SBOM . ':';
    $this->agentSpecifLongOptions[] = self::OPT_ARTIFACT_ROOT . ':';
    $this->agentSpecifLongOptions[] = self::OPT_OUTPUT . ':';
    $this->agentSpecifLongOptions[] = self::OPT_FORMAT . ':';

    $this->parser = $parser ?: new SracParser();
    $this->validator = $validator ?: new SracValidator();
    $this->integrityChecker = $integrityChecker ?: new SracIntegrityChecker();
    $this->correlator = $correlator ?: new SracCorrelator($this->validator);
    $this->reportGenerator = $reportGenerator ?: new SracReportGenerator();
  }

  /**
   * Run standalone execution from CLI arguments.
   *
   * @param array $options Associative array of options
   * @return array [exitCode, reportContent]
   */
  public function runStandalone(array $options)
  {
    $sbomPath = $options[self::OPT_SBOM] ?? $options['s'] ?? null;
    $sracPath = $options[self::OPT_SRAC] ?? null;
    $artifactRoot = $options[self::OPT_ARTIFACT_ROOT] ?? null;
    $outputPath = $options[self::OPT_OUTPUT] ?? $options['o'] ?? null;
    $format = strtolower($options[self::OPT_FORMAT] ?? 'json');

    if (empty($sbomPath)) {
      throw new \InvalidArgumentException("Missing required parameter: --sbom=<path>");
    }

    // Parse SBOM
    $sbom = $this->parser->parseFile($sbomPath);
    $sbomSha256 = $this->integrityChecker->calculateFileSha256($sbomPath);
    $sourceFormat = (isset($sbom['spdxVersion']) && strpos($sbom['spdxVersion'], 'SPDX-2.') === 0)
      ? 'SPDX' : 'CycloneDX';

    $discoveredRef = null;
    $resolvedSracPath = $sracPath;

    // If srac path not provided directly, try discovering from SBOM references
    if (empty($resolvedSracPath)) {
      $references = $this->integrityChecker->discoverSracReferences($sbom);
      if (empty($references)) {
        throw new \InvalidArgumentException("SBOM has no integrity-protected SRAC external reference, and --srac was not provided");
      }
      if (count($references) > 1) {
        throw new \InvalidArgumentException(
          "SBOM contains " . count($references) . " SRAC external references; refusing ambiguous selection without explicit target"
        );
      }
      $discoveredRef = $references[0];
      $root = $artifactRoot ?: dirname($sbomPath);
      $resolvedSracPath = $this->integrityChecker->resolveAndVerifyArtifact($discoveredRef, $root);
    }

    if (!file_exists($resolvedSracPath)) {
      throw new \InvalidArgumentException("SRAC sidecar file not found: $resolvedSracPath");
    }

    $sracSha256 = $this->integrityChecker->calculateFileSha256($resolvedSracPath);

    // If discovered reference declared a digest, verify it matches
    if ($discoveredRef !== null && !$this->integrityChecker->verifyHash($sracSha256, $discoveredRef['sha256'])) {
      throw new \InvalidArgumentException(
        "SRAC artifact SHA-256 mismatch: expected {$discoveredRef['sha256']}, got $sracSha256"
      );
    }

    // Parse SRAC assertion
    $sracDoc = $this->parser->parseFile($resolvedSracPath);

    $integrity = [
      'algorithm' => 'SHA-256',
      'assertionSha256' => $sracSha256,
      'sbomSha256' => $sbomSha256,
    ];

    // Correlate assertion
    $result = $this->correlator->correlateAssertion($sracDoc, $sbom, $integrity, $discoveredRef);

    // Generate report
    if ($format === 'text') {
      $comprehensive = $this->reportGenerator->generateComprehensiveReport([$result], $sourceFormat, [
        'sbomFile' => basename($sbomPath),
        'sracFile' => basename($resolvedSracPath),
        'sbomSha256' => $sbomSha256,
        'assertionSha256' => $sracSha256,
      ]);
      $outputContent = $this->reportGenerator->generateTextReport($comprehensive);
    } else {
      $singleReport = $this->reportGenerator->generateSingleReport($result, $sourceFormat, $integrity);
      $outputContent = json_encode($singleReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    // Write to output file or return
    if (!empty($outputPath)) {
      $outDir = dirname($outputPath);
      if (!is_dir($outDir)) {
        mkdir($outDir, 0777, true);
      }
      file_put_contents($outputPath, $outputContent);
    }

    $exitCode = ($result->getStatus() === SracResult::STATUS_MATCHED) ? 0 : 1;
    return [$exitCode, $outputContent, $result];
  }

  /**
   * Process an upload in scheduler mode.
   *
   * @param int $uploadId
   * @return bool
   */
  public function processUploadId($uploadId)
  {
    $this->heartbeat(1);

    global $SysConf;
    $fileBase = ($SysConf['FOSSOLOGY']['path'] ?? '/srv/fossology/repository') . '/report/';
    if (!is_dir($fileBase)) {
      mkdir($fileBase, 0777, true);
    }

    $sracFile = $this->args[self::OPT_SRAC] ?? null;
    $sbomFile = $this->args[self::OPT_SBOM] ?? null;

    if (empty($sbomFile)) {
      echo "No SBOM file specified for SRAC correlation on upload $uploadId\n";
      return false;
    }

    $reportPath = $fileBase . 'SRAC_upload_' . $uploadId . '_' . time() . '.json';
    try {
      list($exitCode, $content, $result) = $this->runStandalone([
        self::OPT_SBOM => $sbomFile,
        self::OPT_SRAC => $sracFile,
        self::OPT_OUTPUT => $reportPath,
        self::OPT_FORMAT => 'json',
      ]);

      if ($this->container && $this->container->has('report.utils')) {
        $reportUtils = $this->container->get('report.utils');
        $reportUtils->updateOrInsertReportgenEntry($uploadId, $this->jobId, $reportPath);
      }

      return ($exitCode === 0);
    } catch (\Exception $e) {
      echo "SRAC correlation error on upload $uploadId: " . $e->getMessage() . "\n";
      return false;
    }
  }
}
