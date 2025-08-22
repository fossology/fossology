<?php
/*
 SPDX-FileCopyrightText: © 2025 Harshit Gandhi <gandhiharshit716@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Kotoba;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class AdminKotobaRevision extends DefaultPlugin
{
  const NAME = 'admin_kotoba_revision';

  function __construct()
  {
        parent::__construct(self::NAME, array(
        self::TITLE => _("Manage Kotoba Revision"),
        self::MENU_LIST => "Admin::Agent::Kotoba",
        self::PERMISSION => Auth::PERM_ADMIN
        ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    /** @var AgentDao */
    $agentDao = $this->getObject('dao.agent');
    /** @var AgentRef */
    $kotoba = $agentDao->getCurrentAgentRef('kotoba');
    $rev = $request->get('rev');
    if ($rev==$kotoba->getAgentRevision() && $agentDao->renewCurrentAgent('kotoba')) {
      $text = _("You have renewed the kotoba revision.");
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('message'=>$text)));
    }
    $vars['content'] = '<a href="?mod=admin_kotoba_revision&rev='.$kotoba->getAgentRevision().'">'._('Renew kotoba revision').'</a>';
    return $this->render('include/base.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new AdminKotobaRevision());
