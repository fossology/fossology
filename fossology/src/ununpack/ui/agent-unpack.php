<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

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
 * \file agent_unpack.php
 * \brief the unpack ui, and add unpack job, joqueue
 */

define("TITLE_agent_unpack", _("Schedule an Unpack"));

class agent_unpack extends FO_Plugin
{
  public $Name       = "agent_unpack";
  public $Title      = TITLE_agent_unpack;
  // public $MenuList   = "Jobs::Agents::Unpack";
  public $Version    = "1.0";
  public $Dependency = array();
  public $DBaccess   = PLUGIN_DB_UPLOAD;

  /**
   * \brief register additional menus.
   */
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    } // don't run
    menu_insert("Agents::" . $this->Title,0,$this->Name);
  }

  /**
   * \brief Check if the job is already in the queue or already completed.  
   *
   * \param $upload_pk
　 * \return 
   * - 0 = not scheduled \n
   * - 1 = scheduled but not completed \n
   * - 2 = scheduled and completed \n
   */
  function AgentCheck($upload_pk)
  {
    return CommonAgentCheck($upload_pk, "unpack", "Universal file unpacker", "ununpack_ars");
  } // AgentCheck()

  /**
   * \brief  Given an uploadpk, add a job.
   * 
   * \param $Depends - is for specifying other dependencies.
   * \param $Depends - can be a jq_pk, or an array of jq_pks, or NULL.
   * 
   * \return NULL on success, string on failure.
   */
  function AgentAdd ($uploadpk,$Depends=NULL,$Priority=0)
  {
    /* Prepare the job: job "unpack" */
    $jobpk = JobAddJob($uploadpk,"unpack",$Priority);
    if (empty($jobpk) || ($jobpk < 0)) {
      $text = _("Failed to insert job record");
      return($text);
    }
    if (!empty($Depends) && !is_array($Depends)) {
      $Depends = array($Depends);
    }

    /* job "unpack" has jobqueue item "unpack"
     $jqargs = "SELECT pfile.pfile_sha1 || '.' || pfile.pfile_md5 || '.' || pfile.pfile_size AS pfile,
    upload_pk, pfile_fk
    FROM upload
    INNER JOIN pfile ON upload.pfile_fk = pfile.pfile_pk
    WHERE upload.upload_pk = '$uploadpk';";
    */
    $jqargs = $uploadpk;
    $jobqueuepk = JobQueueAdd($jobpk,"ununpack",$jqargs,"no","",$Depends);
    if (empty($jobqueuepk)) {
      $text = _("Failed to insert item into job queue");
      return($text);
    }

    /* job "unpack" has jobqueue item "adj2nest" */
    $jqargs = "$uploadpk";
    $jobqueuepk = JobQueueAdd($jobpk,"adj2nest",$jqargs,"no","",array($jobqueuepk));
    if (empty($jobqueuepk)) {
      $text = _("Failed to insert adj2nest into job queue");
      return($text);
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) return $error_msg . "\n" . $output;

    return(NULL);
  } // AgentAdd()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    global $PG_CONN;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $uploadpk = GetParm('upload',PARM_INTEGER);
        if (!empty($uploadpk))
        {
          $rc = $this->AgentAdd($uploadpk);
          if (empty($rc))
          {
            /* Need to refresh the screen */
            $text = _("Unpack added to job queue");
            $V .= displayMessage($text);
          }
          else
          {
            $text = _("Unpack of Upload failed:");
            $V .= displayMessage($text.$rc);
          }
        }

        /* Set default values */
        if (empty($GetURL)) {
          $GetURL='http://';
        }

        /* Get list of projects that are not scheduled for uploads */
        $SQL = "SELECT upload_pk,upload_desc,upload_filename
        FROM upload
        WHERE upload_pk NOT IN
        (
          SELECT upload_pk FROM upload
          INNER JOIN job ON job.job_upload_fk = upload.upload_pk
          INNER JOIN jobqueue ON jobqueue.jq_job_fk = job.job_pk
          AND job.job_name = 'unpack'
          AND jobqueue.jq_type = 'ununpack'
          ORDER BY upload_pk
        )
        ORDER BY upload_pk DESC;";
        $result = pg_query($PG_CONN, $SQL);
        DBCheckResult($result, $SQL, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result, 0);

        if (empty($row['upload_pk']))
        {
          $V .= _("All uploaded files are already unpacked, or scheduled to be unpacked.");
        }
        else
        {
          /* Display the form */
          $V .= "<form method='post'>\n"; // no url = this url
          $V .= "<ol>\n";
          $text = _("Select an uploaded file to unpack.\n");
          $V .= "<li>$text";
          $V .= _("Only uploads that are not already unpacked (and not already scheduled) can be scheduled.\n");
          $text = _("Unpack:");
          $V .= "<p />\n$text <select name='upload'>\n";

          while ($Row = pg_fetch_assoc($result))
          {
            if (empty($Row['upload_pk'])) {
              continue;
            }
            if (empty($Row['upload_desc'])) {
              $Name = $Row['upload_filename'];
            }
            else { $Name = $Row['upload_desc'] . " (" . $Row['upload_filename'] . ")";
            }
            $V .= "<option value='" . $Row['upload_pk'] . "'>$Name</option>\n";
          }
          $V .= "</select><P />\n";
          $text = _("Select optional analysis");
          $V .= "<li>$text<br />\n";
          $V .= AgentCheckboxMake(-1,$this->Name);
          $V .= "</ol>\n";
          $text = _("Unpack");
          $V .= "<input type='submit' value='$text!'>\n";
          $V .= "</form>\n";
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    pg_free_result($result);
    if (!$this->OutputToStdout) {
      return($V);
    }
    print("$V");
    return;
  }
};
$NewPlugin = new agent_unpack;
?>
