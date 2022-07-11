<?php
/*
Copyright (C) 2014-2015, Siemens AG
Authors: Wenhan Feng

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
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Project\Project;
use Fossology\Lib\Data\Upload\UploadProgress;
use Fossology\Lib\Db\DbManager;
use Monolog\Logger;

class ProjectDao
{
    const PROJECT_KEY = "project";
    const DEPTH_KEY = "depth";
    const REUSE_KEY = 'reuse';
    const TOP_LEVEL = 1;

    const MODE_PROJECT = 1;
    const MODE_UPLOAD = 2;
    const MODE_ITEM = 4;

    /** @var DbManager */
    private $dbManager;
    /** @var UserDao */
    private $userDao;
    /** @var UploadDao */
    private $uploadDao;
    /** @var Logger */
    private $logger;

    public function __construct(DbManager $dbManager, UserDao $userDao, UploadDao $uploadDao)
    {
        $this->dbManager = $dbManager;
        $this->logger = new Logger(self::class);
        $this->uploadDao = $uploadDao;
        $this->userDao = $userDao;
    }

    /**
     * @return boolean
     */
    public function hasTopLevelProject()
    {
        $projectInfo = $this->dbManager->getSingleRow("SELECT count(*) cnt FROM project WHERE project_pk=$1", array(self::TOP_LEVEL), __METHOD__);
        $hasProject = $projectInfo['cnt'] > 0;
        return $hasProject;
    }

    public function insertProject($projectName, $projectDescription, $parentProjectId = self::TOP_LEVEL)
    {

        $statementName = __METHOD__;
        $this->dbManager->prepare(
            $statementName,
            "INSERT INTO project (project_name, project_desc) VALUES ($1, $2) returning project_pk"
        );
        $res = $this->dbManager->execute($statementName, array($projectName, $projectDescription));
        $projectRow = $this->dbManager->fetchArray($res);
        $projectId = $projectRow["project_pk"];
        $this->dbManager->freeResult($res);
        $this->insertProjectContents($parentProjectId, self::MODE_PROJECT, $projectId);

        return $projectId;
    }

    public function getProjectId($projectName, $parentProjectId = self::TOP_LEVEL)
    {
        $statementName = __METHOD__;
        $this->dbManager->prepare(
            $statementName,
            "SELECT project_pk FROM project, projectcontents fc"
                . " WHERE LOWER(project_name)=LOWER($1) AND fc.parent_fk=$2 AND fc.projectcontents_mode=$3 AND project_pk=child_id"
        );
        $res = $this->dbManager->execute($statementName, array($projectName, $parentProjectId, self::MODE_PROJECT));
        $rows = $this->dbManager->fetchAll($res);

        $rootProject = !empty($rows) ? intval($rows[0]['project_pk']) : null;
        $this->dbManager->freeResult($res);

        return $rootProject;
    }

    public function insertProjectContents($parentId, $projectcontentsMode, $childId)
    {
        $statementName = __METHOD__;
        $this->dbManager->prepare(
            $statementName,
            "INSERT INTO projectcontents (parent_fk, projectcontents_mode, child_id) VALUES ($1, $2, $3)"
        );
        $res = $this->dbManager->execute($statementName, array($parentId, $projectcontentsMode, $childId));
        $this->dbManager->freeResult($res);
    }

    protected function fixProjectSequence()
    {
        $statementName = __METHOD__;
        $this->dbManager->prepare(
            $statementName,
            "SELECT setval('project_project_pk_seq', (SELECT max(project_pk) + 1 FROM project LIMIT 1))"
        );
        $res = $this->dbManager->execute($statementName);
        $this->dbManager->freeResult($res);
    }

    /**
     * @param int $userId
     * @return Project|null
     */
    public function getRootProject($userId)
    {
        $statementName = __METHOD__;
        $this->dbManager->prepare(
            $statementName,
            "SELECT f.* FROM project f INNER JOIN users u ON f.project_pk = u.root_project_fk WHERE u.user_pk = $1"
        );
        $res = $this->dbManager->execute($statementName, array($userId));
        $row = $this->dbManager->fetchArray($res);
        $rootProject = $row ? new Project(intval($row['project_pk']), $row['project_name'], $row['project_desc'], intval($row['project_perm'])) : null;
        $this->dbManager->freeResult($res);
        return $rootProject;
    }

    /**
     * @param int $userId
     * @return Project|null
     */
    public function getDefaultProject($userId)
    {
        $statementName = __METHOD__;
        $this->dbManager->prepare(
            $statementName,
            "SELECT f.* FROM project f INNER JOIN users u ON f.project_pk = u.default_project_fk WHERE u.user_pk = $1"
        );
        $res = $this->dbManager->execute($statementName, array($userId));
        $row = $this->dbManager->fetchArray($res);
        $rootProject = $row ? new Project(intval($row['project_pk']), $row['project_name'], $row['project_desc'], intval($row['project_perm'])) : null;
        $this->dbManager->freeResult($res);
        return $rootProject;
    }

    public function getProjectTreeCte($parentId = null)
    {
        $parentCondition = $parentId ? 'project_pk=$1' : 'project_pk=' . self::TOP_LEVEL;

        return "WITH RECURSIVE project_tree(project_pk, parent_fk, project_name, project_desc, project_perm, id_path, name_path, depth, cycle_detected) AS (
  SELECT
    f.project_pk, fc.parent_fk, f.project_name, f.project_desc, f.project_perm,
    ARRAY [f.project_pk]   AS id_path,
    ARRAY [f.project_name] AS name_path,
    0                     AS depth,
    FALSE                 AS cycle_detected
  FROM project f LEFT JOIN projectcontents fc ON fc.projectcontents_mode=" . self::MODE_PROJECT . " AND f.project_pk=fc.child_id
  WHERE $parentCondition
  UNION ALL
  SELECT
    f.project_pk, fc.parent_fk, f.project_name, f.project_desc, f.project_perm,
    id_path || f.project_pk,
    name_path || f.project_name,
    array_length(id_path, 1),
    f.project_pk = ANY (id_path)
  FROM project f, projectcontents fc, project_tree ft
  WHERE f.project_pk=fc.child_id AND projectcontents_mode=" . self::MODE_PROJECT . " AND fc.parent_fk = ft.project_pk AND NOT cycle_detected
)";
    }

    public function getProjectStructure($parentId = null)
    {
        $statementName = __METHOD__ . ($parentId ? '.relativeToParent' : '');
        $parameters = $parentId ? array($parentId) : array();
        $this->dbManager->prepare($statementName, $this->getProjectTreeCte($parentId)
            . " SELECT project_pk, parent_fk, project_name, project_desc, project_perm, depth FROM project_tree ORDER BY name_path");
        $res = $this->dbManager->execute($statementName, $parameters);

        $userGroupMap = $this->userDao->getUserGroupMap(Auth::getUserId());

        $results = array();
        while ($row = $this->dbManager->fetchArray($res)) {
            $countUploads = $this->countProjectUploads(intval($row['project_pk']), $userGroupMap);

            $results[] = array(
                self::PROJECT_KEY => new Project(
                    intval($row['project_pk']),
                    $row['project_name'],
                    $row['project_desc'],
                    intval($row['project_perm'])
                ),
                self::DEPTH_KEY => $row['depth'],
                self::REUSE_KEY => $countUploads
            );
        }
        $this->dbManager->freeResult($res);
        return $results;
    }

    /**
     * @param int $parentId
     * @param string[] $userGroupMap map groupId=>groupName
     * @return array  of array(group_id,count,group_name)
     */
    public function countProjectUploads($parentId, $userGroupMap)
    {
        $trustGroupIds = array_keys($userGroupMap);
        $statementName = __METHOD__;
        $trustedGroups = '{' . implode(',', $trustGroupIds) . '}';
        $parameters = array($parentId, $trustedGroups);

        $this->dbManager->prepare($statementName, "
SELECT group_fk group_id,count(*) FROM projectcontents fc
  INNER JOIN upload u ON u.upload_pk = fc.child_id
  INNER JOIN upload_clearing uc ON u.upload_pk=uc.upload_fk AND uc.group_fk=ANY($2)
WHERE fc.parent_fk = $1 AND fc.projectcontents_mode = " . self::MODE_UPLOAD . " AND (u.upload_mode = 100 OR u.upload_mode = 104)
GROUP BY group_fk
");
        $res = $this->dbManager->execute($statementName, $parameters);
        $results = array();
        while ($row = $this->dbManager->fetchArray($res)) {
            $row['group_name'] = $userGroupMap[$row['group_id']];
            $results[$row['group_name']] = $row;
        }
        $this->dbManager->freeResult($res);
        return $results;
    }

    public function getAllProjectIds()
    {
        $statementName = __METHOD__;
        $this->dbManager->prepare($statementName, "SELECT DISTINCT project_pk FROM project");
        $res = $this->dbManager->execute($statementName);
        $results = $this->dbManager->fetchAll($res);
        $this->dbManager->freeResult($res);

        $allIds = array();
        for ($i = 0; $i < sizeof($results); $i++) {
            array_push($allIds, intval($results[$i]['project_pk']));
        }

        return $allIds;
    }

    public function getProjectChildUploads($parentId, $trustGroupId)
    {
        $statementName = __METHOD__;
        $parameters = array($parentId, $trustGroupId);

        $this->dbManager->prepare($statementName, $sql = "
SELECT u.*,uc.*,fc.projectcontents_pk FROM projectcontents fc
  INNER JOIN upload u ON u.upload_pk = fc.child_id
  INNER JOIN upload_clearing uc ON u.upload_pk=uc.upload_fk AND uc.group_fk=$2
WHERE fc.parent_fk = $1 AND fc.projectcontents_mode = " . self::MODE_UPLOAD . " AND (u.upload_mode = 100 OR u.upload_mode = 104);");
        $res = $this->dbManager->execute($statementName, $parameters);
        $results = $this->dbManager->fetchAll($res);
        $this->dbManager->freeResult($res);
        return $results;
    }

    /**
     * @param int $parentId
     * @param int $trustGroupId
     * @return UploadProgress[]
     */
    public function getProjectUploads($parentId, $trustGroupId = null)
    {
        if (empty($trustGroupId)) {
            $trustGroupId = Auth::getGroupId();
        }
        $results = array();
        foreach ($this->getProjectChildUploads($parentId, $trustGroupId) as $row) {
            $results[] = UploadProgress::createFromTable($row);
        }
        return $results;
    }

    public function createProject($projectName, $projectDescription, $parentId)
    {
        $projectId = $this->dbManager->insertTableRow("project", array("project_name" => $projectName, "user_fk" => Auth::getUserId(), "project_desc" => $projectDescription), null, 'project_pk');
        $this->insertProjectContents($parentId, self::MODE_PROJECT, $projectId);
        return $projectId;
    }


    public function ensureTopLevelProject()
    {
        if (!$this->hasTopLevelProject()) {
            $this->dbManager->insertTableRow("project", array("project_pk" => self::TOP_LEVEL, "project_name" => "Software Repository", "project_desc" => "Top Project"));
            $this->insertProjectContents(1, 0, 0);
            $this->fixProjectSequence();
        }
    }

    public function isWithoutReusableProjects($projectStructure)
    {
        foreach ($projectStructure as $project) {
            $posibilities = array_reduce($project[self::REUSE_KEY], function ($sum, $groupInfo) {
                return $sum + $groupInfo['count'];
            }, 0);
            if ($posibilities > 0) {
                return false;
            }
        }
        return true;
    }

    protected function isInProjectTree($parentId, $projectId)
    {
        $cycle = $this->dbManager->getSingleRow(
            $this->getProjectTreeCte($parentId) . " SELECT depth FROM project_tree WHERE project_pk=$2 LIMIT 1",
            array($parentId, $projectId),
            __METHOD__
        );
        return !empty($cycle);
    }

    public function getContent($projectContentId)
    {
        $content = $this->dbManager->getSingleRow(
            'SELECT * FROM projectcontents WHERE projectcontents_pk=$1',
            array($projectContentId),
            __METHOD__ . '.getContent'
        );
        if (empty($content)) {
            throw new \Exception('invalid ProjectContentId');
        }
        return $content;
    }

    protected function isContentMovable($content, $newParentId)
    {
        if ($content['parent_fk'] == $newParentId) {
            return false;
        }
        $newParent = $this->dbManager->getSingleRow(
            'SELECT * FROM project WHERE project_pk=$1',
            array($newParentId),
            __METHOD__ . '.getParent'
        );
        if (empty($newParent)) {
            throw new \Exception('invalid parent project');
        }

        if ($content['projectcontents_mode'] == self::MODE_PROJECT) {
            if ($this->isInProjectTree($content['child_id'], $newParentId)) {
                throw new \Exception("action would cause a cycle");
            }
        } elseif ($content['projectcontents_mode'] == self::MODE_UPLOAD) {
            $uploadId = $content['child_id'];
            if (!$this->uploadDao->isEditable($uploadId, Auth::getGroupId())) {
                throw new \Exception('permission to upload denied');
            }
        }

        return true;
    }

    public function moveContent($projectContentId, $newParentId)
    {
        $content = $this->getContent($projectContentId);
        if (!$this->isContentMovable($content, $newParentId)) {
            return;
        }

        $this->dbManager->getSingleRow(
            'UPDATE projectcontents SET parent_fk=$2 WHERE projectcontents_pk=$1',
            array($projectContentId, $newParentId),
            __METHOD__ . '.updateProjectParent'
        );
    }

    public function copyContent($projectContentId, $newParentId)
    {
        $content = $this->getContent($projectContentId);
        if (!$this->isContentMovable($content, $newParentId)) {
            return;
        }

        $this->insertProjectContents($newParentId, $content['projectcontents_mode'], $content['child_id']);
    }

    public function getRemovableContents($projectId)
    {
        $sqlChildren = "SELECT child_id,projectcontents_mode
             FROM projectcontents GROUP BY child_id,projectcontents_mode
             HAVING count(*)>1 AND bool_or(parent_fk=$1)";
        $sql = "SELECT fc.* FROM projectcontents fc,($sqlChildren) chi "
            . "WHERE fc.child_id=chi.child_id AND fc.projectcontents_mode=chi.projectcontents_mode and fc.parent_fk=$1";
        $this->dbManager->prepare($stmt = __METHOD__, $sql);
        $res = $this->dbManager->execute($stmt, array($projectId));
        $contents = array();
        while ($row = $this->dbManager->fetchArray($res)) {
            $contents[] = $row['projectcontents_pk'];
        }
        $this->dbManager->freeResult($res);
        return $contents;
    }

    public function isRemovableContent($childId, $mode)
    {
        $sql = "SELECT count(parent_fk) FROM projectcontents WHERE child_id=$1 AND projectcontents_mode=$2";
        $parentCounter = $this->dbManager->getSingleRow($sql, array($childId, $mode), __METHOD__);
        return $parentCounter['count'] > 1;
    }

    public function removeContent($projectContentId)
    {
        $content = $this->getContent($projectContentId);
        if ($this->isRemovableContent($content['child_id'], $content['projectcontents_mode'])) {
            $sql = "DELETE FROM projectcontents WHERE projectcontents_pk=$1";
            $this->dbManager->getSingleRow($sql, array($projectContentId), __METHOD__);
        }
    }

    public function removeContentById($uploadpk, $projectId)
    {
        $sql = "DELETE FROM projectcontents WHERE child_id=$1 AND parent_fk=$2 AND projectcontents_mode=$3";
        $this->dbManager->getSingleRow($sql, array($uploadpk, $projectId, 2), __METHOD__);
    }

    public function getProjectChildProjects($projectId)
    {
        $results = array();
        $stmtProject = __METHOD__;
        $sqlProject = "SELECT projectcontents_pk,projectcontents_mode, project_name FROM projectcontents,project "
            . "WHERE projectcontents.parent_fk=$1 AND projectcontents.child_id=project.project_pk"
            . " AND projectcontents_mode=" . self::MODE_PROJECT;
        $this->dbManager->prepare($stmtProject, $sqlProject);
        $res = $this->dbManager->execute($stmtProject, array($projectId));
        while ($row = $this->dbManager->fetchArray($res)) {
            $results[$row['projectcontents_pk']] = $row;
        }
        $this->dbManager->freeResult($res);
        return $results;
    }

    /**
     * @param int $projectId
     * @return Project|null
     */
    public function getProject($projectId)
    {
        $projectRow = $this->dbManager->getSingleRow('SELECT * FROM project WHERE project_pk = $1', array($projectId));
        if (!$projectRow) {
            return null;
        }
        return new Project($projectRow['project_pk'], $projectRow['project_name'], $projectRow['project_desc'], $projectRow['project_perm']);
    }

    /**
     * @param int $projectId
     * @param int $userId
     * @return true|false
     */
    public function isProjectAccessible($projectId, $userId = null)
    {
        $allUserProjects = array();
        if ($userId == null) {
            $userId = Auth::getUserId();
        }
        $rootProject = $this->getRootProject($userId)->getId();
        GetProjectArray($rootProject, $allUserProjects);
        if (in_array($projectId, array_keys($allUserProjects))) {
            return true;
        }
        return false;
    }

    /**
     * Get the project contents id for a given child id
     * @param integer $childId Id of the child
     * @param integer $mode    Mode of child
     * @return NULL|integer Project content id if success, NULL otherwise
     */
    public function getProjectContentsId($childId, $mode)
    {
        $projectContentsRow = $this->dbManager->getSingleRow(
            'SELECT projectcontents_pk FROM projectcontents ' .
                'WHERE child_id = $1 AND projectcontents_mode = $2',
            [$childId, $mode]
        );
        if (!$projectContentsRow) {
            return null;
        }
        return intval($projectContentsRow['projectcontents_pk']);
    }

    /**
     * For a given project id, get the parent project id.
     * @param integer $projectPk ID of the project
     * @return number Parent id if parent exists, null otherwise.
     */
    public function getProjectParentId($projectPk)
    {
        $sql = "SELECT parent_fk FROM projectcontents " .
            "WHERE projectcontents_mode = " . self::MODE_PROJECT .
            " AND child_id = $1;";
        $statement = __METHOD__ . ".getParentId";
        $row = $this->dbManager->getSingleRow($sql, [$projectPk], $statement);
        return (empty($row)) ? null : $row['parent_fk'];
    }
}
