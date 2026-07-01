<?php
/*
 SPDX-FileCopyrightText: © 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
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
  use Fossology\UI\Api\Models\Scancode;
  use Fossology\Lib\Dao\UploadDao;
  use Fossology\Lib\Dao\UserDao;
  use Fossology\Lib\Auth\Auth;
  use Symfony\Component\HttpFoundation\Request;
  use Fossology\UI\Api\Models\ApiVersion;

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
    public function setUp() : void
    {
      global $container;
      $container = M::mock('ContainerBuilder');
      $this->agentAdderMock = M::mock('overload:\AgentAdder');
      $this->userDao = M::mock(UserDao::class);
      $uploadDao = M::mock(UploadDao::class);
      $uploadDao->shouldReceive('isAccessible')->andReturn(true);
      $container->shouldReceive('get')->withArgs(["dao.upload"])
        ->andReturn($uploadDao);
      $container->shouldReceive('get')->withArgs(["dao.user"])
        ->andReturn($this->userDao);
      $GLOBALS['SysConf']['auth'][Auth::GROUP_ID] = 2;
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
          new ScanOptions(null, null, null, null)));
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
        if (is_array($reuserOpts['upload'])) {
          $reuserSelectors = [];
          foreach ($reuserOpts['upload'] as $uploadId) {
            $reuserSelectors[] = $uploadId . "," . $reuserOpts['group'];
          }
          $request->request->set('uploadToReuse', $reuserSelectors);
        } else {
          $reuserSelector = $reuserOpts['upload'] . "," . $reuserOpts['group'];
          $request->request->set('uploadToReuse', $reuserSelector);
        }
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
    public function testScheduleAgentsApiVersionV1()
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

      $_SERVER['REQUEST_URI'] = "/api/v1/";

      $mockApiVersion = $this->createMock(ApiVersion::class);
      $mockApiVersion->method("getVersionFromUri")->willReturn(ApiVersion::V1);

      $request = new Request();
      $request = $this->prepareRequest($request, $reuserOpts, $deciderOpts);

      $analysis = new Analysis();
      $analysis->setUsingString("nomos,ojo,monk");

      $reuse = new Reuser($reuseUploadId, $groupName);

      $decider = new Decider();
      $decider->setOjoDecider(true);
      $decider->setNomosMonk(true);
      $decider->setConcludeLicenseType("Permissive");

      $scancode = new Scancode();

      $scanOption = new ScanOptions($analysis, $reuse, $decider, $scancode);

      $this->userDao->shouldReceive('getGroupIdByName')
        ->withArgs([$groupName])->andReturn($groupId);
      $this->agentAdderMock->shouldReceive('scheduleAgents')
        ->once()
        ->andReturn(25);

      $scanOption->scheduleAgents($folderId, $uploadId);
    }

    public function testScheduleAgentsApiVersionV2()
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

      $_SERVER['REQUEST_URI'] = "/api/v2/";

      $mockApiVersion = $this->createMock(ApiVersion::class);
      $mockApiVersion->method("getVersionFromUri")->willReturn(ApiVersion::V2);

      $request = new Request();
      $request = $this->prepareRequest($request, $reuserOpts, $deciderOpts);

      $analysis = new Analysis();
      $analysis->setUsingString("nomos,ojo,monk");

      $reuse = new Reuser($reuseUploadId, $groupName);

      $decider = new Decider();
      $decider->setOjoDecider(true);
      $decider->setNomosMonk(true);
      $decider->setConcludeLicenseType("Permissive");

      $scancode = new Scancode();

      $scanOption = new ScanOptions($analysis, $reuse, $decider, $scancode);

      $this->userDao->shouldReceive('getGroupIdByName')
        ->withArgs([$groupName])->andReturn($groupId);
      $this->agentAdderMock->shouldReceive('scheduleAgents')
        ->once()
        ->andReturn(25);

      $scanOption->scheduleAgents($folderId, $uploadId);
    }

    /**
     * @test
     * -# Test for ScanOptions::scheduleAgents() with multiple reuse uploads
     * -# Verify multiple upload IDs are correctly handled
     */
    public function testScheduleAgentsMultipleReuseUploads()
    {
      $reuseUploadIds = [2, 5, 10];
      $uploadId = 4;
      $folderId = 2;
      $groupId = 2;
      $groupName = "fossy";
      $agentsToAdd = ['agent_nomos', 'agent_ojo', 'agent_monk'];

      $_SERVER['REQUEST_URI'] = "/api/v1/";

      $analysis = new Analysis();
      $analysis->setUsingString("nomos,ojo,monk");

      $reuse = new Reuser($reuseUploadIds, $groupName);

      $decider = new Decider();
      $decider->setOjoDecider(true);
      $decider->setNomosMonk(true);
      $decider->setConcludeLicenseType("Permissive");

      $scancode = new Scancode();

      $scanOption = new ScanOptions($analysis, $reuse, $decider, $scancode);

      $expectedSelectors = array_map(function ($id) use ($groupId) {
        return $id . "," . $groupId;
      }, $reuseUploadIds);

      $this->userDao->shouldReceive('getGroupIdByName')
        ->withArgs([$groupName])->andReturn($groupId);
      $this->agentAdderMock->shouldReceive('scheduleAgents')
        ->once()
        ->withArgs(function ($scheduledUploadId, $scheduledAgents, $scheduledRequest) use ($uploadId, $agentsToAdd, $expectedSelectors) {
          return $scheduledUploadId === $uploadId &&
            $scheduledAgents === $agentsToAdd &&
            $scheduledRequest instanceof Request &&
            $scheduledRequest->get('uploadToReuse') === $expectedSelectors;
        })
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
