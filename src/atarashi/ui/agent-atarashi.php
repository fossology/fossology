<?php
/***********************************************************
 * Copyright (C) 2019-2020, Siemens AG
 * Copyright (C) 2025, Rajul Jha <rajuljha49@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Atarashi\Ui;

use Fossology\Lib\Plugin\AgentPlugin;

class AtarashiAgentPlugin extends AgentPlugin
{
  /** @var atarashiDesc */
  private $atarashiDesc = "Runs text statistical algorithms like TFIDF, DLD, Ngram etc on text for license classification";

  public function __construct() {
    $this->Name = "agent_atarashi";
    $this->Title =  _("Atarashi License Analysis <img src=\"images/info_16.png\" data-toggle=\"tooltip\" title=\"".$this->atarashiDesc."\" class=\"info-bullet\"/>");
    $this->AgentName = "atarashi";

    parent::__construct();
  }

  /**
   * @brief Render HTML from template
   * @param array $vars Variables using in template
   * @return string HTML rendered from agent_decider.html.twig template
   */
  public function renderContent(&$vars)
  {
    $renderer = $GLOBALS['container']->get('twig.environment');
    return $renderer->load('atarashi.html.twig')->render($vars);
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

  public function getScriptIncludes(&$vars)
  {
    return "";
  }

  /**
   * @brief Schedule Atarashi agent
   * @param int $jobId Job ID
   * @param int $uploadId Upload ID
   * @param string $errorMsg Error message if any
   * @param Request $request Symfony request object
   * @return int JobQueue ID or 0 if scheduling failed
   */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $dependencies = array();
    $config = parse_ini_file(__DIR__ . "/atarashi.conf", true);
    $agent = $config['atarashi']['agentName'] ?? null;
    $similarity = $config['atarashi']['similarityMethod'] ?? null;
    // $agent = $request->get('atarashiAgent');         // e.g. DLD, tfidf, etc.
    // $similarity = $request->get('atarashiSimilarity'); // could be null

    $args = $this->getAtarashiArgs($agent, $similarity);
    if ($args === null) {
      $errorMsg = _("Invalid Atarashi configuration.");
      return 0;
    }

    return parent::AgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $args);
  }

  /**
   * @brief Construct Atarashi CLI arguments from UI selections
   * @param string $agent Selected agent (e.g., tfidf, DLD, etc.)
   * @param string|null $similarity Optional similarity method
   * @return string|null CLI arguments string or null if invalid
   */
  public function getAtarashiArgs($agent, $similarity)
  {
    if (empty($agent)) {
      return null;
    }

    $validAgents = ["DLD", "tfidf", "Ngram", "wordFrequencySimilarity"];
    if (!in_array($agent, $validAgents)) {
      return null;
    }

    $argString = "-a " . escapeshellarg($agent);

    if ($agent === "tfidf") {
      $validSims = ["cosineSim", "scoreSim"];
      if (!in_array($similarity, $validSims)) return null;
      $argString .= " -s " . escapeshellarg($similarity);
    } elseif ($agent === "Ngram") {
      $validSims = ["cosineSim", "DiceSim", "BigramCosineSim"];
      if (!in_array($similarity, $validSims)) return null;
      $argString .= " -s " . escapeshellarg($similarity);
    } else {
      // DLD and wordFrequencySimilarity don't need -s
      if (!empty($similarity)) return null;
    }

    return $argString;
  }

  /**
   * Check if the agent has already generated results for an upload
   * @param int $uploadId
   * @return bool
   */
  public function AgentHasResults($uploadId = 0)
  {
    return CheckARS($uploadId, $this->AgentName, "atarashi agent", "atarashi_ars");
  }

  /**
   * Add this plugin to the menu if the binary is found
   */
  public function preInstall()
  {
    // if ($this->isAtarashiInstalled()) {
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
    // }
  }

  /**
   * Check if the Atarashi binary is installed in the system
   * @return bool
   */
  public function isAtarashiInstalled()
  {
    exec('which atarashi', $lines, $returnVar);
    return (0 === $returnVar);
  }
}

register_plugin(new AtarashiAgentPlugin());
