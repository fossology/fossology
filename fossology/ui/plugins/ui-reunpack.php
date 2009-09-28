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
if(!isset($GlobalReady)){exit;}

class ui_reunpack extends FO_Plugin
{
  public $Name       = "ui_reunpack";
  public $Title      = "Schedule an Reunpack";
  //public $MenuList   = "Jobs::Agents::Reunpack";
  public $Version    = "1.2";
  public $Dependency = array("db");
  public $DBaccess   = PLUGIN_DB_UPLOAD;

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $DB;
    global $Plugins;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
	     break;
      case "HTML":
	     /* If this is a POST, then process the request. */
	     $uploadpk = GetParm('uploadunpack',PARM_INTEGER);
	     if (!empty($uploadpk))
	     {
	       $P = &$Plugins[plugin_find_id("agent_unpack")];
	       $rc = $P->AgentAdd($uploadpk);
	       if (empty($rc))
	       {
	       /* Need to refresh the screen */
	         $V .= displayMessage('Unpack added to job queue');
	       }
	       else
	       {
	         $V .= displayMessage("Unpack of Upload failed: $rc");
	       }
	     }

	     /* Set default values */
	     if (empty($GetURL)) { $GetURL='http://'; }

	     //$V .= $this->ShowReunpackView($uploadtree_pk);
	
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
    /*********************************************
   AgentAdd(): Given an uploadpk, add a job.
   $Depends is for specifying other dependencies.
   $Depends can be a jq_pk, or an array of jq_pks, or NULL.
   Returns NULL on success, string on failure.
   *********************************************/
  function AgentAdd ($uploadpk,$Depends=NULL,$Priority=0)
  {
    /* Prepare the job: job "unpack" */
    $jobpk = JobAddJob($uploadpk,"unpack",$Priority);
    if (empty($jobpk) || ($jobpk < 0)) { return("Failed to insert job record"); }
    if (!empty($Depends) && !is_array($Depends)) { $Depends = array($Depends); }

    /* job "unpack" has jobqueue item "unpack" */
    $jqargs = "SELECT pfile.pfile_sha1 || '.' || pfile.pfile_md5 || '.' || pfile.pfile_size AS pfile,
	    upload_pk, pfile_fk
	    FROM upload
	    INNER JOIN pfile ON upload.pfile_fk = pfile.pfile_pk
	    WHERE upload.upload_pk = '$uploadpk';";
    $jobqueuepk = JobQueueAdd($jobpk,"unpack",$jqargs,"no","pfile",$Depends,1);
    if (empty($jobqueuepk)) { return("Failed to insert item into job queue"); }

    /* job "unpack" has jobqueue item "adj2nest" */
    $jqargs = "$uploadpk";
    $jobqueuepk = JobQueueAdd($jobpk,"adj2nest",$jqargs,"no","",array($jobqueuepk));
    if (empty($jobqueuepk)) { return("Failed to insert adj2nest into job queue"); }

    return(NULL);
  } // AgentAdd()
  /*********************************************
   ShowReunpackView(): Generate the reunpack view
   page. Give the unploadtree_pk, return page view
   output.
   *********************************************/
  function ShowReunpackView($Item, $Reunpack=0)
  {
    global $DB;
    $V = "";
   
    if (empty($DB)) {return; }
   
    $Sql = "SELECT upload_fk FROM uploadtree WHERE uploadtree_pk = $Item;";
    $Result = $DB->Action($Sql);
    $Row = $Result[0];
    if (empty($Row['upload_fk'])) { return; }
    $Upload_pk = $Row['upload_fk'];
    $Sql = "SELECT pfile_fk,ufile_name from uploadtree where upload_fk=$Upload_pk and parent is NULL;";
    $Result = $DB->Action($Sql);
    $Row = $Result[0];
    if (empty($Row['pfile_fk'])) { return; }
    $Pfile_fk = $Row['pfile_fk'];
    $Ufile_name = $Row['ufile_name'];
      
    $Fin_gold = @fopen( RepPath($Pfile_fk,"gold") ,"rb");
    if (empty($Fin_gold))
    {
        $V = "<p/>The File's Gold file are not available in the repository.\n";
        return $V;
    }
      
    $V = "<p/>";
    $V.= "This file is unpacked from <font color='blue'>[".$Ufile_name."]</font>\n";

      	  /* Display the form */
	  $V .= "<form method='post'>\n"; // no url = this url

	  $V .= "<p />\nReunpack: " . $Ufile_name . "<input name='uploadunpack' type='hidden' value='$Upload_pk'/>\n";
	  $V .= "<input type='submit' value='Reunpack!' ";
	  if ($Reunpack) {$V .= "disabled";}
	  $V .= " >\n";
	  $V .= "</form>\n";
	  
      return $V;
}
}
$NewPlugin = new ui_reunpack;    

?>
