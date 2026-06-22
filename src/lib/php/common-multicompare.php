<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file
 * \brief N-way file matching and link generation for multi-component comparison.
 */


/**
 * \brief Remove one key from a name-index bucket list (used by MakeMasterN).
 */
function _mcIndexRemove(array &$idx, string $val, $key): void
{
  if ($val === '' || !isset($idx[$val])) {
    return;
  }
  $filtered = array_filter($idx[$val], function ($k) use ($key) {
    return $k !== $key;
  });
  if (empty($filtered)) {
    unset($idx[$val]);
  } else {
    $idx[$val] = array_values($filtered);
  }
}

/**
 * \brief Hashmap-accelerated best-match (O(1) for stages 1, 2, 4; O(M) only for rare stage 3).
 *
 * @return int|string|null pool key or null
 */
function _mcFindBestMatch(array $child, array $pool,
                          array &$byName, array &$byFuzzyExt, array &$byFuzzy)
{
  /* Stage 1: exact ufile_name */
  $name = $child['ufile_name'];
  if (isset($byName[$name])) {
    foreach ($byName[$name] as $k) {
      if (isset($pool[$k])) {
        return $k;
      }
    }
  }

  /* Stage 2: fuzzynameext */
  $fe = $child['fuzzynameext'] ?? '';
  if ($fe !== '' && isset($byFuzzyExt[$fe])) {
    foreach ($byFuzzyExt[$fe] as $k) {
      if (isset($pool[$k])) {
        return $k;
      }
    }
  }

  /* Stage 3: levenshtein == 1 on fuzzynameext (linear, but rare) */
  if ($fe !== '') {
    foreach ($pool as $k => $candidate) {
      $cfe = $candidate['fuzzynameext'] ?? '';
      if ($cfe !== '' && levenshtein($fe, $cfe) === 1) {
        return $k;
      }
    }
  }

  /* Stage 4: fuzzyname */
  $f = $child['fuzzyname'] ?? '';
  if ($f !== '' && isset($byFuzzy[$f])) {
    foreach ($byFuzzy[$f] as $k) {
      if (isset($pool[$k])) {
        return $k;
      }
    }
  }

  return null;
}


/**
 * \brief Build the master array for N file lists using hashmap-based O(M·N) matching.
 *
 * @param array $ChildrenArrays  Indexed array: $ChildrenArrays[$colIdx] = children list
 * @return array Master rows
 */
function MakeMasterN(array $ChildrenArrays): array
{
  $N = count($ChildrenArrays);
  if ($N < 2) {
    return [];
  }

  /* Build per-column hashmap indexes for O(1) name lookups */
  $remaining = [];
  $byName = [];
  $byFuzzyExt = [];
  $byFuzzy = [];

  foreach ($ChildrenArrays as $colIdx => $children) {
    $remaining[$colIdx] = $children;
    $byName[$colIdx] = [];
    $byFuzzyExt[$colIdx] = [];
    $byFuzzy[$colIdx] = [];
    foreach ($children as $key => $child) {
      $byName[$colIdx][$child['ufile_name']][] = $key;
      $fe = $child['fuzzynameext'] ?? '';
      if ($fe !== '') {
        $byFuzzyExt[$colIdx][$fe][] = $key;
      }
      $f = $child['fuzzyname'] ?? '';
      if ($f !== '') {
        $byFuzzy[$colIdx][$f][] = $key;
      }
    }
  }

  $masterRows = [];

  for ($anchor = 0; $anchor < $N; $anchor++) {
    foreach ($remaining[$anchor] as $anchorKey => $anchorChild) {
      $row = [$anchor => $anchorChild];
      _mcIndexRemove($byName[$anchor],     $anchorChild['ufile_name'],        $anchorKey);
      _mcIndexRemove($byFuzzyExt[$anchor], $anchorChild['fuzzynameext'] ?? '', $anchorKey);
      _mcIndexRemove($byFuzzy[$anchor],    $anchorChild['fuzzyname']    ?? '', $anchorKey);
      unset($remaining[$anchor][$anchorKey]);

      for ($other = 0; $other < $N; $other++) {
        if ($other === $anchor || isset($row[$other])) {
          continue;
        }
        $matchKey = _mcFindBestMatch($anchorChild,
                      $remaining[$other],
                      $byName[$other], $byFuzzyExt[$other], $byFuzzy[$other]);
        if ($matchKey !== null) {
          $matched = $remaining[$other][$matchKey];
          $row[$other] = $matched;
          _mcIndexRemove($byName[$other],     $matched['ufile_name'],        $matchKey);
          _mcIndexRemove($byFuzzyExt[$other], $matched['fuzzynameext'] ?? '', $matchKey);
          _mcIndexRemove($byFuzzy[$other],    $matched['fuzzyname']    ?? '', $matchKey);
          unset($remaining[$other][$matchKey]);
        }
      }
      $masterRows[] = $row;
    }
  }

  usort($masterRows, function (array $a, array $b): int {
    $aChild = reset($a);
    $bChild = reset($b);
    $aName = $aChild['fuzzyname'] ?? $aChild['ufile_name'];
    $bName = $bChild['fuzzyname'] ?? $bChild['ufile_name'];
    return strcasecmp($aName, $bName);
  });

  return $masterRows;
}


/**
 * \brief Generate the navigation link for one cell in the multi-component table.
 *
 * @param array  $MasterRow   Full master row (indexed by colIdx)
 * @param int    $colIdx      Column index of the cell being rendered
 * @param int    $agentPk     Agent pk for this column
 * @param string $filter      Current filter value
 * @param string $pluginName  Plugin name used for URL (e.g. 'multicompare')
 * @param array  $items       Current items array (uploadtree_pk per column)
 * @param string $mode        'license'|'copyright'|'ecc'
 * @param int    $baseline    0 = none, 1-based column index
 * @return string HTML link string
 */
function GetDiffLinkN(array $MasterRow, int $colIdx, int $agentPk, string $filter,
                      string $pluginName, array $items, string $mode, int $baseline): string
{
  global $Plugins;

  /* Resolve once per request; avoids repeated plugin registry lookups */
  static $viewLicId = null;
  if ($viewLicId === null) {
    $viewLicId = plugin_find_id("view-license");
  }
  $ModLicView = ($viewLicId !== false && isset($Plugins[$viewLicId]))
      ? $Plugins[$viewLicId] : null;

  $Child = $MasterRow[$colIdx];
  $IsDir = Isdir($Child['ufile_mode']);
  $IsContainer = Iscontainer($Child['ufile_mode']);

  $newItems = $items;
  $newItems[$colIdx] = $Child['uploadtree_pk'];

  foreach ($items as $c => $parentPk) {
    if ($c === $colIdx) {
      continue;
    }
    if (isset($MasterRow[$c]) && !empty($MasterRow[$c]['uploadtree_pk'])) {
      $newItems[$c] = $MasterRow[$c]['uploadtree_pk'];
    }
  }

  $LinkUri = null;
  if (!empty($Child['pfile_fk']) && !empty($ModLicView)) {
    $LinkUri = Traceback_uri();
    $LinkUri .= "?mod=view-license&napk=$agentPk&upload=$Child[upload_fk]&item=$Child[uploadtree_pk]";
  }

  $LicUri = null;
  if ($IsContainer) {
    $LicUri = "?mod=$pluginName&items=" . implode(",", $newItems);
    $LicUri .= "&col=$colIdx&filter=" . urlencode($filter);
    $LicUri .= "&mode=" . urlencode($mode);
    $LicUri .= "&baseline=$baseline";
  }

  $parts = [];
  if ($IsContainer && $LicUri) {
    $parts[] = "<a href='$LicUri'><b>";
  } elseif ($LinkUri) {
    $parts[] = "<a href='$LinkUri'>";
  }

  $parts[] = htmlspecialchars($Child['ufile_name']);
  if ($IsDir) {
    $parts[] = "/";
  }

  if ($IsContainer && $LicUri) {
    $parts[] = "</b></a>";
  } elseif ($LinkUri) {
    $parts[] = "</a>";
  }

  return implode("", $parts);
}


/**
 * \brief Attach linkurl to every cell in Master (N-way version of FileList()).
 *
 * @param array  $Master      Master rows (modified in place)
 * @param array  $agentPks    Agent pk per column (indexed by colIdx)
 * @param string $filter      Current filter
 * @param string $pluginName  Plugin name for URL building
 * @param array  $items       Current items (uploadtree_pk per column)
 * @param string $mode        'license'|'copyright'|'ecc'
 * @param int    $baseline    0 or 1-based column index
 */
function FileListN(array &$Master, array $agentPks, string $filter, string $pluginName,
                   array $items, string $mode, int $baseline): void
{
  foreach ($Master as &$row) {
    foreach ($row as $colIdx => &$child) {
      if (empty($child)) {
        continue;
      }
      $agentPk = $agentPks[$colIdx] ?? 0;
      $child['linkurl'] = GetDiffLinkN($row, $colIdx, $agentPk, $filter,
                                       $pluginName, $items, $mode, $baseline);
    }
    unset($child);
  }
  unset($row);
}


/**
 * \brief Render the folder/path breadcrumb banner for one column.
 *
 * @param array  $path        Dir2Path result for this column
 * @param string $filter      Current filter
 * @param int    $colIdx      This column's index
 * @param string $pluginName  Plugin name for URL building
 * @param array  $items       Current items (uploadtree_pk per column)
 * @param string $mode        'license'|'copyright'|'ecc'
 * @param int    $baseline    0 or 1-based
 * @return string HTML
 */
function Dir2BrowseDiffN(array $path, string $filter, int $colIdx, string $pluginName,
                         array $items, string $mode, int $baseline): string
{
  static $folderCache = [];

  if (empty($path)) {
    return "<div class='card card-body p-1 bg-light'><em>No path</em></div>";
  }

  $Last = $path[count($path) - 1];
  $Uri2 = Traceback_uri() . "?mod=$pluginName";
  $baseQS = "&filter=" . urlencode($filter) . "&mode=" . urlencode($mode)
          . "&baseline=$baseline";

  $FreezeText = _("Freeze");
  $freezeBtnId = "Freeze$colIdx";
  $freezeOpts = "id='$freezeBtnId' onclick='Freeze($colIdx)'"
              . " class='btn btn-outline-secondary'"
              . " style='font-size:0.65rem;padding:1px 5px;line-height:1.3;white-space:nowrap;flex-shrink:0'";

  $uploadFk = $path[0]['upload_fk'];
  if (!isset($folderCache[$uploadFk])) {
    $folderCache[$uploadFk] = FolderGetFromUpload($uploadFk);
  }
  $FolderList = $folderCache[$uploadFk];

  $parts = [];
  $parts[] = "<div class='card card-body p-1 bg-light'>";
  $parts[] = "<div class='d-flex justify-content-between align-items-start'>";
  $parts[] = "<div>";
  $parts[] = "<strong>" . _("Folder") . ":</strong> ";
  foreach ($FolderList as $Folder) {
    $parts[] = "<b>" . htmlspecialchars($Folder['folder_name']) . "/</b>";
  }
  $parts[] = "<br>";

  /* Single-line path: parent/parent/<b>current</b> */
  foreach ($path as $idx => $PathElt) {
    $itemsForLink = $items;
    $itemsForLink[$colIdx] = $PathElt['uploadtree_pk'];
    $href = $Uri2 . "&items=" . implode(",", $itemsForLink) . "&col=$colIdx$baseQS";
    $isLast = ($PathElt['uploadtree_pk'] == $Last['uploadtree_pk']);
    if ($idx > 0) {
      $parts[] = "/";
    }
    if ($isLast) {
      $parts[] = "<b>" . htmlspecialchars($PathElt['ufile_name']) . "</b>";
    } else {
      $parts[] = "<a href='" . htmlspecialchars($href) . "'>"
               . htmlspecialchars($PathElt['ufile_name']) . "</a>";
    }
  }

  $parts[] = "</div>";
  $parts[] = "<button type='button' $freezeOpts>$FreezeText</button>";
  $parts[] = "</div>";
  $parts[] = "</div>";
  return implode("", $parts);
}
