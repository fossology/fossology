<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_maintagent", _("FOSSology Maintenance"));

/**
 * \class maintagent extend from FO_Plugin
 * \brief Queue the maintenance agent with the requested parameters
 */
class maintagent extends FO_Plugin {

  public function __construct()
  {
    $this->Name = "maintagent";
    $this->Title = TITLE_maintagent;
    $this->MenuList = "Admin::Maintenance";
    $this->DBaccess = PLUGIN_DB_ADMIN;
    parent::__construct();
  }
  
  /**
   * \brief queue the job
   *
   * \param
   * \returns status string
   **/
  function QueueJob()
  {
    global $SysConf;

    /* Find all the maintagent options specified by the user.
     * They look like _REQUEST["a"] = "a", _REQUEST["b"]="b", ...
     */
    $options = "-";
    foreach($_REQUEST as $key=>$value) if ($key == $value) $options .= $value;

    /* Create the maintenance job */
    $user_pk = $SysConf['auth']['UserId'];
    $upload_pk = 0;  // dummy

    $job_pk = JobAddJob($user_pk, "Maintenance", $upload_pk);
    if (empty($job_pk) || ($job_pk < 0)) return _("Failed to insert job record");
    
    $jq_pk = JobQueueAdd($job_pk, "maintagent", NULL, NULL, NULL, NULL, $options);
    if (empty($jq_pk)) return _("Failed to insert task 'Maintenance' into job queue");

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) return($error_msg . "\n" . $output);

    return _("The maintenance job has been queued");
  }


  /**
   * \brief Display the input form
   *
   * \param
   * \returns HTML in string
   **/
  function DisplayForm()
  {
    /* Array of maintagent options and description */
    $Options = array("a"=>_("Run all non slow maintenance operations."),
                     "A"=>_("Run all maintenance operations."),
                     "F"=>_("Validate folder contents."),
              //       "g"=>_("Remove orphaned gold files."),
                     "N"=>_("Normalize priority "),
              //       "p"=>_("Verify file permissions (report only)."),
              //       "P"=>_("Verify and fix file permissions."),
                     "R"=>_("Remove uploads with no pfiles."),
                     "T"=>_("Remove orphaned temp tables."),
                     "D"=>_("Vacuum Analyze the database."),
              //       "U"=>_("Process expired uploads (slow)."),
              //       "Z"=>_("Remove orphaned files from the repository (slow)."),
                     "v"=>_("verbose (turns on debugging output)")
                    );
    $V = "";

    $V.= "<form method='post'>\n"; // no url = this url
    $V.= "<ol>\n";

    foreach ($Options as $option=>$description)
    {
      $V.= "<li>";
      $V.= "<input type='checkbox' name='$option' value='$option' > $description <p>\n";
    }

    $V.= "</ol>\n";
    $text = _("Queue the maintenance agent");
    $V.= "<input type='submit' value='$text'>\n";

    $V.= "<p>";
    $V.= _("More information about these operations can be found ");
    $text = _("here.");
    $V.= "<a href=http://www.fossology.org/projects/fossology/wiki/Maintagent> $text </a>";

    $V.= "<input type=hidden name=queue value=true>";

    $V.= "</form>\n";
    return $V;
  }


  protected function htmlContent() 
  {
    $V = "";
    /* If this is a POST, then process the request. */
    $queue = GetParm('queue', PARM_STRING);
    if (!empty($queue))
    {
      $Msg = $this->QueueJob();
      $V .= "<font style='background-color:gold'>" . $Msg . "</font>";
    }
    $V .= $this->DisplayForm();
    return $V;
  }
}

$NewPlugin = new maintagent;