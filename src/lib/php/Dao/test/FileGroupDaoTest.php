<?php
/*
 SPDX-FileCopyrightText: © 2024 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Mockery as M;
use Monolog\Logger;

/**
 * @class FileGroupDaoTest
 * @brief Unit tests for FileGroupDao using Mockery mocks (no real DB required).
 */
class FileGroupDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbManager|M\MockInterface */
  private $dbManager;

  /** @var Logger|M\MockInterface */
  private $logger;

  /** @var FileGroupDao */
  private $fileGroupDao;

  private int $uploadId   = 1;
  private int $groupId    = 2;
  private int $userId     = 3;
  private int $fgPk       = 10;
  private int $uploadtree = 100;

  protected function setUp(): void
  {
    $this->dbManager    = M::mock(DbManager::class);
    $this->logger       = M::mock(Logger::class);
    $this->fileGroupDao = new FileGroupDao($this->dbManager, $this->logger);
  }

  protected function tearDown(): void
  {
    M::close();
  }

  // ─── createGroup ───────────────────────────────────────────────────────────

  /**
   * @test
   * createGroup() should prepare/execute INSERT and return the new fg_pk.
   */
  public function testCreateGroupReturnsFgPk(): void
  {
    $this->dbManager->shouldReceive('booleanToDb')->with(true)->andReturn('t');
    $this->dbManager->shouldReceive('prepare')
      ->once()
      ->withArgs([M::any(), M::on(fn($s) => str_contains($s, 'INSERT INTO file_group'))]);
    $this->dbManager->shouldReceive('execute')
      ->once()
      ->andReturn('res');
    $this->dbManager->shouldReceive('fetchArray')
      ->once()
      ->andReturn(['fg_pk' => $this->fgPk]);
    $this->dbManager->shouldReceive('freeResult')->once();

    $result = $this->fileGroupDao->createGroup(
      $this->uploadId, $this->groupId, $this->userId, 'Test Group'
    );

    $this->assertSame($this->fgPk, $result);
  }

  // ─── deleteGroup ───────────────────────────────────────────────────────────

  /**
   * @test
   * deleteGroup() should run a DELETE query with the correct fg_pk.
   */
  public function testDeleteGroupRunsDeleteQuery(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->withArgs([
        M::on(fn($s) => str_contains($s, 'DELETE FROM file_group')),
        [$this->fgPk],
        M::any()
      ]);

    $this->fileGroupDao->deleteGroup($this->fgPk);

    // assertion is the Mockery shouldReceive expectation
    $this->addToAssertionCount(1);
  }

  // ─── addMembers ────────────────────────────────────────────────────────────

  /**
   * @test
   * addMembers() with empty array should not make any DB calls.
   */
  public function testAddMembersWithEmptyArrayDoesNothing(): void
  {
    $this->dbManager->shouldNotReceive('prepare');
    $this->dbManager->shouldNotReceive('execute');

    $this->fileGroupDao->addMembers($this->fgPk, []);
    $this->addToAssertionCount(1);
  }

  /**
   * @test
   * addMembers() with two IDs should execute the INSERT twice.
   */
  public function testAddMembersCallsInsertForEachItem(): void
  {
    $this->dbManager->shouldReceive('prepare')->once();
    $this->dbManager->shouldReceive('execute')
      ->twice()
      ->andReturn('res');
    $this->dbManager->shouldReceive('freeResult')->twice();

    $this->fileGroupDao->addMembers($this->fgPk, [101, 102]);
    $this->addToAssertionCount(1);
  }

  // ─── removeMember ──────────────────────────────────────────────────────────

  /**
   * @test
   * removeMember() should execute DELETE with fgPk and uploadtreePk.
   */
  public function testRemoveMemberExecutesDeleteWithCorrectParams(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->withArgs([
        M::on(fn($s) => str_contains($s, 'DELETE FROM file_group_member')),
        [$this->fgPk, $this->uploadtree],
        M::any()
      ]);

    $this->fileGroupDao->removeMember($this->fgPk, $this->uploadtree);
    $this->addToAssertionCount(1);
  }

  // ─── getGroupsForUpload ────────────────────────────────────────────────────

  /**
   * @test
   * getGroupsForUpload() should return the rows from getRows().
   */
  public function testGetGroupsForUploadReturnsRows(): void
  {
    $expected = [
      ['fg_pk' => 1, 'fg_name' => 'Group A', 'member_count' => '2'],
    ];

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->withArgs([M::any(), [$this->uploadId, $this->groupId], M::any()])
      ->andReturn($expected);

    $result = $this->fileGroupDao->getGroupsForUpload($this->uploadId, $this->groupId);

    $this->assertSame($expected, $result);
  }

  // ─── getGroupById ──────────────────────────────────────────────────────────

  /**
   * @test
   * getGroupById() should return a single row.
   */
  public function testGetGroupByIdReturnsSingleRow(): void
  {
    $expected = ['fg_pk' => $this->fgPk, 'fg_name' => 'Test'];

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->withArgs([M::any(), [$this->fgPk], M::any()])
      ->andReturn($expected);

    $result = $this->fileGroupDao->getGroupById($this->fgPk);
    $this->assertSame($expected, $result);
  }

  // ─── getGroupMembers ───────────────────────────────────────────────────────

  /**
   * @test
   * getGroupMembers() should query the specified uploadtree table.
   */
  public function testGetGroupMembersQueriesCorrectTable(): void
  {
    $table    = 'uploadtree_a';
    $expected = [['uploadtree_pk' => 200, 'ufile_name' => 'file.c', 'pfile_fk' => 50]];

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->withArgs([
        M::on(fn($s) => str_contains($s, "\"$table\"")),
        [$this->fgPk],
        M::any()
      ])
      ->andReturn($expected);

    $result = $this->fileGroupDao->getGroupMembers($this->fgPk, $table);
    $this->assertSame($expected, $result);
  }

  // ─── updateGroup ───────────────────────────────────────────────────────────

  /**
   * @test
   * updateGroup() with only name change should build correct SET clause.
   */
  public function testUpdateGroupWithNameOnly(): void
  {
    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->withArgs([
        M::on(fn($s) => str_contains($s, 'UPDATE file_group') &&
                        str_contains($s, '"fg_name"')),
        M::any(),
        M::any()
      ]);

    $this->fileGroupDao->updateGroup($this->fgPk, 'New Name');
    $this->addToAssertionCount(1);
  }

  // ─── suggestGroups ─────────────────────────────────────────────────────────

  /**
   * @test
   * suggestGroups() should pass uploadId and use the given table name.
   */
  public function testSuggestGroupsPassesCorrectParams(): void
  {
    $table    = 'uploadtree_a';
    $expected = [
      ['file_list' => '{101,102}', 'license_set' => '{5,7}', 'member_count' => '2'],
    ];

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->withArgs([
        M::on(fn($s) => str_contains($s, "\"$table\"")),
        [$this->uploadId],
        M::any()
      ])
      ->andReturn($expected);

    $result = $this->fileGroupDao->suggestGroups($this->uploadId, $table);
    $this->assertSame($expected, $result);
  }

  // ─── getAllGroupDataForUpload ───────────────────────────────────────────────

  /**
   * @test
   * getAllGroupDataForUpload() should join file_group and file_group_member.
   */
  public function testGetAllGroupDataForUploadJoinsBothTables(): void
  {
    $expected = [
      ['fg_pk' => 1, 'fg_name' => 'G', 'uploadtree_fk' => 101],
    ];

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->withArgs([
        M::on(fn($s) => str_contains($s, 'file_group_member')),
        [$this->uploadId],
        M::any()
      ])
      ->andReturn($expected);

    $result = $this->fileGroupDao->getAllGroupDataForUpload($this->uploadId);
    $this->assertSame($expected, $result);
  }
}
