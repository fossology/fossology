<?php
/***********************************************************
 Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.

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
 * \file agent-bucket.php
 * \brief schedule the bucket agent
 */

define("TITLE_agent_bucket", _("Bucket Analysis"));

class agent_bucket extends FO_Plugin {
  function __construct()
  {
    $this->Name = "agent_bucket";
    $this->Title = TITLE_agent_bucket;
  //   $this->MenuList   = "Jobs::Agents::Bucket Analysis";
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->AgentName = "buckets";   // agent.agent_name
    parent::__construct();
  }
  
  /**
   * \brief Register additional menus.
   */
  function RegisterMenus() 
  {
    global $SysConf;

    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    } // don't run

    /* Get the users.default_bucketpool_fk */
    $AuthRec = GetArrayval('auth', $SysConf);
    if (empty($AuthRec)) return 0;

    $user_pk = $SysConf['auth']['UserId'];

    /* Unless the user is authenticated, we can't do anything. */
    if (empty($user_pk)) return 0;

    /* Get users default bucketpool so we know which bucketpool to use. */
    $usersRec = GetSingleRec("users", "where user_pk='$user_pk'");
    $default_bucketpool_fk = $usersRec['default_bucketpool_fk'];
    if (empty($default_bucketpool_fk)) return 0;

    /* fake menu item used to identify plugin agents */
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }


/**
 * \brief Check if the upload has results from this agent.
 *
 * \param $upload_pk
 *
 * \returns
 * 0 = no
 * 1 = yes, from latest agent version
 * 2 = yes, from older agent version
 */
function AgentHasResults($upload_pk) 
{
  global $SysConf;

  /* Get the users.default_bucketpool_fk */
  $user_pk = $SysConf['auth']['UserId'];

  /* Unless the user is authenticated, we can't do anything. */
  if (empty($user_pk)) return 0;

  /* Get users default bucketpool so we know which bucketpool to use. */
  $usersRec = GetSingleRec("users", "where user_pk='$user_pk'");
  $default_bucketpool_fk = $usersRec['default_bucketpool_fk'];
  if (empty($default_bucketpool_fk)) return 0;

  /* get the latest nomos agent_pk */
  $Latest_nomos_agent_pk = GetAgentKey("nomos", "Nomos license scanner");

  /* get the latest bucket agent_pk */
  $Latest_bucket_agent_pk = GetAgentKey($this->AgentName, "Bucket scanner");

  if (empty($Latest_nomos_agent_pk) || empty($Latest_bucket_agent_pk)) return 0; // no any nomos or bucket agent in agent table

  /* see if the latest nomos and bucket agents have scaned this upload for this bucketpool */
  $bucket_arsRec = GetSingleRec("bucket_ars", "where bucketpool_fk='$default_bucketpool_fk' and upload_fk='$upload_pk' and nomosagent_fk='$Latest_nomos_agent_pk' and agent_fk='$Latest_bucket_agent_pk' and ars_success='true'");
  if (!empty($bucket_arsRec)) return 1;

  /* see if older nomos and/or bucket agents have scaned this upload for this bucketpool */
  $bucket_arsRec = GetSingleRec("bucket_ars", "where bucketpool_fk='$default_bucketpool_fk' and upload_fk='$upload_pk' and ars_success='true'");
  if (!empty($bucket_arsRec)) return 2;

  return (0);
} // AgentHasResults()

  /**
   * \brief Queue the bucket agent.
   *
   * \param $job_pk
   * \param $upload_pk - upload_pk
   * \param $ErrorMsg - error message on failure
   * \param $Dependencies - array of plugin names representing dependencies.
   *        This is for dependencies that this plugin cannot know about ahead of time.
   *
   * \returns
   * - jq_pk Successfully queued
   * -   0   Not queued, latest version of agent has previously run successfully
   * -  -1   Not queued, error, error string in $ErrorMsg
   */
  function AgentAdd($job_pk, $upload_pk, &$ErrorMsg, $Dependencies)
  {
    global $Plugins;
    global $SysConf;
    $Dep = array();
    $jqDeps = array();
    $EmptyDeps = array();

    /* Is the user authenticated?  If not, then fail
     * because we won't know which bucketpool to use.
     */ 
    $user_pk = $SysConf['auth']['UserId'];
    if (empty($user_pk))
    {
      $ErrorMsg = _("Session is unauthenticated, bucket agent cannot run without knowing who the user is.");
      return(-1);
    }

    /* get the default_bucketpool_fk from the users record */
    $usersRec = GetSingleRec("users", "where user_pk='$user_pk'");
    $default_bucketpool_fk = $usersRec['default_bucketpool_fk'];
    if (!$default_bucketpool_fk)
    {
      $ErrorMsg = _("User does not have a default bucketpool.  Bucket agent cannot be scheduled without this.");
      return (-1);
    }

    /* schedule buckets */
    /* queue up dependencies */
    $Dependencies[] = "agent_nomos";
    $Dependencies[] = "agent_pkgagent";
    $jqargs = "bppk=$default_bucketpool_fk, upk=$upload_pk";
    return CommonAgentAdd($this, $job_pk, $upload_pk, $ErrorMsg, $Dependencies, $jqargs);
  } // AgentAdd()

  /**
   * \brief There is no Output() form for buckets.
   */
  function Output() {
      return;
  }
}
$NewPlugin = new agent_bucket;
