<?php
/**
 * *********************************************************
 * Copyright (C) 2020 Siemens AG
 *
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * *********************************************************
 */

/**
 * @file
 * Report general job status of the server to user.
 */
namespace Fossology\UI\Page;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class AllJobStatus
 * Page to show all scheduled/running jobs on server to users.
 */
class AllJobStatus extends DefaultPlugin
{

  /**
   * @var string NAME
   *      Mod name
   */
  const NAME = "all_job_status";

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::TITLE => "Status - all server jobs",
        self::MENU_LIST => "Admin::Dashboards::All Jobs",
        self::REQUIRES_LOGIN => false,
        self::PERMISSION => Auth::PERM_READ
      ));
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    global $SysConf;
    $vars = array();
    $vars['clockTime'] = $SysConf['SYSCONFIG']['ShowJobsAutoRefresh'];
    return $this->render('all_job_status.html.twig',
      $this->mergeWithDefault($vars));
  }
}

register_plugin(new AllJobStatus());
