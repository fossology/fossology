<?php
/*
 SPDX-FileCopyrightText: © 2010-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \file PickerPlugin.php
 * \brief Multi-component and 2-way file picker.
 */

class PickerPlugin extends DefaultPlugin
{
  const NAME = 'picker';

  /** @var DbManager */
  private $dbManager;
  /** @var UploadDao */
  private $uploadDao;

  public function __construct()
  {
    parent::__construct(self::NAME, [
      self::TITLE => _("File Picker"),
      self::DEPENDENCIES => ["browse", "view"],
      self::PERMISSION => Auth::PERM_READ,
      self::REQUIRES_LOGIN => true,
    ]);
    $this->dbManager = $this->getObject('db.manager');
    $this->uploadDao = $this->getObject('dao.upload');
  }

  public function preInstall(): void
  {
    // Browse-Pfile: used by the file-listing rows in ui-browse.php
    menu_insert("Browse-Pfile::Compare", 4, self::NAME, _("Compare this file to another."));
  }

  // ── DB setup ───────────────────────────────────────────────────────────

  private function createFilePicker(): void
  {
    if (!$this->dbManager->existsTable('file_picker')) {
      $this->dbManager->queryOnce(
        "CREATE TABLE file_picker (
          file_picker_pk serial NOT NULL PRIMARY KEY,
          user_fk integer NOT NULL,
          uploadtree_fk1 integer NOT NULL,
          uploadtree_fk2 integer NOT NULL,
          last_access_date date NOT NULL
        );
        ALTER TABLE ONLY file_picker
          ADD CONSTRAINT file_picker_user_fk_key
          UNIQUE (user_fk, uploadtree_fk1, uploadtree_fk2)",
        __METHOD__
      );
    }
    $this->createFilePickerMulti();
  }

  private function createFilePickerMulti(): void
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
   * Return the upload-specific uploadtree table name for a given uploadtree pk.
   * Falls back to 'uploadtree' if the row is not found.
   */
  private function getUploadtreeTableName(int $uploadtreePk): string
  {
    $row = $this->dbManager->getSingleRow(
      "SELECT u.uploadtree_tablename
       FROM upload u
       JOIN uploadtree ut ON ut.upload_fk = u.upload_pk
       WHERE ut.uploadtree_pk = \$1",
      [$uploadtreePk], __METHOD__
    );
    return $row['uploadtree_tablename'] ?? 'uploadtree';
  }

  /**
   * Return permission-filtered uploads for a folder, with timestamps formatted
   * for the browser.
   */
  private function getFolderUploads(int $folder_pk): array
  {
    $groupId = Auth::getGroupId();
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
      "SELECT u.upload_pk, u.upload_filename AS name,
              u.upload_desc, u.upload_ts, u.uploadtree_tablename,
              ut.uploadtree_pk
       FROM foldercontents fc
       JOIN upload u ON u.upload_pk = fc.child_id
                     AND fc.foldercontents_mode = 2
                     AND (u.upload_mode & (1<<5)) != 0
       JOIN uploadtree ut ON ut.upload_fk = u.upload_pk AND ut.parent IS NULL
       WHERE fc.parent_fk = \$1
       ORDER BY u.upload_filename, u.upload_pk"
    );
    $res = $this->dbManager->execute($stmt, [$folder_pk]);
    $uploads = [];
    while ($row = $this->dbManager->fetchArray($res)) {
      if (!$this->uploadDao->isAccessible($row['upload_pk'], $groupId)) {
        continue;
      }
      $uploads[] = [
        'upload_pk' => intval($row['upload_pk']),
        'uploadtree_pk' => intval($row['uploadtree_pk']),
        'name' => htmlspecialchars($row['name']),
        'desc' => htmlspecialchars((string)($row['upload_desc'] ?? '')),
        'ts' => !empty($row['upload_ts'])
            ? htmlspecialchars(Convert2BrowserTime(substr($row['upload_ts'], 0, 19)))
            : '',
      ];
    }
    $this->dbManager->freeResult($res);
    return $uploads;
  }

  /**
   * Pick history select-box for classic 2-way mode.
   * Returns HTML string or empty string.
   */
  private function historyPick(int $uploadtree_pk, int &$rtncount): string
  {
    $user_pk = Auth::getUserId();
    if (empty($user_pk)) {
      return "";
    }
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
      "SELECT file_picker_pk, uploadtree_fk1, uploadtree_fk2 FROM file_picker
       WHERE user_fk=\$1 AND (\$2=uploadtree_fk1 OR \$2=uploadtree_fk2)"
    );
    $result = $this->dbManager->execute($stmt, [$user_pk, $uploadtree_pk]);
    $pickerRows = [];
    while ($row = $this->dbManager->fetchArray($result)) {
      $pickerRows[] = $row;
    }
    $this->dbManager->freeResult($result);
    $rtncount = count($pickerRows);

    if ($rtncount < 1) {
      return "";
    }

    $PickSelectArray = [];
    foreach ($pickerRows as $PickRec) {
      $item2 = ($PickRec['uploadtree_fk1'] == $uploadtree_pk)
          ? $PickRec['uploadtree_fk2'] : $PickRec['uploadtree_fk1'];
      $tableName = $this->getUploadtreeTableName($item2);
      $PathArray = Dir2Path($item2, $tableName);
      $PickSelectArray[$item2] = $this->uploadtree2PathStr($PathArray);
    }

    $Options = "id=HistoryPick onchange='AppJump(this.value)'";
    return Array2SingleSelect($PickSelectArray, "HistoryPick", "", true, true, $Options);
  }

  private function applicationPick(string $SLName, string $selectedVal, string $label): string
  {
    $AppList = [
      "multicompare" => _("Upload Comparison"),
      "nomosdiff" => _("License Difference (2-way)"),
      "bucketsdiff" => _("Bucket Difference (2-way)"),
    ];
    $Options = "id=apick";
    $SelectList = Array2SingleSelect($AppList, $SLName, $selectedVal, false, true, $Options);
    return $label ? "$SelectList $label" : $SelectList;
  }

  private function uploadtree2PathStr(array $PathArray): string
  {
    $parts = [];
    foreach ($PathArray as $PathRow) {
      $parts[] = $PathRow['ufile_name'];
    }
    return implode('/', $parts);
  }

  // ── Request handler ────────────────────────────────────────────────────

  protected function handle(Request $request): Response
  {
    $this->createFilePicker();

    $RtnMod = $request->get('rtnmod', 'multicompare');
    $uploadtree_pk = (int)($request->get('item', 0));
    $folder_pk = (int)($request->get('folder', 0));
    $user_pk = Auth::getUserId();

    /* Parse items (comma-separated, same format as multicompare) */
    $multiItems = [];
    $rawItems = $request->get('items', '');
    if (!empty($rawItems)) {
      $multiItems = array_values(array_unique(array_filter(
        array_map('intval', explode(',', $rawItems)), fn($v) => $v > 0
      )));
    }

    /* ── Multi-compare flow ───────────────────────────────────────────── */
    if ($RtnMod === 'multicompare') {
      if (!empty($uploadtree_pk) && !in_array($uploadtree_pk, $multiItems)) {
        array_unshift($multiItems, $uploadtree_pk);
      }

      if (empty($multiItems)) {
        return $this->flushContent(
          "<div class='alert alert-warning'>"
          . _("No component selected. Please navigate to an upload and click Compare.")
          . " <a href='" . Traceback_uri() . "?mod=browse'>" . _("Go to Browse") . "</a>"
          . "</div>"
        );
      }

      foreach ($multiItems as $idx => $pk) {
        $row = $this->dbManager->getSingleRow(
          "SELECT ut.upload_fk, u.uploadtree_tablename
           FROM uploadtree ut
           JOIN upload u ON u.upload_pk = ut.upload_fk
           WHERE ut.uploadtree_pk = \$1",
          [$pk], __METHOD__ . '.permcheck'
        );
        if (!$row || !$this->uploadDao->isAccessible($row['upload_fk'], Auth::getGroupId())) {
          return $this->flushContent(
            "<h2>" . _("Permission Denied") . " (item " . ($idx + 1) . ")</h2>"
          );
        }
      }

      if (empty($folder_pk)) {
        $folder_pk = GetFolderFromItem("", $multiItems[0]);
      }

      if (!empty($user_pk) && count($multiItems) >= 2) {
        $stmt = __METHOD__ . '.insertMulti';
        $this->dbManager->prepare($stmt,
          "INSERT INTO file_picker_multi (user_fk, items, last_access_date)
           VALUES(\$1, \$2, now()) ON CONFLICT DO NOTHING"
        );
        $res = $this->dbManager->execute($stmt, [$user_pk, implode(',', $multiItems)]);
        $this->dbManager->freeResult($res);
      }

      return $this->renderPicker($RtnMod, $multiItems[0], $folder_pk, $multiItems);
    }

    /* ── Classic 2-way flow ───────────────────────────────────────────── */
    if (!$uploadtree_pk) {
      return $this->flushContent("<h2>" . _("Unidentified item 1") . "</h2>");
    }

    $uploadtree_pk2 = (int)($request->get('item2', 0));

    $Item1Row = $this->dbManager->getSingleRow(
      "SELECT ut.upload_fk, u.uploadtree_tablename
       FROM uploadtree ut
       JOIN upload u ON u.upload_pk = ut.upload_fk
       WHERE ut.uploadtree_pk = \$1",
      [$uploadtree_pk], __METHOD__ . '.item1'
    );
    if (!$this->uploadDao->isAccessible($Item1Row['upload_fk'], Auth::getGroupId())) {
      return $this->flushContent("<h2>" . _("Permission Denied") . " item 1</h2>");
    }

    if (!empty($uploadtree_pk2)) {
      $Item2Row = $this->dbManager->getSingleRow(
        "SELECT ut.upload_fk, u.uploadtree_tablename
         FROM uploadtree ut
         JOIN upload u ON u.upload_pk = ut.upload_fk
         WHERE ut.uploadtree_pk = \$1",
        [$uploadtree_pk2], __METHOD__ . '.item2'
      );
      if (!$this->uploadDao->isAccessible($Item2Row['upload_fk'], Auth::getGroupId())) {
        return $this->flushContent("<h2>" . _("Permission Denied") . " item 2</h2>");
      }
    }

    /* Redirect to comparison plugin when both items are set */
    if (!empty($user_pk) && !empty($RtnMod) && $uploadtree_pk && $uploadtree_pk2) {
      $stmt = __METHOD__ . '.insertPicker';
      $this->dbManager->prepare($stmt,
        "INSERT INTO file_picker (user_fk, uploadtree_fk1, uploadtree_fk2, last_access_date)
         VALUES(\$1, \$2, \$3, now())"
      );
      $res = $this->dbManager->execute($stmt, [$user_pk, $uploadtree_pk, $uploadtree_pk2]);
      $this->dbManager->freeResult($res);
      $uri = Traceback_uri() . "?mod=$RtnMod&item1=$uploadtree_pk&item2=$uploadtree_pk2";
      return new RedirectResponse($uri);
    }

    if (empty($folder_pk)) {
      $folder_pk = GetFolderFromItem("", $uploadtree_pk);
    }
    return $this->renderPicker($RtnMod, $uploadtree_pk, $folder_pk, []);
  }

  /**
   * Build Twig vars and render picker.html.twig.
   */
  private function renderPicker(string $RtnMod, int $anchorPk,
                                int $folder_pk, array $currentItems): Response
  {
    $isMulti = ($RtnMod === 'multicompare');
    $uri = Traceback_uri() . "?mod=" . self::NAME;

    /* Folder dropdown */
    $rootFolder = FolderGetTop();
    $folderOptions = FolderListOption($rootFolder, 0, 1, $folder_pk);

    /* Upload list — for multi mode, exclude already-selected uploads */
    $uploads = $this->getFolderUploads($folder_pk);
    if ($isMulti && !empty($currentItems)) {
      $selectedUploadPks = [];
      foreach ($currentItems as $utPk) {
        $row = $this->dbManager->getSingleRow(
          "SELECT upload_fk FROM uploadtree WHERE uploadtree_pk = \$1",
          [$utPk], __METHOD__ . '.uploadfk'
        );
        if ($row) {
          $selectedUploadPks[] = intval($row['upload_fk']);
        }
      }
      $uploads = array_values(array_filter(
        $uploads, fn($u) => !in_array($u['upload_pk'], $selectedUploadPks)
      ));
    }

    /* App picker HTML */
    $appPicker = $this->applicationPick(
      "PickRtnApp", $RtnMod,
      $isMulti ? "" : _("will run after choosing a file")
    );

    $vars = [
      'isMulti' => $isMulti,
      'currentItems' => $currentItems,
      'folderOptions' => $folderOptions,
      'uploads' => $uploads,
      'appPicker' => $appPicker,
      /* JS vars */
      'currentItemsJson' => "[" . implode(",", array_map('intval', $currentItems)) . "]",
      'folderPk' => $folder_pk,
      'anchorPk' => $anchorPk,
      'rtnmod' => $RtnMod,
      'pickerUri' => $uri,
    ];

    if ($isMulti) {
      $count = count($currentItems);

      /* Compare Now button URI */
      $vars['compareUri'] = $count >= 2
          ? htmlspecialchars(Traceback_uri() . "?mod=multicompare&items="
              . implode(",", $currentItems))
          : '';

      /* Path strings and Remove URIs for each selected item */
      $selectedPaths = [];
      $removeUris = [];
      foreach ($currentItems as $pk) {
        $tableName = $this->getUploadtreeTableName($pk);
        $pathArr = Dir2Path($pk, $tableName);
        $selectedPaths[$pk] = $this->uploadtree2PathStr($pathArr);
        $remaining = array_values(array_filter($currentItems, fn($p) => $p !== $pk));
        $removeUri = $uri . "&rtnmod=$RtnMod&folder=$folder_pk";
        if (!empty($remaining)) {
          $removeUri .= "&items=" . implode(',', $remaining);
        }
        $removeUris[$pk] = htmlspecialchars($removeUri);
      }
      $vars['selectedPaths'] = $selectedPaths;
      $vars['removeUris'] = $removeUris;
      $vars['hint'] = $count < 2
          ? _("Select at least 2 components to compare.")
          : _("Add more components below, or click Compare Now.");
      $vars['browseHint'] = _("Choose a folder, then click Add next to an upload.");
    } else {
      $tableName = $this->getUploadtreeTableName($anchorPk);
      $pathStr = $this->uploadtree2PathStr(Dir2Path($anchorPk, $tableName));
      $vars['pathStr'] = htmlspecialchars($pathStr);
      $vars['browseHint'] = _("Choose a folder, then click Select to pick File 2.");

      $historyCount = 0;
      $historyPick = $this->historyPick($anchorPk, $historyCount);
      $vars['historyPick'] = $historyPick;
      $vars['historyCount'] = $historyCount;
    }

    return $this->render('picker.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new PickerPlugin());
