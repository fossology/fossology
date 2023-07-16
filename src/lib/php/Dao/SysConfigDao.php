<?php
/*
 SPDX-FileCopyrightText: ©  Soham Banerjee <sohambanerjee4abc@hotmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Monolog\Logger;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;



class SysConfigDao
{
  const TYPE_MAP = array(
    '1' => "int",
    '2' => "text",
    '3' => "textarea",
    '4' => "password",
    '5' => "dropdown",
    '6' => "boolean"
  );
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;

  function __construct(DbManager $dbManager, Logger $logger)
  {
    $this->dbManager = $dbManager;
    $this->logger = $logger;
  }

  /**
   * \brief Fetch configuration rows.
   */
  function getConfigData()
  {
    $sql = "select * from sysconfig order by group_name, group_order";
    return $this->dbManager->getRows($sql);
  }

  /**
   * Get all customise information for admin
   * @param array $data Array of data from the sysconfig table
   * @return array
   */
  public function getCustomiseData($data)
  {
    $finalVal = [];
    foreach ($data as $row) {
      $type = self::TYPE_MAP[$row['vartype']];
      $finalVal[] = array(
        "key" => $row['variablename'],
        "value" => $row['conf_value'],
        "type" => $type,
        "label" => $row['ui_label'],
        "group_name" => $row['group_name'],
        "group_order" => intval($row['group_order']),
        "option_value" => $row["option_value"]
      );
    }
    return $finalVal;
  }

  /**
   * \brief Update Configuration Data.
   *  @return Info
   */
  public function UpdateConfigData($data)
  {
    $key = strval($data['key']);
    $value = strval($data['value']);
    $stmt = __METHOD__ . 'UpdateConfigData'.$key;
    $sql = "UPDATE sysconfig SET conf_value = $2 WHERE variablename = $1";
    $this->dbManager->getSingleRow($sql, array($key, $value), $stmt);
    return (new Info(200, 'Succesfully Updated Customise data', InfoType::INFO));
  }
}
