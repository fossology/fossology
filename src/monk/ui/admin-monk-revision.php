<?php
/***********************************************************
  Copyright (C) 2014, Siemens AG

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

namespace Fossology\Monk;

use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class AdminMonkRevision extends DefaultPlugin {
  const NAME = 'admin_monk_revision';
  
  function __construct(){
        parent::__construct(self::NAME, array(
        self::TITLE => _("Manage Monk Revision"),
        self::MENU_LIST => "Admin::Agent::Monk",
        self::DEPENDENCIES => array(\ui_menu::NAME),
        self::PERMISSION => self::PERM_ADMIN
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
    if($rev==$monk->getAgentRevision() && $agentDao->renewCurrentAgent('monk'))
    {
      $text = _("You have renewed the monk revision.");
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('message'=>$text)));
    }
    $vars['content'] = '<a href="?mod=admin_monk_revision&rev='.$monk->getAgentRevision().'">'._('Renew monk revision').'</a>';
    return $this->render('include/base.html.twig', $this->mergeWithDefault($vars));
  }

}

register_plugin(new AdminMonkRevision());