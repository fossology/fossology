<?php
/*
 SPDX-FileCopyrightText: © 2019,2021,2022 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Util\StringOperation;

/**
 * @file exportLicenseRefUsingSPDX.php
 * @brief Export a licenses from SPDX.
 *
 * This is typically used to export license data from SPDX json
 **/

class exportLicenseRef
{
  /**
   * @var array $mapArrayData
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
    $deleteDeprecated = false;
    $newLicenseRefData = array();
    $showUsage = '';
    $scanList = array(
      'licenses' => 'https://spdx.org/licenses/licenses.json',
      'exceptions' => 'https://spdx.org/licenses/exceptions.json'
    );
    $usage = "Usage: " . basename($argv[0]) . " [options]

      Create new licenseref.json file.  Options are:
        -E    Update all existing licenses and also add new licenses.
              (NOTE: there may be failure of test cases)

        -e    Only update existing licenses.
              (NOTE: there may be failure of test cases)

        -n    Only add new licenses.

        -d    Delete deprecated licenses.

       --type Usually licenses/exceptions (optional)
              (ex: --type 'licenses')

       --url From where you want to download (optional)
             (ex: --url 'https://spdx.org/licenses/licenses.json')

      Additional note:
        (if --type and --url is empty then the script will automatically download the from below)
          For type 'licenses' URL is : $scanList[licenses]

          For type 'exceptions' URL is : $scanList[exceptions]";

    $options = getopt("hcEend", array("type:", "url:"));
    /* get type and url if exists, if not set them to empty */
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
        case 'd': /* Delete deprecated licenses */
          $deleteDeprecated = true;
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
        $newLicenseRefData = $this->getListSPDX($type, $URL, $updateWithNew, $updateExisting, $addNewLicense,
          $newLicenseRefData, $deleteDeprecated);
      } else if (!empty($type) && empty($URL)) {
        echo "Notice: --url cannot be empty if --type is provided \n";
      } else if (empty($type) && !empty($URL)) {
        echo "Notice: --type cannot be empty if --url is provided \n";
      } else {
        foreach ($scanList as $type => $URL) {
          $newLicenseRefData = $this->getListSPDX($type, $URL, $updateWithNew, $updateExisting, $addNewLicense,
            $newLicenseRefData, $deleteDeprecated);
        }
      }
      $newFileName = "licenseRefNew.json";
      if (file_exists($newFileName)) {
        unlink($newFileName);
      }
      $this->sanitizeRefData($newLicenseRefData);
      $file = fopen($newFileName, 'w+');
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
   * @brief get SPDX license or exception list and update licenseref.json
   *
   * get SPDX license or exception list
   * update the licenseref.json file with changes in existing license text
   * or add a new license if licenseref.json doesn't contain it.
   * Create a new licenserefnew.json file from where it is getting executed.
   * user need to copy the additional license texts from licenserefnew.json
   * to actual licenseref.json
   */
  function getListSPDX($type, $URL, $updateWithNew, $updateExisting, $addNewLicense, $existingLicenseRefData,
                       $deleteDeprecated)
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
      /* dump all the data from licenseRef.json file to an array */
      $existingLicenseRefData = (array) json_decode($getExistingLicenseRefData, true);
    }
    /* get license list and each license's URL */
    $getList = json_decode(file_get_contents($URL));
    foreach ($getList->$type as $listValue) {
      /* get current license data from given URL */
      if (strstr($URL, "spdx.org") !== false) {
        // If fetching exceptions from spdx, fix the detailsUrl
        if (substr_compare($listValue->detailsUrl, ".html", -5) === 0) {
          $baseUrl = str_replace("exceptions.json", "", $URL);
          $listValue->detailsUrl = $baseUrl . str_replace("./", "", $listValue->reference);
        }
      }
      $getCurrentData = file_get_contents($listValue->detailsUrl);
      $getCurrentData = (array) json_decode($getCurrentData, true);
      echo "INFO: search for license " . $getCurrentData[$this->mapArrayData[$type][0]] . "\n";
      /* check if the licenseid of the current license exists in old license data */
      $licenseIdCheck = array_search($getCurrentData[$this->mapArrayData[$type][0]],
        array_column($existingLicenseRefData, 'rf_shortname'));
      $currentText = $this->replaceUnicode($getCurrentData[$this->mapArrayData[$type][1]]);
      $textCheck = array_search($currentText, array_column($existingLicenseRefData, 'rf_text'));
      if ($deleteDeprecated && $listValue->isDeprecatedLicenseId && (
          is_numeric($licenseIdCheck) &&
          (!empty($updateWithNew) || !empty($updateExisting)))) {
        // Existing deprecated license, delete it
        echo "INFO: removing deprecated license " .
          $getCurrentData[$this->mapArrayData[$type][0]] . "\n";
        unset($existingLicenseRefData[$licenseIdCheck]);
        $existingLicenseRefData = array_values($existingLicenseRefData);
        continue;
      } elseif ($listValue->isDeprecatedLicenseId) {
        continue;
      }
      if (is_numeric($licenseIdCheck) &&
          (!empty($updateWithNew) || !empty($updateExisting))) {
        // License exists, just remove old fields
        if (array_key_exists('rf_spdx_compatible',
            $existingLicenseRefData[$licenseIdCheck])) {
          unset($existingLicenseRefData[$licenseIdCheck]['rf_spdx_compatible']);
        }
      }
      if (
        is_numeric($licenseIdCheck) &&
        !is_numeric($textCheck) &&
        (!empty($updateWithNew) ||
          !empty($updateExisting)
        )
      ) {
        $existingLicenseRefData[$licenseIdCheck]['rf_fullname'] = $getCurrentData[$this->mapArrayData[$type][2]];
        $existingLicenseRefData[$licenseIdCheck]['rf_text'] = $getCurrentData[$this->mapArrayData[$type][1]];
        $existingLicenseRefData[$licenseIdCheck]['rf_url'] = $getCurrentData['seeAlso'][0];
        $existingLicenseRefData[$licenseIdCheck]['rf_notes'] = (array_key_exists("licenseComments", $getCurrentData) ? $getCurrentData['licenseComments'] : $existingLicenseRefData[$licenseIdCheck]['rf_notes']);
        echo "INFO: license " . $getCurrentData[$this->mapArrayData[$type][0]] . " updated\n\n";
      }
      if (
        !is_numeric($licenseIdCheck) &&
        !is_numeric($textCheck) &&
        (!empty($updateWithNew) ||
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
          'rf_spdx_compatible' => $listValue->isDeprecatedLicenseId == false,
          'rf_flag' => "1",
        );
        echo "INFO: new license " . $getCurrentData[$this->mapArrayData[$type][0]] . " added\n\n";
      }
    }
    return array_values($existingLicenseRefData);
  }

  /**
   * Replace common unicode characters with ASCII for consistent results.
   *
   * @param string $text Input text
   * @return string Input with characters replaced
   */
  private function replaceUnicode($text)
  {
    if ($text === null) {
      return null;
    }
    $search = [
      "\u{00a0}",  // no break space
      "\u{2018}",  // Left single quote
      "\u{2019}",  // Right single quote
      "\u{201c}",  // Left double quote
      "\u{201d}",  // Right double quote
      "\u{2013}",  // em dash
      "\u{2028}",  // line separator
    ];

    $replace = [
      " ",
      "'",
      "'",
      '"',
      '"',
      "-",
      "\n",
    ];

    return StringOperation::replaceUnicodeControlChar(str_replace($search,
      $replace, $text));
  }

  /**
   * Santize the license ref data before writing to JSON file
   *
   * @param[in,out] array $newLicenseRefData License ref data to be sanitized
   */
  private function sanitizeRefData(&$newLicenseRefData)
  {
    for ($i = 0; $i < count($newLicenseRefData); $i++) {
      $newLicenseRefData[$i]["rf_fullname"] = $this->replaceUnicode($newLicenseRefData[$i]["rf_fullname"]);
      $newLicenseRefData[$i]["rf_text"] = $this->replaceUnicode($newLicenseRefData[$i]["rf_text"]);
      $newLicenseRefData[$i]["rf_notes"] = $this->replaceUnicode($newLicenseRefData[$i]["rf_notes"]);
    }
  }
}
$obj = new exportLicenseRef();
$obj->startProcessingLicenseData();
