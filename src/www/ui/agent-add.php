<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_agent_add", _("Schedule an Analysis"));

/**
 * \class agent_add extend from FO_Plugin
 * \brief 
 */
class agent_add extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "agent_add";
    $this->Title      = TITLE_agent_add;
    $this->MenuList   = "Jobs::Schedule Agents";
    $this->Version    = "1.1";
    $this->DBaccess   = PLUGIN_DB_WRITE;
    parent::__construct();
  }

    /**
   * \brief This function checks if the current job was already scheduled, but did not yet run (You can reschedule failed jobs)
   * 
   * \param $agentName   Name of the agent as specified in the agents table
   * \param $upload_pk   Upload identifier
   * \return true if the agent is currently scheduled for this upload and did not yet run, else false
   */
  function jobAlreadyScheduled( $agentName ,  $upload_pk )
  {
    global $PG_CONN;
    $sql = "select * from job inner join jobqueue on job_pk=jq_job_fk where job_upload_fk=$upload_pk and jq_endtext is null and jq_type='$agentName'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $nrows=pg_num_rows($result);
    pg_free_result($result);
    return !($nrows<1);
  }
  
  /**
   * \brief Add an upload to multiple agents.
   *
   * \param $uploadpk 
   * \param $agentlist - list of agents
   * \return NULL on success, error message string on failure
   */
  private function AgentsAdd($uploadpk, $agentlist)
  {
    global $Plugins;
    global $PG_CONN;
    global $SysConf;

    $rc="";

    /* Make sure the uploadpk is valid */
    if (!$uploadpk) return "agent-add.php AgentsAdd(): No upload_pk specified";
    $sql = "SELECT upload_pk, upload_filename FROM upload WHERE upload_pk = '$uploadpk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      $ErrMsg = __FILE__ . ":" . __LINE__ . " " . _("Upload") . " " . $uploadpk . " " .  _("not found");
      return($ErrMsg);
    }
    $UploadRow = pg_fetch_assoc($result);
    $ShortName = $UploadRow['upload_filename'];
    pg_free_result($result);

    /* Create Job */
    $user_pk = $SysConf['auth']['UserId'];
    $group_pk = $SysConf['auth']['GroupId'];
    $job_pk = JobAddJob($user_pk, $group_pk, $ShortName, $uploadpk);

    /* Validate the agent list and add agents as needed. */
    /** Don't worry about order or duplicates -- it will do the right thing. **/
    $depth=0;
    $agent_list = menu_find("Agents", $depth);
    for($al=0; !empty($agentlist[$al]); $al++)
    {
      /* check if the agent exists in the list of viable agents */
      $Found = -1;
      for($ac=0; ($Found < 0) && !empty($agent_list[$ac]->URI); $ac++)
      {
        if (!strcmp($agent_list[$ac]->URI,$agentlist[$al]))
        {
          $Found = $al;
          break;
        }
      }
      if ($Found >= 0)
      {
        //print "Adding to " . $agentlist[$Found] . "<br>\n";
        $Dependencies = array();
        $P = &$Plugins[plugin_find_id($agentlist[$Found])];
        if ($this->jobAlreadyScheduled($P->AgentName,$uploadpk ) ) {
          continue;
        }
        $rv = $P->AgentAdd($job_pk, $uploadpk, $ErrorMsg, $Dependencies);
        if ($rv == -1) $rc .= $ErrorMsg;
      }
      else
      {
        $rc .= "Agent '" . htmlentities($agentlist[$al]) . "' not found.\n";
      }
    }
    return($rc);
  } // AgentsAdd()

  /**
   * \brief Generate the text for this plugin.
   */
  protected function htmlContent()
  {
    $V="";
    /* If this is a POST, then process the request. */
    $Folder = GetParm('folder',PARM_INTEGER);
    if (empty($Folder)) {
      $Folder = FolderGetTop();
    }
    $uploadpk = GetParm('upload',PARM_INTEGER);
    $agents = array_key_exists('agents', $_REQUEST) ? $_REQUEST['agents'] : '';

    if (!empty($uploadpk) && !empty($agents) && is_array($agents))
    {
      $rc = $this->AgentsAdd($uploadpk,$agents);
      if (empty($rc))
      {
        /** check if the scheudler is running */
        $status = GetRunnableJobList();
        $scheduler_msg = "";
        if (empty($status))
        {
          $scheduler_msg .= _("Is the scheduler running? ");
        }

        $URL = Traceback_uri() . "?mod=showjobs&upload=$uploadpk ";
        /* Need to refresh the screen */
        $text = _("Your jobs have been added to job queue.");
        $LinkText = _("View Jobs");
        $msg = "$scheduler_msg"."$text <a href=$URL>$LinkText</a>";
        $this->vars['message'] = $msg;
      }
      else
      {
        $text = _("Scheduling of Agent(s) failed: ");
        $this->vars['message'] = $text.$rc;
      }
    }

    /**
     * Create the AJAX (Active HTTP) javascript for doing the reply
     * and showing the response. 
     */
    $V .= ActiveHTTPscript("Uploads");
    $V .= "<script language='javascript'>\n";
    $V .= "function Uploads_Reply()\n";
    $V .= "  {\n";
    $V .= "  if ((Uploads.readyState==4) && (Uploads.status==200))\n";
    $V .= "    {\n";
    $V .= "    document.getElementById('uploaddiv').innerHTML = '<select size=\'10\' name=\'upload\' onChange=\'Agents_Get(\"" . Traceback_uri() . "?mod=upload_agent_options&upload=\" + this.value)\'>' + Uploads.responseText + '</select><P />';\n";
    $V .= "    document.getElementById('agentsdiv').innerHTML = '';\n";
    $V .= "    }\n";
    $V .= "  }\n";
    $V .= "</script>\n";

    $V .= ActiveHTTPscript("Agents");
    $V .= "<script language='javascript'>\n";
    $V .= "function Agents_Reply()\n";
    $V .= "  {\n";
    $V .= "  if ((Agents.readyState==4) && (Agents.status==200))\n";
    $V .= "    {\n";
    $V .= "    document.getElementById('agentsdiv').innerHTML = '<select multiple size=\'10\' id=\'agents\' name=\'agents[]\'>' + Agents.responseText + '</select>';\n";
    $V .= "    }\n";
    $V .= "  }\n";
    $V .= "</script>\n";

    /*************************************************************/
    /* Display the form */
    $V .= "<form name='formy' method='post'>\n"; // no url = this url
    $V .= _("Select an uploaded file for additional analysis.\n");

    $V .= "<ol>\n";
    $text = _("Select the folder containing the upload you wish to analyze:");
    $V .= "<li>$text<br>\n";
    $V .= "<select name='folder'\n";
    $V .= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=$Folder' ";
    $V .= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)'>\n";
    $V .= FolderListOption(-1,0,1,$Folder);
    $V .= "</select><P />\n";

    $text = _("Select the upload to analyze:");
    $V .= "<li>$text<br>";
    $V .= "<div id='uploaddiv'>\n";
    $V .= "<select size='10' name='upload' onChange='Agents_Get(\"" . Traceback_uri() . "?mod=upload_agent_options&upload=\" + this.value)'>\n";
    $List = FolderListUploads_perm($Folder, PERM_WRITE);
    foreach($List as $L)
    {
      $isSelected = (!empty($uploadpk) && $L['upload_pk']==$uploadpk) ? " selected='selected'" : '';
      $V .= "<option value='" . $L['upload_pk'] . "'$isSelected>";
      $V .= htmlentities($L['name']);
      if (!empty($L['upload_desc']))
      {
        $V .= " (" . htmlentities($L['upload_desc']) . ")";
      }
      $V .= "</option>\n";
    }
    $V .= "</select><P />\n";
    $V .= "</div>\n";
    $text = _("Select additional analysis.");
    $V .= "<li>$text<br>\n";
    $V .= "<div id='agentsdiv'>\n";
    $V .= "<select multiple size='10' id='agents' name='agents[]'></select>\n";
    $V .= "</div>\n";
    $V .= "</ol>\n";

    if ($uploadpk)
    {
      $V .= "<script language='javascript'>\n";
      $V .= "Agents_Get(\"" . Traceback_uri() . "?mod=upload_agent_options&upload=$uploadpk\");</script>";
    }

    $text = _("Analyze");
    $V .= "<input type='submit' value='$text!'>\n";
    $V .= "</form>\n";

    return $V;
  }
}
$NewPlugin = new agent_add;
