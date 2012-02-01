<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/**
 * \file common-agent.php
 * \brief These are common functions used by analysis agents.
 *
 * Analysis Agents should register themselves in the menu structure under the
 * top-level "Agents" menu. \n
 *
 * Every analysis agent should have a function called "AgentAdd()" that takes
 * an Upload_pk and an optional array of dependent agents ids. \n
 *
 * Every analysis agent should also have a function called "AgentCheck($uploadpk)"
 * that determines if the agent has already been scheduled. \n
 * This function should return: \n
 * 0 = not scheduled \n
 * 1 = scheduled \n
 * 2 = completed \n
 */

/**
 *
 * \brief Generate a checkbox list of available agents.

 * Only agents that are not already scheduled are added. If
 * $upload_pk == -1, then list all.  User agent preferences will be
 * checked as long as the agent is not already scheduled.
 *
 * \param $upload_pk - upload id
 * \param $SkipAgent - agent not generated in the checkbox list
 *
 * \return string containing formatted checkbox list HTML
 */
function AgentCheckBoxMake($upload_pk,$SkipAgent=NULL) {

  global $Plugins;
  global $PG_CONN;

  $AgentList = menu_find("Agents",$Depth);
  $V = "";

  if (!empty($AgentList)) {
    // get user agent preferences
    $userName = $_SESSION['User'];
    $sql = "SELECT user_name, user_agent_list FROM users WHERE
				    user_name='$userName';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);    
    $uList = pg_fetch_all($result);
    pg_free_result($result);
    // Ulist should never be empty, if it is, something really wrong,
    // like the user_agent_list column is missing.
    if(empty($uList))
    {
      $text = _("Fatal! Query Failed getting user_agent_list for user");
      return("<h3 style='color:red'>$text $UserName</h3>");
    }
    $list = explode(',',$uList[0]['user_agent_list']);

    foreach($AgentList as $AgentItem) {
      $Agent = &$Plugins[plugin_find_id($AgentItem->URI)];
      if (empty($Agent)) {
        continue;
      }
      if ($Agent->Name == $SkipAgent) {
        continue;
      }
      if ($upload_pk != -1) {
        $rc = $Agent->AgentCheck($upload_pk);
      }
      else {
        $rc = 0;
      }
      if ($rc == 0) {
        $Name = htmlentities($Agent->Name);
        $Desc = htmlentities($AgentItem->Name);

        // display user agent preferences

        if(in_array($Name, $list))
        {
          $Selected = " checked ";
        }
        else
        {
          $Selected = "";
        }
        $V .= "<input type='checkbox' name='Check_$Name' value='1' $Selected />$Desc<br />\n";
      }
    }
  }
  return($V);
} // AgentCheckBoxMake()

/**
 * \brief  Assume someone called AgentCheckBoxMake() and submitted the HTML form. \n
 *         Run AgentAdd() for each of the checked agents. \n
 *         Because input comes from the user, validate that everything is legitimate.
 *
 * \param $upload_pk - upload id
 */
function AgentCheckBoxDo($upload_pk)
{
  global $Plugins;
  $AgentList = menu_find("Agents",$Depth);
  $V = "";
  if (!empty($AgentList)) {
    foreach($AgentList as $AgentItem) {
      /*
       The URI below contains the agent name e.g agent_license, this is
       not be confused with the Name attribute in the class, for example,
       the Name attribute for agent_license is: Schedule License Analysis
       */
      $Agent = &$Plugins[plugin_find_id($AgentItem->URI)];
      if (empty($Agent)) {
        continue;
      }
      $rc = $Agent->AgentCheck($upload_pk);
      $Name = htmlentities($Agent->Name);
      $Parm = GetParm("Check_" . $Name,PARM_INTEGER);
      if (($rc == 0) && ($Parm == 1)) {
        $Agent->AgentAdd($upload_pk);
      }
    }
  }
  return($V);
} // AgentCheckBoxDo()


/**
 * \brief find the jobs in the job and jobqueue table to be dependent on
 *
 * \param int $UploadPk the upload PK
 * \param array $list an optional array of jobs to use instead of all jobs
 *        associated with the upload
 * \return array of dependencies
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
  foreach($Jobs as $Row) {
    if($Row['job_name'] == 'Copyright Analysis') {
      $jobList[] = $Row['job_pk'];
    }
    elseif($Row['job_name'] == 'Bucket Analysis')
    {
      $jobList[] = $Row['job_pk'];
    }
    elseif($Row['job_name'] == 'Package Agents')
    {
      $jobList[] = $Row['job_pk'];
    }
    elseif($Row['job_name'] == 'Nomos License Analysis')
    {
      $jobList[] = $Row['job_pk'];
    }
  }

  // get the jq_pk's for each job, retrun the list of jq_pk's
  foreach($jobList as $job)
  {
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
 * \brief, get the agent_pk for a given agent,
 *  This needs to match the C version of same function in libfossagent
 *
 * \param string $agentName the name of the agent e.g. nomos
 * \param string $agentDesc the agent_desc colunm
 *
 * \return -1 or agent_pk
 */
function GetAgentKey($agentName, $agentDesc)
{
  global $PG_CONN;

  /* get the exact agent rec requested */
  $sqlselect = "SELECT agent_pk FROM agent WHERE agent_name ='$agentName' order by agent_ts desc limit 1";
  $result = pg_query($PG_CONN, $sqlselect);
  DBCheckResult($result, $sqlselect, __FILE__, __LINE__);

  if (pg_num_rows($result) == 0)
  {
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
 * \brief
 *  The purpose of this function is to return an array of
 *  _ars records for an agent so that the latest agent_pk(s)
 *  can be determined.
 *
 *  This is for _ars tables only, for example, nomos_ars and bucket_ars.
 *  The _ars tables have a standard format but the specific agent ars table
 *  may have additional fields.
 *
 * \param string  $TableName - name of the ars table (e.g. nomos_ars)
 * \param int     $upload_pk
 * \param int     $limit - limit number of rows returned.  0=No limit, default=1
 * \param int     $agent_fk - ARS table agent_fk, optional
 * \param string  $ExtraWhere - Optional, added to where clause.
 *                   eg: "and bucketpool_fk=2"
 *
 * \return assoc array of _ars records.
 *         or FALSE on error, or no rows
 */
function AgentARSList($TableName, $upload_pk, $limit=1, $agent_fk=0, $ExtraWhere="")
{
  global $PG_CONN;

  $LimitClause = "";
  if ($limit > 0) $LimitClause = " limit $limit";
  if ($agent_fk)
  $agentCond = " and agent_fk='$agent_fk' ";
  else
  $agentCond = "";

  $sql = "SELECT * FROM $TableName, agent
           WHERE agent_pk=agent_fk and ars_success=true and upload_fk='$upload_pk' and agent_enabled=true
           $agentCond $ExtraWhere
           order by agent_ts desc $LimitClause";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $resultArray =  pg_fetch_all($result);
  pg_free_result($result);
  return $resultArray;
}


/**
 * \brief Given an upload_pk, find the latest enabled agent_pk with results.
 *
 * \param $upload_pk - upload id
 * \param $arsTableName - name of ars table to check for the requested agent
 *
 * \returns nomos agent_pk or 0 if none
 */
function LatestAgentpk($upload_pk, $arsTableName)
{
  $AgentRec = AgentARSList($arsTableName, $upload_pk, 1);
  if ($AgentRec === false)
    $Agent_pk = 0;
  else
    $Agent_pk = $AgentRec[0]['agent_fk'];
  return $Agent_pk;
}


/**
 * \brief
 *  The purpose of this function is to return a pulldown select list for users
 *  to be able to select the dataset results they want to see.
 *
 *  This is for _ars tables only, for example, nomos_ars and bucket_ars.
 *  The _ars tables have a standard format with optional agent_fk's named
 *  agent_fk2, agent_fk3, ...
 *
 * \param string  $TableName - name of the ars table (e.g. nomos_ars)
 * \param int     $upload_pk
 * \param boolean $DataOnly  - If false, return the latest agent AND agent revs
 *                             that have data for this agent.  Note the latest agent may have
 *                             no entries in $TableName.
 *                             If true (default), return only the agent_revs with ars data.
 * \param string  $SLName    - select list element name
 * \param string  $SLID      - select list element id
 * \param string  $SelectedKey - selected key (optional)
 *                              If absent and $DataOnly is true
 *                              then the latest agent with results is selected.
 *                              If absent and $DataOnly is false
 *                              then the latest agent is selected.
 *
 * \return agent select list
 *      or 0 on error
 */
function AgentSelect($TableName, $upload_pk, $DataOnly=true,
$SLName, $SLID, $SelectedKey="")
{
  echo "DO NOT USE: PRELIMINARY AND INCOMPLETE<br>";
  /*
   / get the agent recs /
   $sql = "SELECT * FROM $TableName, agent
   WHERE upload_fk='$upload_pk' and agent_enabled=true order by agent_ts desc";
   $result = pg_query($PG_CONN, $sql);
   DBCheckResult($result, $sql, __FILE__, __LINE__);

   / Create an assoc array to build the select list from.
   * "{agent_pk} [,{agent_pk} ...] => {agent name} ( {agentrevision} ), ... [ NO DATA]
   * For example:
   *   123 => nomos(rev 1)
   *   111,123 => bucket(rev 5), nomos(rev 7)
   *   112,179 => bucket(latest rev), nomos(latest rev) NO DATA
   or one could just use the ars_pk instead of the pk list, but that is another
   indirection.
   /
   while ($row = pg_fetch_assoc($result))
   {
   }
   $AgentList = GetAgentDataList($AgentName, $upload_pk, $tablename);
   $SelArray = array();

   if ($SelectedKey == "") $SelectedKey = $AgentList[0]['agent_pk'];

   / create key/val array for pulldown /
   foreach($AgentList as $AgentRec)
   {
   $DataInd = ($AgentRec['data']) ? "" : ", NO DATA";
   $SelArray[$AgentRec['agent_pk']] = "$AgentRec[agent_name] rev: $AgentRec[agent_rev]$DataInd";
   }
   return "Results from:" . Array2SingleSelect($SelArray, $SLName, $SelectedKey, false, false);
   */
}


/**
 * \brief read the UI form and format the user selected agents into a
 * comma separated list
 *
 * \return string $agentsChecked list of checked agents
 */
function userAgents()
{
  global $Plugins;
  global $PG_CONN;

  $agentsChecked = "";

  $AgentList = menu_find("Agents",$Depth);
  if (!empty($AgentList)) {
    foreach($AgentList as $AgentItem) {
      /*
       The URI below contains the agent name e.g agent_license, this is
       not be confused with the Name attribute in the class, for example,
       the Name attribute for agent_license is: Schedule License Analysis
       */
      $Agent = &$Plugins[plugin_find_id($AgentItem->URI)];
      if (empty($Agent)) {
        continue;
      }
      $Name = htmlentities($Agent->Name);
      $Parm = GetParm("Check_" . $Name,PARM_INTEGER);
      if ($Parm == 1) {
        // save the name
        $agentsChecked .= $Name . ',';
      }
    }
    // remove , from last name
    $agentsChecked = trim($agentsChecked, ',');
  }
  return($agentsChecked);
}

?>
