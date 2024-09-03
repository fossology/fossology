<?php
/*
 SPDX-FileCopyrightText: © 2008-2012 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2018 Siemens AG

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * \file
 * \brief These are common functions used by analysis agents.
 *
 * Analysis Agents should register themselves in the menu structure under the
 * top-level "Agents" menu.
 *
 * Every analysis agent should have a function called "AgentAdd()" that takes
 * an Upload_pk and an optional array of dependent agents ids.
 *
 * This function should return: \n
 * 0 = not scheduled \n
 * 1 = scheduled \n
 * 2 = completed \n
 */

/**
 *
 * \brief Generate a checkbox list of available agents.
 *
 * Only agents that are not already scheduled are added. If
 * $upload_pk == -1, then list all.  User agent preferences will be
 * checked as long as the agent is not already scheduled.
 *
 * \param int $upload_pk    Upload id
 * \param array $SkipAgents Array of agent names to omit from the checkboxes
 * \param string $specified_username If not empty, use the specified username
 * instead of username stored in session.
 *
 * \return string containing formatted checkbox list HTML
 */
function AgentCheckBoxMake($upload_pk,$SkipAgents=array(), $specified_username = "")
{

  global $Plugins;
  global $PG_CONN;

  $AgentList = menu_find("Agents",$Depth);
  $V = "";

  if (!empty($AgentList)) {
    // get user agent preferences
    $userName = $_SESSION['User'];
    if (!empty($specified_username)) {
      $userName = $specified_username;
    }
    $sql = "SELECT user_name, user_agent_list, default_bucketpool_fk FROM users WHERE
				    user_name='$userName';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $uList = pg_fetch_all($result);
    pg_free_result($result);
    // Ulist should never be empty, if it is, something really wrong,
    // like the user_agent_list column is missing.
    if (empty($uList)) {
      $text = _("Fatal! Query Failed getting user_agent_list for user");
      return("<h3 style='color:red'>$text $userName</h3>");
    }
    $list = explode(',',$uList[0]['user_agent_list']);
    $default_bucketpool_fk = $uList[0]['default_bucketpool_fk'];
    if (empty($default_bucketpool_fk)) {
      $SkipAgents[] = "agent_bucket";
    }

    foreach ($AgentList as $AgentItem) {
      $Agent = &$Plugins[plugin_find_id($AgentItem->URI)];
      if (empty($Agent)) {
        continue;
      }

      // ignore agents to skip from list
      $FoundSkip = false;
      foreach ($SkipAgents as $SkipAgent) {
        if ($Agent->Name == $SkipAgent) {
          $FoundSkip = true;
          break;
        }
      }
      if ($FoundSkip) {
        continue;
      }

      if ($upload_pk != -1) {
        $rc = $Agent->AgentHasResults($upload_pk);
      } else {
        $rc = 0;
      }
      if ($rc != 1) {
        $Name = htmlentities($Agent->Name);
        $Desc = $AgentItem->Name;

        // display user agent preferences

        if (in_array($Name, $list)) {
          $Selected = " checked ";
        } else {
          $Selected = "";
        }
        $V .= "<input type='checkbox' class='browse-upload-checkbox view-license-rc-size' name='Check_$Name' value='1' $Selected /> $Desc<br />\n";
      }
    }
  }
  return($V);
} // AgentCheckBoxMake()

/**
 * \brief  Assume someone called AgentCheckBoxMake() and submitted the HTML form.
 *         Run AgentAdd() for each of the checked agents.
 *
 * \param int $job_pk    Job ID
 * \param int $upload_pk Upload ID
 */
function AgentCheckBoxDo($job_pk, $upload_pk)
{
  $agents = checkedAgents();
  return AgentSchedule($job_pk, $upload_pk, $agents);
}


/**
 * \brief Schedule all given agents
 *
 * \param int $jobId
 * \param int $uploadId
 * \param array $agents Array of agent plugin, mapped by name as in listAgents()
 *
 * \return null|string null on success or error message [sic]
 */
function AgentSchedule($jobId, $uploadId, $agents)
{
  $errorMsg = "";
  foreach ($agents as &$agent) {
    $rv = $agent->AgentAdd($jobId, $uploadId, $errorMsg, array());
    if ($rv == -1) {
      return $errorMsg;
    }
  }
  return null;
}

/**
 * \brief Find the jobs in the job and jobqueue table to be dependent on
 *
 * \param int $UploadPk The upload PK
 * \param array $list An optional array of jobs to use instead of all jobs
 *        associated with the upload
 * \return Array of dependencies
 */
function FindDependent($UploadPk, $list=NULL)
{
  /*
   * Find the jobs that fo_notify should depend on. fo_notify is
   * dependent on the following agents:
   *   copyright
   *   nomos
   *   package
   *   bucket
   *
   *   Determine if the above agents are scheduled and create a list of
   *   jq_pk for each agent.
   *
   */
  global $PG_CONN;

  $Depends = array();
  /* get job list for this upload */

  // get the list of jobs for this upload
  $sql = "SELECT job_upload_fk, job_pk, job_name FROM job WHERE " .
  "job_upload_fk = $UploadPk order by job_pk desc;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $Jobs = pg_fetch_all($result);
  pg_free_result($result);

  $jobList = array();
  foreach ($Jobs as $Row) {
    if ($Row['job_name'] == 'Copyright Analysis') {
      $jobList[] = $Row['job_pk'];
    } elseif ($Row['job_name'] == 'Bucket Analysis') {
      $jobList[] = $Row['job_pk'];
    } elseif ($Row['job_name'] == 'Package Agents') {
      $jobList[] = $Row['job_pk'];
    } elseif ($Row['job_name'] == 'Nomos License Analysis') {
      $jobList[] = $Row['job_pk'];
    }
  }

  // get the jq_pk's for each job, retrun the list of jq_pk's
  foreach ($jobList as $job) {
    $sql = "SELECT jq_pk, jq_job_fk FROM jobqueue WHERE jq_job_fk = $job " .
                     " order by jq_pk desc;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Q = pg_fetch_all($result);
    pg_free_result($result);
    $Depends[] = $Q[0]['jq_pk'];
  }
  return($Depends);
} // FindDependent

/**
 * \brief Get the latest enabled agent_pk for a given agent.
 *
 * This needs to match the C version of same function in libfossagent
 * This will create an agent record if one doesn't already exist.
 *
 * \param string $agentName The name of the agent e.g. nomos
 * \param string $agentDesc The agent_desc colunm
 *
 * \todo When creating an agent record, set the agent_rev.
 *
 * \return -1 or agent_pk
 */
function GetAgentKey($agentName, $agentDesc)
{
  global $PG_CONN;

  /* get the exact agent rec requested */
  $sqlselect = "SELECT agent_pk FROM agent WHERE agent_name ='$agentName' "
             . "and agent_enabled='true' order by agent_ts desc limit 1";
  $result = pg_query($PG_CONN, $sqlselect);
  DBCheckResult($result, $sqlselect, __FILE__, __LINE__);

  if (pg_num_rows($result) == 0) {
    /* no match, so add an agent rec */
    $sql = "INSERT INTO agent (agent_name,agent_desc,agent_enabled) VALUES ('$agentName',E'$agentDesc',1)";
    $result = pg_query($PG_CONN, $sqlselect);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    /* get inserted agent_pk */
    $result = pg_query($PG_CONN, $sqlselect);
    DBCheckResult($result, $sqlselect, __FILE__, __LINE__);
  }

  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  return $row["agent_pk"];

} // GetAgentKey


/**
 * \deprecated Use AgentesDao->agentARSList
 *
 *  The purpose of this function is to return an array of
 *  _ars records for an agent so that the latest agent_pk(s)
 *  can be determined.
 *
 *  This is for _ars tables only, for example, nomos_ars and bucket_ars.
 *  The _ars tables have a standard format but the specific agent ars table
 *  may have additional fields.
 *
 * \param string  $TableName Name of the ars table (e.g. nomos_ars)
 * \param int     $upload_pk
 * \param int     $limit Limit number of rows returned.  0=No limit, default=1
 * \param int     $agent_fk ARS table agent_fk, optional
 * \param string  $ExtraWhere Optional, added to where clause.
 *                   eg: "and bucketpool_fk=2"
 *
 * \return Assoc array of _ars records.
 *         or FALSE on error, or no rows
 */
function AgentARSList($TableName, $upload_pk, $limit=1, $agent_fk=0, $ExtraWhere="")
{
  global $PG_CONN;
  global $container;
  /** @var AgentDao $agentDao */
  $agentDao = $container->get('dao.agent');
  return $agentDao->agentARSList($TableName, $upload_pk, $limit, $agent_fk, $ExtraWhere);
}


/**
 * \brief Given an upload_pk, find the latest enabled agent_pk with results.
 *
 * \param int    $upload_pk Upload id
 * \param string $arsTableName Name of ars table to check for the requested agent
 * \param bool   $arsSuccess Need only success results?
 *
 * \returns Nomos agent_pk or 0 if none
 */
function LatestAgentpk($upload_pk, $arsTableName, $arsSuccess = false)
{
  $AgentRec = AgentARSList($arsTableName, $upload_pk, 1, 0, $arsSuccess);

  if (empty($AgentRec)) {
    $Agent_pk = 0;
  } else {
    $Agent_pk = intval($AgentRec[0]['agent_fk']);
  }
  return $Agent_pk;
}


/**
 *  The purpose of this function is to return a pulldown select list for users
 *  to be able to select the dataset results they want to see.
 *
 *  This is for _ars tables only, for example, nomos_ars and bucket_ars.
 *  The _ars tables have a standard format with optional agent_fk's named
 *  agent_fk2, agent_fk3, ...
 *
 * \param string  $TableName Name of the ars table (e.g. nomos_ars)
 * \param int     $upload_pk
 * \param string  $SLName    Select list element name
 * \param string  &$agent_pk Return which agent is selected
 * \param string  $extra     Extra info for the select element, e.g. "onclick=..."
 *
 * \return Agent select list, when only one data, return null
 */
function AgentSelect($TableName, $upload_pk, $SLName, &$agent_pk, $extra = "")
{
  global $PG_CONN;
  /* get the agent recs */
  $TableName .= '_ars';
  $sql = "select agent_pk, agent_name, agent_rev from agent, $TableName where "
       . "agent.agent_pk = $TableName.agent_fk and upload_fk = $upload_pk order by agent_rev DESC";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  $NumRows = pg_num_rows($result);
  if ($NumRows == 1) { // only one result
    pg_free_result($result);
    return;  /* only one result */
  }

  $select = "<select name='$SLName' id='$SLName' $extra>";
  while ($row = pg_fetch_assoc($result)) {
    $select .= "<option value='$row[agent_pk]'";

    if (empty($agent_pk)) {
      $select .= " SELECTED ";
      $agent_pk = $row["agent_pk"];
    } else if (in_array($row['agent_pk'], $agent_pk)) {
      $select .= " SELECTED ";
    }

    $select .= ">$row[agent_name], v $row[agent_rev]\n";
  }
  $select .= "</select>";
  pg_free_result($result);
  return $select;
}


/**
 * \brief Read the UI form and format the user selected agents into a
 * comma separated list
 *
 * \return String $agentsChecked list of checked agents
 */
function userAgents($agents=null)
{
  return implode(',', array_keys(checkedAgents($agents)));
}

/**
 * \brief read the UI form and return array of user selected agents
 *        Because input comes from the user, validate that everything is legitimate.
 *
 * \return Plugin[] list of checked agent plugins, mapped by name
 */
function checkedAgents($agents=null)
{
  $agentsChecked = array();
  $agentList = listAgents();
  foreach ($agentList as $agentName => &$agentPlugin) {
    if (is_null($agents)) {
      if (GetParm("Check_" . $agentName, PARM_INTEGER) == 1) {
        $agentsChecked[$agentName] = &$agentPlugin;
      }
    } else {
      if ($agents["Check_" . $agentName] == 1) {
        $agentsChecked[$agentName] = &$agentPlugin;
      }
    }
  }
  unset($agentPlugin);

  return $agentsChecked;
}

/**
 * \brief Search in available plugins and return all agents
 *
 * \return Plugin[] list of checked agent plugins, mapped by name
 */
function listAgents()
{
  $agents = array();

  $agentList = menu_find("Agents",$Depth);
  if (!empty($agentList)) {
    foreach ($agentList as $agentItem) {
      /*
       The URI below contains the agent name e.g agent_license, this is
       not be confused with the Name attribute in the class, for example,
       the Name attribute for agent_license is: Schedule License Analysis
       */
      $agentPlugin = plugin_find($agentItem->URI);
      if (empty($agentPlugin)) {
        continue;
      }
      $name = htmlentities($agentPlugin->Name);
      $agents[$name] = $agentPlugin;
    }
  }
  return $agents;
}
/**
 * \brief Check the ARS table to see if an agent has successfully scanned an upload.
 *
 * \param int    $upload_pk The upload will be checked
 * \param string $AgentName Agent name, eg "nomos"
 * \param string $AgentDesc Agent description, eg "license scanner"
 * \param string $AgentARSTableName Agent ars table name, eg "nomos_ars"
 *
 * \returns
 * - 0 = no
 * - 1 = yes, from latest agent version
 * - 2 = yes, from older agent version (does not apply to adj2nest)
 */
function CheckARS($upload_pk, $AgentName, $AgentDesc, $AgentARSTableName)
{
  /* get the latest agent_pk */
  $Latest_agent_pk = GetAgentKey($AgentName, $AgentDesc);

  /* get last agent pk with successful results */
  $Last_successful_agent_pk = LatestAgentpk($upload_pk, $AgentARSTableName, true);

  if (! empty($Latest_agent_pk) && ! empty($Last_successful_agent_pk)) {
    if ($Latest_agent_pk == $Last_successful_agent_pk) {
      return 1;
    } else {
      return 2;
    }
  }

  return 0;
} // CheckARS()
