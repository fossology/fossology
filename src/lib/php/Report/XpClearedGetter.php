<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG
 Author: Daniele Fognini

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Report;

use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Proxy\ScanJobProxy;

class XpClearedGetter extends ClearedGetterCommon
{
  /** @var CopyrightDao */
  private $copyrightDao;

  protected $tableName;
  protected $type;
  protected $getOnlyCleared;
  protected $extrawhere;

  public function __construct($tableName, $type=null, $getOnlyCleared=false, $extraWhere=null)
  {
    global $container;

    $this->copyrightDao = $container->get('dao.copyright');

    $this->getOnlyCleared = $getOnlyCleared;
    $this->type = $type;
    $this->tableName = $tableName;
    $this->extrawhere = $extraWhere;
    parent::__construct();
  }

  protected function getStatements($uploadId, $uploadTreeTableName, $groupId = null)
  {
    $agentName = $this->tableName;
    $scanJobProxy = new ScanJobProxy($GLOBALS['container']->get('dao.agent'), $uploadId);
    if ($agentName == "copyright") {
      $scanJobProxy->createAgentStatus(array($agentName, 'reso'));
    } else {
      $scanJobProxy->createAgentStatus(array($agentName));
    }
    $selectedScanners = $scanJobProxy->getLatestSuccessfulAgentIds();
    if (!array_key_exists($agentName, $selectedScanners)) {
      return array();
    }
    $latestXpAgentId[] = $selectedScanners[$agentName];
    if (array_key_exists('reso', $selectedScanners)) {
      $latestXpAgentId[] = $selectedScanners['reso'];
    }
    $ids = implode(',', $latestXpAgentId);
    if (!empty($this->extrawhere)) {
      $this->extrawhere .= ' AND';
    }
    $this->extrawhere .= ' agent_fk IN ('.$ids.')';

    $result = $this->copyrightDao->getAllEntriesReport($this->tableName, $uploadId, $uploadTreeTableName, $this->type, $this->getOnlyCleared, DecisionTypes::IDENTIFIED, $this->extrawhere, $groupId);
    $this->extrawhere = null;
    return $result;
  }
}
