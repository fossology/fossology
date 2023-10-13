<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2019 Siemens AG
 SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

define("TITLE_MAINTAGENT", _("FOSSology Maintenance"));

use Fossology\Lib\Auth\Auth;

/**
 * \class maintagent
 * \brief Queue the maintenance agent with the requested parameters
 */
class maintagent extends FO_Plugin {

   const OPTIONS = [
    "a"=>"Run all non slow maintenance operations.",
    "A"=>"Run all maintenance operations.",
    "F"=>"Validate folder contents.",
    "g"=>"Remove orphaned gold files.",
    "E"=>"Remove orphaned rows from database.",
    "L"=>"Remove orphaned log files from file system.",
    "N"=>"Normalize priority ",
    // "p"=>_("Verify file permissions (report only)."),
    //  "P"=>_("Verify and fix file permissions."),
    "R"=>"Remove uploads with no pfiles.",
    "t"=>"Remove expired personal access tokens.",
    "T"=>"Remove orphaned temp tables.",
    "D"=>"Vacuum Analyze the database.",
    //       "U"=>_("Process expired uploads (slow)."),
    "Z"=>"Remove orphaned files from the repository (slow).",
    "I"=>"Reindexing of database (This activity may take 5-10 mins. Execute only when system is not in use).",
    "v"=>"verbose (turns on debugging output)",
    "o"=>"Remove older gold files from repository.",
    "l"=>"Remove older log files from repository."
    ];

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
  public function handle($request)
  {
    global $SysConf;

    /* Find all the maintagent options specified by the user.
     * They look like _REQUEST["a"] = "a", _REQUEST["b"]="b", ...
     */
    $options = "-";
    foreach ($request['options'] as $key => $value) {
      if ($key == $value) {
        $options .= $value;
        if ($key === "t") {
          $retentionPeriod = $SysConf['SYSCONFIG']['PATMaxPostExpiryRetention'];
          $options .= $retentionPeriod;
        } elseif ($key === "l") {
          $options .= $request['logsDate'];
        }
        if ($key == "o") {
          $options .= $request['goldDate'];
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

    $V = "<form method='post'>\n"; // no url = this url
    foreach (self::OPTIONS as $option => $description) {
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
      $request = ['options' => $_REQUEST , 'logsDate' => GetParm('logsDate', PARM_TEXT), 'goldDate' => GetParm('goldDate', PARM_TEXT)];
      $Msg = $this->handle($request);
      $V .= "<font style='background-color:#111110'>" . $Msg . "</font>";
    }
    $V .= $this->DisplayForm();
    return $V;
  }

  public function getOptions() {
    return $this::OPTIONS;
  }
}

$NewPlugin = new maintagent;
