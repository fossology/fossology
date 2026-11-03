<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Mockery as M;

class SearchHelperDaoTest extends \PHPUnit\Framework\TestCase
{
  /** @var DbManager|M\MockInterface */
  private $dbManager;
  /** @var UploadDao|M\MockInterface */
  private $uploadDao;
  /** @var SearchHelperDao */
  private $searchHelperDao;

  protected function setUp(): void
  {
    $this->dbManager = M::mock(DbManager::class);
    $this->uploadDao = M::mock(UploadDao::class);
    $this->searchHelperDao = new SearchHelperDao($this->dbManager);
    $_SESSION = [];
  }

  protected function tearDown(): void
  {
    M::close();
    $_SESSION = [];
  }

  public function testSpecificInaccessibleUploadReturnsEmptyAndZeroCount(): void
  {
    $uploadId = 42;
    $groupId = 2;

    $this->uploadDao->shouldReceive('isAccessible')
      ->with($uploadId, $groupId)
      ->once()
      ->andReturn(false);

    // dbManager should NOT be queried when the upload is inaccessible
    $this->dbManager->shouldNotReceive('getSingleRow');
    $this->dbManager->shouldNotReceive('getRows');

    list($results, $count) = $this->searchHelperDao->GetResults(
      $Item = 0,
      $Filename = "topsecret-plan.txt",
      $Upload = $uploadId,
      $tag = "",
      $Page = 0,
      $Limit = 50,
      $SizeMin = "",
      $SizeMax = "",
      $searchtype = "allfiles",
      $License = "",
      $Copyright = "",
      $this->uploadDao,
      $groupId
    );

    $this->assertSame([], $results);
    $this->assertSame(0, $count);
  }

  public function testSpecificInaccessibleItemReturnsEmptyAndZeroCount(): void
  {
    $itemId = 100;
    $uploadId = 42;
    $groupId = 2;

    $this->uploadDao->shouldReceive('getUploadEntry')
      ->with($itemId)
      ->once()
      ->andReturn([
        'lft' => 1,
        'rgt' => 10,
        'upload_fk' => $uploadId
      ]);

    $this->uploadDao->shouldReceive('isAccessible')
      ->with($uploadId, $groupId)
      ->once()
      ->andReturn(false);

    $this->dbManager->shouldNotReceive('getSingleRow');
    $this->dbManager->shouldNotReceive('getRows');

    list($results, $count) = $this->searchHelperDao->GetResults(
      $Item = $itemId,
      $Filename = "topsecret-plan.txt",
      $Upload = 0,
      $tag = "",
      $Page = 0,
      $Limit = 50,
      $SizeMin = "",
      $SizeMax = "",
      $searchtype = "allfiles",
      $License = "",
      $Copyright = "",
      $this->uploadDao,
      $groupId
    );

    $this->assertSame([], $results);
    $this->assertSame(0, $count);
  }

  public function testGlobalSearchScopesToAuthorizedUploadsInSqlForBothCountAndResults(): void
  {
    $groupId = 2;
    $capturedCountSql = '';
    $capturedPaginatedSql = '';

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedCountSql) {
        $capturedCountSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn(['count' => 0]);

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedPaginatedSql) {
        $capturedPaginatedSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn([]);

    list($results, $count) = $this->searchHelperDao->GetResults(
      $Item = 0,
      $Filename = "topsecret-plan.txt",
      $Upload = 0,
      $tag = "",
      $Page = 0,
      $Limit = 50,
      $SizeMin = "",
      $SizeMax = "",
      $searchtype = "allfiles",
      $License = "",
      $Copyright = "",
      $this->uploadDao,
      $groupId
    );

    $this->assertSame([], $results);
    $this->assertSame(0, $count);

    $expectedSubquery = 'uploadtree.upload_fk IN (SELECT u.upload_pk FROM upload u LEFT JOIN perm_upload p ON p.upload_fk = u.upload_pk AND p.group_fk = 2 WHERE u.pfile_fk IS NOT NULL AND (p.perm > 0))';
    $this->assertStringContainsString($expectedSubquery, $capturedCountSql, 'Count query must include upload authorization subquery');
    $this->assertStringContainsString($expectedSubquery, $capturedPaginatedSql, 'Paginated query must include upload authorization subquery');
  }

  public function testGlobalSearchAllowsPublicUploadsForLoggedInUser(): void
  {
    $groupId = 2;
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_READ;

    $capturedCountSql = '';
    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedCountSql) {
        $capturedCountSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn(['count' => 0]);

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->andReturn([]);

    $this->searchHelperDao->GetResults(
      0, "test.txt", 0, "", 0, 50, "", "", "allfiles", "", "", $this->uploadDao, $groupId
    );

    $expectedSubquery = 'uploadtree.upload_fk IN (SELECT u.upload_pk FROM upload u LEFT JOIN perm_upload p ON p.upload_fk = u.upload_pk AND p.group_fk = 2 WHERE u.pfile_fk IS NOT NULL AND (p.perm > 0 OR u.public_perm > 0))';
    $this->assertStringContainsString($expectedSubquery, $capturedCountSql);
  }

  public function testGlobalSearchAdminBypassesGroupConstraint(): void
  {
    $groupId = 2;
    $_SESSION[Auth::USER_LEVEL] = Auth::PERM_ADMIN;

    $capturedCountSql = '';
    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedCountSql) {
        $capturedCountSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn(['count' => 5]);

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->andReturn([
        ['uploadtree_pk' => 1, 'parent' => null, 'upload_fk' => 10, 'pfile_fk' => 1, 'ufile_mode' => 33188, 'ufile_name' => 'admin-file.txt']
      ]);

    list($results, $count) = $this->searchHelperDao->GetResults(
      0, "admin-file.txt", 0, "", 0, 50, "", "", "allfiles", "", "", $this->uploadDao, $groupId
    );

    $this->assertSame(5, $count);
    $this->assertCount(1, $results);
    $this->assertStringNotContainsString('perm_upload', $capturedCountSql, 'Admin should not be constrained to group_fk in search query');
  }

  public function testSpecificAccessibleUploadSearchesOnlyThatUpload(): void
  {
    $uploadId = 15;
    $groupId = 2;

    $this->uploadDao->shouldReceive('isAccessible')
      ->with($uploadId, $groupId)
      ->andReturn(true);

    $capturedCountSql = '';
    $capturedPaginatedSql = '';

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedCountSql) {
        $capturedCountSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn(['count' => 1]);

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedPaginatedSql) {
        $capturedPaginatedSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn([
        ['uploadtree_pk' => 10, 'parent' => null, 'upload_fk' => 15, 'pfile_fk' => 5, 'ufile_mode' => 33188, 'ufile_name' => 'accessible.txt']
      ]);

    list($results, $count) = $this->searchHelperDao->GetResults(
      0, "accessible.txt", $uploadId, "", 0, 50, "", "", "allfiles", "", "", $this->uploadDao, $groupId
    );

    $this->assertSame(1, $count);
    $this->assertCount(1, $results);
    $this->assertStringContainsString('uploadtree.upload_fk = 15', $capturedCountSql);
    $this->assertStringContainsString('uploadtree.upload_fk = 15', $capturedPaginatedSql);
  }

  public function testCopyrightSearchExcludesInaccessibleUploadsInCountAndResults(): void
  {
    $groupId = 2;
    $capturedCountSql = '';
    $capturedPaginatedSql = '';

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedCountSql) {
        $capturedCountSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn(['count' => 0]);

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedPaginatedSql) {
        $capturedPaginatedSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn([]);

    list($results, $count) = $this->searchHelperDao->GetResults(
      $Item = 0,
      $Filename = "",
      $Upload = 0,
      $tag = "",
      $Page = 0,
      $Limit = 50,
      $SizeMin = "",
      $SizeMax = "",
      $searchtype = "allfiles",
      $License = "",
      $Copyright = "AcmeSecretProject",
      $this->uploadDao,
      $groupId
    );

    $this->assertSame([], $results);
    $this->assertSame(0, $count);

    $this->assertStringContainsString("copyright.content ilike '%AcmeSecretProject%'", $capturedCountSql);
    $this->assertStringContainsString('uploadtree.upload_fk IN (SELECT u.upload_pk FROM upload u', $capturedCountSql);
    $this->assertStringContainsString("copyright.content ilike '%AcmeSecretProject%'", $capturedPaginatedSql);
    $this->assertStringContainsString('uploadtree.upload_fk IN (SELECT u.upload_pk FROM upload u', $capturedPaginatedSql);
  }

  public function testLicenseSearchExcludesInaccessibleUploadsInCountAndResults(): void
  {
    $groupId = 2;
    $capturedCountSql = '';
    $capturedPaginatedSql = '';

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedCountSql) {
        $capturedCountSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn(['count' => 0]);

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedPaginatedSql) {
        $capturedPaginatedSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn([]);

    list($results, $count) = $this->searchHelperDao->GetResults(
      $Item = 0,
      $Filename = "",
      $Upload = 0,
      $tag = "",
      $Page = 0,
      $Limit = 50,
      $SizeMin = "",
      $SizeMax = "",
      $searchtype = "allfiles",
      $License = "GPL-2.0-only",
      $Copyright = "",
      $this->uploadDao,
      $groupId
    );

    $this->assertSame([], $results);
    $this->assertSame(0, $count);

    $this->assertStringContainsString("pfile_ref.rf_shortname ilike 'GPL-2.0-only'", $capturedCountSql);
    $this->assertStringContainsString('uploadtree.upload_fk IN (SELECT u.upload_pk FROM upload u', $capturedCountSql);
  }

  public function testWildcardFilenameSearch(): void
  {
    $groupId = 2;
    $capturedCountSql = '';

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::on(function ($sql) use (&$capturedCountSql) {
        $capturedCountSql = $sql;
        return true;
      }), [], M::any())
      ->andReturn(['count' => 0]);

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->andReturn([]);

    $this->searchHelperDao->GetResults(
      0, "secret-%", 0, "", 0, 50, "", "", "allfiles", "", "", $this->uploadDao, $groupId
    );

    $this->assertStringContainsString("ufile_name ilike 'secret-%'", $capturedCountSql);
    $this->assertStringContainsString('uploadtree.upload_fk IN (SELECT u.upload_pk FROM upload u', $capturedCountSql);
  }

  public function testUnauthenticatedUserWithoutGroupReturnsZero(): void
  {
    // No session, groupId = 0
    list($results, $count) = $this->searchHelperDao->GetResults(
      0, "secret.txt", 0, "", 0, 50, "", "", "allfiles", "", "", $this->uploadDao, 0
    );

    $this->assertSame([], $results);
    $this->assertSame(0, $count);
  }

  public function testGlobalSearchWithMixedAccessibleAndInaccessibleUploadsPreventsCountLeak(): void
  {
    $groupId = 2;

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->with(M::on(function ($sql) {
        return strpos($sql, 'COUNT(DISTINCT uploadtree_pk)') !== false;
      }), [], M::any())
      ->andReturnUsing(function ($sql) {
        if (strpos($sql, 'uploadtree.upload_fk IN') !== false) {
          return ['count' => 1];
        }
        return ['count' => 3];
      });

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->with(M::on(function ($sql) {
        return strpos($sql, 'SELECT DISTINCT uploadtree_pk') !== false;
      }), [], M::any())
      ->andReturnUsing(function ($sql) {
        if (strpos($sql, 'uploadtree.upload_fk IN') !== false) {
          return [
            ['uploadtree_pk' => 101, 'parent' => null, 'upload_fk' => 1, 'pfile_fk' => 10, 'ufile_mode' => 33188, 'ufile_name' => 'target.txt']
          ];
        }
        return [
          ['uploadtree_pk' => 101, 'parent' => null, 'upload_fk' => 1, 'pfile_fk' => 10, 'ufile_mode' => 33188, 'ufile_name' => 'target.txt'],
          ['uploadtree_pk' => 201, 'parent' => null, 'upload_fk' => 2, 'pfile_fk' => 20, 'ufile_mode' => 33188, 'ufile_name' => 'target.txt'],
          ['uploadtree_pk' => 202, 'parent' => null, 'upload_fk' => 2, 'pfile_fk' => 21, 'ufile_mode' => 33188, 'ufile_name' => 'target.txt']
        ];
      });

    $this->uploadDao->shouldReceive('isAccessible')
      ->with(1, $groupId)
      ->andReturn(true);
    $this->uploadDao->shouldReceive('isAccessible')
      ->with(2, $groupId)
      ->andReturn(false);

    list($results, $count) = $this->searchHelperDao->GetResults(
      0, "target.txt", 0, "", 0, 50, "", "", "allfiles", "", "", $this->uploadDao, $groupId
    );

    $this->assertSame(1, $count);
    $this->assertCount(1, $results);
    $this->assertSame(101, $results[0]['uploadtree_pk']);
  }

  public function testPaginationWithInaccessibleRecordsDoesNotStarvePage(): void
  {
    $groupId = 2;

    $this->dbManager->shouldReceive('getSingleRow')
      ->once()
      ->andReturn(['count' => 1]);

    $this->dbManager->shouldReceive('getRows')
      ->once()
      ->with(M::on(function ($sql) {
        return strpos($sql, 'LIMIT 1 OFFSET 0') !== false && strpos($sql, 'uploadtree.upload_fk IN') !== false;
      }), [], M::any())
      ->andReturn([
        ['uploadtree_pk' => 101, 'parent' => null, 'upload_fk' => 1, 'pfile_fk' => 10, 'ufile_mode' => 33188, 'ufile_name' => 'target.txt']
      ]);

    $this->uploadDao->shouldReceive('isAccessible')
      ->with(1, $groupId)
      ->andReturn(true);

    list($results, $count) = $this->searchHelperDao->GetResults(
      0, "target.txt", 0, "", 0, 1, "", "", "allfiles", "", "", $this->uploadDao, $groupId
    );

    $this->assertSame(1, $count);
    $this->assertCount(1, $results);
    $this->assertSame(101, $results[0]['uploadtree_pk']);
  }
}
