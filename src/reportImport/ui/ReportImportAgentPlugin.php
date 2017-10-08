<?php
/***********************************************************
 * Copyright (C) 2015-2017, Siemens AG
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
    'addNewLicensesAs'
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
      // TODO: validate filename
      $targetFile = time().'_'.rand().'_'.$report['name'];
      if (move_uploaded_file($report['tmp_name'], $fileBase.$targetFile))
      {
        return '--report='.$targetFile;
      }
    }elseif($report && is_string($report)){
      return '--report='.$report;
    }
    return '';
  }
}

register_plugin(new ReportImportAgentPlugin());
