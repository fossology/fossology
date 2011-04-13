<?php
/*
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
 */

/**
 * testFramework
 * \brief The base class for UI tests to build on.  The purpose of this
 * class is to supply methods that all tests can use.
 *
 * @package FOSSologyUiTest
 * @version "$Id: $"
 *
 * created on Oct. 4, 2010
 */
require_once '/usr/share/php/PHPUnit/Extensions/SeleniumTestCase.php';
require_once ('TestEnvironment.php');

global $USER;
global $PASSWORD;

class testFramework extends PHPUnit_Extensions_SeleniumTestCase
{
	
  protected $user;
	protected $password;
	protected $browserUrl;

	protected function setUp()
	{
		require_once('browsers.php');
		$this->setBrowserUrl('http://randotest.ostt/repo/');
	}

	public function testLogin()
	{

		print "test Login starting...\n";

		$this->login();
		$this->assertTitle('Welcome to FOSSology');
	}
	
	/**
	 * login
	 * \brief login to the web site driven by the seleniumRC server for 
	 * testing the UI. The caller should test the page after logging in.
	 * 
	 * If no parameters are passed in then the values from the included
	 * file TestEnvironment $USER and $PASSWORD will be used.
	 * 
	 *  @param $string, $user the optional user name
	 *  @param $string, $password the optional user password in plain text
	 *  
	 *  @return NULL
	 *
	 */

	public function login($user=NULL, $password=NULL)
	{

		global $USER;
		global $PASSWORD;

		if(strlen($user)) {
			$this->user = $user;
		}
		else {
			$this->user = $USER;
		}
		if(!strlen($password)) {
			$this->password = $password;
		}
		else {
			$this->password = $PASSWORD;
		}

		$this->open("/repo/?mod=auth");
		$this->click("//a/b");
		$this->waitForPageToLoad("30000");
		$cookie = $this->getCookieByName("Login",$dummy);
		$this->createCookie("Login=$cookie");
		$this->assertTrue($this->type("unamein", $this->user),
		  "Could not type $this->user");
		$this->type("password", $this->password);
		$this->click("//input[@value='Login']");
		$this->waitForPageToLoad("30000");
		return;
	} // login

	/**
	 * setAgents
	 *
	 * Set 0 or more agents
	 *
	 * Assumes it is on a page where agents can be selected with
	 * check boxes.  Will produce test errors if it is not.
	 *
	 * @param string $agents: a comma separated list of names or the word all.
	 * Valid names are: buckets, copyright, mimetype, metadata, nomos,
	 * package, specagent, license.
	 *
	 * e.g. all or copyright,nomos,package
	 *
	 * Case should not matter as all names are shifted to lower case
	 *
	 * @return NULL, or string on error
	 *
	 */
	public function setAgents($agents = NULL)
	{
		$agentList = array (
      'buckets'   => 'Check_agent_bucket', 
      'copyright' => 'Check_agent_copyright',
      'mimetype' => 'Check_agent_mimetype',
      'metadata' => 'Check_agent_pkgmetagetta',
      'nomos' => 'Check_agent_nomos',
      'package' => 'Check_agent_pkgagent',
      'specagent' => 'Check_agent_specagent',
      'license' => 'Check_agent_license',
		);
		/* check parameters and parse */
		if (is_null($agents)) {
			return NULL; // No agents to set
		}
		/* set them all if 'all' */
		if (0 === strcasecmp($agents, 'all'))
		{
			foreach ($agentList as $agent => $name)
			{
				if ($this->debug)
				{
					print "setAgents: setting agents for 'all', agent name is:$name\n";
				}
				$this->assertTrue($this->check($name),
          "could not set checkbox\n$agent\n");
			}
			return (NULL);
		}
		/*
		 * what is left is 0 or more numbers, comma seperated
		 * parse them then use them to set a list of agents.
		 */
		$numberList = explode(',', $agents);
		$numAgents = count($numberList);

		if ($numAgents = 0)
		{
			return NULL; // no agents to schedule
		}
		else
		{
			foreach ($numberList as $number)
			{
				switch (strtolower($number))
				{
					case 'buckets':
						$checklist[] = $agentList['buckets'];
						break;
					case 'copyright':
						$checklist[] = $agentList['copyright'];
						break;
					case 'mimetype':
						$checklist[] = $agentList['mimetype'];
						break;
					case 'metadata':
						$checklist[] = $agentList['metadata'];
						break;
					case 'nomos':
						$checklist[] = $agentList['nomos'];
						break;
					case 'package':
						$checklist[] = $agentList['package'];
						break;
					case 'specagent':
						$checklist[] = $agentList['specagent'];
						break;
					case 'license':
						$checklist[] = $agentList['license'];
						break;
					default:
						return(NULL);      // no agents to schedule, nothing matched
				}
			} // foreach

			if ($this->debug == 1)
			{
				print "the agent list is:\n";
			}

			foreach ($checklist as $agent)
			{
				if ($this->debug) {
					print "DEBUG: $agent\n";
				}
				$this->assertTrue($this->check($agent),
				  "could not set checkbox\n$agent\n");
			}
		}
		return (NULL);
	} //setAgents
};
?>
