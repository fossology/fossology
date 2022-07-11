<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015-2017 Siemens AG

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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;

define("TITLE_ADMIN_PROJECT_DELETE", _("Delete Project"));

/**
 * @class admin_project_delete
 * @brief UI plugin to delete projects
 */
class admin_project_delete extends FO_Plugin
{

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name = "admin_project_delete";
    $this->Title = TITLE_ADMIN_PROJECT_DELETE;
    $this->MenuList = "Organize::Project::Delete Project";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->folderDao = $GLOBALS['container']->get('dao.folder');
    $this->projectDao = $GLOBALS['container']->get('dao.project');
  }

  /**
   * @brief Creates a job to detele the project
   * @param int $projectpk the project_pk to remove
   * @param int $userId   the user deleting the project
   * @return NULL on success, string on failure.
   */
  function Delete($projectpk, $userId)
  {

    $splitProject = explode(" ",$projectpk);

    if (! $this->projectDao->isProjectAccessible($splitProject[1], $userId)) {
      $text = _("No access to delete this project");
      return ($text);
    }
    /* Can't remove top project */
    if ($splitProject[1] == ProjectGetTop()) {
      $text = _("Can Not Delete Root Project");
      return ($text);
    }
    /* Get the project's name */
    $ProjectName = ProjectGetName($splitProject[1]);

    /* Prepare the job: job "Delete" */
    $groupId = Auth::getGroupId();
    $jobpk = JobAddJob($userId, $groupId, "Delete Project: $ProjectName");
    if (empty($jobpk) || ($jobpk < 0)) {
      $text = _("Failed to create job record");
      return ($text);
    }

    /* Add job: job "Delete" has jobqueue item "delagent" */
    $jqargs = "DELETE PROJECT $projectpk";
    $jobqueuepk = JobQueueAdd($jobpk, "delagent", $jqargs, NULL, NULL);
    if (empty($jobqueuepk)) {
      $text = _("Failed to place delete in job queue");
      return ($text);
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);

    if (! $success) {
      return $error_msg . "\n" . $output;
    }

    return (null);
  } // Delete()

  /**
   * @copydoc FO_Plugin::Output()
   * @see FO_Plugin::Output()
   */
  public function Output()
  {
    /* If this is a POST, then process the request. */
    $project = GetParm('project', PARM_RAW);
    $splitProject = explode(" ",$project);
    if (!empty($project)) {
      $userId = Auth::getUserId();
      $sql = "SELECT project_name FROM project join users on (users.user_pk = project.user_fk or users.user_perm = 10) where project_pk = $1 and users.user_pk = $2;";
      $Project = $this->dbManager->getSingleRow($sql,array($splitProject[1],$userId),__METHOD__."GetRowWithProjectName");
      if (!empty($Project['project_name'])) {
        $rc = $this->Delete($project, $userId);
        if (empty($rc)) {
          /* Need to refresh the screen */
          $text = _("Deletion of project ");
          $text1 = _(" added to job queue");
          $this->vars['message'] = $text . $Project['project_name'] . $text1;
        } else {
          $text = _("Deletion of ");
          $text1 = _(" failed: ");
          $this->vars['message'] =  $text . $Project['project_name'] . $text1 . $rc;
        }
      } else {
        $text = _("Cannot delete this project :: Permission denied");
        $this->vars['message'] = $text;
      }
    }

    $V= "<form method='post'>\n"; // no url = this url
    $text  =  _("Select the project to");
    $text1 = _("delete");
    $V.= "$text <em>$text1</em>.\n";
    $V.= "<ul>\n";
    $text = _("This will");
    $text1 = _("delete");
    $text2 = _("the project, all subprojects, and all uploaded files stored within the project!");
    $V.= "<li>$text <em>$text1</em> $text2\n";
    $text = _("Be very careful with your selection since you can delete a lot of work!");
    $V.= "<li>$text\n";
    $text = _("All analysis only associated with the deleted uploads will also be deleted.");
    $V.= "<li>$text\n";
    $text = _("THERE IS NO UNDELETE. When you select something to delete, it will be removed from the database and file repository.");
    $V.= "<li>$text\n";
    $V.= "</ul>\n";
    $text = _("Select the project to delete:  ");
    $V.= "<P>$text\n";
    $V.= "<select name='project' class='ui-render-select2'>\n";
    $text = _("select project");
    $V.= "<option value='' disabled selected>[$text]</option>\n";
    $V.= ProjectListOption(-1, 0, 1, -1, true);
    $V.= "</select><P />\n";
    $text = _("Delete");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";
    return $V;
  }
}

$NewPlugin = new admin_project_delete();
