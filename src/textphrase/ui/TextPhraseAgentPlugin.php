<?php
/**
 * SPDX-FileCopyrightText: © 2022 Siemens AG
 * SPDX-License-Identifier: GPL-2.0-only AND LGPL-2.1-only
 */

/***************************************************************
 Copyright (C) 2024 FOSSology Team

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
 ***************************************************************/

use Fossology\Lib\Plugin\AgentPlugin;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Data\AgentRef;
use Symfony\Component\HttpFoundation\Request;

class TextPhraseAgentPlugin extends AgentPlugin
{
  const NAME = 'agent_textphrase';

  public $AgentName = 'textphrase';

  /** @var UploadDao */
  private $uploadDao;

  /** @var AgentDao */
  private $agentDao;

  /** @var array */
  protected $agentNames = array('textphrase' => 'Text Phrase Scanner');

  public function __construct()
  {
    parent::__construct();
    $this->Name = self::NAME;
    $this->Title = _("Text Phrase Scanner");
    $this->Dependency = array("agent_adj2nest");
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
    $this->agentDao = $GLOBALS['container']->get('dao.agent');
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::preInstall()
   * @see Fossology::Lib::Plugin::AgentPlugin::preInstall()
   */
  function preInstall()
  {
    menu_insert("Agents::" . $this->Title, 0, $this->Name);
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentAdd()
   */
  public function AgentAdd($jobId, $uploadId, &$errorMsg, $dependencies=[],
      $arguments=null, $request=null, $unpackArgs=null)
  {
    $dependencies[] = "agent_adj2nest";
    if ($this->AgentHasResults($uploadId) == 1) {
      return 0;
    }

    $jobQueueId = \IsAlreadyScheduled($jobId, $this->AgentName, $uploadId);
    if ($jobQueueId != 0) {
      return $jobQueueId;
    }

    $args = is_array($arguments) ? '' : $arguments;
    return $this->doAgentAdd($jobId, $uploadId, $errorMsg, $dependencies,
        $uploadId, $args, $request);
  }

  /**
   * @copydoc Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   * @see Fossology::Lib::Plugin::AgentPlugin::AgentHasResults()
   */
  public function AgentHasResults($uploadId=0)
  {
    return $this->agentDao->hasAgentResults($uploadId, $this->AgentName);
  }
} 