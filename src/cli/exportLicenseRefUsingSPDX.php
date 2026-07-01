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

  /**
   * @var const BOA_LIST_URL
   */
  const BOA_LIST_URL = 'https://blueoakcouncil.org/list.json';


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
      if (empty($newLicenseRefData)) {
        echo "\nERROR: No license data collected. Verify \$LIBEXECDIR ";
        exit(1);
      }
      $newFileName = "licenseRefNew.json";
      if (file_exists($newFileName)) {
        unlink($newFileName);
      }
      $this->sanitizeRefData($newLicenseRefData);
      file_put_contents($newFileName, json_encode($newLicenseRefData, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES));
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
      return [];
    }

    if (!is_readable($LIBEXECDIR)) {
      print "FATAL: Unable to access '$LIBEXECDIR'.\n";
      return [];
    }
    /* check if licenseref.json exists */
    $fileName = "$LIBEXECDIR/licenseRef.json";
    if (!file_exists($fileName)) {
      print "FATAL: File '$fileName' does not exist.\n";
      return [];
    }

    if (empty($existingLicenseRefData)) {
      echo "INFO: get existing licenseRef.json from $LIBEXECDIR\n";
      $getExistingLicenseRefData = file_get_contents("$fileName");
      /* dump all the data from licenseRef.json file to an array */
      $existingLicenseRefData = (array) json_decode($getExistingLicenseRefData, true);
    }
    $boaIds = [];
    if ($type === 'licenses') {
      $boaRaw = fetchBoaList(BOA_LIST_URL);
      if ($boaRaw !== false) {
        $boaJson = json_decode($boaRaw, true);
        foreach (($boaJson['ratings'] ?? []) as $rating) {
          foreach (($rating['licenses'] ?? []) as $lic) {
            if (!empty($lic['id'])) {
              $boaIds[$lic['id']] = true;
            }
          }
        }
      }
      $boaCount = count($boaIds);
      if ($boaCount === 0) {
        echo "WARNING: Blue Oak Council fetch failed or returned empty — Permissive classification degraded.\n";
      } else {
        echo "INFO: loaded $boaCount permissive licenses from Blue Oak Council\n";
      }
    }

    /* get license list and each license's URL */
    $rawList = file_get_contents($URL, false, $httpCtx);
    if ($rawList === false) {
      print "FATAL: Unable to fetch data from '$URL'.\n";
      return [];
    }
    $getList = json_decode($rawList);
    if ($getList === null || !isset($getList->$type)) {
      print "FATAL: Invalid data received from '$URL'.\n";
      return [];
    }
    foreach ($getList->$type as $listValue) {
      /* get current license data from given URL */
      if (strstr($URL, "spdx.org") !== false) {
        // If fetching exceptions from spdx, fix the detailsUrl
        if (substr_compare($listValue->detailsUrl, ".html", -5) === 0) {
          if (!isset($listValue->reference)) {
            echo "WARNING: Missing 'reference' field for '" . ($listValue->licenseExceptionId ?? '?') . "', skipping.\n";
            continue;
          }
          $baseUrl = str_replace("exceptions.json", "", $URL);
          $listValue->detailsUrl = $baseUrl . str_replace("./", "", $listValue->reference);
        }
      }
      $rawCurrentData = file_get_contents($listValue->detailsUrl, false, $httpCtx);
      if ($rawCurrentData === false) {
        echo "WARNING: Unable to fetch license details from '" . $listValue->detailsUrl . "', skipping.\n";
        continue;
      }
      $getCurrentData = json_decode($rawCurrentData, true);
      if (!is_array($getCurrentData) || empty($getCurrentData[$this->mapArrayData[$type][0]])) {
        echo "WARNING: Invalid or missing data from '" . $listValue->detailsUrl . "', skipping.\n";
        continue;
      }
      $getCurrentData = (array) $getCurrentData;
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
        $existingLicenseRefData[$licenseIdCheck]['rf_spdx_compatible'] =
          $listValue->isDeprecatedLicenseId ? "f" : "t";
        $existingLicenseRefData[$licenseIdCheck]['rf_licensetype'] =
          $this->getLicenseType($type, $getCurrentData, $boaIds);
      }
      if (
        is_numeric($licenseIdCheck) &&
        !is_numeric($textCheck) &&
        (!empty($updateWithNew) ||
          !empty($updateExisting)
        )
      ) {
        $existingLicenseRefData[$licenseIdCheck]['rf_fullname'] = $getCurrentData[$this->mapArrayData[$type][2]];
        $existingLicenseRefData[$licenseIdCheck]['rf_text'] = $currentText;
        $existingLicenseRefData[$licenseIdCheck]['rf_url'] = isset($getCurrentData['seeAlso'][0]) ? $getCurrentData['seeAlso'][0] : $existingLicenseRefData[$licenseIdCheck]['rf_url'];
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
          'rf_text' =>  $currentText,
          'rf_url' =>  isset($getCurrentData['seeAlso'][0]) ? $getCurrentData['seeAlso'][0] : null,
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
          'rf_spdx_compatible' => $listValue->isDeprecatedLicenseId ? "f" : "t",
          'rf_flag' => "1",
          'rf_licensetype' => $this->getLicenseType($type, $getCurrentData, $boaIds),
        );
        echo "INFO: new license " . $getCurrentData[$this->mapArrayData[$type][0]] . " added\n\n";
      }
    }
    return array_values($existingLicenseRefData);
  }

  /**
   * @brief Classify a license into rf_licensetype using name, comments, and BOA membership.
   * @return string Exception|Public Domain|Permissive|Weak Copyleft|Strong Copyleft|
   *                Network Copyleft|Non-commercial|Source Available|Font|Data|Unknown
   */
  private function getLicenseType($type, $licenseData, $boaIds)
  {
    if ($type === 'exceptions') {
      return 'Exception';
    }

    $licenseId = $licenseData[$this->mapArrayData[$type][0]] ?? '';
    $name = strtolower($licenseData[$this->mapArrayData[$type][2]] ?? '');
    $comments = strtolower($licenseData['licenseComments'] ?? '');

    // Check public domain before Creative Commons to catch CC0/PDM.
    if (strpos($name, 'public domain') !== false ||
        strpos($name, 'unlicense') !== false ||
        strpos($comments, 'public domain') !== false) {
      return 'Public Domain';
    }

    // Classify Creative Commons by variant.
    if (strpos($name, 'creative commons') !== false) {
      if (strpos($licenseId, 'CC0') === 0 || strpos($licenseId, 'CC-PDM') === 0 ||
          strpos($licenseId, 'CC-PDDC') === 0) {
        return 'Public Domain';
      }
      if (strpos($licenseId, '-NC-') !== false ||
          strpos($name, 'noncommercial') !== false ||
          strpos($name, 'non-commercial') !== false) {
        return 'Non-commercial';
      }
      // ND variants prohibit modification; treat as Source Available.
      if (strpos($licenseId, '-ND') !== false ||
          strpos($name, 'noderivatives') !== false ||
          strpos($name, 'no derivatives') !== false) {
        return 'Source Available';
      }
      if (strpos($licenseId, '-SA') !== false || strpos($name, 'sharealike') !== false) {
        return 'Weak Copyleft';
      }
      return 'Permissive';
    }

    if (strpos($licenseId, 'GFDL') === 0 ||
        strpos($name, 'free documentation license') !== false) {
      return 'Weak Copyleft';
    }

    // Export/military restriction licenses are not freely usable; keep Unknown.
    if (strpos($licenseId, 'No-Nuclear') !== false ||
        strpos($licenseId, 'No-Military') !== false ||
        strpos($name, 'no nuclear') !== false ||
        strpos($name, 'no military') !== false) {
      return 'Unknown';
    }

    if (preg_match('/\bfont\b/i', $name)) {
      return 'Font';
    }

    if (strpos($name, 'database') !== false ||
        (strpos($name, 'data') !== false && strpos($name, 'license') !== false)) {
      return 'Data';
    }

    if (isset($boaIds[$licenseId])) {
      return 'Permissive';
    }

    $isCopyleft = strpos($name, 'copyleft') !== false ||
                  strpos($comments, 'copyleft') !== false ||
                  strpos($name, 'general public license') !== false;
    if ($isCopyleft) {
      $isNetwork = strpos($name, 'affero') !== false ||
                   strpos($name, 'network copyleft') !== false ||
                   strpos($comments, 'affero') !== false;
      $isWeak = strpos($name, 'lesser') !== false ||
                strpos($name, 'limited') !== false ||
                strpos($name, 'weak copyleft') !== false ||
                strpos($comments, 'weak copyleft') !== false ||
                strpos($comments, 'lesser general public') !== false;
      if ($isNetwork) {
        return 'Network Copyleft';
      }
      if ($isWeak) {
        return 'Weak Copyleft';
      }
      return 'Strong Copyleft';
    }

    if (strpos($name, 'non-commercial') !== false ||
        strpos($name, 'noncommercial') !== false) {
      return 'Non-commercial';
    }

    return 'Unknown';
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

  /**
   * Pull/fetch data from BOA.
   *
   * @param string $url Input text
   * @return JSON raw
   */
  private function fetchBoaList($url)
  {
    try {
      $boaRaw = file_get_contents($url, false, stream_context_create([
        'http' => [
          'timeout' => 30,
          'user_agent' => 'FOSSology/SPDX'
        ]
      ]));

      if ($boaRaw === false) {
        throw new Exception("Failed to fetch BOA list");
      }

      return $boaRaw;
    } catch (Exception $e) {
      error_log("BOA List fetch error: " . $e->getMessage());
      return null;
    }
  }
}
$obj = new exportLicenseRef();
$obj->startProcessingLicenseData();
