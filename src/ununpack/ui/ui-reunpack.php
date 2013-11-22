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

/**
 * \file ui-reunpack.php
 * \brief reunpack the archive
 */

define("TITLE_ui_reunpack", _("Schedule an Reunpack"));

class ui_reunpack extends FO_Plugin
{
  public $Name       = "ui_reunpack";
  public $Title      = TITLE_ui_reunpack;
  //public $MenuList   = "Jobs::Agents::Reunpack";
  public $Version    = "1.2";
  public $Dependency = array();
  public $DBaccess   = PLUGIN_DB_WRITE;

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
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
          $text = _("Unpack added to job queue");
          $V .= displayMessage($text);
        }
        else
        {
          $text = _("Unpack of Upload failed");
          $V .= displayMessage("$text: $rc");
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

  /**
   * \brief Given an uploadpk and job_name
   * to check if an reunpack/rewget job is running.
   * \return 0 no reunpack/rewget job running;
   * - 1 reunpack/rewget job failed
   * - 2 reunpack/rewget job completed
   * - 3 reunpack/rewget job running
   * - 4 reunpack/rewget job pending
   */
  function CheckStatus ($uploadpk, $job_name, $jobqueue_type)
  {
    global $PG_CONN; 
    $SQLcheck = "SELECT jq_pk,jq_starttime,jq_endtime,jq_end_bits FROM jobqueue
      LEFT OUTER JOIN job ON jobqueue.jq_job_fk = job.job_pk
      WHERE job.job_upload_fk = '$uploadpk'
      AND job.job_name = '$job_name'
      AND jobqueue.jq_type = '$jobqueue_type' ORDER BY jq_pk DESC;";
    $result = pg_query($PG_CONN, $SQLcheck);
    DBCheckResult($result, $SQLcheck, __FILE__, __LINE__);
      $i = 0;
      $State = 0;
      while ($Row = pg_fetch_assoc($result)) {
        if ($Row['jq_end_bits'] == 2) {
          $State = 1;
          break;
        }
        if (!empty($Row['jq_starttime'])) {
          if (!empty($Row['jq_endtime'])) {
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
    
    pg_free_result($result);
  }

  /**
   * \brief Given an uploadpk, add a job.
   * \param $Depends - specifying other dependencies.
   * $Depends can be a jq_pk, or an array of jq_pks, or NULL.
   *
   * \return NULL on success, string on failure.
   */
  function AgentAdd ($uploadpk,$Depends=NULL,$priority=0)
  {
    global $PG_CONN; 
    $Job_name = str_replace("'", "''", "reunpack");
    
    //get userpk from uploadpk
    $UploadRec = GetSingleRec("upload", "where upload_pk='$uploadpk'");
    
    //updated ununpack_ars table to let reunpack run 
    $SQLARS = "UPDATE ununpack_ars SET ars_success = FALSE WHERE upload_fk = '$uploadpk';";
    $result = pg_query($PG_CONN, $SQLARS);
    DBCheckResult($result, $SQLARS, __FILE__, __LINE__);
    pg_free_result($result);

    if (empty($uploadpk)) {
      $SQLInsert = "INSERT INTO job
        (job_queued,job_priority,job_name,job_user_fk) VALUES
        (now(),'$priority','$Job_name',{$UploadRec['user_fk']});";
    }
    else {
      $SQLInsert = "INSERT INTO job
        (job_queued,job_priority,job_name,job_upload_fk,job_user_fk) VALUES
        (now(),'$priority','$Job_name','$uploadpk',{$UploadRec['user_fk']});";
    }

    $SQLcheck = "SELECT job_pk FROM job WHERE job_upload_fk = '$uploadpk' AND job_name = '$Job_name' AND job_user_fk = {$UploadRec['user_fk']} ORDER BY job_pk DESC LIMIT 1;";
    $result = pg_query($PG_CONN, $SQLcheck);
    DBCheckResult($result, $SQLcheck, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);

    if (!empty($row)){
      $jobpk = $row['job_pk'];
    } else {
      $result = pg_query($PG_CONN, $SQLInsert);
      DBCheckResult($result, $SQLInsert, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      $SQLcheck = "SELECT job_pk FROM job WHERE job_upload_fk = '$uploadpk' AND job_name = '$Job_name' AND job_user_fk = {$UploadRec['user_fk']};";
      $result = pg_query($PG_CONN, $SQLcheck);
      DBCheckResult($result, $SQLcheck, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      $jobpk = $row['job_pk']; 
    }      

    if (empty($jobpk) || ($jobpk < 0)) { return("Failed to insert job record! $SQLInsert"); }
    if (!empty($Depends) && !is_array($Depends)) { $Depends = array($Depends); }

    /* job "unpack" has jobqueue item "unpack" */
    $jqargs = "SELECT pfile.pfile_sha1 || '.' || pfile.pfile_md5 || '.' || pfile.pfile_size AS pfile,
      upload_pk, pfile_fk
        FROM upload
        INNER JOIN pfile ON upload.pfile_fk = pfile.pfile_pk
        WHERE upload.upload_pk = '$uploadpk';";
echo "JobQueueAdd used to do a reschedule here<br>";
    $jobqueuepk = JobQueueAdd($jobpk,"ununpack",$uploadpk,NULL,$Depends);
    if (empty($jobqueuepk)) { return("Failed to insert item into job queue"); }

    return(NULL);
  } // AgentAdd()

  /**
   * \brief Generate the reunpack view
   * page. Give the unploadtree_pk, return page view output.
   */
  function ShowReunpackView($Item, $Reunpack=0)
  {
    global $PG_CONN;        
    $V = "";

    $sql = "SELECT upload_fk FROM uploadtree WHERE uploadtree_pk = $Item;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($row['upload_fk'])) { return; }
    $Upload_pk = $row['upload_fk'];
    $sql = "SELECT pfile_fk,ufile_name from uploadtree where upload_fk=$Upload_pk and parent is NULL;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($row['pfile_fk'])) { return; }
    $Pfile_fk = $row['pfile_fk'];
    $Ufile_name = $row['ufile_name'];

    $Fin_gold = @fopen( RepPath($Pfile_fk,"gold") ,"rb");
    if (empty($Fin_gold))
    {
      $text = _("The File's Gold file is not available in the repository.");
      $V = "<p/>$text\n";
      return $V;
    }

    $V = "<p/>";
    $text = _("This file is unpacked from");
    $V.= "$text <font color='blue'>[".$Ufile_name."]</font>\n";

    /* Display the form */
    $V .= "<form method='post'>\n"; // no url = this url

    $text = _("Reunpack");
    $V .= "<p />\n$text: " . $Ufile_name . "<input name='uploadunpack' type='hidden' value='$Upload_pk'/>\n";
    $V .= "<input type='submit' value='$text!' ";
    if ($Reunpack) {$V .= "disabled";}
    $V .= " >\n";
    $V .= "</form>\n";

    return $V;
  }
}
$NewPlugin = new ui_reunpack;    

?>
