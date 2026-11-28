<?php
/*
 SPDX-FileCopyrightText: © 2015-2017 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

class ReportImportAgentPlugin extends AgentPlugin
{
  private static $keys = array(
    'addConcludedAsDecisions',
    'addLicenseInfoFromInfoInFile',
    'addLicenseInfoFromConcluded',
    'addConcludedAsDecisionsOverwrite',
    'addConcludedAsDecisionsTBD',
    'addCopyrights',
    'addNewLicensesAs',
    'licenseMatch'
  );

  public function __construct() {
    $this->Name = "agent_reportImport";
    $this->Title =  _("Report Import");
    $this->AgentName = "reportImport";

    parent::__construct();
  }

  function preInstall()
  {
    // no AgentCheckBox
  }

  public function setAdditionalJqCmdArgs($request)
  {
    $additionalJqCmdArgs = "";

    foreach(self::$keys as $key) {
      if($request->get($key) !== NULL)
      {
        $additionalJqCmdArgs .= " --".$key."=".$request->get($key);
      }
    }

    return $additionalJqCmdArgs;
  }

  /**
   * @brief Make a client supplied file name safe for the report store.
   *
   * The name ends up in a file system path and in the jq_args string that the
   * scheduler splits on spaces and quote characters, and which the agent
   * later resolves again (see ReportImportAgent::processUploadId()). Strip
   * any directory part and replace everything but [A-Za-z0-9._-] so that the
   * stored file is always found again by the agent. The suffix is kept, as
   * the agent selects the parser by it.
   *
   * @param string $name Original (client side) file name
   * @return string Sanitized file name, never empty
   */
  public static function sanitizeReportFilename($name)
  {
    $name = basename(str_replace('\\', '/', (string) $name));
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $name = ltrim(substr($name, -100), '.');
    return ($name === '') ? 'report' : $name;
  }

  public function addReport($report)
  {
    if ($report &&
        is_array($report) &&
        array_key_exists('error',$report) &&
        $report['error'] == UPLOAD_ERR_OK)
    {
      if(!file_exists($report['tmp_name']))
      {
        throw new Exception('Uploaded tmpfile not found');
      }

      global $SysConf;
      $fileBase = $SysConf['FOSSOLOGY']['path']."/ReportImport/";
      if (!is_dir($fileBase))
      {
        mkdir($fileBase,0755,true);
      }
      $targetFile = time().'_'.random_int(0, getrandmax()).'_'.
        self::sanitizeReportFilename($report['name'] ?? '');
      if (move_uploaded_file($report['tmp_name'], $fileBase.$targetFile))
      {
        return '--report=' . escapeshellarg($targetFile);
      }
      throw new Exception('Could not store the uploaded report in ' . $fileBase);
    }elseif($report && is_string($report)){
      return '--report=' . escapeshellarg($report);
    }
    return '';
  }
}

register_plugin(new ReportImportAgentPlugin());
