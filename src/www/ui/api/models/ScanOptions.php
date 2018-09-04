<?php
/***************************************************************
Copyright (C) 2017 Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/


namespace www\ui\api\models;
use Fossology\Lib\Auth\Auth;
use Fossology\Reuser\ReuserAgentPlugin;
use Symfony\Component\HttpFoundation\Request;
use www\ui\api\models\Info;
use www\ui\api\models\InfoType;

require_once dirname(dirname(__FILE__)) . "/agent-add.php";
require_once dirname(dirname(dirname(dirname(__DIR__)))) . "/lib/php/common-folders.php";


class ScanOptions
{
  private $analysis;
  private $reuse;
  private $decider;

  /**
   * ScanOptions constructor.
   * @param $analysis Analysis
   * @param $reuse integer
   * @param $decider Decider
   */
  public function __construct($analysis, $reuse, $decider)
  {
    $this->analysis = $analysis;
    $this->reuse = $reuse;
    $this->decider = $decider;
  }

  /**
   * Get ScanOptions elements as array
   * @return array
   */
  public function getArray()
  {
    return [
      "analysis"  => $this->analysis,
      "reuse"     => $this->reuse,
      "decide"    => $this->decider
    ];
  }

  public function scheduleAgents($folderId, $uploadId)
  {
    $uploadsAccessible = FolderListUploads_perm($folderId, Auth::PERM_WRITE);
    $found = false;
    foreach ($uploadsAccessible as $singleUpload)
    {
      if($singleUpload['upload_pk'] == $uploadId) {
        $found = true;
        break;
      }
    }
    if($found === false) {
      return new Info(404, "Folder id $folderId does not have upload id ".
        "$uploadId or you do not have write access to the folder.", InfoType::ERROR);
    }

    $paramAgentRequest = new Request();
    $agentsToAdd = $this->prepareAgents();
    $this->prepareReuser($paramAgentRequest);
    $this->prepareDecider($paramAgentRequest);
    $returnStatus = (new AgentAdder())->scheduleAgents($uploadId, $agentsToAdd, $paramAgentRequest);
    if(is_numeric($returnStatus)) {
      return new Info(200, $returnStatus, InfoType::INFO);
    } else {
      return new Info(403, $returnStatus, InfoType::ERROR);
    }
  }

  private function prepareAgents() {
    $agentsToAdd = [];
    foreach ($this->analysis->getArray() as $agent => $set) {
      if($set === true) {
        $agentsToAdd[] = "agent_$agent";
      }
    }
    return $agentsToAdd;
  }

  private function prepareReuser(Request &$request) {

    $reuserRules = [];
    if($this->reuse->getReuseMain() === true) {
      $reuserRules[] = 'reuseMain';
    }
    if($this->reuse->getReuseEnhanced() === true) {
      $reuserRules[] = 'reuseEnhanced';
    }
    $reuserSelector = $this->reuse->getReuseUpload() . "," . $this->reuse->getReuseGroup();
    $request->request->set(ReuserAgentPlugin::UPLOAD_TO_REUSE_SELECTOR_NAME, $reuserSelector);
    //global $SysConf;
    //$request->request->set('groupId', $SysConf['auth'][Auth::GROUP_ID]);
    $request->request->set('reuseMode', $reuserRules);
  }

  private function prepareDecider(Request &$request) {
    $deciderRules = [];
    if($this->decider->getNomosMonk() === true) {
      $deciderRules[] = 'nomosInMonk';
    }
    if($this->decider->getBulkReused() === true) {
      $deciderRules[] = 'reuseBulk';
    }
    if($this->decider->getNewScanner() === true) {
      $deciderRules[] = 'wipScannerUpdates';
    }
    $request->request->set('deciderRules', $deciderRules);
  }
}
