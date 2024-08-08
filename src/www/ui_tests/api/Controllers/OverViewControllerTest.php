<?php

/*
 SPDX-FileCopyrightText: © 2022 Valens Niyonsenga <valensniyonsenga2003@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for CopyrightController
 */
namespace Fossology\UI\Api\Test\Controllers;

use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Controllers\CopyrightController;
use Fossology\UI\Api\Controllers\OverviewController;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Mockery as M;

class OverViewControllerTest extends  \PHPUnit\Framework\TestCase
{

  /**
   * @var OverviewController $overViewController
   * overViewController object to test
   */
  private $overViewController;
  /**
   * @var DbHelper $dbHelper
   * DbHelper mock
   */
  private $dbHelper;

  /**
   * @var RestHelper $restHelper
   * RestHelper mock
   */
  private $restHelper;
  /**
   * @var UploadDao $uploadDao
   * UploadDao mock
   */
  private $uploadDao;
  /**
   * @var StreamFactory $streamFactory
   * Stream factory to create body streams.
   */
  private $streamFactory;

  /**
   * @brief Setup test objects
   * @see PHPUnit_Framework_TestCase::setUp()
   */
  public function setUp(): void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->overViewController = new OverViewController($container);
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->userDao = M::mock(UserDao::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->copyrightDao = M::mock(CopyrightDao::class);

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->restHelper->shouldReceive('getPlugin')
      ->withArgs(['ajax_copyright_hist'])->andReturn($this->copyrightHist);
    $this->restHelper->shouldReceive('getUploadDao')
      ->andReturn($this->uploadDao);
    $container->shouldReceive('get')->withArgs(array(
      'helper.restHelper'))->andReturn($this->restHelper);
    $container->shouldReceive('get')->withArgs(array(
      'dao.copyright'
    ))->andReturn($this->copyrightDao);
    $this->streamFactory = new StreamFactory();
    $this->copyrightController = new CopyrightController($container);
  }

  /**
   * Helper function to get JSON array from response
   *
   * @param Response $response
   * @return array Decoded response
   */
  private function getResponseJson($response)
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

}
