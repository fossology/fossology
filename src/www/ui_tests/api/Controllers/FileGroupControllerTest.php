<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Tests for FileGroupController (Issue #2847)
 */

namespace Fossology\UI\Api\Test\Controllers;

require_once dirname(__DIR__, 4) . '/lib/php/Plugin/FO_Plugin.php';

use Fossology\Lib\Dao\FileGroupDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Controllers\FileGroupController;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Mockery as M;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

/**
 * @class FileGroupControllerTest
 * @brief PHPUnit tests for FileGroupController
 */
class FileGroupControllerTest extends \PHPUnit\Framework\TestCase
{
  /** @var integer $assertCountBefore */
  private $assertCountBefore;

  /** @var DbHelper|M\MockInterface $dbHelper */
  private $dbHelper;

  /** @var RestHelper|M\MockInterface $restHelper */
  private $restHelper;

  /** @var FileGroupDao|M\MockInterface $fileGroupDao */
  private $fileGroupDao;

  /** @var UploadDao|M\MockInterface $uploadDao */
  private $uploadDao;

  /** @var DbManager|M\MockInterface $dbManager */
  private $dbManager;

  /** @var FileGroupController $fileGroupController */
  private $fileGroupController;

  /** @var StreamFactory $streamFactory */
  private $streamFactory;

  // ── Fixtures ───────────────────────────────────────────────────────────────

  private $uploadId   = 1;
  private $groupId    = 2;
  private $userId     = 3;
  private $fgPk       = 10;
  private $uploadtree = 100;

  /**
   * @brief Setup mocks before each test
   */
  protected function setUp(): void
  {
    global $container;
    $container = M::mock('ContainerBuilder');

    $this->dbHelper      = M::mock(DbHelper::class);
    $this->restHelper    = M::mock(RestHelper::class);
    $this->fileGroupDao  = M::mock(FileGroupDao::class);
    $this->uploadDao     = M::mock(UploadDao::class);
    $this->dbManager     = M::mock(DbManager::class);
    $this->streamFactory = new StreamFactory();

    $this->restHelper->shouldReceive('getDbHelper')->andReturn($this->dbHelper);
    $this->dbHelper->shouldReceive('getDbManager')->andReturn($this->dbManager);

    $container->shouldReceive('get')
      ->withArgs(['helper.restHelper'])->andReturn($this->restHelper);
    $container->shouldReceive('get')
      ->withArgs(['dao.filegroup'])->andReturn($this->fileGroupDao);
    $container->shouldReceive('get')
      ->withArgs(['dao.upload'])->andReturn($this->uploadDao);

    $this->fileGroupController = new FileGroupController($container);
    $this->assertCountBefore   = \Hamcrest\MatcherAssert::getCount();
  }

  /** @brief Tear down mocks after each test */
  protected function tearDown(): void
  {
    $this->addToAssertionCount(
      \Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore
    );
    M::close();
  }

  /**
   * Build a Slim Request with optional JSON body.
   */
  private function makeRequest(string $method = 'GET', ?array $body = null): Request
  {
    $headers = new Headers();
    if ($body !== null) {
      $headers->setHeader('Content-Type', 'application/json');
      $stream = $this->streamFactory->createStream(json_encode($body));
    } else {
      $stream = $this->streamFactory->createStream('');
    }
    return new Request($method, new Uri('HTTP', 'localhost'), $headers, [], [], $stream);
  }

  /** Helper: read JSON from a response */
  private function getResponseJson(ResponseHelper $response): array
  {
    $response->getBody()->seek(0);
    return json_decode($response->getBody()->getContents(), true);
  }

  // ── getGroups ──────────────────────────────────────────────────────────────

  /**
   * @test
   * GET /uploads/{id}/filegroups → 200 with an array of groups
   */
  public function testGetGroupsReturnsGroupList(): void
  {
    $rows = [
      [
        'fg_pk'              => $this->fgPk,
        'fg_name'            => 'MIT group',
        'fg_curation_notes'  => 'All good',
        'fg_include_in_report' => 't',
        'member_count'       => '3',
        'date_created'       => '2024-01-01T00:00:00+00:00',
        'date_modified'      => null,
      ]
    ];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->fileGroupDao->shouldReceive('getGroupsForUpload')
      ->withArgs([$this->uploadId, $this->groupId])->andReturn($rows);
    $this->dbManager->shouldReceive('booleanFromDb')->with('t')->andReturn(true);

    $actualResponse = $this->fileGroupController->getGroups(
      $this->makeRequest(), new ResponseHelper(), ['id' => $this->uploadId]
    );

    $this->assertEquals(200, $actualResponse->getStatusCode());
    $body = $this->getResponseJson($actualResponse);
    $this->assertIsArray($body);
    $this->assertCount(1, $body);
    $this->assertEquals('MIT group', $body[0]['name']);
  }

  // ── createGroup ────────────────────────────────────────────────────────────

  /**
   * @test
   * POST /uploads/{id}/filegroups → 201 with new group id
   */
  public function testCreateGroupReturns201(): void
  {
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->restHelper->shouldReceive('getUserId')->andReturn($this->userId);
    $this->dbManager->shouldReceive('booleanToDb')->with(true)->andReturn('t');
    $this->dbManager->shouldReceive('prepare')->once();
    $this->dbManager->shouldReceive('execute')
      ->andReturn('result');
    $this->dbManager->shouldReceive('fetchArray')
      ->andReturn(['fg_pk' => $this->fgPk]);
    $this->dbManager->shouldReceive('freeResult')->once();

    $request = $this->makeRequest('POST', [
      'name'            => 'GPL group',
      'curationNotes'   => 'Verified',
      'includeInReport' => true,
    ]);

    $actualResponse = $this->fileGroupController->createGroup(
      $request, new ResponseHelper(), ['id' => $this->uploadId]
    );

    $this->assertEquals(201, $actualResponse->getStatusCode());
    $body = $this->getResponseJson($actualResponse);
    $this->assertEquals($this->fgPk, $body['message']);
  }

  /**
   * @test
   * POST /uploads/{id}/filegroups without 'name' → 400
   */
  public function testCreateGroupWithoutNameThrows400(): void
  {
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);

    $request = $this->makeRequest('POST', ['curationNotes' => 'no name']);

    $this->expectException(HttpBadRequestException::class);

    $this->fileGroupController->createGroup(
      $request, new ResponseHelper(), ['id' => $this->uploadId]
    );
  }

  // ── updateGroup ────────────────────────────────────────────────────────────

  /**
   * @test
   * PATCH /uploads/{id}/filegroups/{fgId} → 200 on success
   */
  public function testUpdateGroupReturns200(): void
  {
    $groupRow = [
      'fg_pk'    => $this->fgPk,
      'upload_fk' => $this->uploadId,
      'group_fk'  => $this->groupId,
    ];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);
    $this->fileGroupDao->shouldReceive('getGroupById')
      ->withArgs([$this->fgPk])->andReturn($groupRow);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->fileGroupDao->shouldReceive('updateGroup')
      ->withArgs([$this->fgPk, 'New name', null, null])->once();

    $request = $this->makeRequest('PATCH', ['name' => 'New name']);

    $actualResponse = $this->fileGroupController->updateGroup(
      $request, new ResponseHelper(),
      ['id' => $this->uploadId, 'fgId' => $this->fgPk]
    );

    $this->assertEquals(200, $actualResponse->getStatusCode());
  }

  // ── deleteGroup ────────────────────────────────────────────────────────────

  /**
   * @test
   * DELETE /uploads/{id}/filegroups/{fgId} → 200
   */
  public function testDeleteGroupReturns200(): void
  {
    $groupRow = [
      'fg_pk'    => $this->fgPk,
      'upload_fk' => $this->uploadId,
      'group_fk'  => $this->groupId,
    ];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);
    $this->fileGroupDao->shouldReceive('getGroupById')
      ->withArgs([$this->fgPk])->andReturn($groupRow);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->fileGroupDao->shouldReceive('deleteGroup')
      ->withArgs([$this->fgPk])->once();

    $actualResponse = $this->fileGroupController->deleteGroup(
      $this->makeRequest('DELETE'), new ResponseHelper(),
      ['id' => $this->uploadId, 'fgId' => $this->fgPk]
    );

    $this->assertEquals(200, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * DELETE with wrong group ownership → 403
   */
  public function testDeleteGroupThrows403WhenWrongOwner(): void
  {
    $groupRow = [
      'fg_pk'    => $this->fgPk,
      'upload_fk' => $this->uploadId,
      'group_fk'  => 999, // different group
    ];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);
    $this->fileGroupDao->shouldReceive('getGroupById')
      ->withArgs([$this->fgPk])->andReturn($groupRow);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);

    $this->expectException(HttpForbiddenException::class);

    $this->fileGroupController->deleteGroup(
      $this->makeRequest('DELETE'), new ResponseHelper(),
      ['id' => $this->uploadId, 'fgId' => $this->fgPk]
    );
  }

  // ── addMembers ─────────────────────────────────────────────────────────────

  /**
   * @test
   * POST .../members → 200 with count message
   */
  public function testAddMembersReturns200(): void
  {
    $groupRow = [
      'fg_pk' => $this->fgPk, 'upload_fk' => $this->uploadId,
      'group_fk' => $this->groupId,
    ];
    $members  = [101, 102, 103];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);
    $this->fileGroupDao->shouldReceive('getGroupById')
      ->withArgs([$this->fgPk])->andReturn($groupRow);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);
    $this->fileGroupDao->shouldReceive('addMembers')
      ->withArgs([$this->fgPk, $members])->once();

    $request = $this->makeRequest('POST', ['members' => $members]);

    $actualResponse = $this->fileGroupController->addMembers(
      $request, new ResponseHelper(),
      ['id' => $this->uploadId, 'fgId' => $this->fgPk]
    );

    $this->assertEquals(200, $actualResponse->getStatusCode());
  }

  /**
   * @test
   * POST .../members without 'members' key → 400
   */
  public function testAddMembersWithoutMembersKeyThrows400(): void
  {
    $groupRow = [
      'fg_pk' => $this->fgPk, 'upload_fk' => $this->uploadId,
      'group_fk' => $this->groupId,
    ];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);
    $this->fileGroupDao->shouldReceive('getGroupById')
      ->withArgs([$this->fgPk])->andReturn($groupRow);
    $this->restHelper->shouldReceive('getGroupId')->andReturn($this->groupId);

    $this->expectException(HttpBadRequestException::class);

    $this->fileGroupController->addMembers(
      $this->makeRequest('POST'), new ResponseHelper(),
      ['id' => $this->uploadId, 'fgId' => $this->fgPk]
    );
  }

  // ── suggestGroups ──────────────────────────────────────────────────────────

  /**
   * @test
   * GET .../suggest → 200 with candidate list
   */
  public function testSuggestGroupsReturns200(): void
  {
    $suggestions = [
      ['file_list' => '{101,102}', 'license_set' => '{5,7}', 'member_count' => '2'],
    ];

    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', $this->uploadId])->andReturn(true);
    $this->uploadDao->shouldReceive('getUploadtreeTableName')
      ->withArgs([$this->uploadId])->andReturn('uploadtree_a');
    $this->fileGroupDao->shouldReceive('suggestGroups')
      ->withArgs([$this->uploadId, 'uploadtree_a'])->andReturn($suggestions);

    $actualResponse = $this->fileGroupController->suggestGroups(
      $this->makeRequest(), new ResponseHelper(), ['id' => $this->uploadId]
    );

    $this->assertEquals(200, $actualResponse->getStatusCode());
    $body = $this->getResponseJson($actualResponse);
    $this->assertCount(1, $body);
    $this->assertEquals([101, 102], $body[0]['fileList']);
    $this->assertEquals([5, 7],    $body[0]['licenseSet']);
    $this->assertEquals(2,         $body[0]['memberCount']);
  }

  /**
   * @test
   * GET .../suggest on non-existent upload → 404
   */
  public function testSuggestGroupsThrows404(): void
  {
    $this->dbHelper->shouldReceive('doesIdExist')
      ->withArgs(['upload', 'upload_pk', 9999])->andReturn(false);

    $this->expectException(HttpNotFoundException::class);

    $this->fileGroupController->suggestGroups(
      $this->makeRequest(), new ResponseHelper(), ['id' => 9999]
    );
  }
}
