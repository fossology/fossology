<?php
/***************************************************************
 * Copyright (C) 2021 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***************************************************************/
/**
 * @file
 * @brief Tests for Scan model
 */

namespace Fossology\UI\Api\Test\Models
{
  use Mockery as M;
  use Fossology\UI\Api\Models\Analysis;
  use Fossology\UI\Api\Models\ScanOptions;
  use Fossology\UI\Api\Models\Reuser;
  use Fossology\UI\Api\Models\Decider;
  use Fossology\Lib\Dao\UserDao;
  use Fossology\Lib\Auth\Auth;
  use Symfony\Component\HttpFoundation\Request;

  /**
   * @class ScanOptionsTest
   * @brief Tests for ScanOption model
   */
  class ScanOptionsTest extends \PHPUnit\Framework\TestCase
  {
    /**
     * @var \Mockery\MockInterface $functions
     * Public function mock
     */
    public static $functions;

    /**
     * @var AgentAdder $agentAdderMock
     * Mock object of overloaded AgentAdder class
     */
    private $agentAdderMock;

    /**
     * @var UserDao $userDao
     * UserDao mock
     */
    private $userDao;

    /**
     * @brief Setup test objects
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    public function setUp()
    {
      global $container;
      $container = M::mock('ContainerBuilder');
      $this->agentAdderMock = M::mock('overload:\AgentAdder');
      $this->userDao = M::mock(UserDao::class);
      $container->shouldReceive('get')->withArgs(["dao.user"])
        ->andReturn($this->userDao);
      $container->shouldReceive('get')->andReturn(null);

      self::$functions = M::mock(\stdClass::class);
      self::$functions->shouldReceive('FolderListUploads_perm')
        ->withArgs([2, Auth::PERM_WRITE])->andReturn([
          ['upload_pk' => 2],
          ['upload_pk' => 3],
          ['upload_pk' => 4]
        ]);
      self::$functions->shouldReceive('register_plugin')
        ->with(\Hamcrest\Matchers::identicalTo(
          new ScanOptions(null, null, null)));
    }

    /**
     * Prepare request for scan
     * @param Request $request
     * @param array $reuserOpts
     * @param array $deciderOpts
     * @return Request
     */
    private function prepareRequest($request, $reuserOpts, $deciderOpts)
    {
      if (!empty($reuserOpts)) {
        $reuserSelector = $reuserOpts['upload'] . "," . $reuserOpts['group'];
        $request->request->set('uploadToReuse', $reuserSelector);
        if (key_exists('rules', $reuserOpts)) {
          $request->request->set('reuseMode', $reuserOpts['rules']);
        }
      }
      if (!empty($deciderOpts)) {
        $request->request->set('deciderRules', $deciderOpts);
        if (in_array('nomosInMonk', $deciderOpts)) {
          $request->request->set('Check_agent_nomos', 1);
        }
      }
      return $request;
    }

    /**
     * @test
     * -# Test for ScanOptions::scheduleAgents()
     * -# Prepare Request and call ScanOptions::scheduleAgents()
     * -# Function should call AgentAdder::scheduleAgents()
     */
    public function testScheduleAgents()
    {
      $reuseUploadId = 2;
      $uploadId = 4;
      $folderId = 2;
      $groupId = 2;
      $groupName = "fossy";
      $agentsToAdd = ['agent_nomos', 'agent_ojo', 'agent_monk'];
      $reuserOpts = [
        'upload' => $reuseUploadId,
        'group' => $groupId,
        'rules' => []
      ];
      $deciderOpts = [
        'nomosInMonk',
        'ojoNoContradiction'
      ];
      $request = new Request();
      $request = $this->prepareRequest($request, $reuserOpts, $deciderOpts);

      $analysis = new Analysis();
      $analysis->setUsingString("nomos,ojo,monk");

      $reuse = new Reuser($reuseUploadId, $groupName);

      $decider = new Decider();
      $decider->setOjoDecider(true);
      $decider->setNomosMonk(true);

      $scanOption = new ScanOptions($analysis, $reuse, $decider);

      $this->userDao->shouldReceive('getGroupIdByName')
        ->withArgs([$groupName])->andReturn($groupId);
      $this->agentAdderMock->shouldReceive('scheduleAgents')
        ->once()
        ->andReturn(25);

      $scanOption->scheduleAgents($folderId, $uploadId);
    }
  }
}

namespace Fossology\UI\Api\Models
{
  function register_plugin($obj)
  {
    return \Fossology\Ui\Api\Test\Models\ScanOptionsTest::$functions
      ->register_plugin($obj);
  }

  function FolderListUploads_perm($parentFolder, $perm)
  {
    return \Fossology\Ui\Api\Test\Models\ScanOptionsTest::$functions
      ->FolderListUploads_perm($parentFolder, $perm);
  }
}
