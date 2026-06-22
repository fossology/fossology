<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \file MultiComparePlugin.php
 * \brief Multi-component comparison: license, copyright, and ECC diff across N uploads.
 */

class MultiComparePlugin extends DefaultPlugin
{
  const NAME = 'multicompare';

  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;
  /** @var AgentDao */
  private $agentDao;

  public function __construct()
  {
    parent::__construct(self::NAME, [
      self::TITLE        => _("Multi-Component Comparison"),
      self::DEPENDENCIES => ["browse", "view"],
      self::PERMISSION   => Auth::PERM_READ,
      self::REQUIRES_LOGIN => true,
    ]);
    $this->dbManager = $this->getObject('db.manager');
    $this->uploadDao = $this->getObject('dao.upload');
    $this->agentDao = $this->getObject('dao.agent');
  }

  // ── DB setup ───────────────────────────────────────────────────────────

  private function createFilePickerMultiTable(): void
  {
    if ($this->dbManager->existsTable('file_picker_multi')) {
      return;
    }
    $this->dbManager->queryOnce(
      "CREATE TABLE file_picker_multi (
        file_picker_multi_pk serial NOT NULL PRIMARY KEY,
        user_fk integer NOT NULL,
        items text NOT NULL,
        last_access_date date NOT NULL
      )",
      __METHOD__
    );
  }

  // ── Data helpers ───────────────────────────────────────────────────────

  /**
   * Return uploadtree info + all relevant agent pks for one tree node.
   * Uses the upload-specific table (uploadtree_tablename) when available,
   * falling back to the parent uploadtree table for the bootstrap query.
   */
  private function GetTreeInfo(int $uploadtree_pk): array
  {
    /* Bootstrap: query the parent table to get the upload metadata and table
     * name in a single round-trip. PostgreSQL inheritance ensures rows stored
     * in uploadtree_a are found here via PK index. */
    $TreeInfo = $this->dbManager->getSingleRow(
      "SELECT ut.*, u.uploadtree_tablename, u.upload_filename
       FROM uploadtree ut
       JOIN upload u ON u.upload_pk = ut.upload_fk
       WHERE ut.uploadtree_pk = \$1",
      [$uploadtree_pk], __METHOD__ . '.bootstrap'
    );
    if (!$TreeInfo) {
      return [];
    }

    /* Re-fetch lft/rgt and key columns from the upload-specific table so
     * subtree queries in buildHistData and AddDataStr operate on that table
     * directly rather than through the inheritance umbrella. */
    $tableName = $TreeInfo['uploadtree_tablename'];
    if ($tableName !== 'uploadtree') {
      $specific = $this->dbManager->getSingleRow(
        "SELECT lft, rgt, ufile_mode, ufile_name, pfile_fk, parent,
                uploadtree_pk, upload_fk
         FROM $tableName WHERE uploadtree_pk = \$1",
        [$uploadtree_pk], __METHOD__ . ".$tableName"
      );
      if ($specific) {
        $TreeInfo = array_merge($TreeInfo, $specific);
      }
    }

    $upload_pk = intval($TreeInfo['upload_fk']);
    $TreeInfo['display_name'] = !empty($TreeInfo['upload_filename'])
        ? basename($TreeInfo['upload_filename'])
        : $TreeInfo['ufile_name'];

    /* Fetch all 5 agent PKs in a single SQL round-trip instead of 5 separate
     * agentARSList() calls (each of which issues its own existsTable check +
     * ARS query). The static cache avoids repeating existsTable checks across
     * multiple GetTreeInfo calls in the same request. */
    $agentPks = $this->batchAgentPks($upload_pk);
    $TreeInfo = array_merge($TreeInfo, $agentPks);
    $TreeInfo['agent_pk'] = $TreeInfo['nomos_agent_pk'];

    return $TreeInfo;
  }

  /**
   * Fetch the latest successful agent_fk for all five ARS tables in one query.
   * A per-process static cache avoids redundant existsTable checks.
   *
   * @return array Keys: nomos_agent_pk, monk_agent_pk, ojo_agent_pk,
   *                     copyright_agent_pk, ecc_agent_pk
   */
  private function batchAgentPks(int $uploadPk): array
  {
    static $existsCache = [];

    $agentDef = [
      'nomos_pk'     => 'nomos_ars',
      'monk_pk'      => 'monk_ars',
      'ojo_pk'       => 'ojo_ars',
      'copyright_pk' => 'copyright_ars',
      'ecc_pk'       => 'ecc_ars',
    ];

    $parts = [];
    $existMask = '';
    foreach ($agentDef as $alias => $table) {
      if (!array_key_exists($table, $existsCache)) {
        $existsCache[$table] = $this->dbManager->existsTable($table);
      }
      if ($existsCache[$table]) {
        $parts[] = "(SELECT a.agent_fk FROM $table a"
                 . " JOIN agent ON agent_pk=a.agent_fk"
                 . " WHERE a.upload_fk=\$1 AND a.ars_success AND agent_enabled"
                 . " ORDER BY agent_ts DESC LIMIT 1) AS $alias";
        $existMask .= '1';
      } else {
        $parts[] = "NULL::integer AS $alias";
        $existMask .= '0';
      }
    }

    $stmt = __METHOD__ . '.' . $existMask;
    $this->dbManager->prepare($stmt, "SELECT " . implode(",\n", $parts));
    $res = $this->dbManager->execute($stmt, [$uploadPk]);
    $row = $this->dbManager->fetchArray($res) ?: [];
    $this->dbManager->freeResult($res);

    return [
      'nomos_agent_pk'     => intval($row['nomos_pk'] ?? 0),
      'monk_agent_pk'      => intval($row['monk_pk'] ?? 0),
      'ojo_agent_pk'       => intval($row['ojo_pk'] ?? 0),
      'copyright_agent_pk' => intval($row['copyright_pk'] ?? 0),
      'ecc_agent_pk'       => intval($row['ecc_pk'] ?? 0),
    ];
  }

  /**
   * Populate dataarray and datastr on every child for the given mode.
   * License and copyright/ECC data are fetched in single batch queries
   * (one per column) instead of one query per child.
   */
  private function AddDataStr(array $treeInfo, array &$children, string $mode): void
  {
    if ($mode === 'license') {
      $licAgentPks = array_values(array_filter([
        $treeInfo['nomos_agent_pk'],
        $treeInfo['monk_agent_pk'],
        $treeInfo['ojo_agent_pk'],
      ]));

      /* Batch-fetch licenses for all leaf files in this column at once */
      $licByPfile = [];
      if (!empty($licAgentPks)) {
        $pfileUniq = array_values(array_unique(array_filter(
          array_map(function ($c) {
            return intval($c['pfile_fk'] ?? 0);
          }, $children)
        )));
        if (!empty($pfileUniq)) {
          $params = [];
          $agentPh = [];
          foreach ($licAgentPks as $apk) {
            $params[] = $apk;
            $agentPh[] = '$' . count($params);
          }
          $pfilePh = [];
          foreach ($pfileUniq as $pf) {
            $params[] = $pf;
            $pfilePh[] = '$' . count($params);
          }
          $agentIn = implode(',', $agentPh);
          $pfileIn = implode(',', $pfilePh);
          $stmt = __METHOD__ . ".licbatch." . implode('_', $licAgentPks) . ".p" . count($pfileUniq);
          $this->dbManager->prepare($stmt,
            "SELECT lf.pfile_fk, lr.rf_pk, lr.rf_shortname
             FROM ONLY license_ref lr, license_file lf
             WHERE lf.rf_fk = lr.rf_pk
               AND lf.agent_fk IN ($agentIn)
               AND lf.pfile_fk IN ($pfileIn)"
          );
          $res = $this->dbManager->execute($stmt, $params);
          while ($row = $this->dbManager->fetchArray($res)) {
            $pf = intval($row['pfile_fk']);
            /* rf_pk key deduplicates same license found by multiple agents */
            $licByPfile[$pf][intval($row['rf_pk'])] = $row['rf_shortname'];
          }
          $this->dbManager->freeResult($res);
        }
      }

      foreach ($children as &$child) {
        $pf = intval($child['pfile_fk'] ?? 0);
        if ($pf > 0) {
          $dataarray = $licByPfile[$pf] ?? [];
        } else {
          /* Directory: fall back to per-item call to preserve subtree aggregation */
          $dataarray = [];
          foreach ($licAgentPks as $agentPk) {
            $dataarray += GetFileLicenses($agentPk, 0, $child['uploadtree_pk'],
                $treeInfo['uploadtree_tablename']);
          }
        }
        $child['dataarray'] = $dataarray;
        $child['datastr'] = implode(", ", $dataarray);
        if (empty($child['datastr'])) {
          $child['datastr'] = "No_license_found";
          $child['dataarray'] = ["No_license_found" => "No_license_found"];
        }
      }
      unset($child);

    } elseif ($mode === 'copyright' || $mode === 'ecc') {
      $table = ($mode === 'ecc') ? 'ecc' : 'copyright';
      $agentPk = ($mode === 'ecc')
          ? $treeInfo['ecc_agent_pk']
          : $treeInfo['copyright_agent_pk'];

      /* Batch-fetch all pfile content in one query */
      $dataByPfile = [];
      if ($agentPk > 0) {
        $pfileUniq = array_values(array_unique(array_filter(
          array_map(function ($c) {
            return intval($c['pfile_fk'] ?? 0);
          }, $children)
        )));
        if (!empty($pfileUniq)) {
          $params = [intval($agentPk)];
          $pfilePh = [];
          foreach ($pfileUniq as $pf) {
            $params[] = $pf;
            $pfilePh[] = '$' . count($params);
          }
          $pfileIn = implode(',', $pfilePh);
          $stmt = __METHOD__ . ".$table.batch.p" . count($pfileUniq);
          $this->dbManager->prepare($stmt,
            "SELECT pfile_fk, content FROM $table
             WHERE agent_fk=\$1 AND pfile_fk IN ($pfileIn)
               AND content IS NOT NULL AND content!=''
             ORDER BY pfile_fk, content"
          );
          $res = $this->dbManager->execute($stmt, $params);
          while ($row = $this->dbManager->fetchArray($res)) {
            $pf = intval($row['pfile_fk']);
            $dataByPfile[$pf][$row['content']] = $row['content'];
          }
          $this->dbManager->freeResult($res);
        }
      }

      foreach ($children as &$child) {
        $pf = intval($child['pfile_fk'] ?? 0);
        $dataarray = $dataByPfile[$pf] ?? [];
        $child['dataarray'] = $dataarray;
        $child['datastr'] = implode(", ", $dataarray);
      }
      unset($child);
    }
  }

  // ── Filters ────────────────────────────────────────────────────────────

  private function FilterN(string $filter, array &$Master, int $N): void
  {
    switch ($filter) {
      case 'samehash':
        $this->filterSamehashN($Master, $N);
        break;
      case 'samelic':
        $this->filterSamehashN($Master, $N);
        $this->filterSamelicN($Master, $N);
        break;
      case 'samelicfuzzy':
        $this->filterSamehashN($Master, $N);
        $this->filterSamelicFuzzyN($Master, $N);
        break;
      case 'nolics':
        $this->filterSamehashN($Master, $N);
        $this->filterSamelicFuzzyN($Master, $N);
        $this->filterNolicsN($Master);
        break;
      case 'allsame':
        $this->filterSamehashN($Master, $N);
        $this->filterAllsame($Master, $N);
        break;
    }
  }

  /**
   * Remove rows where all N columns have the same pfile_fk (identical content).
   * Rows with missing columns are kept because the absence is itself a difference.
   */
  private function filterSamehashN(array &$Master, int $N): void
  {
    foreach ($Master as $key => $row) {
      $pfiles = [];
      foreach ($row as $child) {
        if (!empty($child) && !empty($child['pfile_fk'])) {
          $pfiles[] = $child['pfile_fk'];
        }
      }
      if (count($pfiles) === $N && count(array_unique($pfiles)) === 1) {
        unset($Master[$key]);
      }
    }
  }

  /**
   * Remove rows where all N columns are present, share the same filename,
   * and carry identical data.
   */
  private function filterSamelicN(array &$Master, int $N): void
  {
    foreach ($Master as $key => $row) {
      $present = array_filter($row, fn($c) => !empty($c));
      if (count($present) !== $N) {
        continue;
      }
      if (count(array_unique(array_column($present, 'ufile_name'))) === 1 &&
          count(array_unique(array_column($present, 'datastr'))) === 1) {
        unset($Master[$key]);
      }
    }
  }

  /**
   * Like filterSamelicN but uses fuzzy filename comparison.
   */
  private function filterSamelicFuzzyN(array &$Master, int $N): void
  {
    foreach ($Master as $key => $row) {
      $present = array_filter($row, fn($c) => !empty($c));
      if (count($present) !== $N) {
        continue;
      }
      if (count(array_unique(array_column($present, 'fuzzyname'))) === 1 &&
          count(array_unique(array_column($present, 'datastr'))) === 1) {
        unset($Master[$key]);
      }
    }
  }

  /**
   * Remove rows where every present column has no meaningful data.
   * For license mode this is "No_license_found"; for other modes it is
   * an empty datastr.
   */
  private function filterNolicsN(array &$Master): void
  {
    foreach ($Master as $key => $row) {
      $present = array_filter($row, fn($c) => !empty($c));
      if (empty($present)) {
        continue;
      }
      $allEmpty = true;
      foreach ($present as $child) {
        $ds = $child['datastr'];
        if ($ds !== '' && $ds !== 'No_license_found') {
          $allEmpty = false;
          break;
        }
      }
      if ($allEmpty) {
        unset($Master[$key]);
      }
    }
  }

  /**
   * Remove rows where all N columns are present and report identical data.
   */
  private function filterAllsame(array &$Master, int $N): void
  {
    foreach ($Master as $key => $row) {
      $present = array_filter($row, fn($c) => !empty($c));
      if (count($present) !== $N) {
        continue;
      }
      if (count(array_unique(array_column($present, 'datastr'))) === 1) {
        unset($Master[$key]);
      }
    }
  }

  // ── Table row rendering ────────────────────────────────────────────────

  /**
   * Render <td> pair for one cell: filename+data badges + links.
   */
  private function ChildElt(array $child, int $colIdx, array $row,
                             array $treeInfoArray, string $mode, int $baseline): string
  {
    $dataarray = $child['dataarray'] ?? [];

    $refKeys = [];
    if ($baseline > 0) {
      $bIdx = $baseline - 1;
      if (isset($row[$bIdx]) && !empty($row[$bIdx]['dataarray'])) {
        $refKeys = array_keys($row[$bIdx]['dataarray']);
      }
    } else {
      foreach ($row as $c => $other) {
        if ($c === $colIdx || empty($other) || empty($other['dataarray'])) {
          continue;
        }
        foreach (array_keys($other['dataarray']) as $k) {
          $refKeys[] = $k;
        }
      }
      $refKeys = array_unique($refKeys);
    }
    $refKeySet = array_flip($refKeys);

    $badges = [];
    foreach ($dataarray as $k => $val) {
      $missing = !empty($refKeys) && !isset($refKeySet[$k]);
      if ($missing) {
        $badges[] = "<span class='badge badge-pill'"
                  . " style='background-color:#ffd6cc;color:#333;font-weight:normal'>"
                  . htmlspecialchars($val) . "</span>";
      } else {
        $badges[] = "<span class='badge badge-pill badge-light border'>"
                  . htmlspecialchars($val) . "</span>";
      }
    }
    $dataStr = implode(" ", $badges);

    $ColStr = "<td class='align-top py-1' id='c{$child['uploadtree_pk']}'>";
    $ColStr .= $child['linkurl'] ?? htmlspecialchars($child['ufile_name']);
    if (!empty($dataStr)) {
      $ColStr .= "<div class='mt-1 ml-1'>$dataStr</div>";
    }
    $ColStr .= "</td>";

    $agentPk = $treeInfoArray[$colIdx]['agent_pk'] ?? 0;
    $uploadtree_tablename = $treeInfoArray[$colIdx]['uploadtree_tablename'] ?? 'uploadtree';
    $ColStr .= "<td class='align-top py-1' style='white-space:nowrap'>";
    $uniqueTagArray = [];
    $ColStr .= FileListLinks(
        $child['upload_fk'], $child['uploadtree_pk'],
        $agentPk, $child['pfile_fk'], true,
        $uniqueTagArray, $uploadtree_tablename
    );
    $ColStr .= "</td>";

    return $ColStr;
  }

  /**
   * Render all table body rows (diff or matrix view).
   */
  private function ItemComparisonRows(array $Master, array $treeInfoArray,
                                      string $mode, int $baseline, string $view): string
  {
    $N = count($treeInfoArray);

    if ($view === 'matrix') {
      return $this->FileMatrixRows($Master, $N);
    }

    $parts = [];
    foreach ($Master as $row) {
      $parts[] = "<tr>";
      for ($c = 0; $c < $N; $c++) {
        if ($c > 0) {
          $parts[] = "<td class='border-left border-success p-0' style='width:3px'></td>";
        }
        if (empty($row[$c])) {
          $parts[] = "<td class='text-muted py-1'>&mdash;</td><td></td>";
        } else {
          $parts[] = $this->ChildElt($row[$c], $c, $row, $treeInfoArray, $mode, $baseline);
        }
      }
      $parts[] = "</tr>";
    }
    return implode("", $parts);
  }

  private function FileMatrixRows(array $Master, int $N): string
  {
    $parts = [];
    foreach ($Master as $row) {
      $parts[] = "<tr>";
      $firstName = "";
      for ($c = 0; $c < $N && empty($firstName); $c++) {
        if (!empty($row[$c])) {
          $firstName = htmlspecialchars($row[$c]['ufile_name']);
        }
      }
      $parts[] = "<td class='py-1'>$firstName</td>";

      /* Detect whether all present columns share the same pfile_fk */
      $pfiles = [];
      for ($c = 0; $c < $N; $c++) {
        if (!empty($row[$c]) && !empty($row[$c]['pfile_fk'])) {
          $pfiles[] = $row[$c]['pfile_fk'];
        }
      }
      $allSameHash = count($pfiles) >= 2 && count(array_unique($pfiles)) === 1;

      for ($c = 0; $c < $N; $c++) {
        if (!empty($row[$c])) {
          /* Green = content differs or unique to this column; gray = identical everywhere */
          $parts[] = $allSameHash
              ? "<td class='text-center py-1'><span class='badge badge-light border text-muted' title='identical'>&#10004;</span></td>"
              : "<td class='text-center py-1'><span class='badge badge-success' title='differs'>&#10004;</span></td>";
        } else {
          $parts[] = "<td class='text-center py-1 text-muted'>&mdash;</td>";
        }
      }
      $parts[] = "</tr>";
    }
    return implode("", $parts);
  }

  // ── Twig data builders ─────────────────────────────────────────────────

  /**
   * Build the per-column summary data for the Twig summary panel.
   * Called on the UNFILTERED master for accurate counts.
   * O(M·N·K) — no inner N² scan; uses per-row key-count tables instead.
   */
  private function buildSummaryData(array $Master, int $N,
                                    array $treeInfoArray, string $mode): array
  {
    $uniqueFiles = array_fill(0, $N, 0);
    $missingFiles = array_fill(0, $N, 0);
    $uniqueEntries = array_fill(0, $N, []);

    foreach ($Master as $row) {
      /* Which columns have data in this row? */
      $presentSet = [];
      for ($c = 0; $c < $N; $c++) {
        if (!empty($row[$c])) {
          $presentSet[$c] = true;
        }
      }
      $nPresent = count($presentSet);

      for ($c = 0; $c < $N; $c++) {
        if (!isset($presentSet[$c])) {
          $missingFiles[$c]++;
        }
      }
      if ($nPresent === 1) {
        $uniqueFiles[key($presentSet)]++;
      }

      /* Count how many columns contain each data key — O(N·K) per row */
      $keyColCount = [];
      foreach ($presentSet as $c => $_) {
        foreach ($row[$c]['dataarray'] ?? [] as $k => $val) {
          $keyColCount[$k] = ($keyColCount[$k] ?? 0) + 1;
        }
      }

      /* Keys appearing in exactly one column are unique to that column */
      foreach ($presentSet as $c => $_) {
        foreach ($row[$c]['dataarray'] ?? [] as $k => $val) {
          if ($keyColCount[$k] === 1) {
            $uniqueEntries[$c][$k] = $val;
          }
        }
      }
    }

    $summaryData = [];
    for ($c = 0; $c < $N; $c++) {
      $uniqList = array_unique($uniqueEntries[$c]);
      $uniqCount = count($uniqList);
      $shown = array_map('htmlspecialchars', array_slice($uniqList, 0, 10));
      $summaryData[] = [
        'name'          => htmlspecialchars($treeInfoArray[$c]['display_name'] ?? ("Col " . ($c + 1))),
        'uniqueFiles'   => $uniqueFiles[$c],
        'missingFiles'  => $missingFiles[$c],
        'uniqueEntries' => $shown,
        'moreCount'     => max(0, $uniqCount - 10),
      ];
    }
    return $summaryData;
  }

  /**
   * Run histogram queries and return structured data for the Twig histogram table.
   * Returns [entry => [colIdx => count], ...] sorted by total descending.
   */
  private function buildHistData(array $items, array $treeInfoArray, string $mode): array
  {
    $N = count($items);
    $histData = [];

    for ($c = 0; $c < $N; $c++) {
      $treeInfo = $treeInfoArray[$c];

      if ($mode === 'license') {
        $licAgentPks = array_values(array_filter([
          $treeInfo['nomos_agent_pk'],
          $treeInfo['monk_agent_pk'],
          $treeInfo['ojo_agent_pk'],
        ]));
        if (empty($licAgentPks)) {
          continue;
        }
        $lft = intval($treeInfo['lft']);
        $rgt = intval($treeInfo['rgt']);
        $upPk = intval($treeInfo['upload_fk']);
        $table = $treeInfo['uploadtree_tablename'];

        $params = [$lft, $rgt];
        $upClause = '';
        if ($table === 'uploadtree_a' || $table === 'uploadtree') {
          $params[] = $upPk;
          $upClause = "upload_fk=\$" . count($params) . " AND ";
        }
        /* Build IN-list for the agent PKs */
        $agentPlaceholders = [];
        foreach ($licAgentPks as $apk) {
          $params[] = $apk;
          $agentPlaceholders[] = "\$" . count($params);
        }
        $agentIn = implode(",", $agentPlaceholders);
        $sql = "SELECT rf_shortname AS entry, count(DISTINCT pfile_fk) AS cnt
                 FROM ONLY license_ref, license_file,
                   (SELECT DISTINCT(pfile_fk) AS PF FROM $table
                    WHERE {$upClause}{$table}.lft BETWEEN \$1 AND \$2) AS SS
                 WHERE PF=pfile_fk AND agent_fk IN ($agentIn) AND rf_fk=rf_pk
                 GROUP BY rf_shortname ORDER BY cnt DESC";
        $stmt = __METHOD__ . ".lic.$table.$c." . implode("_", $licAgentPks);
        $this->dbManager->prepare($stmt, $sql);
        $res = $this->dbManager->execute($stmt, $params);
        while ($row = $this->dbManager->fetchArray($res)) {
          $histData[$row['entry']][$c] = (int)$row['cnt'];
        }
        $this->dbManager->freeResult($res);

      } elseif ($mode === 'copyright' || $mode === 'ecc') {
        $table = ($mode === 'ecc') ? 'ecc' : 'copyright';
        $agentPk = ($mode === 'ecc')
            ? intval($treeInfo['ecc_agent_pk'])
            : intval($treeInfo['copyright_agent_pk']);
        if ($agentPk == 0) {
          continue;
        }
        $lft = intval($treeInfo['lft']);
        $rgt = intval($treeInfo['rgt']);
        $upPk = intval($treeInfo['upload_fk']);
        $utbl = $treeInfo['uploadtree_tablename'];

        $params = [$agentPk, $lft, $rgt];
        $upClause = '';
        if ($utbl === 'uploadtree_a' || $utbl === 'uploadtree') {
          $params[] = $upPk;
          $upClause = "AND UT.upload_fk=\$" . count($params);
        }
        $sql = "SELECT C.content AS entry, count(*) AS cnt
                 FROM $table C
                 INNER JOIN $utbl UT ON C.pfile_fk = UT.pfile_fk
                 WHERE C.agent_fk=\$1
                   AND C.content IS NOT NULL AND C.content!=''
                   AND UT.lft BETWEEN \$2 AND \$3
                   $upClause
                 GROUP BY C.content ORDER BY cnt DESC LIMIT 100";
        $stmt = __METHOD__ . ".$mode.$utbl.$c";
        $this->dbManager->prepare($stmt, $sql);
        $res = $this->dbManager->execute($stmt, $params);
        while ($row = $this->dbManager->fetchArray($res)) {
          $histData[$row['entry']][$c] = (int)$row['cnt'];
        }
        $this->dbManager->freeResult($res);
      }
    }

    /* Sort by total count across all columns, descending */
    uasort($histData, function (array $a, array $b): int {
      return array_sum($b) - array_sum($a);
    });
    return $histData;
  }

  // ── Request handler ────────────────────────────────────────────────────

  protected function handle(Request $request): Response
  {
    $this->createFilePickerMultiTable();

    $itemsRaw = $request->get('items', '');
    $items = [];
    if (!empty($itemsRaw)) {
      $items = array_values(array_unique(array_filter(
        array_map('intval', explode(',', $itemsRaw)),
        fn($v) => $v > 0
      )));
    }

    $filter = $request->get('filter', 'samehash');
    $mode = $request->get('mode', 'license');
    $view = $request->get('view', 'diff');
    $baseline = (int)($request->get('baseline', 0));
    $updcache = (int)($request->get('updcache', 0));

    if (!in_array($mode, ['license', 'copyright', 'ecc'])) {
      $mode = 'license';
    }
    if (!in_array($view, ['diff', 'matrix'])) {
      $view = 'diff';
    }

    if (count($items) < 2) {
      return $this->flushContent(
        "<h3>" . _("Please select at least 2 components to compare.") . "</h3>"
        . "<p><a href='javascript:history.back()'>" . _("Go back") . "</a></p>"
      );
    }

    foreach ($items as $idx => $itemPk) {
      /* Lightweight query: only upload_fk is needed for the access check.
       * This must happen before the cache check to avoid serving cached pages
       * to users who lost access since the page was cached. */
      $permRow = $this->dbManager->getSingleRow(
        "SELECT upload_fk FROM uploadtree WHERE uploadtree_pk = \$1",
        [$itemPk], __METHOD__ . '.perm'
      );
      if (!$permRow || !$this->uploadDao->isAccessible($permRow['upload_fk'], Auth::getGroupId())) {
        return $this->flushContent(
          "<h2>" . _("Permission Denied") . " (item " . ($idx + 1) . ")</h2>"
        );
      }
    }

    /* Freeze support: replace one column's item with a frozen pk.
     * $freezeCol is 1-based; $clickedCol is 0-based from the link's &col= param.
     * Default -1 is a sentinel meaning "toolbar navigation, not a column click",
     * so the freeze is always preserved on filter/mode/view/baseline changes. */
    $freezeCol = (int)($request->get('freeze', 0));
    $frozenItem = (int)($request->get('itemf', 0));
    $clickedCol = (int)($request->get('col', -1));
    if ($freezeCol > 0 && $frozenItem > 0 && ($freezeCol - 1) !== $clickedCol) {
      $colIdx0 = $freezeCol - 1;
      if (isset($items[$colIdx0])) {
        $frozenUploadFk = $this->dbManager->getSingleRow(
          "SELECT upload_fk FROM uploadtree WHERE uploadtree_pk = \$1",
          [$frozenItem], __METHOD__ . '.freeze'
        )['upload_fk'] ?? 0;
        if ($frozenUploadFk && $this->uploadDao->isAccessible($frozenUploadFk, Auth::getGroupId())) {
          $items[$colIdx0] = $frozenItem;
        }
      }
    }

    $cacheKey = "?mod=" . self::NAME
        . "&items=" . implode(",", $items)
        . "&filter=$filter&mode=$mode&view=$view&baseline=$baseline"
        . ($freezeCol > 0 ? "&freeze=$freezeCol&itemf=$frozenItem" : "");

    if ($updcache) {
      ReportCachePurgeByKey($cacheKey);
    } else {
      $cached = ReportCacheGet($cacheKey);
      if (!empty($cached)) {
        return new Response($cached, Response::HTTP_OK, $this->getDefaultHeaders());
      }
    }

    /* ── Build data ─────────────────────────────────────────────────── */
    $treeInfoArray = [];
    $agentPks = [];
    $ErrMsg = "";
    $N = count($items);

    foreach ($items as $c => $itemPk) {
      $treeInfo = $this->GetTreeInfo($itemPk);
      if (empty($treeInfo)) {
        return $this->flushContent(
          "<div class='alert alert-danger'>"
          . sprintf(_("Could not load data for item %d. The item may have been deleted."), $itemPk)
          . "</div>"
        );
      }
      $agentPk = 0;
      if ($mode === 'license') {
        $agentPk = $treeInfo['nomos_agent_pk'] ?: $treeInfo['monk_agent_pk'] ?: $treeInfo['ojo_agent_pk'];
        if ($agentPk == 0 && empty($ErrMsg)) {
          $ErrMsg = sprintf(
            _("No license scan data for component %d (%s). Schedule a nomos, monk, or ojo scan first."),
            $c + 1, htmlspecialchars($treeInfo['display_name'])
          );
        }
      } elseif ($mode === 'copyright') {
        $agentPk = $treeInfo['copyright_agent_pk'];
      } elseif ($mode === 'ecc') {
        $agentPk = $treeInfo['ecc_agent_pk'];
      }
      $treeInfo['agent_pk'] = $agentPk;
      $agentPks[$c] = $agentPk;
      $treeInfoArray[$c] = $treeInfo;
    }

    if (!empty($ErrMsg)) {
      return $this->flushContent("<div class='alert alert-warning'>$ErrMsg</div>");
    }

    $allChildren = [];
    foreach ($items as $c => $itemPk) {
      $children = GetNonArtifactChildren($itemPk, $treeInfoArray[$c]['uploadtree_tablename']);
      FuzzyName($children);
      $this->AddDataStr($treeInfoArray[$c], $children, $mode);
      $allChildren[$c] = $children;
    }

    $Master = MakeMasterN($allChildren);

    FileListN($Master, $agentPks, $filter, self::NAME, $items, $mode, $baseline);

    /* Summary must be computed BEFORE filtering */
    $summaryData = $this->buildSummaryData($Master, $N, $treeInfoArray, $mode);

    /* Matrix uses the unfiltered master: filters remove rows, which makes the
     * matrix lie about file presence (a filtered-out same-hash file would appear
     * as absent instead of present). Diff view is filtered normally. */
    if ($view === 'matrix') {
      $tableRows = $this->ItemComparisonRows($Master, $treeInfoArray, $mode, $baseline, $view);
      $this->FilterN($filter, $Master, $N);
    } else {
      $this->FilterN($filter, $Master, $N);
      $tableRows = $this->ItemComparisonRows($Master, $treeInfoArray, $mode, $baseline, $view);
    }

    /* Path banners per column */
    $pathBanners = [];
    for ($c = 0; $c < $N; $c++) {
      $tableName = $treeInfoArray[$c]['uploadtree_tablename'] ?? 'uploadtree';
      $path = Dir2Path($items[$c], $tableName);
      $pathBanners[] = Dir2BrowseDiffN($path, $filter, $c, self::NAME, $items, $mode, $baseline);
    }

    /* Histogram data */
    $histData = $this->buildHistData($items, $treeInfoArray, $mode);
    $colNames = array_map(
        fn($ti) => htmlspecialchars($ti['display_name'] ?? ''),
        $treeInfoArray
    );
    $modeLabel = $mode === 'license' ? _("License")
               : ($mode === 'ecc'    ? _("ECC") : _("Copyright"));

    /* ── Twig vars ──────────────────────────────────────────────────── */
    $filters = [
      'none' => _("0. Remove nothing"),
      'samehash' => _("1. Remove identical files (same hash)"),
      'samelic' => _("2. Remove files with unchanged data"),
      'samelicfuzzy' => _("2b. Same as 2 but fuzzy name match"),
      'nolics' => _("3. Same as 2b + remove no-license files"),
      'allsame' => _("4. Remove rows where all columns agree"),
    ];
    $filterDescriptions = [
      'none' => _("Show every file. Nothing is hidden."),
      'samehash' => _("Hide files with identical content across all columns."),
      'samelic' => _("Also hide files that share the same name and the same license/copyright data."),
      'samelicfuzzy' => _("Like above, but uses fuzzy filename matching — ignores version numbers in filenames."),
      'nolics' => _("Show only files where a license difference exists. Files with no license found are also hidden."),
      'allsame' => _("Hide any row where every column reports identical data, regardless of filename."),
    ];
    $modes = [
      'license' => _("Licenses"),
      'copyright' => _("Copyrights"),
      'ecc' => _("ECC"),
    ];
    $views = [
      'diff' => _("Diff"),
      'matrix' => _("Matrix"),
    ];

    $vars = [
      'pluginName' => self::NAME,
      'items' => $items,
      'N' => $N,
      'filter' => $filter,
      'mode' => $mode,
      'view' => $view,
      'baseline' => $baseline,
      'freezeCol' => $freezeCol,
      'frozenItem' => $frozenItem,
      'filters' => $filters,
      'filterDescriptions' => $filterDescriptions,
      'modes' => $modes,
      'views' => $views,
      'treeInfoArray' => $treeInfoArray,
      'summaryData' => $summaryData,
      'pathBanners' => $pathBanners,
      'tableRows' => $tableRows,
      'histData' => $histData,
      'colNames' => $colNames,
      'modeLabel' => $modeLabel,
    ];

    $response = $this->render(
      'multicompare.html.twig',
      $this->mergeWithDefault($vars)
    );

    if (strlen($response->getContent()) > 0) {
      ReportCachePut($cacheKey, $response->getContent());
    }

    return $response;
  }
}

register_plugin(new MultiComparePlugin());
