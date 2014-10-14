<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\Data\Tree;


class UploadTreeView
{
  /** @var string */
  private $uploadTreeTableName;

  /** @var string */
  public $uploadTreeViewQuery;

  /**
   * @param int $uploadId
   * @param array $options
   * @param string $uploadTreeTableName
   * @param string $additionalCondition
   */
  public function __construct($uploadId, $options, $uploadTreeTableName, $additionalCondition = "")
  {
    $this->uploadTreeTableName = $uploadTreeTableName;
    $this->uploadTreeViewQuery = self::createUploadTreeViewQuery($uploadId, $options, $uploadTreeTableName, $additionalCondition);
  }

  /**
   * @return string
   */
  public function getUploadTreeTableName()
  {
    return $this->uploadTreeTableName;
  }

  /**
   * @return string
   */
  public function getUploadTreeViewQuery()
  {
    return $this->uploadTreeViewQuery;
  }


  /**
   * @param int $uploadId
   * @param array $options
   * @param string $uploadTreeTableName
   * @param string $additionalCondition
   * @return string
   */
  private static function createUploadTreeViewQuery($uploadId, $options, $uploadTreeTableName, $additionalCondition = "")
  {
    return $options === null ?
        self::getDefaultUploadTreeView($uploadId, $uploadTreeTableName) :
        self::getUploadTreeView($uploadId, $options, $uploadTreeTableName, $additionalCondition);
  }

  /**
   * @param $uploadId
   * @param $uploadTreeTableName
   * @return string
   */
  private static function getDefaultUploadTreeView($uploadId, $uploadTreeTableName)
  {
    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName)
    {
      $sql_upload = " WHERE ut.upload_fk=$uploadId ";
    }
    $uploadTreeView = "WITH UploadTreeView  As ( SELECT * FROM $uploadTreeTableName UT $sql_upload)";
    return $uploadTreeView;
  }

  /**
   * @param $uploadId
   * @param $options
   * @param $uploadTreeTableName
   * @param $additionalCondition
   * @return string
   */
  private static function getUploadTreeView($uploadId, $options, $uploadTreeTableName, $additionalCondition)
  {
      $skipThese = $options['skipThese'];
      if (isset($skipThese))
      {
        switch ($skipThese)
        {
          case "none":
            break;
          case "noLicense":
          case "alreadyCleared":
          case "noCopyright":

            $queryCondition = self::getQueryCondition($skipThese);
            $sql_upload = "";
            if ('uploadtree_a' == $uploadTreeTableName)
            {
              $sql_upload = " AND ut.upload_fk=$uploadId ";
            }
            $uploadTreeView = " WITH UploadTreeView AS (
                                select
                                  *
                                from $uploadTreeTableName ut
                                where
                                  (
                                   $queryCondition
                                   $additionalCondition
                                  )
                                  $sql_upload
                                )";
            return $uploadTreeView;
        }
      }
      //default case, if cookie is not set or set to none
      $uploadTreeView = self::getDefaultUploadTreeView($uploadId, $uploadTreeTableName);
      return $uploadTreeView;
  }

  /**
   * @param $skipThese
   * @return string
   */
  private function getQueryCondition($skipThese)
  {
    $conditionQueryHasLicense = "EXISTS (SELECT rf_pk FROM license_file_ref lr WHERE rf_shortname NOT IN ('No_license_found', 'Void') AND lr.pfile_fk=ut.pfile_fk)";

    switch ($skipThese)
    {
      case "noLicense":
        return $conditionQueryHasLicense;
      case "alreadyCleared":
//        $conditionQuery = "( $conditionQueryHasLicense
//              AND  NOT EXISTS  (SELECT license_decision_event_pk
//                                    FROM license_decision_event lde where ut.uploadtree_pk = lde.uploadtree_fk
//                                    OR ( lde.pfile_fk = ut.pfile_fk AND lde.is_global = TRUE  ) ) )";
        $conditionQuery = "( $conditionQueryHasLicense
              AND  NOT EXISTS  (SELECT clearing_decision_pk
                                    FROM clearing_decision cd where ut.uploadtree_pk = cd.uploadtree_fk
                                    OR ( cd.pfile_fk = ut.pfile_fk AND cd.is_global = TRUE  ) ) )";
        return $conditionQuery;
      case "noCopyright":
        $conditionQuery = "EXISTS (SELECT ct_pk FROM copyright cp WHERE cp.pfile_fk=ut.pfile_fk and cp.hash is not null )";
        return $conditionQuery;
    }


  }

} 