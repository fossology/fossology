<?php
/*
 SPDX-FileCopyrightText: © 2014-2017 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Page;

use Fossology\Lib\Application\CurlRequestService;
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
    );

    if (Auth::isAdmin()) {
      $repositoryApi = new RepositoryApi(new CurlRequestService());
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
