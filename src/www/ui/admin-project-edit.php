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

use Fossology\Lib\Db\DbManager;

define("TITLE_PROJECT_PROPERTIES", _("Edit Project Properties"));

class project_properties extends FO_Plugin
{

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name = "project_properties";
    $this->Title = TITLE_PROJECT_PROPERTIES;
    $this->MenuList = "Organize::Project::Edit Properties";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Given a project's ID and a name, alter
   * the project properties.
   * Includes idiot checking since the input comes from stdin.
   * \return 1 if changed, 0 if failed.
   */
  function Edit($ProjectId, $NewName, $NewDesc)
  {
    $sql = 'SELECT * FROM project where project_pk = $1;';
    $Row = $this->dbManager->getSingleRow($sql,array($ProjectId),__METHOD__."Get");
    /* If the project does not exist. */
    if ($Row['project_pk'] != $ProjectId) {
      return (0);
    }
    $NewName = trim($NewName);
    if (! empty($ProjectId)) {
      // Reuse the old name if no new name was given
      if (empty($NewName)) {
        $NewName = $Row['project_name'];
      }
      // Reuse the old description if no new description was given
      if (empty($NewDesc)) {
        $NewDesc = $Row['project_desc'];
      }
    } else {
      return (0); // $ProjectId is empty
    }
    /* Change the properties */
    $sql = 'UPDATE project SET project_name = $1, project_desc = $2 WHERE project_pk = $3;';
    $this->dbManager->getSingleRow($sql,array($NewName, $NewDesc, $ProjectId),__METHOD__."Set");
    return (1);
  }

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output()
  {
    /* If this is a POST, then process the request. */
    $ProjectSelectId = GetParm('selectprojectid', PARM_INTEGER);
    if (empty($ProjectSelectId)) {
      $ProjectSelectId = ProjectGetTop();
    }
    $ProjectId = GetParm('oldprojectid', PARM_INTEGER);
    $NewName = GetParm('newname', PARM_TEXT);
    $NewDesc = GetParm('newdesc', PARM_TEXT);
    if (! empty($ProjectId)) {
      $ProjectSelectId = $ProjectId;
      $rc = $this->Edit($ProjectId, $NewName, $NewDesc);
      if ($rc == 1) {
        /* Need to refresh the screen */
        $text = _("Project Properties changed");
        $this->vars["message"] = $text;
      }
    }
    /* Get the project info */
    $sql = 'SELECT * FROM project WHERE project_pk = $1;';
    $Project = $this->dbManager->getSingleRow($sql,array($ProjectSelectId),__METHOD__."getProjectRow");

    /* Display the form */
    $formVars["onchangeURI"] = Traceback_uri() . "?mod=" . $this->Name . "&selectprojectid=";
    $formVars["projectListOption"] = ProjectListOption(-1, 0, 1, $ProjectSelectId);
    $formVars["project_name"] = $Project['project_name'];
    $formVars["project_desc"] = $Project['project_desc'];
    return $this->renderString("admin-project-edit-form.html.twig",$formVars);
  }
}
$NewPlugin = new project_properties;
