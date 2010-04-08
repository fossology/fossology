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
 * Verify special fossology test archive loaded correctly
 *
 * @version "$Id$"
 *
 * Created on March 17, 2010
 */

require_once('../fossologyTestCase.php');
require_once('../commonTestFuncs.php');
require_once('../TestEnvironment.php');
require_once('../testClasses/parseBrowseMenu.php');
require_once('../testClasses/parseMiniMenu.php');
require_once('../testClasses/parseFolderPath.php');
require_once('../testClasses/dom-parseLicenseTable.php');
require_once('libCopyRight.php');

global $URL;

/**
 * This test verifies that the archive 3files.tar.bz2 contains the correct set
 * of copyrights, emails and urls.
 */
class verifySimpletest extends fossologyTestCase
{
	public $mybrowser;
	public $host;

	function setUp()
	{
		/*
		 * This test requires that the fossology test archive has been
		 * loaded under the name 3files.tar.bz2
		 */
		global $URL;
		global $name;
		global $safeName;

		$name = 'simpletest_1.0.1.tar.gz';
		$safeName = escapeDots($name);
		$this->host = getHost($URL);
		$this->Login();

		/* check for existense of archive */
		$page = $this->mybrowser->get($URL);
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
     "verifySimpletest FAILED! Could not find Browse menu\n");
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
     "verifySimpleTest FAILED! Could not find simpletest_1.0.1.tar.gz upload\n");
		$result = $this->myassertText($page, "/$name/");
		if(!($result)) { exit(FALSE); }
	}

	function testSimpletest()
	{
		global $URL;
		global $name;
		global $safeName;

		$copyRights = array(
		'(C) 1991, 1999 Free Software Foundation, Inc. 51' => '1');

		$emailStd = array(
		'secret@www.lastcraft.com' => '4',
		'marcus@lastcraft.com' => '2',
		'name@example.com'=> '2',
		'password@www.somewhere.com' => '2',
		'secret@host.com' => '2',
		'secret@this.com' => '2',
		'secret@www.here.com' => '2',
		'simpletest-support@lists.sourceforge.net' => '2',
		'development@domain51.com' => '1',
		'password@somewhere.com' => '1',
		'secret@this.host' => '1',
		'username@www.somewhere.com' => '1');

		$urlStd = array(
		'http://sourceforge.net/projects/simpletest/' => 28,
		'http://www.my' => 23,
		'http://www.lastcraft.com/simple_test.php' => 21,
		'http://simpletest.org/api/' => 20,
		'http://this.com/page.html' => 20,
		'htp://host' => 16,
		'http://host/' => 13,
		'http://www.lastcraft.com/' => 12,
		'http://my' => 11,
		'http://www.first.com/' => 11,
		'http://test' => 10,
		'http://this.com/this/path/page.html' => 10,
		'www.lastcraft.com' => 10,
		'http://this.com/handler.html' => 9,
		'http://' => 8,
		'http://a.valid.host/here.html' => 8,
		'http://site.with.frames/' => 8,
		'http://somewhere' => 8,
		'http://www.lastcraft.com/form_testing_documentation.php' => 8,
		'http://www.lastcraft.com/protected/' => 7,
		'http://www.second.com/' => 7,
		'http://host' => 6,
		'http://host/somewhere.php' => 6,
		'http://site.with.frames/frame_a.html' => 6,
		'http://site.with.frames/frame_b.html' => 6,
		'http://site.with.frames/frame_c.html' => 6,
		'http://site.with.one.frame/' => 6,
		'http://www.here.com/path/hello.html' => 6,
		'http://www.lastcraft.com/first_test_tutorial.php' => 6,
		'http://www.somewhere' => 6,
		'http://here/' => 5,
		'http://host/a/index.html' => 5,
		'http://site.with.no.frames/' => 5,
		'http://site.with.one.frame/frame.html' => 5,
		'http://www.junit.org/' => 5,
		'http://host/a.html' => 4,
		'http://host.com/I/am/here/page.html' => 4,
		'http://host/here.php' => 4,
		'http://simpletest.sourceforge.net/' => 4,
		'http://site.with.nested.frames/' => 4,
		'https://sourceforge.net/project/showfiles.php?group_id' => 4,
		'http://this.com/new.html' => 4,
		'http://www' => 4,
		'http://www.here.com/path/' => 4,
		'www' => 4,
		'www.somewhere.com' => 4,
		'http://a.valid.host' => 3,
		'http://a.valid.host/here.html?a' => 3,
		'http://here/there?a' => 3,
		'http://host.com/I/am/here/' => 3,
		'http://pear.php.net/manual/en/package.php.phpunit.php' => 3,
		'http://site.with.frames/frame_d.html' => 3,
		'http://there.com/that.html' => 3,
		'http://www.lastcraft.com/test/redirect.php' => 3,
		'http://www.mockobjects.com/' => 3,
		'http://www.third.com/' => 3,
		'http://c2.com/cgi/wiki?MockObject' => 2,
		'http://elsewhere' => 2,
		'http://google.com/' => 2,
		'http://here' => 2,
		'http://here/2.html' => 2,
		'http://here/3.html' => 2,
		'http://here.com/' => 2,
		'http://here.com/somewhere.php' => 2,
		'http://host/b.html' => 2,
		'http://host/c.html' => 2,
		'http://htmlunit.sourceforge.net/' => 2,
		'http://httpunit.sourceforge.net/' => 2,
		'http://junit.sourceforge.net/doc/faq/faq.htm' => 2,
		'http://junit.sourceforge.net/doc/testinfected/testing.htm' => 2,
		'http://jwebunit.sourceforge.net/' => 2,
		'http://localhost/stuff' => 2,
		'http://me' => 2,
		'http://myserver/' => 2,
		'http://php.net/' => 2,
		'https://' => 2,
		'http://selenium.openqa.org/' => 2,
		'https://host.com' => 2,
		'https://host.com/here/' => 2,
		'https://host.com/I/am/there/somewhere.php' => 2,
		'http://simpletest.sourceforge.net/.' => 2,
		'http://simpletest.sourceforge.net/projects/simpletest/' => 2,
		'http://site.with.nested.frame/' => 2,
		'http://site.with.nested.frame/page.html' => 2,
		'http://site.with.nested.frames/inner.html' => 2,
		'http://site.with.nested.frames/one.html' => 2,
		'http://site.with.nested.frames/three.html' => 2,
		'http://site.with.nested.frames/two.html' => 2,
		'http://somewhere.com' => 2,
		'http://sourceforge.net/projects/phpunit' => 2,
		'http://sourceforge.net/projects/phpunit/' => 2,
		'http://sourceforge.net/sflogo.php?group_id' => 2,
		'http://test/' => 2,
		'http://this.com/link.html' => 2,
		'http://www.agilealliance.com/articles/index' => 2,
		'http://www.extremeprogramming.org/' => 2,
		'http://www.fourth.com/' => 2,
		'http://www.google.com/moo/' => 2,
		'http://www.here.com/' => 2,
		'http://www.here.com/a/there.html' => 2,
		'http://www.here.com/hello.html' => 2,
		'http://www.here.com/index.html' => 2,
		'http://www.here.com/path/goodbye.html' => 2,
		'http://www.here.com/path/index.html' => 2,
		'http://www.here.com/path/more/hello.html' => 2,
		'http://www.jmock.org/' => 2,
		'http://www.lastcraft.com' => 2,
		'http://www.lastcraft.com/display_subclass_tutorial.php' => 2,
		'http://www.lastcraft.com/overview.php' => 2,
		'http://www.mockobjects.com/MocksObjectsPaper.html' => 2,
		'http://www.nowhere.com' => 2,
		'http://www.onpk.net/index.php/2005/01/12/254' => 2,
		'http://www.php.net/' => 2,
		'http://www.sidewize.com/company/mockobjects.pdf' => 2,
		'http://www.testdriven.com/modules/news/' => 2,
		'http://www.therationaledge.com/content/dec_01/f_spiritOfTheRUP_pk.html' => 2,
		'http://www.w3.org/People/Raggett/tidy/' => 2,
		'http://www.w3.org/TR/html4/loose.dtd' => 2,
		'http://xpdeveloper.com/cgi' => 2,
		'tls://' => 2,
		'www.lastcraft.com/protected/' => 2,
		'www.lastcraft.com/SimpleTest/Beta3/Report' => 2,
		'www.lastcraft.com/stuff/' => 2,
		'www.mockobjects.com' => 2,
		'http://en.wikipedia.org/wiki/List_of_unit_testing_frameworks' => 1,
		'http://here/1.html' => 1,
		'http://here/4.html' => 1,
		'http://here.com/path/hello.html' => 1,
		'http://here/there' => 1,
		'http://host?a' => 1,
		'http://host.com/here/' => 1,
		'http://host.com/here/there/somewhere.php' => 1,
		'http://host/d.html' => 1,
		'http://host#stuff' => 1,
		'http://jmock.org/' => 1,
		'http://localhost/' => 1,
		'http://localhost/name/example.com' => 1,
		'http://mockobjects.com' => 1,
		'http://phpunit.sourceforge.net/' => 1,
		'https://host.com/I/am/here/./../there/somewhere.php' => 1,
		'https://host.com/I/am/here/../there/somewhere.php' => 1,
		'http://site.with.nested.frame/inner.html' => 1,
		'http://sourceforge.net/projects/htmlsax' => 1,
		'https://test' => 1,
		'https://there.com/stuff/' => 1,
		'https://there.com/stuff/1.html' => 1,
		'https://username' => 1,
		'https://www.here.com' => 1,
		'https://www.lastcraft.com' => 1,
		'https://www.lastcraft.com/test/' => 1,
		'https://www.somewhere.com' => 1,
		'http://this.host/' => 1,
		'http://this.host/this/path/page.html' => 1,
		'http://us4.php.net/manual/en/reference.pcre.pattern.syntax.php' => 1,
		'http://wact.sourceforge.net/' => 1,
		'http://wp.netscape.com/newsref/std/cookie_spec.html' => 1,
		'http://wtr.rubyforge.org/' => 1,
		'http://www.cookiecentral.com/faq/' => 1,
		'http://www.domain.com/index.php/foo/bar' => 1,
		'http://www.domain.com/some/path/' => 1,
		'http://www.here.com/?a' => 1,
		'http://www.here.com/a/index.html' => 1,
		'http://www.here.com/path' => 1,
		'http://www.here.com/path/here/hello.html' => 1,
		'http://www.here.com/path/here/index.html' => 1,
		'http://www.here.com/pathindex.html' => 1,
		'http://www.here.com/pathmore/hello.html' => 1,
		'http://www.here.com/paths/index.html' => 1,
		'http://www.here.com/path/there/goodbye.html' => 1,
		'http://www.here.com/path/there/index.html' => 1,
		'http://www.lastcraft.com/test/' => 1,
		'http://www.lastcraft.com/unit_test_documentation.php' => 1,
		'http://www.mockobjects.com' => 1,
		'http://www.openqa.org/selenium/' => 1,
		'http://www.opensourcetesting.org/functional.php' => 1,
		'http://www.php.net/manual/en/function.htmlentities.php' => 1,
		'http://www.php.net/manual/fr/function.htmlentities.php' => 1,
		'http://www.site.com/home.html' => 1,
		'http://www.testingfaqs.org/t' => 1,
		'scheme://' => 1,
		'www.another' => 1,
		'www.here.com' => 1,
		'www.lastcraft.com/stuff/somewhere.php' => 1,
		);

		print "\nstarting VerifySimpletest test\n";
		$page = $this->mybrowser->clickLink('Browse');
		$this->assertTrue($this->myassertText($page, '/Browse/'),
             "verifySimpleTest FAILED! Could not find Browse menu\n");
		$this->assertTrue($this->myassertText($page, "/Browse/"),
       "verifySimpleTest FAILED! Browse Title not found\n");
		$this->assertTrue($this->myassertText($page, "/$safeName/"),
       "verifySimpleTest FAILED! did not find $name\n");
		$this->assertTrue($this->myassertText($page, "/>View</"),
       "verifySimpletest FAILED! >View< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Info</"),
       "verifySimpletest FAILED! >Info< not found\n");
		$this->assertTrue($this->myassertText($page, "/>Download</"),
       "verifySimpletest FAILED! >Download< not found\n");

		/* Select archive */
		$page = $this->mybrowser->clickLink($name);
		//print "************ Page after select foss archive *************\n$page\n";
		$this->assertTrue($this->myassertText($page, "/simpletest\//"),
      "verifySimpletest FAILED! 'simpletest/' not found\n");
		$this->assertTrue($this->myassertText($page, "/1 item/"),
      "verifySimpletest FAILED! '1 item' not found\n");

		/* Select the License link to View License Historgram */
		$browse = new parseBrowseMenu($page);
		$mini = new parseMiniMenu($page);
		$miniMenu = $mini->parseMiniMenu();
		$url = makeUrl($this->host, $miniMenu['Copyright/Email/URL']);
		if($url === NULL) { $this->fail("verifySimpletest Failed, host is not set"); }

		$page = $this->mybrowser->get($url);
		//print "page after get of $url is:\n$page\n";

		// get the 'Show' links and License color links
		$ct = new domParseLicenseTbl($page, 'copyright');
		$ct->parseLicenseTbl();
		// empty table? Error
		if($ct->noRows) {
			$this->fail("Error! table with id=copyright has no rows!" .
									" nothing to process, There should be!\n");
		}
		else if(empty($ct->hList)) {
			$this->fail("Error! table with id=copyright was not found on" .
									"the page, There should be one\n");
		}
		else {
			$this->assertTrue($this->myassertText($page, '/Copyright\/Email\/URL Browser/'),
          "verifySimpleTest FAILED! Copyright/Email/URL Browser Title not found\n");
			$this->assertTrue($this->myassertText($page, '/Total Copyrights: 1/'),
        "verifySimpleTest FAILED! Total copyrights does not equal 1\n");

			$comparisons = checkStandard($ct->hList, $copyRights, 'verifySimpleTest');
			if(empty($comparisons))
			{
				$this->pass();
			}
			else
			{
				foreach($comparisons as $error) {
					$this->fail($error);
				}
			}
		}

		$email = new domParseLicenseTbl($page, 'copyrightemail');
		$email->parseLicenseTbl();

		if($email->noRows) {
			$this->fail("Error! table with id=copyrightemail has no rows " .
									"There should be 12\n");
		}
		else if(empty($email->hList)) {
			$this->fail("Error! table with id=copyrightemail was not found on" .
									"the page, it should be on the page\n");
		}
		else {
			$this->assertTrue($this->myassertText($page, '/Total Emails: 22/'),
        "verifySimpleTest FAILED! Total emails does not equal 22\n");
			$this->assertTrue($this->myassertText($page, '/Unique Emails: 12/'),
        "verifySimpleTest FAILED! Unique emacs does not equal 12\n");	

			$comparisons = checkStandard($email->hList, $emailStd, 'verifySimpleTest');
			if(empty($comparisons))
			{
				$this->pass();
			}
			else
			{
				foreach($comparisons as $error) {
					$this->fail($error);
				}
			}
			//print "email list is:\n"; print_r($email->hList) . "\n";
		}

		$url = new domParseLicenseTbl($page, 'copyrighturl');
		$url->parseLicenseTbl();

		if($url->noRows) {
			$this->fail("Error! table with id=copyrighturl has no rows " .
									"There should be 12\n");
		}
		else if(empty($url->hList)) {
			$this->fail("Error! table with id=copyrighturl was not found on" .
									"the page, it should be on the page\n");
		}
		else {
			$this->assertTrue($this->myassertText($page, '/Total URLs: 616/'),
        "verifySimpleTest FAILED! Total urls does not equal 616\n");
			$this->assertTrue($this->myassertText($page, '/Unique URLs: 183/'),
        "verifySimpleTest FAILED! Unique emacs does not equal 183\n");	

			$comparisons = checkStandard($url->hList, $urlStd, 'verifySimpleTest');
			if(empty($comparisons))
			{
				$this->pass();
			}
			else
			{
				foreach($comparisons as $error) {
					$this->fail($error);
				}
			}
		}
	}
}
?>
