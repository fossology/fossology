<?php
/***********************************************************
 * Copyright (C) 2014-2015, Siemens AG
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

use Fossology\Lib\Plugin\AgentPlugin;
use Fossology\Lib\Dao\PackageDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Util\StringOperation;

include_once(__DIR__ . "/../agent/version.php");

class ReuserAgentPlugin extends AgentPlugin
{
  public function __construct() {
    $this->Name = "agent_reuser";
    $this->Title =  _("Automatic Clearing Decision Reuser");
    $this->AgentName = REUSER_AGENT_NAME;

    parent::__construct();
  }

  function preInstall()
  {
    // no AgentCheckBox
  }
  
  /**
   * @param int $uploadId
   * @param int $reuseUploadId
   * @internal description
   */
  public function createPackageLink($uploadId, $reuseUploadId)
  {
    /** @var UploadDao */
    $uploadDao = $GLOBALS['container']->get('dao.upload');
    /** @var PackageDao */
    $packageDao = $GLOBALS['container']->get('dao.package');
    $newUpload = $uploadDao->getUpload($uploadId);
    $uploadForReuse = $uploadDao->getUpload($reuseUploadId);

    $package = $packageDao->findPackageForUpload($reuseUploadId);

    if ($package === null)
    {
      $packageName = StringOperation::getCommonHead($uploadForReuse->getFilename(), $newUpload->getFilename());
      $package = $packageDao->createPackage($packageName ?: $uploadForReuse->getFilename());
      $packageDao->addUploadToPackage($reuseUploadId, $package);
    }

    $packageDao->addUploadToPackage($uploadId, $package);

    $uploadDao->addReusedUpload($uploadId, $reuseUploadId);
  }

}

register_plugin(new ReuserAgentPlugin());
