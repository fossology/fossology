<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Monk;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class AdminMonkRevision extends DefaultPlugin
{
  const NAME = 'admin_monk_revision';

  function __construct()
  {
        parent::__construct(self::NAME, array(
        self::TITLE => _("Manage Monk Revision"),
        self::MENU_LIST => "Admin::Agent::Monk",
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
    $monk = $agentDao->getCurrentAgentRef('monk');
    $rev = $request->get('rev');
    if ($rev==$monk->getAgentRevision() && $agentDao->renewCurrentAgent('monk')) {
      $text = _("You have renewed the monk revision.");
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('message'=>$text)));
    }
    $vars['content'] = '<a href="?mod=admin_monk_revision&rev='.$monk->getAgentRevision().'">'._('Renew monk revision').'</a>';
    return $this->render('include/base.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new AdminMonkRevision());
