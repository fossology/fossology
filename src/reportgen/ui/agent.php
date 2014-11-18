<?php
/*
 Copyright (C) 2014, Siemens AG
 Author: Daniele Fognini

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
 */

define("TITLE_agent_reportgen", _("Report Generator"));

class agent_reportgen extends FO_Plugin
{
  private $dbManager;

  function __construct()
  {
    $this->Name = "agent_reportgen";
    $this->Title = TITLE_agent_reportgen;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->AgentName = "reportgen"; // agent.agent_name
    parent::__construct();

    $this->vars['jqPk'] = -1;
    $this->vars['downloadLink'] = "";

    global $container;
    $this->dbManager = $container->get('db.manager');
  }

  /**
   * \brief Register copyright agent in "Agents" menu
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) return (0);
    //do not advertise this agent: it can be scheduled only from directly
    //menu_insert("Agents::" . $this->Title, 0, $this->Name);

    $text = _("Generate Report");
    menu_insert("Browse-Pfile::Generate Word Report", 0, $this->Name, $text);
  }

  /**
   * \brief Check if the upload has already been successfully scanned.
   *
   * \param $upload_pk
   *
   * \returns:
   * - 0 = no
   * - 1 = yes, from latest agent version
   * - 2 = yes, from older agent version
   **/
  function AgentHasResults($upload_pk)
  {
    return 0; /* this agent can be re run multiple times */
  } // AgentHasResults()

  /**
   * \brief Queue the ip agent.
   *  Before queuing, check if agent needs to be queued.  It doesn't need to be queued if:
   *  - It is already queued
   *  - It has already been run by the latest agent version
   *
   * \param $job_pk
   * \param $upload_pk
   * \param $ErrorMsg - error message on failure
   * \param $Dependencies - array of plugin names representing dependencies.
   *        This is for dependencies that this plugin cannot know about ahead of time.
   *
   * \returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   **/
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies)
  {
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies);
  } // AgentAdd()

  function htmlContent()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }

    global $SysConf;
    $user_pk = $SysConf['auth']['UserId'];
    $group_pk = $SysConf['auth']['GroupId'];

    $uploadId = GetParm('upload', PARM_INTEGER);
    if ($uploadId <=0)
      return _("parameter error");

    if (GetUploadPerm($uploadId) < PERM_WRITE)
      return _("permission denied");

    $row = $this->dbManager->getSingleRow(
      "SELECT upload_filename FROM upload WHERE upload_pk = $1",
      array($uploadId), "getUploadName"
    );

    if ($row === false)
      return _("cannot find uploadId");

    $ShortName = $row['upload_filename'];

    $job_pk = JobAddJob($user_pk, $group_pk, $ShortName, $uploadId);
    $error = "";
    $jq_pk = $this->AgentAdd($job_pk, $uploadId, $error, array());

    if ($jq_pk<0)
    {
      return _("Cannot schedule").": ".$error;
    }

    $this->vars['jqPk'] = $jq_pk;
    $this->vars['downloadLink'] = Traceback_uri(). "?mod=download&report=".$job_pk;

    $text = sprintf(_("Generating new report for '%s'"), $ShortName);
    return "<h2>".$text."</h2>";
  }

  public function getTemplateName()
  {
    return "report.html.twig";
  }

}

$NewPlugin = new agent_reportgen;

