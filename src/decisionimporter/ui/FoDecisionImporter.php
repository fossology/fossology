<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @dir
 * @brief Contains UI plugin for Decision Importer agent
 * @file
 * @brief Contains UI plugin for Decision Importer agent
 */

namespace Fossology\DecisionImporter\UI;

use Fossology\Lib\Plugin\AgentPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use UnexpectedValueException;

/**
 * Decision importer plugin to handle upload requests from UI
 * @class FoDecisionExporter
 * @brief FOSSology Decision Exporter UI plugin
 */
class FoDecisionImporter extends AgentPlugin
{
  const NAME = 'agent_fodecisionimporter';       ///< Plugin mod name

  private const KEYS = [
    'userselect'
  ];                                            ///< Additional keys used by agent

  public function __construct()
  {
    $this->Name = self::NAME;
    $this->Title = _("FOSSology Dump Importer");
    $this->AgentName = "decisionimporter";

    parent::__construct();
  }

  /**
   * @copydoc Fossology::Lib::Plugin::DefaultPlugin::preInstall()
   * @see Fossology::Lib::Plugin::DefaultPlugin::preInstall()
   */
  function preInstall()
  {
    // no AgentCheckBox
  }

  /**
   * Translate the data from UI request to CLI arguments.
   * @param Request $request
   * @return string
   */
  public function setAdditionalJqCmdArgs(Request $request): string
  {
    $additionalJqCmdArgs = "";

    foreach (self::KEYS as $key) {
      if ($request->get($key) !== NULL) {
        $additionalJqCmdArgs .= " --" . $key . "=" . $request->get($key);
      }
    }

    return $additionalJqCmdArgs;
  }

  /**
   * Save the uploaded file at correct path and add it to the CLI argument.
   * @param UploadedFile $report
   * @return string
   * @throws UnexpectedValueException
   */
  public function addReport(UploadedFile $report): string
  {
    if ($report->isValid()) {
      if (!$report->isFile()) {
        throw new UnexpectedValueException('Uploaded tmpfile not found');
      }

      global $SysConf;
      $fileBase = $SysConf['FOSSOLOGY']['path'] . "/DecisionImport/";
      if (!is_dir($fileBase)) {
        mkdir($fileBase, 0755, true);
      }
      $targetFile = time() . '_' . rand() . '_' . $report->getClientOriginalName();
      $movedFile = $report->move($fileBase, $targetFile);
      if ($movedFile->isFile()) {
        return '--report=' . $movedFile->getFilename();
      }
    }
    return '';
  }
}

register_plugin(new FoDecisionImporter());
