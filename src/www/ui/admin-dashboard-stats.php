<?php
/*
 SPDX-FileCopyrightText: © 2019 Orange

 SPDX-License-Identifier: GPL-2.0-only
*/

define("TITLE_DASHBOARD_STATISTICS", _("Statistics Dashboard"));

use Fossology\Lib\Db\DbManager;

class dashboardReporting extends FO_Plugin
{
  protected $pgVersion;

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name       = "dashboard-statistics";
    $this->Title      = TITLE_DASHBOARD_STATISTICS;
    $this->MenuList   = "Admin::Dashboards::Statistics";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Lists number of ever quequed jobs per job type (agent)..
   */
  function CountAllJobs($fromRest = false)
  {
    $query = "SELECT ag.agent_name,ag.agent_desc,count(jq.*) AS fired_jobs ";
    $query.= "FROM agent ag LEFT OUTER JOIN jobqueue jq ON (jq.jq_type = ag.agent_name) ";
    $query.= "GROUP BY ag.agent_name,ag.agent_desc ORDER BY fired_jobs DESC;";
    $restRes = [];
    $rows = $this->dbManager->getRows($query);

    $V = "<table border=1>";
    $V .= "<tr><th>".("AgentName")."</th><th>"._("Description")."</th><th>"._("Number of jobs")."</th></tr>";

    foreach ($rows as $agData) {
      $V .= "<tr><td>".$agData['agent_name']."</td><td>".$agData['agent_desc']."</td><td align='right'>".$agData['fired_jobs']."</td></tr>";
      $restRes[] = [
        'agentName' => $agData['agent_name'],
        'agentDesc' => $agData['agent_desc'],
        'firedJobs' => intval($agData['fired_jobs']),
      ];
    }

    $V .= "</table>";

    if ($fromRest) {
      return $restRes;
    }
    return $V;
  }

  public function Output()
  {
      $V = "<h1> Statistics </h1>";
      $V .= "<table style='width: 100%;' border=0>\n";

      $V .= "<tr>";
      $V .= "<td class='dashboard'>";
      $text = _("Jobs Sumary");
      $V .= "<h2>$text</h2>\n";
      $V .= $this->CountAllJobs();
      $V .= "</td>";
      $V .= "</tr>";

      $V .= "</table>";

      return $V;
  }
}

$dash = new dashboardReporting ;
$dash->Initialize();
