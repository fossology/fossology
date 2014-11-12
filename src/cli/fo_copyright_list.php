<?php
/***********************************************************
 Copyright (C) 2013-2014 Hewlett-Packard Development Company, L.P.

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

$longopts = array("user:", "password:", "type:", "container:");
$options = getopt("c:u:t:hx:X:", $longopts);
if (($options === false) || empty($options) || !is_array($options))
{
  $text = _("Invalid option or missing argument.");
  print "$text\n";
  print $Usage;
  return 1;
}

$user = $passwd = "";

require_once dirname(__DIR__).'/lib/php/Util/CopyrightLister.php';
use Fossology\Lib\Util\CopyrightLister;

$cpLister = new CopyrightLister();

foreach($options as $option => $value)
{
  switch($option)
  {
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
      $cpLister->setType($value);
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
if (is_numeric($item) && !is_numeric($upload)) $upload = GetUploadID($item);

/** check if parameters are valid */
if (!is_numeric($upload) || (!empty($item) && !is_numeric($item)))
{
  print "Upload ID or Uploadtree ID must be numeric\n";
  print $Usage;
  return 1;
}

account_check($user, $passwd); // check username/password

$return_value = read_permission($upload, $user); // check if the user has the permission to read this upload
if (empty($return_value))
{
  $text = _("The user '$user' has no permission to read the information of upload $upload\n");
  echo $text;
  return 1;
}

/** get copyright information for this uploadtree */
$cpLister->getCopyrightList($item, $upload);
return 0;
