<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Given a fossology License Broswe page, parse it and return the
 * license table.  The rest of the page can be parsed by the browseMenu
 * class.
 *
 * @param string $page the xhtml page to parse
 *
 * @return assocative array with  Can return an empty array indicating
 * nothing on the page to browse.
 *
 * @todo add in link fixups and adjust consumers
 *
 * @version "$Id:  $"
 * Created on Oct. 17, 2008
 */

class parseLicenseTblDirs
{
	public $page;
	private $test;

	function __construct($page)
	{
		if (empty ($page)) { return; }
		$this->page = $page;
	}
	/**
	 * function parseLicenseTblDirs
	 * given a fossology license browse page parse the directory artifacts
	 * on the page.  This is the listing to the right of the table.
	 *
	 * @returns associative array of dirnames and their links. 
	 */
	function parseLicenseTblDirs()
	{

		// old bsam table$pat ='|.*?id="Lic-.+" align="left"><a href=\'(.*?)\'><b>(.*?)<\/b>|';
		$pat = "|.*id='[0-9]+'.*align='left'.*href='(.*?)'>(.*?)<\/a>|";
		$matches = preg_match_all($pat, $this->page, $tableEntries, PREG_PATTERN_ORDER);
		//print "PLTDIR: Matches is:$matches\ntableEntries are:\n"; print_r($tableEntries) . "\n";
		return($this->_createDirList($tableEntries, $matches));
	}

	function _createDirList($toCombine, $matches)
	{
		/*
		 * if we have a match, the create return array, else return empty
		 * array.
		 */
		if ($matches > 0)
		{
			$numTblEntries = count($toCombine[1]);
			$rtnList = array ();
			for ($i = 0; $i <= $numTblEntries-1; $i++)
			{
				$clean = strip_tags($toCombine[2][$i]);
				$rtnList[$clean] = $toCombine[1][$i];
			}
			return ($rtnList);
		}
		else
		{
			return (array ());
		}
	}
}
