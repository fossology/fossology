<?php
/*
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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
/**
 *  allFilePaths
 *
 *  given a directory, iterate through it and all subdirectories returning
 *  the absolute path to the files.
 *
 * created: May 22, 2009
 */

//ldir = '/home/markd/Eddy';
//$ldir = '/home/fosstester/regression/license/eddy/GPL';
/**
 * allFilePaths
 *
 * given a directory, iterate through it and all subdirectories returning
 * the absolute path to the files.
 *
 * @param string $dir the directory to start from either an absolute path or
 * a relative one.
 *
 * @return array $fileList a list of the absolute path to the files or empty
 * array on error.
 */
function allFilePaths($dir) {

	$fileList = array();
	if(empty($dir)) {
		return($fileList);  // nothing to process, return empty list.
	}
	try {
		foreach(new recursiveIteratorIterator(
		new recursiveDirectoryIterator($dir)) as $file) {
			$fileList[] = $file->getPathName($file);
		}
		return($fileList);
	}
	/*
	 * if the directory does not exist or the directory or a sub directory
	 * does not have sufficent permissions for reading return an empty list
	 */
	catch(Exception $e) {
		print $e->getMessage();
		return(array());
	}
}

/**
 * escapeDots($string)
 *
 * Escape '.' in a string by replacing '.' with '\.'
 * @param string $string the input string to escape.
 * @return string $estring the escaped string or False.
 */
function escapeDots($string)
{
	if (empty ($string))
	{
		return (FALSE);
	}
	$estring = preg_replace('/\./', '\\.', $string);
	//print  "ED: string is:$string, estring is:$estring\n";
	if ($estring === NULL)
	{
		return (FALSE);
	}
	return ($estring);
}

/**
 * public function getHost
 *
 * returns the host (if present) from a URL
 *
 * @param string $URL a url in the form of http://somehost.xx.com/repo/
 *
 * @return string $host the somehost.xx.com part is returned or NULL,
 * if there is no host in the uri
 *
 */

function getHost($URL)
{
	if (empty ($URL))
	{
		return (NULL);
	}
	$found = parse_url($URL, PHP_URL_HOST);
	//print "DB: getHost: url is:$URL\nafter parse, found is:$found\n";
	return ($found);
} // getHost

/**
 * getMailSubjects
 *
 * Check to see if there is new mail for the user
 *
 * NOTE: must be run by the user who owns the system mailbox in /var/mail
 *
 * @return array Subjects, list of Fossology subjects that match.  On error,
 * the first entry in the array will start with the string 'ERROR!'
 *
 */
function getMailSubjects() {
	/*
	 * use preg_match, but the test must be run by the user who owns the email file
	 * in /var/mail.
	 */
	$MailFile = "/var/mail/";

	//$user = get_current_user();
	$user = exec('id -un', $out, $rtn);
	$UserMail = $MailFile . $user;
	if(file_exists($UserMail) === FALSE) {
		return(array("ERROR! $UserMail does not exist"));
	}
	$FH = fopen($UserMail,'r');
	if($FH === FALSE) {
		return(array("ERROR! Cannot open $UserMail"));
	}
	while (! feof($FH)){
		$line = fgets($FH);
		$matched = preg_match('/Subject:\sFOSSology Results.*?$/',$line, $matches);
		if($matched) {
			$Subjects[] = $line;
		}
	}
	return($Subjects);
} //getMailSubjects

function lastDir($dirpath) {
	// can't have a tailing slash, remove it if there
	$dirpath = rtrim($dirpath, '/');
	$directories = explode('/',$dirpath);
	return(end($directories));
}

/**
 * makeUrl($host,$query)
 *
 * Make a url from the host and query strings.
 *
 * @param $string $host the host (e.g. somehost.com, host.privatenet)
 * @param $string $query the query to append to the host.
 *
 * @return the http string or NULL on error
 */
function makeUrl($host, $query) {
	if (empty ($host)) {
		return (NULL);
	}
	if (empty ($query)) {
		return (NULL);
	}
	return ("http://$host$query");
}


/**
 * \brief given a directory name, return a array of subdir paths and an array of
 * the files under the last subdir.
 *
 * @param string $dir
 * @return array ByDir, an array of arrays.
 *
 * array[dirpath]=>(array)list of files under leaf dir
 * 
 * \todo test this routine with files other than the leaf dirs, does it work?
 *
 */

function filesByDir($dir) {

	$ByDir = array();
	$fileList = array();
	$subPath = '';

	if(empty($dir)) {
		return($fileList);  // nothing to process, return empty list.
	}

	try {
		$dirObject = new recursiveIteratorIterator(
		new recursiveDirectoryIterator($dir),RecursiveIteratorIterator::SELF_FIRST);
		// dirobjs is recusiveIteratorIterator object
		foreach($dirObject as $name) {
				
			$aSubPath = $dirObject->getSubPath();
				
			/*
			 * if we changed subpaths, we are in a new sub-dir, reset the file list
			 */
			if($aSubPath != $subPath) {
				//print "DB: fileByDir: asb != sb, Init fileList!\n";
				$fileList = array();
			}

			if(is_file($name)) {
				$subPath = $dirObject->getSubPath();
				$spn = $dirObject->getSubPathName();
				$subDir = dirname($spn);
				if($subDir == $aSubPath) {
					$fileName = $dirObject->getFilename();
					$fileList[] = $fileName;
				}
			}
			if (empty($subPath)){
				continue;
			}
			else {
				if(empty($fileList)){
					continue;
				}
				$ByDir[$subPath] = $fileList;
			}

			/* Debug
			 *
			 $subPath = $dirObject->getSubPath();
			 print "DB: fileByDir: subpath is:$subPath\n";
			 $sbn = $dirObject->getSubPathName();
			 print "DB: fileByDir: subpathname is:$sbn\n";
			 $dirpath = $dirObject->getPath();
			 print "DB: fileByDir: dirpath is:$dirpath\n";
			 	
			 */

		} // foreach
		//print "DB: fileByDir: ByDir is:\n ";print_r($ByDir) . "\n";
		return($ByDir);
	}

	/*
	 if the directory does not exist or the directory or a sub directory
	 does not have sufficent permissions for reading return an empty list
	 */
	catch(Exception $e) {
		//print "in exception!\n$e\n";
		return(array());
	}
} // fileByDir
?>
