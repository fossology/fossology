<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Given a fossology Broswe page, parse it and return it.
 *
 * @param string $page the xhtml page to parse
 *
 * @return see the documentation below as the format of the arrays returned
 * can vary (is this true?)
 *
 * @version "$Id: parseBrowseMenu.php 3459 2010-09-17 03:41:53Z rrando $"
 * 
 * Created on Aug 19, 2008
 * 
 * revised to use dom methods for the browse page parser in 2010.
 */

class parseBrowseMenu
{
	public $page;
	public $browseList = array();
	public $noRows = FALSE;
	public $emptyTable = FALSE;
	private $tableId;
	private $title;
	protected $anchors = array();

	private $test;

	function __construct($page,$tblId,$title=1)
	{
		if (empty ($page)) { return; }
		$this->page = $page;
		if (strlen($tblId) == 0) { return; }
		$this->tableId = $tblId;
		$this->title = $title;
	}

	/**
	 * function parseBrowseMenuFiles
	 * given a fossology browse page parse the browse listing into the
	 * upload name, upload link, view, info and download links.
	 * 
	 * The caller should check either noRows or browseList properties for
	 * results.
	 *
	 * @returns void. Sets the associative array browseList to upload names
	 *  for keys, the array entries look like:
	 * Array(
    [3files.tar.bz2] => Array
        (
            [3files.tar.bz2] => /repo/?mod=browse&upload=1&folder=1&item=1&show=detail
            [View] => /repo/?mod=view&upload=1&show=detail&item=1
            [Info] => /repo/?mod=view_info&upload=1&show=detail&item=1
            [Download] => /repo/?mod=download&upload=1&show=detail&item=1
        )

    [agpl-3.0] => Array
        (
            [View] => /repo/?mod=view&upload=18&show=detail&item=895
            [Info] => /repo/?mod=view_info&upload=18&show=detail&item=895
            [Download] => /repo/?mod=download&upload=18&show=detail&item=895
        )

   * Note, that when a single file is uploaded (agpl-3.0), there is no link to the 
   * upload, only View, Info and Download links.
   * 
	 * Sets property noRows if the resulting array is empty and sets
   * $browseList to an empty array if the browse page is blank.
	 */

	function fromFile($nodeString)
	{
		$parts = explode(': ', $nodeString);
		//print "parts are:\n";print_r($parts) . "\n";
		if(!empty($parts[1]))
		{
			$uploadName = $parts[1];
			//print "fromFile: returning $uploadName\n";
			return($uploadName);
		}
		return(NULL);
	}

	function fromFS($nodeString)
	{
		$parts = explode(': ', $nodeString);
		//print "parts are:\n";print_r($parts) . "\n";
		$uploadName = pathinfo($parts[1], PATHINFO_FILENAME);
		if(strlen($uploadName) != 0)
		{
			//print "fromFS: returning $uploadName\n";
			return($uploadName);
		}
		return(NULL);
	}

	function fromURL($nodeString)
	{
		$parts = explode(': ', $nodeString);
		//print "parts are:\n";print_r($parts) . "\n";
		$urlParts = parse_url($parts[1]);
		$uploadName = pathinfo($urlParts['path'], PATHINFO_FILENAME);
		//print "fromURL: returning $uploadName\n";
		return($uploadName);
	}

	function getAnchors($node, $uploadName)
	{
		$anchorList = $node->getElementsByTagName('a');
		foreach($anchorList as $anchorEle)
		{
			$anchorText = $anchorEle->textContent;
			//print "the anchor text is:$anchorText\n";
			//print "inserting anchor text is:$anchorText\n";
			$this->anchors[$uploadName][$anchorText] = $anchorEle->getAttribute('href');
		}
	}
	
	function parseBrowseMenuFiles()
	{

		$dom = new domDocument;
		@$dom->loadHTML($this->page);
		/*** discard white space ***/
		$dom->preserveWhiteSpace = false;
		$table = $dom->getElementById($this->tableId);
		if(empty($table)) {
			$this->emptyTable = TRUE;
			print "DPLTDB: table is empty, can't find table! with table id of:$this->tableId\n";
			return($this->browseList=array());
		}

		foreach ($table->childNodes as $tblChildNode)
		{
			if($tblChildNode->nodeName == 'tr')
			{
				$childNodes = $tblChildNode->childNodes;
				$clen = $childNodes->length;
				for($i=0; $i<$clen; $i++)
				{
					$node = $childNodes-> item($i);
					$nn = $node->nodeName;
					if($node->nodeName == 'td')
					{
						$fileName = $node->nodeValue;
						$childNodes = $node->childNodes;
						$tdclen = $childNodes->length;
						for($i=0; $i<$tdclen; $i++)
						{
							$tdnode = $childNodes->item($i);
							$tdnn = $tdnode->nodeName;
							//print "\tname of td-node is:$tdnn\n";
							if($tdnn == '#text')
							{
								$tdNodeValue = $tdnode->nodeValue;
								//print "\tDB-td: #text node value is:$tdNodeValue\n";

								$fromFS = 'Added from filesystem:';
								$fromFile = 'Added by file upload:';
								$fromURL = 'Added by URL:';

								$matches = 0;

								if($matches = preg_match("/$fromFile/",$tdNodeValue,$ffMatch))
								{
									$fileUploadName = $this->fromFile($tdNodeValue);
									$this->getAnchors($node, $fileUploadName);
								}
								else if($matches = preg_match("/$fromFS/",$tdNodeValue,$fsMatch))
								{
									$fsUploadName = $this->fromFS($tdNodeValue);
									$this->getAnchors($node, $fsUploadName);
								}
								else if($matches = preg_match("/$fromURL/",$tdNodeValue,$urlMatch))
								{
									$urlUploadName = $this->fromURL($tdNodeValue);
									$this->getAnchors($node, $urlUploadName);
								}
							}
						}
					}
				}
			}
			if(!empty($this->anchors))
			{
				$this->browseList = array_merge($this->browseList, $this->anchors);
				$this->anchors = array();
			}
		} // foreach
		
		if(empty($this->browseList)) {
			$this->noRows = TRUE;
		}
    //print "at the end of parse, browseList is:\n";print_r($this->browseList) . "\n";
	} //parseBrowseMenu
	
	/**
	 * function parseBrowseFileMinis
	 * given a fossology browse page gather up view|meta|download entries,
	 * and the links associated with them.
	 *
	 * @returns array of v|m|d keys and links or empty array if none found
	 * on that page.
	 *
	 * @todo clear up what the array looks like I think it's an array of
	 * arrays with keys.
	 */
	function parseBrowseFileMinis()
	{
		$matches = preg_match_all("/.*?\[<a href='(.*?)'.*?>([V|I|Down].*?)</", $this->page, $fileMini, PREG_PATTERN_ORDER);
		print "fileMini Menus are:";
		print_r($fileMini) . "\n";
		return ($this->_createMiniArray($fileMini, $matches));
	}
	/**
	 * function parseBrowseDirs
	 * given a fossology browse page gather up directory entries, and the
	 * links associated with them.
	 *
	 * @returns array of directory names as  keys and links or empty array
	 * if none found on that page.
	 */
	function parseBrowseMenuDirs()
	{
		$matches = preg_match_all("/.+class='mono'.*?<a href='(.*)'>(.*?)<\/a>/", $this->page, $dirs, PREG_PATTERN_ORDER);
		//print "dirs is:";
		//print_r($dirs) . "\n";
		return ($this->_createRtnArray($dirs, $matches));
	}

	function _createRtnArray($array, $matches)
	{
		/*
		 * if we have a match, the create return array, else return empty
		 * array.
		 */
		if ($matches > 0)
		{
			$numMenus = count($array[1]);
			$rtnList = array ();
			for ($i = 0; $i <= $numMenus -1; $i++)
			{
				$rtnList[$array[2][$i]] = $array[1][$i];
			}
			return ($rtnList);
		} else
		{
			return (array ());
		}
	}

	/**
	 * function _createMiniArray
	 *
	 * combine two arrays into a single associative array.  One of the
	 * arrays is already associative and had duplicate keys.
	 *
	 * @param array, $array
	 * @param scalar, $matches
	 *
	 * @todo what does the return array look like! Docuement it!
	 */
	function _createMiniArray($array, $matches)
	{
		/*
		 * if we have a match, then create return array, else return empty
		 * array. file mini menus have duplicated keys (view,meta,download)
		 * so they must be processed a different way.
		 */
		//print "_CMiniA: matches is:$matches\n";
		if ($matches > 0)
		{
			$triple = array ();
			$numMenus = count($array[1]);
			$loopCnt = $numMenus / 3;
			$rtnList = array ();
			/* index is used to step through all the links*/
			$index = 0;
			for ($i = 0; $i <= $loopCnt -1; $i++)
			{
				$triple = array ();
				for ($j = 0; $j <= 2; $j++)
				{
					$triple[$array[2][$j]] = $array[1][$index];
					$index++;
				}
				$rtnList[$i] = $triple;
			}
			return ($rtnList);
		} else
		{
			return (array ());
		}

	}
}
