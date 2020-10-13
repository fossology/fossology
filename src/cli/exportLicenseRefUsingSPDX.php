<?php
/***********************************************************
 Copyright (C) 2019 Siemens AG

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
 ***********************************************************/

/**
 * @file exportLicenseRefUsingSPDX.php
 * @brief Export a licenses from SPDX.
 *
 * This is typically used to export license data from SPDX json
 **/

class exportLicenseRef
{
  /**
   * @var mapArrayData $mapArrayData
   * actual names for license/exception in SPDX for text and licenseid
   */
  private $mapArrayData = array(
    'licenses' => array('licenseId', 'licenseText', 'name'),
    'exceptions' => array('licenseExceptionId', 'licenseExceptionText', 'name')
  );


  function startProcessingLicenseData()
  {
    global $argv;
    $updateWithNew = '';
    $updateExisting = '';
    $addNewLicense = '';
    $newLicenseRefData = array();
    $usage = "Usage: " . basename($argv[0]) . " [options]

      Create new licenseref.json file.  Options are:
        -E    Update all existing licenses and also add new licenses.
              (NOTE: there may be failure of test cases)

        -e    Only update existing licenses.
              (NOTE: there may be failure of test cases)

        -n    Only add new licenses.

       --type Usually licenses/exceptions (optional)
              (ex: --type 'licenses')

       --url From where you want to download (optional)
             (ex: --url 'https://spdx.org/licenses/licenses.json')

      Additional note:
        (if --type and --url is empty then the script will automatically download the from below)
          For type 'licenses' URL is : $scanList[licenses]

          For type 'exceptions' URL is : $scanList[exceptions]";
    $scanList = array(
    'licenses' => 'https://spdx.org/licenses/licenses.json',
    'exceptions' => 'https://spdx.org/licenses/exceptions.json'
    );

    $options = getopt("hcEen", array("type:","url:"));
    /* get type and url if exists if not set them to empty */
    $type = array_key_exists("type", $options) ? $options["type"] : '';
    $URL =  array_key_exists("url", $options) ? $options["url"] : '';
    foreach ($options as $option => $optVal) {
      switch ($option) {
        case 'c': /* used by fo_wrapper */
          break;
        case 'E': /* Update all existing licenses and also add new licenses */
          $updateWithNew = $option;
          break;
        case 'e': /* Only update existing licenses */
          $updateExisting = $option;
          break;
        case 'n': /* only add new licenses */
          $addNewLicense = $option;
          break;
        case 'h': /* help */
          $showUsage = true;
          break;
      }
    }

    if ($showUsage) {
      print "$usage\n";
      exit;
    }

    if (!empty($updateWithNew) || !empty($updateExisting) || !empty($addNewLicense)) {
      if (!empty($type) && !empty($URL)) {
        $newLicenseRefData = $this->getListSPDX($type, $URL, $updateWithNew, $updateExisting, $addNewLicense, $newLicenseRefData);
      } else if (!empty($type) && empty($URL)) {
        echo "Notice: --url cannot be empty if --type is provided \n";
      } else if (empty($type) && !empty($URL)) {
        echo "Notice: --type cannot be empty if --url is provided \n";
      } else {
        foreach ($scanList as $type => $URL) {
          $newLicenseRefData = $this->getListSPDX($type, $URL, $updateWithNew, $updateExisting, $addNewLicense, $newLicenseRefData);
        }
      }
      $newFileName = "licenseRefNew.json";
      if (file_exists($newFileName)) {
        unlink($newFileName);
      }
      $file = fopen($newFileName,'w+');
      file_put_contents($newFileName, json_encode($newLicenseRefData, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES));
      fclose($file);
      echo "\n\n INFO: new $newFileName file created \n\n";
    } else {
      echo "\nINVALID OPTION PROVIDED\n\n";
      print "$usage\n";
      exit;
    }
  }

  /**
   * @brief check if -only or -or-later exists.
   *
   * Check if -only or -or-later exists
   * @returns license name after concatunation otherwise actual license name.
   */
  function getLicenseNameWithOutSuffix($RFShortName)
  {
    if (strpos($RFShortName, "-only") !== false) {
      return strstr($RFShortName, "-only", true);
    } else if (strpos($RFShortName, "-or-later") !== false) {
      $licenseShortname = strstr($RFShortName, "-or-later", true);
      return $licenseShortname."+";
    } else {
      return $RFShortName;
    }
  }

  /**
   * @brief get SPDX license or exception list and update licenseref.json
   *
   * get SPDX license or exception list
   * update the licenseref.json file with changes in existing license text
   * or add a new license if licenseref.json does'nt contain it.
   * Create a new licenserefnew.json file from where it is getting executed.
   * user need to copy the additional license texts from licenserefnew.json
   * to actual licenseref.json
   */
  function getListSPDX($type, $URL, $updateWithNew, $updateExisting, $addNewLicense, $existingLicenseRefData)
  {
    global $LIBEXECDIR;

    if (!is_dir($LIBEXECDIR)) {
      print "FATAL: Directory '$LIBEXECDIR' does not exist.\n";
      return (1);
    }

    $dir = opendir($LIBEXECDIR);
    if (!$dir) {
      print "FATAL: Unable to access '$LIBEXECDIR'.\n";
      return (1);
    }
    /* check if licenseref.json exists */
    $fileName = "$LIBEXECDIR/licenseRef.json";
    if (!file_exists($fileName)) {
      print "FATAL: File '$fileName' does not exist.\n";
      return (1);
    }

    if (empty($existingLicenseRefData)) {
      echo "INFO: get existing licenseRef.json from $LIBEXECDIR\n";
      $getExistingLicenseRefData = file_get_contents("$fileName");
      /* dump all the data from licenseRef.json file to a array */
      $existingLicenseRefData = (array) json_decode($getExistingLicenseRefData, true);
    }
    /* get license list and each license's URL */
    $getList = json_decode(file_get_contents($URL));
    foreach ($getList->$type as $listValue) {
      /* get current license data from given URL */
      $getCurrentData = file_get_contents($listValue->detailsUrl);
      $getCurrentData = (array) json_decode($getCurrentData, true);
      echo "INFO: search for license ".$getCurrentData[$this->mapArrayData[$type][0]]."\n";
      /* check if the licenseid of the current license exists in old license data */
      $licenseIdCheck = array_search($getCurrentData[$this->mapArrayData[$type][0]], array_column($existingLicenseRefData, 'rf_shortname'));
      $currentText = $getCurrentData[$this->mapArrayData[$type][1]];
      $textCheck = array_search($currentText, array_column($existingLicenseRefData, 'rf_text'));
      if (!is_numeric($licenseIdCheck)) {
        /* if licenseid does'nt exists then remove the suffix if any and search again */
        $getCurrentData[$this->mapArrayData[$type][0]] = $this->getLicenseNameWithOutSuffix($getCurrentData[$this->mapArrayData[$type][0]]);
        $getCurrentData[$this->mapArrayData[$type][0]];
        $licenseIdCheck = array_search($getCurrentData[$this->mapArrayData[$type][0]], array_column($existingLicenseRefData, 'rf_shortname'));
      }
      if (is_numeric($licenseIdCheck) &&
          !is_numeric($textCheck) &&
          (
            !empty($updateWithNew) ||
            !empty($updateExisting)
          )
         ) {
        $existingLicenseRefData[$licenseIdCheck]['rf_fullname'] = $getCurrentData[$this->mapArrayData[$type][2]];
        $existingLicenseRefData[$licenseIdCheck]['rf_text'] = $getCurrentData[$this->mapArrayData[$type][1]];
        $existingLicenseRefData[$licenseIdCheck]['rf_url'] = $getCurrentData['seeAlso'][0];
        $existingLicenseRefData[$licenseIdCheck]['rf_notes'] = (array_key_exists("licenseComments", $getCurrentData) ? $getCurrentData['licenseComments'] : $existingLicenseRefData[$licenseIdCheck]['rf_notes']);
        echo "INFO: license ".$getCurrentData[$this->mapArrayData[$type][0]]." updated\n\n";
      }
      if (!is_numeric($licenseIdCheck) &&
          !is_numeric($textCheck) &&
          (
            !empty($updateWithNew) ||
            !empty($addNewLicense)
          )
         ) {
        $existingLicenseRefData[] = array(
          'rf_shortname' => $getCurrentData[$this->mapArrayData[$type][0]],
          'rf_text' =>  $getCurrentData[$this->mapArrayData[$type][1]],
          'rf_url' =>  $getCurrentData['seeAlso'][0],
          'rf_add_date' => null,
          'rf_copyleft' => null,
          'rf_OSIapproved' => null,
          'rf_fullname' => $getCurrentData[$this->mapArrayData[$type][2]],
          'rf_FSFfree' => null,
          'rf_GPLv2compatible' => null,
          'rf_GPLv3compatible' => null,
          'rf_notes' => (array_key_exists("licenseComments", $getCurrentData) ? $getCurrentData['licenseComments'] : null),
          'rf_Fedora' => null,
          'marydone' => "f",
          'rf_active' => "t",
          'rf_text_updatable' => "f",
          'rf_detector_type' => 1,
          'rf_source' => null,
          'rf_risk' => null,
          'rf_spdx_compatible' => "t",
          'rf_flag' => "1"
        );
        echo "INFO: new license ".$getCurrentData[$this->mapArrayData[$type][0]]." added\n\n";
      }
    }
    return $existingLicenseRefData;
  }
}
$obj = new exportLicenseRef();
echo $obj->startProcessingLicenseData();
