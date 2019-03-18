<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2019 Siemens AG

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

use Fossology\Lib\Auth\Auth;

/**
 * \class maintagent
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
      if ($key == $value)
        $options .= $value;
    }

    /* Create the maintenance job */
    $user_pk = Auth::getUserId();
    $groupId = Auth::getGroupId();

    $job_pk = JobAddJob($user_pk, $groupId, "Maintenance");
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
   * \returns string HTML in string
   */
  function DisplayForm()
  {
    /* Array of maintagent options and description */
    $Options = array("a"=>_("Run all non slow maintenance operations."),
                     "A"=>_("Run all maintenance operations."),
                     "F"=>_("Validate folder contents."),
              //       "g"=>_("Remove orphaned gold files."),
                     "E"=>_("Remove orphaned rows from database."),
                     "N"=>_("Normalize priority "),
              //       "p"=>_("Verify file permissions (report only)."),
              //       "P"=>_("Verify and fix file permissions."),
                     "R"=>_("Remove uploads with no pfiles."),
                     "T"=>_("Remove orphaned temp tables."),
                     "D"=>_("Vacuum Analyze the database."),
              //       "U"=>_("Process expired uploads (slow)."),
              //       "Z"=>_("Remove orphaned files from the repository (slow)."),
                     "I"=>_("Reindexing of database (This activity may take 5-10 mins. Execute only when system is not in use)."),
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
