<?php
/*
 SPDX-FileCopyrightText: © 2013-2014 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file fo_copyright_list.php
 *
 * @brief get a list of filepaths and copyright information for those
 * files.
 *
 */

$Usage = "Usage: " . basename($argv[0]) . "
  -u upload id        :: required - upload id
  -t uploadtree id    :: required - uploadtree id
  -c sysconfdir       :: optional - Specify the directory for the system configuration
  --type type         :: optional - all/statement/url/email, default: all
  --user username     :: user name
  --password password :: password
  --container         :: include container or not, 1: yes, 0: no (default)
  -x copyright        :: to match all that does not contain my copyright, (default): show all files
  -X copyright        :: to match my copyright, (default): show all files
  -h  help, this message
";

$upload = $item = "";
$typeCopyright = array("statement","email","url");

$longopts = array("user:", "password:", "type:", "container:");
$options = getopt("c:u:t:hx:X:", $longopts);
if (($options === false) || empty($options) || ! is_array($options)) {
  $text = _("Invalid option or missing argument.");
  print "$text\n";
  print $Usage;
  return 1;
}

$user = $passwd = "";

use Fossology\Lib\Util\CopyrightLister;

$cpLister = new CopyrightLister();

foreach ($options as $option => $value) {
  switch ($option) {
    case 'c': // handled in fo_wrapper
      break;
    case 'u':
      $upload = $value;
      break;
    case 't':
      $item = $value;
      break;
    case 'h':
      print $Usage;
      return 1;
    case 'user':
      $user = $value;
      break;
    case 'password':
      $passwd = $value;
      break;
    case 'type':
      if (empty($value) || in_array($value, $typeCopyright)) {
        $cpLister->setType($value);
      } else {
        print "Invalid argument '$value' for type.\n";
        print $Usage;
        return 1;
      }
      break;
    case 'container':
      $cpLister->setContainerInclusion($value);
      break;
    case 'x': // exclude my copyright
      $cpLister->setExcludingCopyright($value);
      break;
    case 'X': // include my copyright
      $cpLister->setIncludingCopyright($value);
      break;
    default:
      print "unknown option $option\n";
      print $Usage;
  }
}

/** get upload id through uploadtree id */
if (is_numeric($item) && !is_numeric($upload)) {
  $upload = GetUploadID($item);
}

/** check if parameters are valid */
if (!is_numeric($upload)) {
  print "Upload ID is empty or invalid.\n";
  print $Usage;
  return 1;
}
if (!empty($item) && !is_numeric($item)) {
  print "Uploadtree ID is empty or invalid.\n";
  print $Usage;
  return 1;
}

require_once("$MODDIR/lib/php/common-cli.php");
cli_Init();
account_check($user, $passwd); // check username/password

$return_value = read_permission($upload, $user); // check if the user has the permission to read this upload
if (empty($return_value)) {
  $text = _("The user '$user' has no permission to read the information of upload $upload\n");
  echo $text;
  return 1;
}

/** get copyright information for this uploadtree */
$cpLister->getCopyrightList($item, $upload);
return 0;
