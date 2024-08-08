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

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\CopyrightDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\UI\Api\Controllers\CopyrightController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;


class CopyrightControllerTest extends  \PHPUnit\Framework\TestCase
{
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
   * @var CopyrightDao $copyrightDao
   * CopyrightDao mock
   */
  private $copyrightDao;
  /**
   * @var UploadDao $uploadDao
   * UploadDao mock
   */
  private $uploadDao;

  /**
   * @var CopyrightController $copyrightController
   * copyrightController object to test
   */

  private $copyrightController;
  /**
   * @var M\MockInterface $copyrightHist
   * ajaxCopyrightPlugin mock
   */

  private $copyrightHist;
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
    $this->dbHelper = M::mock(DbHelper::class);
    $this->restHelper = M::mock(RestHelper::class);
    $this->userDao = M::mock(UserDao::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->auth = M::mock(Auth::class);
    $this->copyrightDao = M::mock(CopyrightDao::class);
    $this->copyrightHist = M::mock('ajax_copyright_hist');

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



  /**
   * @test
   * -# Test CopyrightController::getTotalFileCopyrights() with upload that is not accessible
   * -# Check if response is 404 with HttpNotFoundException.
   */
  public function testGetTotalFileCopyrightsUploadNotFound()
  {
    $args = [
      "id" => 4,
      "itemId" => 2,
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(false);
    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams([]);
    $this->expectException(HttpNotFoundException::class);
    $this->copyrightController->getTotalFileCopyrights($request, new ResponseHelper(),$args);
  }

  /**
   * @test
   * -# Test CopyrightController::getTotalFileCopyrights() with item that is not found
   * -# Check if response is 404 with HttpNotFoundException.
   */
  public function testGetTotalFileCopyrightsItemNotFound()
  {
    $args = [
      "id" => 4,
      "itemId" => 2,
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(false);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams([]);
    $this->expectException(HttpNotFoundException::class);
    $this->copyrightController->getTotalFileCopyrights($request, new ResponseHelper(),$args);
  }


  /**
   * @test
   * -# Test CopyrightController::getTotalFileCopyrights() with bad request, no query parameters provided
   * -# Check if response is 400 with HttpBadRequestException.
   */
  public function testGetTotalFileCopyrightsWithBadRequest()
  {
    $args = [
      "id" => 4,
      "itemId" => 2,
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams([]);
    $this->expectException(HttpBadRequestException::class);
    $this->copyrightController->getTotalFileCopyrights($request, new ResponseHelper(),$args);
  }


  /**
   * @test
   * -# Test CopyrightController::getTotalFileCopyrights()
   * -# Check if both expected and actual object match.
   * -# Check if the response status code is 200
   */
  public function testGetTotalFileCopyrightsWithInvalidQueryParams()
  {
    $args = [
      "id" => 4,
      "itemId" => 2,
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'copyright_ars'])->andReturn(1);
    $this->copyrightDao->shouldReceive('getTotalCopyrights')
      ->withArgs([$args['id'],4,'uploadtree',3])->andReturn(10);

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "invalid"]);
    $this->expectException(HttpBadRequestException::class);

    $expectedResponse = (new ResponseHelper())->withJson(array('total_copyrights' => 10));
    $actualResponse = $this->copyrightController->getTotalFileCopyrights($request, new ResponseHelper(),$args);
    $this->assertEquals($expectedResponse, $actualResponse);
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test CopyrightController::getFileCopyrights() with invalid limit.
   * -# Check if the status code is 400 with HttpBadRequestException
   */
  public function testGetFileCopyrightsWithInvalidLimit()
  {
    $args = [
      "id" => 4,
      "itemId" => 2,
    ];
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 0);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "invalid"]);
    $this->expectException(HttpBadRequestException::class);
    $this->copyrightController->getFileCopyrights($request, new ResponseHelper(),$args);

  }


  /**
   * @test
   * -# Test CopyrightController::getFileCopyrights() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testGetFileCopyrights()
  {
    $dumRows = $this->getDummyData()['dumRows'];
    $args = $this->getDummyData()['args'];

    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive("doesIdExist")
          ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'copyright_ars'])->andReturn(1);

    $this->copyrightHist->shouldReceive('getCopyrights')
      ->withArgs([$args['id'],2,'uploadtree',1,'statement', 'active',true,0,100])->andReturn([$dumRows,10,10]);
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 100);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    foreach ($dumRows as $row) {
      $row['count'] = intval($row['copyright_count']);
      unset($row['copyright_count']);
      $finalVal[] = $row;
    }
    $totalPages = intval(ceil(10/100));
    $expectedResponse = (new ResponseHelper())->withHeader('X-Total-Pages',$totalPages)->withJson($finalVal,200);
    $actualResponse = $this->copyrightController->getFileCopyrights($request, new ResponseHelper(),$args);
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test CopyrightController::getFileCopyrights() with Invalid page.
   * -# Check if the status code is 400 with HttpBadRequestException .
   */
  public function testGetFileCopyrightsWithInvalidPage()
  {
    $args = [
      "id" => 4,
      "itemId" => 2,
    ];

    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'copyright_ars'])->andReturn(1);

    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 100);
    $requestHeaders->setHeader("page",-1);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    $this->expectException(HttpBadRequestException::class);
     $this->copyrightController->getFileCopyrights($request, new ResponseHelper(),$args);
  }


  public function getDummyData()
  {
    $dumRows = array(
      array(
        "count" => 10,
        "copyright_count" => 10,
      ),
      array(
        "count" => 15,
        "copyright_count" => 12,
      )
    );
    $args = [
      "id" => 4,
      "itemId" => 2,
    ];
    return [
      'dumRows' => $dumRows,
      'args' => $args,
    ];
  }
  /**
   * @test
   * -# Test CopyrightController::getFileEmail() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testGetFileEmail()
  {
    $dumRows = $this->getDummyData()['dumRows'];
    $args = $this->getDummyData()['args'];
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'copyright_ars'])->andReturn(1);

    $this->copyrightHist->shouldReceive('getCopyrights')
      ->withArgs([$args['id'],2,'uploadtree',1,'email', 'active',true,0,100])->andReturn([$dumRows,10,10]);
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 100);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    foreach ($dumRows as $row) {
      $row['count'] = intval($row['copyright_count']);
      unset($row['copyright_count']);
      $finalVal[] = $row;
    }
    $totalPages = intval(ceil(10/100));
    $expectedResponse = (new ResponseHelper())->withHeader('X-Total-Pages',$totalPages)->withJson($finalVal,200);
    $actualResponse = $this->copyrightController->getFileEmail($request, new ResponseHelper(),$args);
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }



  /**
   * @test
   * -# Test CopyrightController::getFileUrl() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testGetFileUrl()
  {
    $dumRows = $this->getDummyData()['dumRows'];
    $args = $this->getDummyData()['args'];
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'copyright_ars'])->andReturn(1);

    $this->copyrightHist->shouldReceive('getCopyrights')
      ->withArgs([$args['id'],2,'uploadtree',1,'url', 'active',true,0,100])->andReturn([$dumRows,10,10]);
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 100);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    foreach ($dumRows as $row) {
      $row['count'] = intval($row['copyright_count']);
      unset($row['copyright_count']);
      $finalVal[] = $row;
    }
    $totalPages = intval(ceil(10/100));
    $expectedResponse = (new ResponseHelper())->withHeader('X-Total-Pages',$totalPages)->withJson($finalVal,200);
    $actualResponse = $this->copyrightController->getFileUrl($request, new ResponseHelper(),$args);
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }




  /**
   * @test
   * -# Test CopyrightController::getFileAuthor() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testGetFileAuthor()
  {
    $dumRows = $this->getDummyData()['dumRows'];
    $args = $this->getDummyData()['args'];
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'copyright_ars'])->andReturn(1);

    $this->copyrightHist->shouldReceive('getCopyrights')
      ->withArgs([$args['id'],2,'uploadtree',1,'author', 'active',true,0,100])->andReturn([$dumRows,10,10]);
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 100);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    foreach ($dumRows as $row) {
      $row['count'] = intval($row['copyright_count']);
      unset($row['copyright_count']);
      $finalVal[] = $row;
    }
    $totalPages = intval(ceil(10/100));
    $expectedResponse = (new ResponseHelper())->withHeader('X-Total-Pages',$totalPages)->withJson($finalVal,200);
    $actualResponse = $this->copyrightController->getFileAuthor($request, new ResponseHelper(),$args);
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }


  /**
   * @test
   * -# Test CopyrightController::getFileEcc() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testGetFileEcc()
  {
    $dumRows = $this->getDummyData()['dumRows'];
    $args = $this->getDummyData()['args'];
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'ecc_ars'])->andReturn(1);

    $this->copyrightHist->shouldReceive('getCopyrights')
      ->withArgs([$args['id'],2,'uploadtree',1,'ecc', 'active',true,0,100])->andReturn([$dumRows,10,10]);
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 100);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    foreach ($dumRows as $row) {
      $row['count'] = intval($row['copyright_count']);
      unset($row['copyright_count']);
      $finalVal[] = $row;
    }
    $totalPages = intval(ceil(10/100));
    $expectedResponse = (new ResponseHelper())->withHeader('X-Total-Pages',$totalPages)->withJson($finalVal,200);
    $actualResponse = $this->copyrightController->getFileEcc($request, new ResponseHelper(),$args);
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }



  /**
   * @test
   * -# Test CopyrightController::getFileKeyword() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testGetFileKeyword()
  {
    $dumRows = $this->getDummyData()['dumRows'];
    $args = $this->getDummyData()['args'];
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'keyword_ars'])->andReturn(1);

    $this->copyrightHist->shouldReceive('getCopyrights')
      ->withArgs([$args['id'],2,'uploadtree',1,'keyword', 'active',true,0,100])->andReturn([$dumRows,10,10]);
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 100);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    foreach ($dumRows as $row) {
      $row['count'] = intval($row['copyright_count']);
      unset($row['copyright_count']);
      $finalVal[] = $row;
    }
    $totalPages = intval(ceil(10/100));
    $expectedResponse = (new ResponseHelper())->withHeader('X-Total-Pages',$totalPages)->withJson($finalVal,200);
    $actualResponse = $this->copyrightController->getFileKeyword($request, new ResponseHelper(),$args);
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }


  /**
   * @test
   * -# Test CopyrightController::getFileIpra() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testGetFileIpra()
  {
    $dumRows = $this->getDummyData()['dumRows'];
    $args = $this->getDummyData()['args'];
    $this->uploadDao->shouldReceive("getUploadtreeTableName")
      ->withArgs([$args['id']])->andReturn("uploadtree");
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);
    $this->copyrightHist->shouldReceive('getAgentId')
      ->withArgs([$args['id'],'ipra_ars'])->andReturn(1);

    $this->copyrightHist->shouldReceive('getCopyrights')
      ->withArgs([$args['id'],2,'uploadtree',1,'ipra', 'active',true,0,100])->andReturn([$dumRows,10,10]);
    $requestHeaders = new Headers();
    $requestHeaders->setHeader('limit', 100);
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    foreach ($dumRows as $row) {
      $row['count'] = intval($row['copyright_count']);
      unset($row['copyright_count']);
      $finalVal[] = $row;
    }
    $totalPages = intval(ceil(10/100));
    $expectedResponse = (new ResponseHelper())->withHeader('X-Total-Pages',$totalPages)->withJson($finalVal,200);
    $actualResponse = $this->copyrightController->getFileIpra($request, new ResponseHelper(),$args);
    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
    $this->assertEquals(200,$actualResponse->getStatusCode());
  }

  /**
   * @test
   * -# Test CopyrightController::deleteFileCopyright() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testDeleteFileCopyright()
  {
    $args = $this->getDummyData()['args'];
    $args['hash'] = "hash";
    $userId = 4;

    $itemTreeBounds = [
      'itemId' => 2,
      'uploadId' => 2,
      'uploadTreeTableName' => "uploadtree",
      'left' => 1,
      'right' => 31314
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);

    $this->uploadDao->shouldReceive("getUploadTreeTableName")
      ->withAnyArgs()->andReturn("uploadtree");

    $this->restHelper->shouldReceive("getUserId")
      ->andReturn($userId);
    $this->copyrightHist->shouldReceive('getTableName')
      ->withArgs(['statement'])->andReturn("statement");
    $this->uploadDao->shouldReceive("getItemTreeBounds")
      ->andReturn($itemTreeBounds);
    $this->copyrightDao->shouldReceive('updateTable');

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    $returnVal = new Info(200, "Successfully removed copyright.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($returnVal->getArray(),$returnVal->getCode());
    $actualResponse = $this->copyrightController->deleteFileCopyright($request, new ResponseHelper(),$args);

    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));

  }

  /**
   * @test
   * -# Test CopyrightController::deleteFileEmail() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testDeleteFileEmail()
  {
    $args = $this->getDummyData()['args'];
    $args['hash'] = "hash";
    $userId = 4;

    $itemTreeBounds = [
      'itemId' => 2,
      'uploadId' => 2,
      'uploadTreeTableName' => "uploadtree",
      'left' => 1,
      'right' => 31314
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);

    $this->uploadDao->shouldReceive("getUploadTreeTableName")
      ->withAnyArgs()->andReturn("uploadtree");

    $this->restHelper->shouldReceive("getUserId")
      ->andReturn($userId);
    $this->copyrightHist->shouldReceive('getTableName')
      ->withArgs(['email'])->andReturn("copyrights");
    $this->uploadDao->shouldReceive("getItemTreeBounds")
      ->andReturn($itemTreeBounds);
    $this->copyrightDao->shouldReceive('updateTable');

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    $returnVal = new Info(200, "Successfully removed email.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($returnVal->getArray(),$returnVal->getCode());
    $actualResponse = $this->copyrightController->deleteFileEmail($request, new ResponseHelper(),$args);

    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));

  }
  /**
   * @test
   * -# Test CopyrightController::deleteFileEmail() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testDeleteFileUrl()
  {
    $args = $this->getDummyData()['args'];
    $args['hash'] = "hash";
    $userId = 4;

    $itemTreeBounds = [
      'itemId' => 2,
      'uploadId' => 2,
      'uploadTreeTableName' => "uploadtree",
      'left' => 1,
      'right' => 31314
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);

    $this->uploadDao->shouldReceive("getUploadTreeTableName")
      ->withAnyArgs()->andReturn("uploadtree");

    $this->restHelper->shouldReceive("getUserId")
      ->andReturn($userId);
    $this->copyrightHist->shouldReceive('getTableName')
      ->withArgs(['url'])->andReturn("copyrights");
    $this->uploadDao->shouldReceive("getItemTreeBounds")
      ->andReturn($itemTreeBounds);
    $this->copyrightDao->shouldReceive('updateTable');

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    $returnVal = new Info(200, "Successfully removed url.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($returnVal->getArray(),$returnVal->getCode());
    $actualResponse = $this->copyrightController->deleteFileUrl($request, new ResponseHelper(),$args);

    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));

  }



  /**
   * @test
   * -# Test CopyrightController::deleteFileAuthor() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testDeleteFileAuthor()
  {
    $args = $this->getDummyData()['args'];
    $args['hash'] = "hash";
    $userId = 4;

    $itemTreeBounds = [
      'itemId' => 2,
      'uploadId' => 2,
      'uploadTreeTableName' => "uploadtree",
      'left' => 1,
      'right' => 31314
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);

    $this->uploadDao->shouldReceive("getUploadTreeTableName")
      ->withAnyArgs()->andReturn("uploadtree");

    $this->restHelper->shouldReceive("getUserId")
      ->andReturn($userId);
    $this->copyrightHist->shouldReceive('getTableName')
      ->withArgs(['author'])->andReturn("copyrights");
    $this->uploadDao->shouldReceive("getItemTreeBounds")
      ->andReturn($itemTreeBounds);
    $this->copyrightDao->shouldReceive('updateTable');

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    $returnVal = new Info(200, "Successfully removed author.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($returnVal->getArray(),$returnVal->getCode());
    $actualResponse = $this->copyrightController->deleteFileAuthor($request, new ResponseHelper(),$args);

    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));

  }
  /**
   * @test
   * -# Test CopyrightController::deleteFileEcc() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testDeleteFileEcc()
  {
    $args = $this->getDummyData()['args'];
    $args['hash'] = "hash";
    $userId = 4;

    $itemTreeBounds = [
      'itemId' => 2,
      'uploadId' => 2,
      'uploadTreeTableName' => "uploadtree",
      'left' => 1,
      'right' => 31314
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);

    $this->uploadDao->shouldReceive("getUploadTreeTableName")
      ->withAnyArgs()->andReturn("uploadtree");

    $this->restHelper->shouldReceive("getUserId")
      ->andReturn($userId);
    $this->copyrightHist->shouldReceive('getTableName')
      ->withArgs(['ecc'])->andReturn("copyrights");
    $this->uploadDao->shouldReceive("getItemTreeBounds")
      ->andReturn($itemTreeBounds);
    $this->copyrightDao->shouldReceive('updateTable');

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    $returnVal = new Info(200, "Successfully removed ecc.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($returnVal->getArray(),$returnVal->getCode());
    $actualResponse = $this->copyrightController->deleteFileEcc($request, new ResponseHelper(),$args);

    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));

  }

  /**
   * @test
   * -# Test CopyrightController::deleteFileKeyword() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testDeleteFileKeyword()
  {
    $args = $this->getDummyData()['args'];
    $args['hash'] = "hash";
    $userId = 4;

    $itemTreeBounds = [
      'itemId' => 2,
      'uploadId' => 2,
      'uploadTreeTableName' => "uploadtree",
      'left' => 1,
      'right' => 31314
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);

    $this->uploadDao->shouldReceive("getUploadTreeTableName")
      ->withAnyArgs()->andReturn("uploadtree");

    $this->restHelper->shouldReceive("getUserId")
      ->andReturn($userId);
    $this->copyrightHist->shouldReceive('getTableName')
      ->withArgs(['keyword'])->andReturn("copyrights");
    $this->uploadDao->shouldReceive("getItemTreeBounds")
      ->andReturn($itemTreeBounds);
    $this->copyrightDao->shouldReceive('updateTable');

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    $returnVal = new Info(200, "Successfully removed keyword.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($returnVal->getArray(),$returnVal->getCode());
    $actualResponse = $this->copyrightController->deleteFileKeyword($request, new ResponseHelper(),$args);

    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
  }



  /**
   * @test
   * -# Test CopyrightController::deleteFileIpra() with success.
   * -# Check if the actual and expected responses are same.
   * -# Check if the status code is 200
   */
  public function testDeleteFileIpra()
  {
    $args = $this->getDummyData()['args'];
    $args['hash'] = "hash";
    $userId = 4;

    $itemTreeBounds = [
      'itemId' => 2,
      'uploadId' => 2,
      'uploadTreeTableName' => "uploadtree",
      'left' => 1,
      'right' => 31314
    ];
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['uploadtree','uploadtree_pk', $args['itemId']])->andReturn(true);
    $this->dbHelper->shouldReceive("doesIdExist")
      ->withArgs(['upload','upload_pk', $args['id']])->andReturn(true);
    $this->restHelper->shouldReceive("getGroupId")
      ->andReturn(4);
    $this->uploadDao->shouldReceive("isAccessible")
      ->withArgs([$args['id'],4])->andReturn(true);

    $this->uploadDao->shouldReceive("getUploadTreeTableName")
      ->withAnyArgs()->andReturn("uploadtree");

    $this->restHelper->shouldReceive("getUserId")
      ->andReturn($userId);
    $this->copyrightHist->shouldReceive('getTableName')
      ->withArgs(['ipra'])->andReturn("copyrights");
    $this->uploadDao->shouldReceive("getItemTreeBounds")
      ->andReturn($itemTreeBounds);
    $this->copyrightDao->shouldReceive('updateTable');

    $requestHeaders = new Headers();
    $body = $this->streamFactory->createStream();
    $request = new Request("GET", new Uri("HTTP", "localhost"),
      $requestHeaders, [], [], $body);
    $request = $request->withQueryParams(["status" => "active"]);

    $returnVal = new Info(200, "Successfully removed ipra.", InfoType::INFO);
    $expectedResponse = (new ResponseHelper())->withJson($returnVal->getArray(),$returnVal->getCode());
    $actualResponse = $this->copyrightController->deleteFileIpra($request, new ResponseHelper(),$args);

    $this->assertEquals($this->getResponseJson($expectedResponse), $this->getResponseJson($actualResponse));
  }

}
