<?php
/*
SPDX-FileCopyrightText: © 2012-2013 Hewlett-Packard Development Company, L.P.
SPDX-FileCopyrightText: © 2015 Siemens AG

SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Plugin\AgentPlugin;

/**
 * @file agent_adj2nest.php
 * adj2nest UI plugin
 */

/**
 * @class Adj2nestAgentPlugin
 * The adj2nest UI agent plugin class
 */
class Adj2nestAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_adj2nest";
    $this->Title = 'adj2nest';
    $this->AgentName = "adj2nest";

    parent::__construct();
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentHasResults()
   */
  function AgentHasResults($uploadId=0)
  {
    $dbManager = $GLOBALS['container']->get('db.manager');

    $uploadtree_tablename = GetUploadtreeTableName($uploadId);
    if (NULL == $uploadtree_tablename) strcpy($uploadtree_tablename, "uploadtree");

    /* see if the latest nomos and bucket agents have scaned this upload for this bucketpool */
    $uploadtreeRec = $dbManager->getSingleRow("SELECT * FROM $uploadtree_tablename WHERE upload_fk=$1 and lft is not null",
            array($uploadId),__METHOD__.'.lftNotSet');
    if (empty($uploadtreeRec))
    {
      return 0;
    }

    $stmt = __METHOD__.$uploadtree_tablename;
    $sql = "SELECT parent,lft FROM $uploadtree_tablename WHERE upload_fk=$1 ORDER BY parent, ufile_mode&(1<<29) DESC, ufile_name";
    $dbManager->prepare($stmt,$sql);
    $res=$dbManager->execute($stmt,array($uploadId));
    $prevRow = array('parent'=>0,'lft'=>0);
    $wrongOrder = false;
    while($row=$dbManager->fetchArray($res))
    {
      $wrongOrder = $prevRow['parent']==$row['parent'] && $prevRow['lft']>$row['lft'];
      if ($wrongOrder) {
        break;
      }
      $prevRow = $row;
    }
    $dbManager->freeResult($res);
    return $wrongOrder ? 2 : 1;
  }

  /**
   * @copydoc Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   * @see \Fossology\Lib\Plugin\AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
      $arguments=null, $request=null, $unpackArgs=null)
  {
    if ($this->AgentHasResults($uploadId) == 1)
    {
      return 0;
    }

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0)
    {
      return $jobQueueId;
    }

    if (!$this->isAgentIncluded($dependencies, 'agent_unpack')) {
      $dependencies[] = array('name' => "agent_unpack", 'args' => $unpackArgs);
    }
    $args = is_array($arguments) ? '' : $arguments;
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies,
        $uploadId, $args, $request);
  }

  /**
   * Check if agent already included in the dependency list
   * @param mixed  $dependencies Array of job dependencies
   * @param string $agentName    Name of the agent to be checked for
   * @return boolean true if agent already in dependency list else false
   */
  protected function isAgentIncluded($dependencies, $agentName)
  {
    foreach($dependencies as $dependency)
    {
      if ($dependency == $agentName)
      {
        return true;
      }
      if (is_array($dependency) && $agentName == $dependency['name'])
      {
        return true;
      }
    }
    return false;
  }
}

register_plugin(new Adj2nestAgentPlugin());
