<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2019 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

define("TITLE_MAINTAGENT", _("FOSSology Maintenance"));

use Fossology\Lib\Auth\Auth;

/**
 * \class maintagent
 * \brief Queue the maintenance agent with the requested parameters
 */
class maintagent extends FO_Plugin {

  public function __construct()
  {
    $this->Name = "maintagent";
    $this->Title = TITLE_MAINTAGENT;
    $this->MenuList = "Admin::Maintenance";
    $this->DBaccess = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /**
   * \brief Queue the job
   * \returns string Status string
   */
  function QueueJob()
  {
    global $SysConf;

    /* Find all the maintagent options specified by the user.
     * They look like _REQUEST["a"] = "a", _REQUEST["b"]="b", ...
     */
    $options = "-";
    foreach ($_REQUEST as $key => $value) {
      if ($key == $value) {
        $options .= $value;
        if ($key === "t") {
          $retentionPeriod = $SysConf['SYSCONFIG']['PATMaxPostExpiryRetention'];
          $options .= $retentionPeriod;
        } elseif ($key === "l") {
          $options .= GetParm("logsDate", PARM_TEXT) . " ";
        }
        if ($key == "o") {
          $options .= GetParm("goldDate", PARM_TEXT) . " ";
        }
      }
    }

    /* Create the maintenance job */
    $user_pk = Auth::getUserId();
    $groupId = Auth::getGroupId();

    $job_pk = JobAddJob($user_pk, $groupId, "Maintenance");
    if (empty($job_pk) || ($job_pk < 0)) { return _("Failed to insert job record");
    }

    $jq_pk = JobQueueAdd($job_pk, "maintagent", NULL, NULL, NULL, NULL, $options);
    if (empty($jq_pk)) { return _("Failed to insert task 'Maintenance' into job queue");
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success) { return($error_msg . "\n" . $output);
    }

    return _("The maintenance job has been queued");
  }


  /**
   * \brief Display the input form
   * \returns string HTML in string
   */
  function DisplayForm()
  {
    /* Array of maintagent options and description */
    $Options = array("a"=>_("Run all non slow maintenance operations."),
                     "A"=>_("Run all maintenance operations."),
                     "F"=>_("Validate folder contents."),
                     "g"=>_("Remove orphaned gold files."),
                     "E"=>_("Remove orphaned rows from database."),
                     "L"=>_("Remove orphaned log files from file system."),
                     "N"=>_("Normalize priority "),
              //       "p"=>_("Verify file permissions (report only)."),
              //       "P"=>_("Verify and fix file permissions."),
                     "R"=>_("Remove uploads with no pfiles."),
                     "t"=>_("Remove expired personal access tokens."),
                     "T"=>_("Remove orphaned temp tables."),
                     "D"=>_("Vacuum Analyze the database."),
              //       "U"=>_("Process expired uploads (slow)."),
                     "Z"=>_("Remove orphaned files from the repository (slow)."),
                     "I"=>_("Reindexing of database (This activity may take 5-10 mins. Execute only when system is not in use)."),
                     "v"=>_("verbose (turns on debugging output)"),
                     "o"=>_("Remove older gold files from repository."),
                     "l"=>_("Remove older log files from repository.")
                    );
    $V = "";

    $V .= "<form method='post'>\n"; // no url = this url
    foreach ($Options as $option => $description) {
      $V .= "<div class='form-group'><div class='form-check'>";
      $V .= " <input class='form-check-input' type='checkbox' name='$option' value='$option' id='men$option'>
        <label class='form-check-label' for='men$option'>$description</label>";
      if ($option === "o") {
        $V .= "<input type='date' class='form-control' name='goldDate' value='" . gmdate('Y-m-d', strtotime("-1 year")) . "' style='width:auto'>";
      }
      if ($option === "l") {
        $V .= "<input type='date' class='form-control' name='logsDate' value='" . gmdate('Y-m-d', strtotime("-1 year")) . "' style='width:auto'>";
      }
      $V .= "</div></div>\n";
    }

    $text = _("Queue the maintenance agent");
    $V.= "<br /><button type='submit' class='btn btn-primary'>$text</button>\n";

    $V.= "<p>";
    $V.= _("More information about these operations can be found ");
    $text = _("here.");
    $V.= "<a href=https://github.com/fossology/fossology/wiki/Maintenance-Agent> $text </a></p>";

    $V.= "<input type=hidden name=queue value=true>";

    $V.= "</form>\n";
    return $V;
  }


  /**
   * @copydoc FO_Plugin::Output()
   * @see FO_Plugin::Output()
   */
  public function Output()
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
