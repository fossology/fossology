<?php
/*
Copyright (C) 2014-2016, Siemens AG
Author: Andreas Würl

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

namespace Fossology\UI\Page;

use Fossology\Lib\Application\RepositoryApi;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \brief about page on UI
 */
class AboutPage extends DefaultPlugin
{
  const NAME = "about";

  /** @var LicenseDao $licenseDao */
  private $licenseDao;

  public function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => "About Fossology",
        self::MENU_LIST => "Help::About",
        self::REQUIRES_LOGIN => false,
    ));

    $this->licenseDao = $this->getObject('dao.license');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {   
    $vars = array(
        'licenseCount' => $this->licenseDao->getLicenseCount(),
        'project' => _("FOSSology"),
        'copyright' => _("Copyright (C) 2007-2014 Hewlett-Packard Development Company, L.P.<br>\nCopyright (C) 2014-2016 Siemens AG."),
    );
    
    if (Auth::isAdmin()) {
      $repositoryApi = new RepositoryApi();
      $latestRelease = $repositoryApi->getLatestRelease();
      $commits = $repositoryApi->getCommitsOfLastDays(30);
      $commit = empty($commits) ? '' : substr($commits[0]['sha'],0,6);

      $vars = array_merge($vars, array(
          'latestVersion' => $latestRelease,
          'lastestCommit' => $commit));
    }

    return $this->render('about.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new AboutPage());
