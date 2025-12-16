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
      $reportName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $report['name']);
      $targetFile = time().'_'.random_int(0, getrandmax()).'_'.$reportName;
      if (move_uploaded_file($report['tmp_name'], $fileBase.$targetFile))
      {
        return '--report=' . escapeshellarg($targetFile);
      }
    }elseif($report && is_string($report)){
      return '--report=' . escapeshellarg($report);
    }
    return '';
  }
}

register_plugin(new ReportImportAgentPlugin());
