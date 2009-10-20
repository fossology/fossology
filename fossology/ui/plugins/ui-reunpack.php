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
   CheckStatus(): Given an uploadpk and job_name
   to check if an reunpack/rewget job is running.
   Returns 0 no reunpack/rewget job running;
           1 reunpack/rewget job failed
           2 reunpack/rewget job completed
           3 reunpack/rewget job running
           4 reunpack/rewget job pending
   *********************************************/ 
  function CheckStatus ($uploadpk, $job_name, $jobqueue_type)
  {
    global $DB;
    if (empty($DB)) {return;}
    
    $SQLcheck = "SELECT jq_pk,jq_starttime,jq_endtime,jq_end_bits FROM jobqueue
    LEFT OUTER JOIN job ON jobqueue.jq_job_fk = job.job_pk
  	WHERE job.job_upload_fk = '$uploadpk'
  	AND job.job_name = '$job_name'
  	AND jobqueue.jq_type = '$jobqueue_type';";
    
    $Results = $DB->Action($SQLcheck);
    if(empty($Results)) {
      print $SQLcheck;
      return(0);
    }
    else {
      $i = 0;
      $State = 0;
      while (!empty($Results[$i]['jq_pk'])) {
        if ($Results[$i]['jq_end_bits'] == 2) {
          $State = 1;
          break;
        }
        if (!empty($Results[$i]['jq_starttime'])) {
          if (!empty($Results[$i]['jq_endtime'])) {
            $State = 2;
          } else {
            $State = 3;
            break;
          }
        } else {
          $State = 4;
          break;
        }
        $i++;
      }
      return ($State);
    }
  }
    /*********************************************
   AgentAdd(): Given an uploadpk, add a job.
   $Depends is for specifying other dependencies.
   $Depends can be a jq_pk, or an array of jq_pks, or NULL.
   Returns NULL on success, string on failure.
   *********************************************/
  function AgentAdd ($uploadpk,$Depends=NULL,$priority=0)
  {
    global $DB;
    if (empty($DB)) {
      return;
    }
    
    $Job_name = str_replace("'", "''", "unpack");

    if (empty($uploadpk)) {
      $SQLInsert = "INSERT INTO job
    	 (job_queued,job_priority,job_name) VALUES
    	 (now(),'$priority','$Job_name');";
    }
    else {
      $SQLInsert = "INSERT INTO job
    	 (job_queued,job_priority,job_name,job_upload_fk) VALUES
     	  (now(),'$priority','$Job_name','$uploadpk');";
    }

    $SQLcheck = "SELECT job_pk FROM job WHERE job_upload_fk = '$uploadpk' AND job_name = '$Job_name' AND job_user_fk is NULL;";
    $Results = $DB->Action($SQLcheck);
    if (!empty($Results)){
      $jobpk = $Results[0]['job_pk'];
    } else {
      $DB->Action($SQLInsert);
      $SQLcheck = "SELECT job_pk FROM job WHERE job_upload_fk = '$uploadpk' AND job_name = '$Job_name' AND job_user_fk is NULL;";
      $Results = $DB->Action($SQLcheck);
      $jobpk = $Results[0]['job_pk']; 
    }      
  
    if (empty($jobpk) || ($jobpk < 0)) { return("Failed to insert job record! $SQLInsert"); }
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
    //$jqargs = "$uploadpk";
    //$jobqueuepk = JobQueueAdd($jobpk,"adj2nest",$jqargs,"no","",array($jobqueuepk));
    //if (empty($jobqueuepk)) { return("Failed to insert adj2nest into job queue"); }

    /* job "sqlagent" has jobqueue item "unpack" sqlagent to clean up reunpack jobqueue*/
    //$jqargs = "DELETE FROM jobdepends WHERE jdep_jq_fk IN (SELECT jq_pk FROM jobqueue WHERE jq_job_fk = '$jobpk') OR jdep_jq_depends_fk IN (SELECT jq_pk FROM jobqueue WHERE jq_job_fk = '$jobpk');DELETE FROM jobqueue WHERE jq_job_fk = '$jobpk';DELETE FROM job WHERE job_pk = '$jobpk'; ";
    //$jobqueuepk = JobQueueAdd($jobpk,"sqlagent",$jqargs,"no","",array($jobqueuepk));
    //if (empty($jobqueuepk)) { return("Failed to insert delete sqlagent into job queue"); }
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
