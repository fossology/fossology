<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

class agent_add extends FO_Plugin
{
  public $Name       = "agent_add";
  public $Title      = "Schedule an Analysis";
  public $MenuList   = "Jobs::Agents";
  public $Version    = "1.1";
  public $Dependency = array("db");
  public $DBaccess   = PLUGIN_DB_ANALYZE;

  /*********************************************
   AgentsAdd(): Add an upload to multiple agents.
   *********************************************/
  function AgentsAdd	($uploadpk, $agentlist)
  {
    $rc="";
    $Alist = array();

    /* Make sure the uploadpk is valid */
    global $Plugins;
    global $DB;
    $Results = $DB->Action("SELECT upload_pk FROM upload WHERE upload_pk = '$upload_pk';");
    if ($Results[0]['upload_pk'] != $upload_pk)
    {
      return("Upload not found.");
    }

    /* Validate the agent list and add agents as needed. */
    /** Don't worry about order or duplicates -- it will do the right thing. **/
    $Depth=0;
    $agent_list = menu_find("Agents", $depth);
    for($al=0; !empty($agentlist[$al]); $al++)
    {
      /* check if the agent exists in the list of viable agents */
      $Found = -1;
      for($ac=0; ($Found < 0) && !empty($agent_list[$ac]->URI); $ac++)
      {
        if (!strcmp($agent_list[$ac]->URI,$agentlist[$al])) { $Found = $al; }
      }
      if ($Found >= 0)
      {
        //print "Adding to " . $agentlist[$Found] . "<br>\n";
        $P = &$Plugins[plugin_find_id($agentlist[$Found])];
        $P->AgentAdd($uploadpk);
        $Alist[] = $agentlist[$Found];
      }
      else
      {
        $rc .= "Agent '" . htmlentities($agentlist[$al]) . "' not found.\n";
      }
    }
    if (CheckEnotification()) {
      /* Create list for dependency checking by enotification */
      $sched = scheduleEmailNotification($uploadpk,NULL,NULL,NULL,$Alist,TRUE);
      if ($sched !== NULL) {
        return($sched);
      }
    }
    return($rc);
  } // AgentsAdd()

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $DB;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $Folder = GetParm('folder',PARM_INTEGER);
        if (empty($Folder)) { $Folder = FolderGetTop(); }
        $uploadpk = GetParm('upload',PARM_INTEGER);
        $agents = $_POST['agents'];
        if (!empty($uploadpk) && !empty($agents) && is_array($agents))
        {
          $rc = $this->AgentsAdd($uploadpk,$agents);
          if (empty($rc))
          {
            /* Need to refresh the screen */
            $V .= PopupAlert('Analysis added to job queue');
          }
          else
          {
            $V .= PopupAlert("Scheduling failed: $rc");
          }
        }

        /*************************************************************/
        /* Create the AJAX (Active HTTP) javascript for doing the reply
         and showing the response. */
        $V .= ActiveHTTPscript("Uploads");
        $V .= "<script language='javascript'>\n";
        $V .= "function Uploads_Reply()\n";
        $V .= "  {\n";
        $V .= "  if ((Uploads.readyState==4) && (Uploads.status==200))\n";
        $V .= "    {\n";
        /* Remove all options */
        $V .= "    document.formy.upload.innerHTML = Uploads.responseText;\n";
        $V .= "    document.getElementById('agents').innerHTML = '';\n";
        /* Add new options */
        $V .= "    }\n";
        $V .= "  }\n";
        $V .= "</script>\n";

        $V .= ActiveHTTPscript("Agents");
        $V .= "<script language='javascript'>\n";
        $V .= "function Agents_Reply()\n";
        $V .= "  {\n";
        $V .= "  if ((Agents.readyState==4) && (Agents.status==200))\n";
        $V .= "    {\n";
        /* Remove all options */
        $V .= "    document.getElementById('agents').innerHTML = Agents.responseText;\n";
        /* Add new options */
        $V .= "    }\n";
        $V .= "  }\n";
        $V .= "</script>\n";

        /*************************************************************/
        /* Display the form */
        $V .= "<form name='formy' method='post'>\n"; // no url = this url
        $V .= "Select an uploaded file for additional analysis.\n";

        $V .= "<ol>\n";
        $V .= "<li>Select the folder containing the upload you wish to analyze:<br>\n";
        $V .= "<select name='folder'\n";
        $V .= "onLoad='Uploads_Get((\"" . Traceback_uri() . "?mod=upload_options&folder=$Folder' ";
        $V .= "onChange='Uploads_Get(\"" . Traceback_uri() . "?mod=upload_options&folder=\" + this.value)'>\n";
        $V .= FolderListOption(-1,0,1,$Folder);
        $V .= "</select><P />\n";

        $V .= "<li>Select the upload to analyze:<br>";
        $V .= "<select size='10' name='upload' onChange='Agents_Get(\"" . Traceback_uri() . "?mod=upload_agent_options&upload=\" + this.value)'>\n";
        $List = FolderListUploads($Folder);
        foreach($List as $L)
        {
          $V .= "<option value='" . $L['upload_pk'] . "'>";
          $V .= htmlentities($L['name']);
          if (!empty($L['upload_desc']))
          {
            $V .= " (" . htmlentities($L['upload_desc']) . ")";
          }
          $V .= "</option>\n";
        }
        $V .= "</select><P />\n";
        $V .= "<li>Select additional analysis.<br>\n";
        $V .= "<select multiple size='10' id='agents' name='agents[]'></select>\n";
        $V .= "</ol>\n";
        $V .= "<input type='submit' value='Analyze!'>\n";
        $V .= "</form>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  }
};
$NewPlugin = new agent_add;
$NewPlugin->Initialize();
?>
