<?php
/*
 SPDX-FileCopyrightText: © Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Reuse;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Db\DbManager;

/**
 * @class ReuseAutoDetect
 * @brief Pure decision logic for auto-selecting a reuse upload
 *
 * Kept free of Auth/container/global state so it can be unit tested
 * directly. The UI plugin passes in the current user id, the db manager
 * and the clearing dao as plain parameters.
 */
class ReuseAutoDetect
{
  /**
   * @brief Parse package name and version from a filename
   * @param string $filename
   * @return array ['baseName'=>string, 'versionParts'=>int[]|null,
   *                'prerelease'=>int]
   */
  public static function parsePackageName($filename)
  {
    if (empty($filename)) {
      return ['baseName' => '', 'versionParts' => null, 'prerelease' => 0];
    }
    $nameWithoutExt = preg_replace('/\.[^.]+$/', '', $filename);
    $nameWithoutExt = preg_replace('/\.(tar|zip|gz|bz2|xz|tgz|tbz2|txz|rar|7z)(\..*)?$/i', '', $nameWithoutExt);
    $nameWithoutExt = preg_replace('/\.(tar|zip|gz|bz2|xz|tgz|tbz2|txz|rar|7z)$/i', '', $nameWithoutExt);
    $nameWithoutExt = preg_replace('/[-_]\d{8}$/', '', $nameWithoutExt);
    if (preg_match('/[-_](v?\d+(?:\.\d+)*)(?:[-_](?:alpha|beta|rc|pre|patch|p)\d*)?$/i', $nameWithoutExt, $m)) {
      $baseName = substr($nameWithoutExt, 0, -strlen($m[0]));
      $versionParts = array_map('intval', explode('.', preg_replace('/^v/i', '', $m[1])));
      $prerelease = preg_match('/[-_](?:alpha|beta|rc|pre|patch|p)\d*$/i', $nameWithoutExt) ? 1 : 0;
      return ['baseName' => $baseName, 'versionParts' => $versionParts, 'prerelease' => $prerelease];
    }
    $parts = explode('-', $nameWithoutExt);
    return ['baseName' => $parts[0], 'versionParts' => null, 'prerelease' => 0];
  }

  /**
   * @brief Digits of |parts - requestedParts| in base $R
   * @param int[] $parts
   * @param int[] $requestedParts
   * @param int $L Common padded length
   * @param int $R Radix (larger than any component)
   * @return int[] Most-significant-first digits of the absolute difference
   */
  private static function computeVersionDistance($parts, $requestedParts, $L, $R)
  {
    $digits = [];
    $borrow = 0;
    for ($i = $L - 1; $i >= 0; $i--) {
      $v = (isset($parts[$i]) ? $parts[$i] : 0)
        - (isset($requestedParts[$i]) ? $requestedParts[$i] : 0) - $borrow;
      if ($v < 0) {
        $v += $R;
        $borrow = 1;
      } else {
        $borrow = 0;
      }
      $digits[$i] = $v;
    }
    if ($borrow === 1) {
      for ($i = 0; $i < $L; $i++) {
        $digits[$i] = ($R - 1) - $digits[$i];
      }
      $carry = 1;
      for ($i = $L - 1; $i >= 0; $i--) {
        $s = $digits[$i] + $carry;
        if ($s >= $R) {
          $digits[$i] = $s - $R;
          $carry = 1;
        } else {
          $digits[$i] = $s;
          $carry = 0;
        }
      }
    }
    return $digits;
  }

  /**
   * @brief Compare two version tuples for numeric closeness to a requested version
   *
   * Measures |a - requested| and |b - requested| as numbers in base $R
   * (larger than any single component), so a difference in a higher
   * component correctly outweighs differences in lower ones. For example,
   * requested 1.0 ranks 0.9 closer than 0.8 (0.1 apart vs 0.2 apart).
   * @param int[]|null $requestedParts Requested version tuple
   * @param int[]|null $aParts
   * @param int[]|null $bParts
   * @param int $L Common padded length of all version tuples
   * @param int $R Radix (max component + 1, at least 10)
   * @return int negative if $aParts is closer, positive if $bParts is closer
   */
  public static function compareVersionCloseness($requestedParts, $aParts, $bParts, $L, $R)
  {
    if ($requestedParts === null) {
      return 0;
    }
    if ($aParts === null && $bParts === null) {
      return 0;
    }
    if ($aParts === null) {
      return 1;
    }
    if ($bParts === null) {
      return -1;
    }
    $distA = self::computeVersionDistance($aParts, $requestedParts, $L, $R);
    $distB = self::computeVersionDistance($bParts, $requestedParts, $L, $R);
    for ($i = 0; $i < $L; $i++) {
      if ($distA[$i] !== $distB[$i]) {
        return $distA[$i] <=> $distB[$i];
      }
    }
    return 0;
  }

  /**
   * @brief Rank candidates by nearest version, then release, then clearing/upload date
   * @param array $candidates Each with versionParts, prerelease, clearedAt, timestamp
   * @param int[]|null $requestedVersionParts
   */
  public static function rankCandidates(&$candidates, $requestedVersionParts)
  {
    $L = 0;
    $R = 10;
    $all = $candidates;
    if ($requestedVersionParts !== null) {
      $all[] = ['versionParts' => $requestedVersionParts];
      $L = count($requestedVersionParts);
    }
    foreach ($all as $candidate) {
      $parts = $candidate['versionParts'];
      if ($parts === null) {
        continue;
      }
      $L = max($L, count($parts));
      foreach ($parts as $component) {
        $R = max($R, $component + 1);
      }
    }
    usort($candidates, function ($a, $b) use ($requestedVersionParts, $L, $R) {
      if ($requestedVersionParts !== null) {
        $cmp = self::compareVersionCloseness($requestedVersionParts,
          $a['versionParts'], $b['versionParts'], $L, $R);
        if ($cmp !== 0) {
          return $cmp;
        }
        if ($a['prerelease'] !== $b['prerelease']) {
          return $a['prerelease'] <=> $b['prerelease'];
        }
      }
      $tsA = empty($a['clearedAt']) ? null : strtotime($a['clearedAt']);
      $tsB = empty($b['clearedAt']) ? null : strtotime($b['clearedAt']);
      if ($tsA !== null && $tsB !== null && $tsA !== $tsB) {
        return $tsB - $tsA;
      }
      if ($tsA !== null) {
        return -1;
      }
      if ($tsB !== null) {
        return 1;
      }
      return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
    });
  }

  /**
   * @brief Select the single best candidate based on clearing decisions
   *
   * Prefers the first fully cleared candidate in rank order, then the
   * candidate with the highest cleared-files percentage, and finally falls
   * back to the first candidate (best by version/date).
   * @param array $candidates Each with uploadId and groupId, ranked best-first
   * @param ClearingDao $clearingDao
   * @return array|null Selected candidate or null
   */
  public static function selectWinnerByClearing($candidates, ClearingDao $clearingDao)
  {
    if (empty($candidates)) {
      return null;
    }
    $groups = [];
    foreach ($candidates as $candidate) {
      $groups[$candidate['groupId']] = true;
    }
    $coverage = [];
    foreach (array_keys($groups) as $groupId) {
      $ids = [];
      foreach ($candidates as $candidate) {
        if ($candidate['groupId'] === $groupId) {
          $ids[] = $candidate['uploadId'];
        }
      }
      $coverage[$groupId] = $clearingDao->getClearingCoverage($ids, $groupId);
    }
    foreach ($candidates as $candidate) {
      $cov = isset($coverage[$candidate['groupId']][$candidate['uploadId']])
        ? $coverage[$candidate['groupId']][$candidate['uploadId']] : null;
      if ($cov !== null && $cov['total'] > 0 && $cov['cleared'] >= $cov['total']) {
        return $candidate;
      }
    }
    $best = null;
    $bestRatio = -1.0;
    foreach ($candidates as $candidate) {
      $cov = isset($coverage[$candidate['groupId']][$candidate['uploadId']])
        ? $coverage[$candidate['groupId']][$candidate['uploadId']] : null;
      if ($cov === null || $cov['total'] <= 0 || $cov['cleared'] <= 0) {
        continue;
      }
      $ratio = $cov['cleared'] / $cov['total'];
      if ($ratio > $bestRatio) {
        $bestRatio = $ratio;
        $best = $candidate;
      }
    }
    if ($best !== null) {
      return $best;
    }
    return $candidates[0];
  }

  /**
   * @brief Restrict candidate pairs to uploads owned by $currentUserId in
   *        a group the user can access
   *
   * Mirrors the eligibility rule enforced in primarySearchFromPlugin(): the
   * upload must have been uploaded by $currentUserId, and the requested
   * group must be a real upload_clearing group for that upload for which
   * $currentUserId has group membership with group_perm >= 1. This
   * re-checks ownership/access server-side instead of trusting client-
   * supplied uploadId/groupId pairs.
   * @param array $pairs List of ['uploadId'=>int,'groupId'=>int]
   * @param int $currentUserId
   * @param DbManager $dbManager
   * @return array[] Filtered list of eligible pairs, in the original order
   */
  public static function filterEligibleCandidates($pairs, $currentUserId, DbManager $dbManager)
  {
    if ($currentUserId <= 0 || empty($pairs)) {
      return [];
    }

    $uploadIds = array_values(array_unique(array_map(function ($pair) {
      return $pair['uploadId'];
    }, $pairs)));

    $placeholders = [];
    for ($i = 0; $i < count($uploadIds); $i++) {
      $placeholders[] = '$' . ($i + 2);
    }
    $stmtName = __METHOD__;
    $dbManager->prepare($stmtName,
      "SELECT DISTINCT u.upload_pk, uc.group_fk
       FROM upload u
       JOIN upload_clearing uc ON uc.upload_fk = u.upload_pk
       WHERE u.user_fk = $1
         AND u.upload_pk IN (" . implode(',', $placeholders) . ")
         AND EXISTS (SELECT 1 FROM group_user_member gum
                     WHERE gum.group_fk = uc.group_fk AND gum.user_fk = $1 AND gum.group_perm >= 1)");
    $params = array_merge([$currentUserId], $uploadIds);
    $res = $dbManager->execute($stmtName, $params);
    $eligibleKeys = [];
    while ($row = $dbManager->fetchArray($res)) {
      $eligibleKeys[$row['upload_pk'] . ',' . $row['group_fk']] = true;
    }
    $dbManager->freeResult($res);

    $eligiblePairs = [];
    foreach ($pairs as $pair) {
      if (isset($eligibleKeys[$pair['uploadId'] . ',' . $pair['groupId']])) {
        $eligiblePairs[] = $pair;
      }
    }
    return $eligiblePairs;
  }

  /**
   * @brief Whether the status list is a specific filter (neither empty nor
   *        covering all upload statuses)
   *
   * A specific filter selects exactly one upload; an empty or full status
   * list selects up to AUTO_DETECT_LIMIT candidates which are then
   * narrowed by clearing decisions.
   * @param int[] $statusIds
   * @return bool
   */
  public static function isStatusSpecific($statusIds)
  {
    return !empty($statusIds) && count($statusIds) < 4;
  }
}