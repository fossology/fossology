<?php
/***********************************************************
 * Copyright (C) 2014-2018, Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Reuser;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\PackageDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Plugin\AgentPlugin;
use Fossology\Lib\Util\StringOperation;
use Symfony\Component\HttpFoundation\Request;

class ReuserAgentPlugin extends AgentPlugin
{
  const UPLOAD_TO_REUSE_SELECTOR_NAME = 'uploadToReuse';

  /** @var UploadDao */
  private $uploadDao;

  public function __construct() {
    $this->Name = "agent_reuser";
    $this->Title =  _("Reuse of License Clearing");
    $this->AgentName = "reuser";

    parent::__construct();

    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }

  public function doAgentAdd($jobId, $uploadId, &$errorMsg, $dependencies, $jqargs = "", $jq_cmd_args = null)
  {
    parent::doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies, $jqargs, $jq_cmd_args);
  }

  /**
   * @param array $vars
   * @return string
   */
  public function renderContent(&$vars) {
    $reuserPlugin = plugin_find('plugin_reuser');
    return $reuserPlugin->renderContent($vars);
  }

  /**
   * @param array $vars
   * @return string
   */
  public function renderFoot(&$vars) {
    $reuserPlugin = plugin_find('plugin_reuser');
    return $reuserPlugin->renderFoot($vars);
  }


  public function preInstall()
  {
    menu_insert("ParmAgents::" . $this->Title, 0, $this->Name);
  }

  /**
   * @param int $jobId
   * @param int $uploadId
   * @param string $errorMsg
   * @param Request $request
   */
  public function scheduleAgent($jobId, $uploadId, &$errorMsg, $request)
  {
    $reuseUploadPair = explode(',', $request->get(self::UPLOAD_TO_REUSE_SELECTOR_NAME), 2);
    if (count($reuseUploadPair) == 2) {
      list($reuseUploadId, $reuseGroupId) = $reuseUploadPair;
    }
    else
    {
      $errorMsg .= 'no reuse upload id given';
      return -1;
    }
    $groupId = $request->get('groupId', Auth::getGroupId());

    $getReuseValue = $request->get('reuseMode');

    $reuseMode = UploadDao::REUSE_NONE;

    if(!empty($getReuseValue)){
      if(count($getReuseValue)<2){
        if(in_array('reuseMain', $getReuseValue)){
          $reuseMode = UploadDao::REUSE_MAIN;
        }
        else{
          $reuseMode = UploadDao::REUSE_ENHANCED;
        }
      }
      else{
        $reuseMode = UploadDao::REUSE_ENH_MAIN;
      }
    }

    $this->createPackageLink($uploadId, $reuseUploadId, $groupId, $reuseGroupId, $reuseMode);

    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, array("agent_adj2nest"), $uploadId);
  }

  /**
   * @param int $uploadId
   * @param int $reuseUploadId
   * @param int $groupId
   * @param int $reuseGroupId
   * @param int $reuseMode
   */
  protected function createPackageLink($uploadId, $reuseUploadId, $groupId, $reuseGroupId, $reuseMode=0)
  {
    /* @var $packageDao PackageDao */
    $packageDao = $GLOBALS['container']->get('dao.package');
    $newUpload = $this->uploadDao->getUpload($uploadId);
    $uploadForReuse = $this->uploadDao->getUpload($reuseUploadId);

    $package = $packageDao->findPackageForUpload($reuseUploadId);

    if ($package === null)
    {
      $packageName = StringOperation::getCommonHead($uploadForReuse->getFilename(), $newUpload->getFilename());
      $package = $packageDao->createPackage($packageName ?: $uploadForReuse->getFilename());
      $packageDao->addUploadToPackage($reuseUploadId, $package);
    }

    $packageDao->addUploadToPackage($uploadId, $package);

    $this->uploadDao->addReusedUpload($uploadId, $reuseUploadId, $groupId, $reuseGroupId, $reuseMode);
  }
}

register_plugin(new ReuserAgentPlugin());
