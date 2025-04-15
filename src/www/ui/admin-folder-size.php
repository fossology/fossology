<?php
/*
  SPDX-FileCopyrightText: © 2023 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Db\DbManager;

define("TITLE_SIZE_DASHBOARD", _("Folder and upload dashboard"));

class size_dashboard extends FO_Plugin
{

  /** @var DbManager */
  private $dbManager;

  /**
   * @var UploadDao $uploadDao
   */
  private $uploadDao;

  function __construct()
  {
    $this->Name = "size_dashboard";
    $this->Title = TITLE_SIZE_DASHBOARD;
    $this->MenuList = "Admin::Dashboards::Folder/Upload Proportions";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }

  /**
   * \brief Given a folder's ID
   * function will get size of the folder and uploads under it.
   * \return folder data.
   */
  function getFolderAndUploadSize($folderId)
  {
    $sql = 'INNER JOIN upload ON upload.pfile_fk=pfile.pfile_pk '.
           'INNER JOIN foldercontents ON upload.upload_pk=foldercontents.child_id '.
           'WHERE parent_fk=$1;';
    $statementName = __METHOD__."GetFolderSize";
    $folderSizesql = 'SELECT SUM(pfile_size) FROM pfile '.$sql;
    $row = $this->dbManager->getSingleRow($folderSizesql,array($folderId),$statementName);
    $folderSize = HumanSize($row['sum']);

    $statementName = __METHOD__."GetEachUploadSize";
    $dispSql = "SELECT upload_pk, upload_filename, pfile_size, " .
      "to_char(upload_ts, 'YYYY-MM-DD HH24:MI:SS') AS upload_ts FROM pfile " . $sql;
    $results = $this->dbManager->getRows($dispSql, [$folderId], $statementName);
    $var = '';
    foreach ($results as $result) {
      $clearingDuration = $this->uploadDao->getClearingDuration($result["upload_pk"]);
      $var .= "<tr><td align='left'>" . $result['upload_filename'] .
        "</td><td align='left' data-order='{$result['pfile_size']}'>" .
        HumanSize($result['pfile_size']) .
        "</td><td align='left' data-order='{$clearingDuration[1]}'>$clearingDuration[0]</td>
        <td align='left'>{$result['upload_ts']}</td></tr>";
    }
    return [$var, $folderSize];
  }

  /**
   * \brief Generate export data in CSV or JSON format for a given folder ID.
   * \param folderId The ID of the folder.
   * \param format The export format (csv or json).
   */
  private function generateExportData($folderId, $format)
  {
    $results = $this->dbManager->getRows(
      "SELECT upload_pk, upload_filename, pfile_size, to_char(upload_ts, 'YYYY-MM-DD HH24:MI:SS') AS upload_ts " .
      "FROM pfile " .
      "INNER JOIN upload ON upload.pfile_fk=pfile.pfile_pk " .
      "INNER JOIN foldercontents ON upload.upload_pk=foldercontents.child_id " .
      "WHERE parent_fk=$1",
      [$folderId],
      __METHOD__."ExportData"
    );

    $data = [];
    foreach ($results as $row) {
      $clearingDuration = $this->uploadDao->getClearingDuration($row["upload_pk"]);
      $data[] = [
        'name' => $row['upload_filename'],
        'size' => $row['pfile_size'],
        'duration' => $clearingDuration[1],
        'date' => $row['upload_ts']
      ];
    }

    switch ($format) {
      case 'csv':
        $this->outputCSV($data);
        break;
      case 'json':
        $this->outputJSON($data);
        break;
    }
  }

  /**
   * \brief Outputs data in CSV format.
   * \param data The data array to be converted into CSV.
   */
  private function outputCSV($data)
  {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="folder_export.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Upload Name', 'Size (bytes)', 'Clearing Duration (seconds)', 'Upload Date']);

    foreach ($data as $row) {
      fputcsv($output, [
        $row['name'],
        $row['size'],
        $row['duration'],
        $row['date']
      ]);
    }
    fclose($output);
    exit;
  }

  /**
   * \brief Outputs data in JSON format.
   * \param data The data array to be converted into JSON.
   */
  private function outputJSON($data)
  {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="folder_export.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
  }

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output()
  {
    $exportFormat = GetParm('export', PARM_STRING);
    if (!empty($exportFormat) && in_array($exportFormat, ['csv', 'json'])) {
      $folderId = GetParm('folder', PARM_INTEGER);
      $this->generateExportData($folderId, $exportFormat);
    }
    /* If this is a POST, then process the request. */
    $folderId = GetParm('selectfolderid', PARM_INTEGER);
    if (empty($folderId)) {
      $folderId = FolderGetTop();
    }
    list($tableVars, $wholeFolderSize) = $this->getFolderAndUploadSize($folderId);

    /* Display the form */
    $formVars["onchangeURI"] = Traceback_uri() . "?mod=" . $this->Name . "&selectfolderid=";
    $formVars["folderListOption"] = FolderListOption(-1, 0, 1, $folderId);
    $formVars["tableVars"] = $tableVars;
    $formVars["wholeFolderSize"] = $wholeFolderSize;
    $formVars["currentFolderId"] = $folderId;
    $formVars["pluginName"] = $this->Name;
    return $this->renderString("admin-folder-size-form.html.twig", $formVars);
  }
}
$NewPlugin = new size_dashboard;
