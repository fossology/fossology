<?php
/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Main executable entry point for the SRAC sidecar correlation agent.
 *
 * Supports both standalone CLI execution and FOSSology scheduler event-loop execution.
 */

namespace Fossology\Srac;

require_once __DIR__ . '/SracAgent.php';

function printUsage()
{
  echo <<<EOF
FOSSology SRAC Sidecar Correlation Agent
Usage:
  srac --sbom=<path> [--srac=<path>] [--artifact-root=<dir>] [--output=<path>] [--format=json|text]
  srac -c <sysconfdir> --scheduler_start (scheduler mode)

Options:
  --sbom=<path>           Path to SPDX 2.x or CycloneDX SBOM JSON file.
  --srac=<path>           Path to SRAC assertion JSON file. If omitted,
                          the SBOM will be inspected for integrity-protected SRAC references.
  --artifact-root=<dir>   Directory containing referenced SRAC sidecar artifacts.
  --output=<path>         Path to write the resulting correlation report.
  --format=<format>       Output format: json (default) or text.
  -h, --help              Show this help message.

EOF;
}

// Check for help flag
$shortOpts = "h";
$longOpts = ["help", "scheduler_start", "sbom:", "srac:", "artifact-root:", "output:", "format:"];
$options = getopt($shortOpts, $longOpts);

if (isset($options['h']) || isset($options['help'])) {
  printUsage();
  exit(0);
}

// Scheduler mode
if (isset($options['scheduler_start'])) {
  $agent = new SracAgent(null, null, null, null, null, true);
  $agent->scheduler_connect();
  $agent->run_scheduler_event_loop();
  $agent->scheduler_disconnect(0);
  exit(0);
}

// Standalone CLI mode
if (!isset($options['sbom'])) {
  printUsage();
  exit(1);
}

try {
  $agent = new SracAgent();
  list($exitCode, $content, $result) = $agent->runStandalone($options);

  if (empty($options['output'])) {
    echo $content;
  } else {
    echo "Correlation completed with status: " . $result->getStatus() . "\n";
    echo "Report written to: " . $options['output'] . "\n";
  }

  exit($exitCode);
} catch (\Exception $e) {
  fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
  exit(2);
}
