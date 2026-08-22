<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Reuse;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Test\TestPgDb;
use Mockery as M;

require_once(__DIR__ . '/../ReuseAutoDetect.php');

class ReuseAutoDetectTest extends \PHPUnit\Framework\TestCase
{
  /** @var TestPgDb */
  private $testDb;
  /** @var DbManager */
  private $dbManager;
  /** @var int */
  private $now;
  /** @var int */
  private $assertCountBefore;

  protected function setUp(): void
  {
    $this->testDb = new TestPgDb();
    $this->dbManager = &$this->testDb->getDbManager();
    $this->testDb->createPlainTables([
        'clearing_decision',
        'uploadtree',
        'upload',
        'upload_clearing',
        'group_user_member',
        'users'
    ]);
    $this->now = time();
    $this->assertCountBefore = \Hamcrest\MatcherAssert::getCount();
  }

  protected function tearDown(): void
  {
    $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount() - $this->assertCountBefore);
    $this->testDb = null;
    $this->dbManager = null;
  }

  /**
   * Build a ClearingDao mock that returns a fixed coverage map.
   * @param array $coverageByGroup groupId => [uploadId => ['total'=>, 'cleared'=>]]
   */
  private function mockClearingDao($coverageByGroup)
  {
    $clearingDao = M::mock(ClearingDao::class);
    $clearingDao->shouldReceive('getClearingCoverage')
      ->with(M::any(), M::any())
      ->andReturnUsing(function ($ids, $groupId) use ($coverageByGroup) {
        $map = isset($coverageByGroup[$groupId]) ? $coverageByGroup[$groupId] : [];
        $result = [];
        foreach ($ids as $id) {
          $result[$id] = isset($map[$id]) ? $map[$id] : ['total' => 0, 'cleared' => 0];
        }
        return $result;
      });
    return $clearingDao;
  }

  private function uploadOrder($candidates)
  {
    return array_map(function ($c) {
      return $c['uploadId'];
    }, $candidates);
  }

  private function makeCandidate($uploadId, $versionParts, $prerelease = 0, $clearedAt = null, $timestamp = 0, $groupId = 601)
  {
    return [
      'uploadId' => $uploadId,
      'groupId' => $groupId,
      'versionParts' => $versionParts,
      'prerelease' => $prerelease,
      'clearedAt' => $clearedAt,
      'timestamp' => $timestamp
    ];
  }

  /* ---------------------------------------------------------------------
   * parsePackageName
   * --------------------------------------------------------------------- */

  public function testParseEmptyFilename()
  {
    $parsed = ReuseAutoDetect::parsePackageName('');
    assertThat($parsed['baseName'], is(''));
    assertThat($parsed['versionParts'], is(nullValue()));
  }

  public function testParseNoVersionArchive()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo.tar.gz');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(nullValue()));
  }

  public function testParseSimpleVersion()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-1.0.tar.gz');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(1, 0)));
    assertThat($parsed['prerelease'], is(0));
  }

  public function testParseVPrefixVersion()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-v2.3.4.zip');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(2, 3, 4)));
  }

  public function testParseUnderscoreSeparator()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo_1.0.0.tar.bz2');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(1, 0, 0)));
  }

  public function testParsePrereleaseSuffix()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-1.0.0-rc1.tar.gz');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(1, 0, 0)));
    assertThat($parsed['prerelease'], is(1));
  }

  public function testParseUnderscorePrereleaseSuffix()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-2.0-beta2.tar.gz');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(2, 0)));
    assertThat($parsed['prerelease'], is(1));
  }

  public function testParseDateStampOnly()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-20240101.tar.gz');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(nullValue()));
  }

  public function testParseVersionAndDateStamp()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-1.2-20240101.tar.gz');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(1, 2)));
  }

  public function testParseDashedNameWithoutVersion()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-lib.tar.gz');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(nullValue()));
  }

  public function testParseSingleComponentVersion()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-3.tar.gz');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(3)));
  }

  public function testParseTripleExtension()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-1.1.tar.gz.tar');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(1, 1)));
  }

  public function testParseUppercaseExtension()
  {
    $parsed = ReuseAutoDetect::parsePackageName('foo-1.0.TGZ');
    assertThat($parsed['baseName'], is('foo'));
    assertThat($parsed['versionParts'], is(array(1, 0)));
  }

  /* ---------------------------------------------------------------------
   * compareVersionCloseness
   * --------------------------------------------------------------------- */

  public function testCompareCloserMinorVersionWins()
  {
    // requested 1.0: 0.9 is closer than 0.8 (the spec's worked example)
    $cmp = ReuseAutoDetect::compareVersionCloseness([1, 0], [0, 9], [0, 8], 2, 10);
    assertThat($cmp, lessThan(0));
    $reverse = ReuseAutoDetect::compareVersionCloseness([1, 0], [0, 8], [0, 9], 2, 10);
    assertThat($reverse, greaterThan(0));
  }

  public function testCompareExactMatchClosest()
  {
    $cmp = ReuseAutoDetect::compareVersionCloseness([1, 0], [1, 0], [0, 9], 2, 10);
    assertThat($cmp, lessThan(0));
  }

  public function testCompareEquidistantVersionsTie()
  {
    $cmp = ReuseAutoDetect::compareVersionCloseness([1, 5], [1, 4], [1, 6], 2, 7);
    assertThat($cmp, is(0));
  }

  public function testCompareCrossMajor()
  {
    // requested 2.0: 2.0 closer than 1.9.9
    $cmp = ReuseAutoDetect::compareVersionCloseness([2, 0], [2, 0], [1, 9, 9], 3, 10);
    assertThat($cmp, lessThan(0));
  }

  public function testCompareNullRequestedTies()
  {
    assertThat(ReuseAutoDetect::compareVersionCloseness(null, [1, 0], [2, 0], 2, 10), is(0));
  }

  public function testCompareNullCandidateFarthest()
  {
    assertThat(ReuseAutoDetect::compareVersionCloseness([1, 0], null, [0, 9], 2, 10), greaterThan(0));
    assertThat(ReuseAutoDetect::compareVersionCloseness([1, 0], [0, 9], null, 2, 10), lessThan(0));
    assertThat(ReuseAutoDetect::compareVersionCloseness([1, 0], null, null, 2, 10), is(0));
  }

  /* ---------------------------------------------------------------------
   * rankCandidates - the duplicate-upload / selection scenarios
   * --------------------------------------------------------------------- */

  public function testRankMultipleVersionsOfSamePackage()
  {
    // The spec example: upload-1.0, upload-0.9, upload-0.8, upload-0.7
    $candidates = [
      $this->makeCandidate(4, [0, 7], 0, null, 400),
      $this->makeCandidate(3, [0, 8], 0, null, 300),
      $this->makeCandidate(2, [0, 9], 0, null, 200),
      $this->makeCandidate(1, [1, 0], 0, null, 100),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [1, 0]);
    assertThat($this->uploadOrder($candidates), is(array(1, 2, 3, 4)));
  }

  public function testRankWithoutExactVersionMatch()
  {
    $candidates = [
      $this->makeCandidate(3, [0, 7], 0, null, 300),
      $this->makeCandidate(2, [0, 8], 0, null, 200),
      $this->makeCandidate(1, [0, 9], 0, null, 100),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [1, 0]);
    assertThat($this->uploadOrder($candidates), is(array(1, 2, 3)));
  }

  public function testRankDuplicateCandidateEntries()
  {
    // Same upload listed twice plus a weaker version
    $candidates = [
      $this->makeCandidate(1, [1, 0], 0, null, 100),
      $this->makeCandidate(2, [0, 9], 0, null, 200),
      $this->makeCandidate(1, [1, 0], 0, null, 100),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [1, 0]);
    $order = $this->uploadOrder($candidates);
    assertThat($order[0], is(1));
    assertThat($order[1], is(1));
    assertThat($order[2], is(2));
  }

  public function testRankVersionDistanceTieBreaksByClearedAt()
  {
    // 1.1 and 0.9 are both 0.1 away from 1.0 -> clearedAt decides
    $candidates = [
      $this->makeCandidate(1, [1, 1], 0, '2020-01-01 00:00:00', 999999999),
      $this->makeCandidate(2, [0, 9], 0, '2024-01-01 00:00:00', 100),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [1, 0]);
    assertThat($this->uploadOrder($candidates), is(array(2, 1)));
  }

  public function testRankVersionTieClearedAtBeatsTimestamp()
  {
    $candidates = [
      $this->makeCandidate(1, [1, 0], 0, '2020-01-01 00:00:00', 999999999),
      $this->makeCandidate(2, [1, 0], 0, '2024-01-01 00:00:00', 100),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [1, 0]);
    assertThat($this->uploadOrder($candidates), is(array(2, 1)));
  }

  public function testRankReleaseBeatsPrerelease()
  {
    $candidates = [
      $this->makeCandidate(1, [1, 1], 1, null, 100),
      $this->makeCandidate(2, [1, 0], 0, null, 200),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [1, 0]);
    assertThat($this->uploadOrder($candidates), is(array(2, 1)));
  }

  public function testRankVersionlessCandidateLast()
  {
    $candidates = [
      $this->makeCandidate(1, [1, 0], 0, null, 100),
      $this->makeCandidate(2, null, 0, null, 500),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [1, 0]);
    assertThat($this->uploadOrder($candidates), is(array(1, 2)));
  }

  public function testRankNoRequestedVersionSortsByDate()
  {
    $candidates = [
      $this->makeCandidate(1, [1, 0], 0, null, 100),
      $this->makeCandidate(2, [2, 0], 0, null, 200),
    ];
    ReuseAutoDetect::rankCandidates($candidates, null);
    assertThat($this->uploadOrder($candidates), is(array(2, 1)));
  }

  public function testRankVersionTieFallsBackToTimestamp()
  {
    $candidates = [
      $this->makeCandidate(1, [1, 0], 0, null, 100),
      $this->makeCandidate(2, [1, 0], 0, null, 200),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [1, 0]);
    assertThat($this->uploadOrder($candidates), is(array(2, 1)));
  }

  public function testRankCrossMajorVersions()
  {
    $candidates = [
      $this->makeCandidate(1, [1, 9, 9], 0, null, 200),
      $this->makeCandidate(2, [2, 0], 0, null, 100),
    ];
    ReuseAutoDetect::rankCandidates($candidates, [2, 0]);
    assertThat($this->uploadOrder($candidates), is(array(2, 1)));
  }

  /* ---------------------------------------------------------------------
   * selectWinnerByClearing
   * --------------------------------------------------------------------- */

  public function testSelectWinnerFullyClearedWinsEvenIfNotRankFirst()
  {
    $coverage = [601 => [
      101 => ['total' => 2, 'cleared' => 2],
      102 => ['total' => 3, 'cleared' => 0]
    ]];
    $candidates = [
      $this->makeCandidate(102, null, 0, null, 0),
      $this->makeCandidate(101, null, 0, null, 0),
    ];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(101));
  }

  public function testSelectWinnerHighestRatioWins()
  {
    $coverage = [601 => [
      101 => ['total' => 2, 'cleared' => 1],
      102 => ['total' => 3, 'cleared' => 1]
    ]];
    $candidates = [
      $this->makeCandidate(101, null, 0, null, 0),
      $this->makeCandidate(102, null, 0, null, 0),
    ];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(101));
  }

  public function testSelectWinnerFallsBackToFirstCandidate()
  {
    $coverage = [601 => [
      101 => ['total' => 0, 'cleared' => 0],
      102 => ['total' => 0, 'cleared' => 0]
    ]];
    $candidates = [
      $this->makeCandidate(102, null, 0, null, 0),
      $this->makeCandidate(101, null, 0, null, 0),
    ];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(102));
  }

  public function testSelectWinnerEmptyCandidatesReturnsNull()
  {
    assertThat(ReuseAutoDetect::selectWinnerByClearing([], $this->mockClearingDao([])), is(nullValue()));
  }

  public function testSelectWinnerSingleCandidate()
  {
    $coverage = [601 => [101 => ['total' => 5, 'cleared' => 0]]];
    $candidates = [$this->makeCandidate(101, null, 0, null, 0)];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(101));
  }

  public function testSelectWinnerTiedRatioPicksRankFirst()
  {
    $coverage = [601 => [
      101 => ['total' => 2, 'cleared' => 1],
      102 => ['total' => 2, 'cleared' => 1]
    ]];
    $candidates = [
      $this->makeCandidate(101, null, 0, null, 0),
      $this->makeCandidate(102, null, 0, null, 0),
    ];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(101));
  }

  public function testSelectWinnerTiedFullyClearedPicksRankFirst()
  {
    $coverage = [601 => [
      101 => ['total' => 2, 'cleared' => 2],
      102 => ['total' => 3, 'cleared' => 3]
    ]];
    $candidates = [
      $this->makeCandidate(102, null, 0, null, 0),
      $this->makeCandidate(101, null, 0, null, 0),
    ];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(102));
  }

  public function testSelectWinnerDuplicateCandidatePairs()
  {
    $coverage = [601 => [
      101 => ['total' => 2, 'cleared' => 2]
    ]];
    $candidates = [
      $this->makeCandidate(101, null, 0, null, 0),
      $this->makeCandidate(101, null, 0, null, 0),
    ];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(101));
  }

  public function testSelectWinnerZeroClearedSkippedInRatioStep()
  {
    $coverage = [601 => [
      101 => ['total' => 2, 'cleared' => 0],
      102 => ['total' => 3, 'cleared' => 1]
    ]];
    $candidates = [
      $this->makeCandidate(101, null, 0, null, 0),
      $this->makeCandidate(102, null, 0, null, 0),
    ];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(102));
  }

  public function testSelectWinnerAcrossMultipleGroups()
  {
    $coverage = [
      601 => [101 => ['total' => 2, 'cleared' => 0]],
      602 => [102 => ['total' => 1, 'cleared' => 1]]
    ];
    $candidates = [
      $this->makeCandidate(101, null, 0, null, 0, 601),
      $this->makeCandidate(102, null, 0, null, 0, 602),
    ];
    $winner = ReuseAutoDetect::selectWinnerByClearing($candidates, $this->mockClearingDao($coverage));
    assertThat($winner['uploadId'], is(102));
  }

  /* ---------------------------------------------------------------------
   * filterEligibleCandidates - authorization (real DB)
   * --------------------------------------------------------------------- */

  private function seedEligibilityData()
  {
    $this->dbManager->insertInto('upload', 'upload_pk, user_fk, upload_filename',
      array(101, 1, 'upload-1.0.tar.gz'));
    $this->dbManager->insertInto('upload', 'upload_pk, user_fk, upload_filename',
      array(102, 2, 'upload-2.0.tar.gz'));
    $this->dbManager->insertInto('upload_clearing', 'upload_fk, group_fk, status_fk',
      array(101, 601, 3));
    $this->dbManager->insertInto('upload_clearing', 'upload_fk, group_fk, status_fk',
      array(102, 601, 3));
    $this->dbManager->insertInto('upload_clearing', 'upload_fk, group_fk, status_fk',
      array(101, 602, 3));
    $this->dbManager->insertInto('group_user_member', 'group_fk, user_fk, group_perm',
      array(601, 1, 1));
    $this->dbManager->insertInto('group_user_member', 'group_fk, user_fk, group_perm',
      array(601, 2, 1));
    $this->dbManager->insertInto('group_user_member', 'group_fk, user_fk, group_perm',
      array(602, 1, 1));
    $this->dbManager->insertInto('group_user_member', 'group_fk, user_fk, group_perm',
      array(603, 1, 0));
  }

  public function testFilterEligibleLegitimatePairPasses()
  {
    $this->seedEligibilityData();
    $result = ReuseAutoDetect::filterEligibleCandidates(
      [['uploadId' => 101, 'groupId' => 601]], 1, $this->dbManager);
    assertThat($result, is(array(['uploadId' => 101, 'groupId' => 601])));
  }

  public function testFilterEligibleRejectsForeignUpload()
  {
    $this->seedEligibilityData();
    $result = ReuseAutoDetect::filterEligibleCandidates(
      [['uploadId' => 102, 'groupId' => 601]], 1, $this->dbManager);
    assertThat($result, is(emptyArray()));
  }

  public function testFilterEligibleRejectsUnknownGroup()
  {
    $this->seedEligibilityData();
    $result = ReuseAutoDetect::filterEligibleCandidates(
      [['uploadId' => 101, 'groupId' => 999]], 1, $this->dbManager);
    assertThat($result, is(emptyArray()));
  }

  public function testFilterEligibleRejectsInsufficientPermission()
  {
    $this->seedEligibilityData();
    $result = ReuseAutoDetect::filterEligibleCandidates(
      [['uploadId' => 101, 'groupId' => 603]], 1, $this->dbManager);
    assertThat($result, is(emptyArray()));
  }

  public function testFilterEligibleRejectsUnknownUpload()
  {
    $this->seedEligibilityData();
    $result = ReuseAutoDetect::filterEligibleCandidates(
      [['uploadId' => 999, 'groupId' => 601]], 1, $this->dbManager);
    assertThat($result, is(emptyArray()));
  }

  public function testFilterEligibleRejectsTamperedMixKeepsLegit()
  {
    $this->seedEligibilityData();
    $result = ReuseAutoDetect::filterEligibleCandidates([
      ['uploadId' => 101, 'groupId' => 601],
      ['uploadId' => 102, 'groupId' => 601],
      ['uploadId' => 101, 'groupId' => 999],
    ], 1, $this->dbManager);
    assertThat($result, is(array(['uploadId' => 101, 'groupId' => 601])));
  }

  public function testFilterEligiblePreservesOrderAndDuplicates()
  {
    $this->seedEligibilityData();
    $result = ReuseAutoDetect::filterEligibleCandidates([
      ['uploadId' => 101, 'groupId' => 601],
      ['uploadId' => 101, 'groupId' => 601],
      ['uploadId' => 101, 'groupId' => 602],
    ], 1, $this->dbManager);
    assertThat($result, is(array(
      ['uploadId' => 101, 'groupId' => 601],
      ['uploadId' => 101, 'groupId' => 601],
      ['uploadId' => 101, 'groupId' => 602],
    )));
  }

  public function testFilterEligibleEmptyPairs()
  {
    assertThat(ReuseAutoDetect::filterEligibleCandidates([], 1, $this->dbManager), is(emptyArray()));
  }

  public function testFilterEligibleInvalidUserId()
  {
    $this->seedEligibilityData();
    assertThat(ReuseAutoDetect::filterEligibleCandidates(
      [['uploadId' => 101, 'groupId' => 601]], 0, $this->dbManager), is(emptyArray()));
  }

  /* ---------------------------------------------------------------------
   * isStatusSpecific - the 1-vs-4 branching
   * --------------------------------------------------------------------- */

  public function testStatusSpecificEmptyConfigIsNotSpecific()
  {
    assertThat(ReuseAutoDetect::isStatusSpecific([]), is(false));
  }

  public function testStatusSpecificSingleStatus()
  {
    assertThat(ReuseAutoDetect::isStatusSpecific([3]), is(true));
  }

  public function testStatusSpecificPartialStatuses()
  {
    assertThat(ReuseAutoDetect::isStatusSpecific([1, 2, 3]), is(true));
  }

  public function testStatusSpecificAllStatusesIsNotSpecific()
  {
    assertThat(ReuseAutoDetect::isStatusSpecific([1, 2, 3, 4]), is(false));
  }
}