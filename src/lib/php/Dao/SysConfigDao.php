<?php
/*
 SPDX-FileCopyrightText: ©  Soham Banerjee <sohambanerjee4abc@hotmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\Db\DbManager;
use Monolog\Logger;


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
   * \brief Fetch banner message.
   */
  function getBannerData()
  {
    return $this->dbManager->getSingleRow("SELECT conf_value FROM sysconfig
                  WHERE variablename = 'BannerMsg'")["conf_value"];
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
   * @brief Update Configuration Data
   *
   * Update the sysconfig data after validating the value.
   * @return array[bool, string] true on success, false on failure with error
   *  message
   */
  public function UpdateConfigData($data)
  {
    $key = strval($data['key']);
    $value = strval($data['value']);

    $sysconfigData = $this->getConfigData();
    $oldarray = [];
    foreach ($sysconfigData as $item) {
      $oldarray[$item['variablename']] = $item['conf_value'];
    }

    if ($value != $oldarray[$key]) {
      /* get validation_function row from sysconfig table */
      $sys_array = $this->dbManager->getSingleRow("SELECT validation_function, 
       ui_label FROM sysconfig WHERE variablename = $1", [$key],__METHOD__.'.getVarNameData');
      $validation_function = $sys_array['validation_function'];
      $ui_label = $sys_array['ui_label'];
      $is_empty = empty($validation_function);
      /*
       * 1. the validation_function is empty
       * 2. the validation_function is not empty, and after checking, the value
       * is valid update sysconfig table
       */
      if ($is_empty || (! $is_empty && (1 == $validation_function($value)))) {
        $this->dbManager->getSingleRow(
          "UPDATE sysconfig SET conf_value=$1 WHERE variablename=$2",
          [$value, $key], __METHOD__ . '.setVarNameData');
        return [true, $key];
      } else if (! $is_empty && (0 == $validation_function($value))) {
        /*
         * the validation_function is not empty, but after checking, the value
         * is invalid
         */
        $warning_msg = "Error: Unable to update $key.";
        if (! strcmp($validation_function, 'check_boolean')) {
          $warning_msg = _(
            "Error: You set $ui_label to $value. Valid  values are 'true' and 'false'.");
        } else if (strpos($validation_function, "url")) {
          $warning_msg = _(
            "Error: $ui_label $value, is not a reachable URL.");
        }
        return [false, $warning_msg];
      }
    }
    return [true, $key];
  }
}
