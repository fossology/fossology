<?php
/***********************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 * @version "$Id: parseLicenseTbl.php 2865 2010-03-10 19:06:25Z rrando $"
 * Created on Aug 21, 2008
 */

class domParseLicenseTbl
{
	public $page;
	public $hList = array();

	function __construct($page)
	{
		if (empty ($page)) { return; }
		$this->page = $page;
		$this->parseLicenseTbl();
	}
	/**
	 * parseLicenseTbl
	 * \brief given a fossology license histogram, parse it into license
	 * names and Show links.
	 *
	 * @returns an array of associative arrays  with keys of:
	 * licCount, showLink, licName. the values will be the license count
	 * the url of the Show link and the license name.
	 * An empty array if no license histogram on that page,
	 *
	 *
	 */
	function parseLicenseTbl()
	{
		/*
		 * Each table row has 3 td's in it. First is the license count, second
		 * is the show link and third is the license name.
		 */

		$dom = new domDocument;
		@$dom->loadHTML($this->page);
		/*** discard white space ***/
		$dom->preserveWhiteSpace = false;
		$table = $dom->getElementById('lichistogram');
		if($table === NULL) {
			print "DPLTDB: table is NULL!\n";
			return($hList(array()));
		}

		foreach ($table->childNodes as $tblChildNode)
		{
			$histogram = array();
			foreach($tblChildNode->childNodes as $childNode){
				if($childNode->nodeName == 'td'){
					if(is_numeric($childNode->nodeValue)) {
						$histogram['licCount'] = trim($childNode->nodeValue);
					}
					if ($childNode->nodeValue == 'Show') {
						$anchorList = $childNode->getElementsByTagName('a');
						foreach($anchorList as $anchorEle) {
							$histogram['showLink'] = $anchorEle->getAttribute('href');
						}
					}
					if(is_string($childNode->nodeValue)) {
						$histogram['licName'] = trim($childNode->nodeValue);
					}
				}
			} // foreach($tblChildNode
			$this->hList[] = $histogram;
			$histogram = array();
		} // foreach
		// remove empty 1st entry
		unset($this->hList[0]);
		//print "hList is:\n";print_r($this->hList) . "\n";
	} // parseLicenseTbl
}
?>
