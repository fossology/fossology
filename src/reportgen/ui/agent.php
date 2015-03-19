<?php
/*
 Copyright (C) 2014-2015, Siemens AG
 Author: Daniele Fognini, Steffen Weber

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
 */
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

class ReportGenerator extends DefaultPlugin
{
  const NAME = 'ui_reportgen';
  
  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Report Generator"),
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
  }

  protected function handle(Request $request)
  {
    global $SysConf;
    $user_pk = $SysConf['auth'][Auth::USER_ID];
    $group_pk = $SysConf['auth'][Auth::GROUP_ID];

    $uploadId = intval($request->get('upload'));
    if ($uploadId <=0)
    {
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('content'=>_("parameter error"))));
    }

    if (GetUploadPerm($uploadId) < Auth::PERM_WRITE)
    {
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('content'=>_("permission denied"))));
    }

    $dbManager = $this->getObject('db.manager');
    $row = $dbManager->getSingleRow("SELECT upload_filename FROM upload WHERE upload_pk=$1", array($uploadId), "getUploadName");

    if ($row === false)
    {
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('content'=>_("cannot find uploadId"))));
    }
    
    $shortName = $row['upload_filename'];
    $reportGenAgent = plugin_find('agent_reportgen');
    $job_pk = JobAddJob($user_pk, $group_pk, $shortName, $uploadId);
    $error = "";
    $jq_pk = $reportGenAgent->AgentAdd($job_pk, $uploadId, $error, array());

    if ($jq_pk<0)
    {
      return $this->render('include/base.html.twig', $this->mergeWithDefault(array('content'=>_("Cannot schedule").": ".$error)));
    }

    $vars['jqPk'] = $jq_pk;
    $vars['downloadLink'] = Traceback_uri(). "?mod=download&report=".$job_pk;
    $vars['reportType'] = "report";
    $text = sprintf(_("Generating new report for '%s'"), $shortName);
    $vars['content'] = "<h2>".$text."</h2>";
            
    return $this->render("report.html.twig", $this->mergeWithDefault($vars));
  }

  function preInstall()
  {
    $text = _("Generate Report");
    menu_insert("Browse-Pfile::Generate&nbsp;Word&nbsp;Report", 0, self::NAME, $text);
  }
}

register_plugin(new ReportGenerator());
