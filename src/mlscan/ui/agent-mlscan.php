<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file agent-mlscan.php
 * @brief UI plugin for ML License Scanner agent
 */

namespace Fossology\\MLScan\\Ui;

use Fossology\\Lib\\Plugin\\AgentPlugin;
use Symfony\\Component\\HttpFoundation\\Request;

class MLScanAgentPlugin extends AgentPlugin
{
  public function __construct()
  {
    $this->Name = "agent_mlscan";
    $this->Title = _("ML License Scanner");
    $this->AgentName = "mlscan";

    parent::__construct();
  }

  /**
   * @brief Render HTML from template
   * @param array $vars Variables using in template
   * @return string HTML rendered from template
   */
  public function renderContent(&$vars)
  {
    $html = "<div class='mlscan-info'>";
    $html .= "<h3>ML License Scanner</h3>";
    $html .= "<p>Uses machine learning (TF-IDF + BERT) combined with rule-based detection to identify licenses with confidence scores.</p>";
    $html .= "<ul>";
    $html .= "<li><strong>Hybrid Detection:</strong> Combines ML and rule-based approaches</li>";
    $html .= "<li><strong>Confidence Scores:</strong> Provides 0.0-1.0 confidence for each detection</li>";
    $html .= "<li><strong>100+ Licenses:</strong> Supports comprehensive SPDX license coverage</li>";
    $html .= "</ul>";
    $html .= "</div>";
    
    return $html;
  }

  /**
   * @brief Render footer HTML
   * @param array $vars Variables using in template
   * @return string Footer HTML
   */
  public function renderFoot(&$vars)
  {
    return "";
  }

  /**
   * @brief Schedule mlscan agent
   * @param int $jobId Job ID
   * @param int $uploadId Upload ID
   * @param string $errorMsg Error message reference
   * @param Request $request HTTP request
   * @return int Job queue ID or 0 if not scheduled
   */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $dependencies = array();
    
    // ML scanner doesn't need special flags for now
    $args = "";
    
    return parent::AgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $args);
  }

  /**
   * @brief Check if agent has results for an upload
   * @param int $uploadId Upload ID
   * @return int 1 if has results, 0 otherwise
   */
  function AgentHasResults($uploadId=0)
  {
    return CheckARS($uploadId, $this->AgentName, "mlscan agent", "mlscan_ars");
  }

  /**
   * @brief Pre-install hook
   */
  public function preInstall()
  {
    menu_insert("ParmAgents::" . $this->Title, 0, $this->Name);
  }
}

// Register the plugin
register_plugin(new MLScanAgentPlugin());
