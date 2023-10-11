<?php
/*
 SPDX-FileCopyrightText: © 2010 Hewlett-Packard Development Company, L.P.

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
 * @todo change the name to parsetable and adjust all consumers
 *
 * @version "$Id: dom-parseLicenseTable.php 3273 2010-06-18 18:16:40Z rrando $"
 * Created on Aug 21, 2008
 */

class domParseLicenseTbl
{
	public $page;
	public $hList = array();
	public $noRows = FALSE;
	public $emptyTable = FALSE;
	private $tableId;
	private $title;

	function __construct($page,$tblId,$title=1)
	{
		if (empty ($page)) { return; }
		$this->page = $page;
		if (strlen($tblId) == 0) { return; }
		$this->tableId = $tblId;
		$this->title = $title;
	}
	/**
	 * parseLicenseTbl
	 * \brief given a fossology license histogram, parse it into license
	 * names and Show links.
	 *
	 * @returns an array of associative arrays  with keys of:
	 * count, showLink, textOrLink. the values will be the license count
	 * the url of the Show link and whatever is in the next column.  This
	 * can be text or a link or ?
	 *
	 * Sets property noRows.
	 *
	 * An empty array if no license histogram on that page,
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
		$table = $dom->getElementById($this->tableId);
		if(empty($table)) {
			$this->emptyTable = TRUE;
			//print "DPLTDB: table is empty, can't find table! with table id of:$this->tableId\n";
			return($this->hList=array());
		}

		foreach ($table->childNodes as $tblChildNode)
		{
			$histogram = array();
			foreach($tblChildNode->childNodes as $childNode){
				if($childNode->nodeName == 'td'){
					if(is_numeric($childNode->nodeValue)) {
						$histogram['count'] = trim($childNode->nodeValue);
					}
					if ($childNode->nodeValue == 'Show') {
						$anchorList = $childNode->getElementsByTagName('a');
						foreach($anchorList as $anchorEle) {
							$histogram['showLink'] = $anchorEle->getAttribute('href');
						}
					}
					if(is_string($childNode->nodeValue)) {
						$histogram['textOrLink'] = trim($childNode->nodeValue);
					}
				}
			} // foreach($tblChildNode
			$this->hList[] = $histogram;
			$histogram = array();
		} // foreach
		// for tables with titles, the first row is empty as no childNodes match
		// what we are looking for, remove the first row.
		if($this->title)
		{
			// remove empty 1st entry
			unset($this->hList[0]);
		}
		if(empty($this->hList)) {
			$this->noRows = TRUE;
		}
	} // parseLicenseTbl
}
