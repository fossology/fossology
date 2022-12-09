<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * libCopyRight
 * \brief utility functions needed by the copyright test programs
 *
 * @version "$Id: libCopyRight.php 3095 2010-04-22 02:44:56Z rrando $"
 */

/**
 * cleanString
 * \brief clean a string of imbedded newlines, trim the string.
 *
 * @return string $clean the cleaned string or a null string on error
 */
function cleanString($string)
{
	$clean = NULL;
	if(strlen($string) == 0)
	{
		return($clean);
	}
	$cleanText = str_replace("\n", '', $string);
	$clean = trim($cleanText);
	return($clean);
}

/**
 * checkStandard
 * \brief check the passed in list against the passed in standard.
 *
 * The two lists are expected to be arrays with the following format:
 *
 * List to be check: associative array with keys: count, showLink,
 * testOrLink.
 *
 * Standard list: associative array, where the key is the count and the
 * value is the text to compare.
 *
 * @param associative array $list
 * @param associative array $standard
 *
 * @return empty array on success, array of errors on fail.
 *
 */

function checkStandard($list, $standard, $testName)
{
	if(empty($list))
	{
		return(array('ERROR! empty list passed in'));
	}
	if(empty($standard))
	{
		return(array('ERROR! empty Standard list passed in'));
	}
	if((strlen($testName)) == 0)
	{
		return(array('ERROR! no testName supplied'));
	}

	$results = array();

	foreach($list as $uiData) {
		$cleanText = cleanString($uiData['textOrLink']);
		print "ckSTDB: cleanText is:$cleanText\n";
		if (array_key_exists($cleanText, $standard)) {
			$stdCount = $standard[$cleanText];
			if($stdCount != $uiData['count'])
			{
				$results[] = "$testName FAILED! Should be $stdCount files " .
    	 		"got:$uiData[count] for row with text:\n$cleanText\n";
			}
		}
		else
		{
			$results[] = "$testName FAILED! $cleanText did not meet the test Standard\n";
		}
	}
	//print "ckStdDB: results are:\n";print_r($results) . "\n";
	return($results);
}
