<?php
/***********************************************************
 Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\AgentPlugin;

class BucketAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_bucket";
    $this->Title = _("Bucket Analysis");
    $this->AgentName = "buckets";

    parent::__construct();
  }

  /**
   * Get the current user's default bucket pool id
   * @return int Bucket pool id
   */
  protected function getDefaultBucketPool()
  {
    $user_pk = Auth::getUserId();
    if (empty($user_pk)) {
      return 0;
    }

    /** @var $dbManager DbManager */
    $dbManager = $GLOBALS['container']->get('db.manager');
    $usersRec = $dbManager->getSingleRow('SELECT default_bucketpool_fk FROM users WHERE user_pk=$1', array($user_pk));
    return $usersRec['default_bucketpool_fk'];
  }

  /**
   * Register the plugin to UI
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    $bucketPool = $this->getDefaultBucketPool();
    if (!empty($bucketPool))
    {
      menu_insert("Agents::" . $this->Title, 0, $this->Name);
    }
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  public function AgentHasResults($uploadId=0)
  {
    $default_bucketpool_fk = $this->getDefaultBucketPool();
    if (empty($default_bucketpool_fk)) {
      return 0;
    }
    /* @var $agentDao AgentDao */
    $agentDao = $GLOBALS['container']->get('dao.agent');
    $latestNomosAgentId = $agentDao->getCurrentAgentId("nomos", "Nomos license scanner");
    if (empty($latestNomosAgentId)) {
      return 0;
    }
    $latestBucketAgentId = $agentDao->getCurrentAgentId($this->AgentName, "Bucket scanner");
    if (empty($latestBucketAgentId)) {
      return 0;
    }
    /* @var $dbManager DbManager */
    $dbManager = $GLOBALS['container']->get('db.manager');

    $bucketLatestArsRec = $dbManager->getSingleRow("SELECT * FROM bucket_ars WHERE bucketpool_fk=$1 AND upload_fk=$2 AND nomosagent_fk=$3 and agent_fk=$4 AND ars_success=$5",
            array($default_bucketpool_fk,$uploadId,$latestNomosAgentId,$latestBucketAgentId,$dbManager->booleanToDb(true)),
            __METHOD__.'.latestNomosAndBucketScannedThisPool');
    if (!empty($bucketLatestArsRec)) return 1;

    $bucketOldArsRec = $dbManager->getSingleRow("SELECT * FROM bucket_ars WHERE bucketpool_fk=$1 AND upload_fk=$2 AND ars_success=$3",
            array($default_bucketpool_fk,$uploadId,$dbManager->booleanToDb(true)),
            __METHOD__.'.anyBucketScannedThisPool');
    if (!empty($bucketOldArsRec)) return 2;

    return 0;
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=array(), $arguments=null)
  {
    $default_bucketpool_fk = $this->getDefaultBucketPool();
    if (!$default_bucketpool_fk)
    {
      $errorMsg = _("User does not have a default bucketpool.  Bucket agent cannot be scheduled without this.");
      return (-1);
    }

    $dependencies[] = "agent_nomos";
    $dependencies[] = "agent_pkgagent";
    $jqargs = "bppk=$default_bucketpool_fk, upk=$uploadId";
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $jqargs);
  }
}

register_plugin(new BucketAgentPlugin());