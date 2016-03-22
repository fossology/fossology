<?php
/***********************************************************
 * Copyright (C) 2015, Siemens AG
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

class SpdxTwoImportAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_spdx2Import";
    $this->Title =  _("SPDX2 importing");
    $this->AgentName = "spdx2Import";

    parent::__construct();
  }

  function preInstall()
  {
    // no AgentCheckBox
  }

  public function addSpdxReport($spdxReport)
  {
    if ($spdxReport &&
        is_array($spdxReport) &&
        array_key_exists('error',$spdxReport) &&
        $spdxReport['error'] == UPLOAD_ERR_OK)
    {
      if(!file_exists($spdxReport['tmp_name']))
      {
        throw new Exception('Uploaded tmpfile not found');
      }

      global $SysConf;
      $fileBase = $SysConf['FOSSOLOGY']['path']."/SPDX2Import/";
      if (!is_dir($fileBase))
      {
        mkdir($fileBase,0755,true);
      }
      // TODO: validate filename
      $targetFile = time().'_'.rand().'_'.$spdxReport['name'];
      if (move_uploaded_file($spdxReport['tmp_name'], $fileBase.$targetFile))
      {
        return '--spdxReport='.$targetFile;
      }
    }elseif($spdxReport && is_string($spdxReport)){
      return '--spdxReport='.$spdxReport;
    }
    return '';
  }
}

register_plugin(new SpdxTwoImportAgentPlugin());
