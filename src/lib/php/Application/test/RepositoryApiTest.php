<?php
/*
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

namespace Fossology\Lib\Application;

function time()
{
  return 1535371200;
}

/**
 * @class RepositoryApiTest
 * @brief Test for RepositoryApi
 */
class RepositoryApiTest extends \PHPUnit\Framework\TestCase
{
  /** @var CurlRequest $mockCurlRequest
   * CurlRequest object for testing */
  private $mockCurlRequest;

  /**
   * @brief One time setup for test
   *
   * Mock the CurlRequest class and set mockCurlRequest variable
   * @see PHPUnit::Framework::TestCase::setUp()
   */
  protected function setUp()
  {
    $this->mockCurlRequest = \Mockery::mock('CurlRequest');

    $this->mockCurlRequest->shouldReceive('setOptions')->once()->with(array(
      CURLOPT_HEADER         => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => array('User-Agent: fossology'),
      CURLOPT_TIMEOUT        => 2,
    ));
    $this->mockCurlRequest->shouldReceive('execute')->once()
      ->andReturn('HEADER{"key": "value"}');
    $this->mockCurlRequest->shouldReceive('getInfo')->once()
      ->with(CURLINFO_HEADER_SIZE)->andReturn(6);
    $this->mockCurlRequest->shouldReceive('close')->once();
  }

  /**
   * @brief Tear down mock objects
   * @see PHPUnit::Framework::TestCase::tearDown()
   */
  public function tearDown()
  {
    \Mockery::close();
  }

  /**
   * @brief Test for RepositoryApi::getLatestRelease()
   * @test
   * -# Mock CurlRequestService object and pass to RepositoryApi
   * -# Get the result of RepositoryApi::getLatestRelease()
   * -# Check if you receive array `(key => value)`
   */
  public function testGetLatestRelease()
  {
    $mockCurlRequestServer = \Mockery::mock('CurlRequestService');
    $mockCurlRequestServer->shouldReceive('create')->once()
      ->with('https://api.github.com/repos/fossology/fossology/releases/latest')
      ->andReturn($this->mockCurlRequest);
    $repositoryApi = new RepositoryApi($mockCurlRequestServer);

    $this->assertEquals(array('key' => 'value'), $repositoryApi->getLatestRelease());
  }

  /**
   * @brief Test for RepositoryApi::getCommitsOfLastDays()
   * @test
   * -# Mock CurlRequestService object and pass to RepositoryApi
   * -# Get the result of RepositoryApi::getCommitsOfLastDays()
   * -# Check if you receive array `(key => value)`
   */
  public function testGetCommitsOfLastDays()
  {
    $mockCurlRequestServer = \Mockery::mock('CurlRequestServer');
    $mockCurlRequestServer->shouldReceive('create')->once()
      ->with('https://api.github.com/repos/fossology/fossology/commits?since=2018-06-28T12:00:00Z')
      ->andReturn($this->mockCurlRequest);
    $repositoryApi = new RepositoryApi($mockCurlRequestServer);

    $this->assertEquals(array('key' => 'value'), $repositoryApi->getCommitsOfLastDays(60));
  }
}
