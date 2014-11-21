<?php
/*
Copyright (C) 2014, Siemens AG

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

namespace Fossology\Lib\Proxy;

use Mockery as M;
use Fossology\Lib\Db\DbManager;


class LicenseViewProxyTest extends \PHPUnit_Framework_TestCase
{
  private $dbManagerMock;

  public function setUp()
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbManagerMock = M::mock(DbManager::classname());
    $container->shouldReceive('get')->withArgs(array('db.manager'))->andReturn($this->dbManagerMock);
  }

  public function tearDown()
  {
    M::close();
  }

  public function testQueryOnlyLicenseRef()
  {
    $licenseViewProxy = new LicenseViewProxy(0);

    $reflection = new \ReflectionClass($licenseViewProxy->classname() );
    $method = $reflection->getMethod('queryOnlyLicenseRef');
    $method->setAccessible(true);
    
    $options1 = array('columns'=>array('rf_pk','rf_shortname'));
    $query1 = $method->invoke($licenseViewProxy,$options1);
    assertThat($query1, is("SELECT rf_pk,rf_shortname FROM ONLY license_ref"));
    
    $options2 = array('extraCondition'=>'rf_pk<100');
    $query2 = $method->invoke($licenseViewProxy,$options2);
    assertThat($query2, is("SELECT *,0 AS group_fk FROM ONLY license_ref WHERE rf_pk<100"));
  }
  
  public function testQueryLicenseCandidate()
  {
    $groupId = 123;
    $licenseViewProxy = new LicenseViewProxy($groupId);

    $reflection = new \ReflectionClass($licenseViewProxy->classname() );
    $method = $reflection->getMethod('queryLicenseCandidate');
    $method->setAccessible(true);
    
    $options1 = array('columns'=>array('rf_pk','rf_shortname'));
    $query1 = $method->invoke($licenseViewProxy,$options1);
    assertThat($query1, is("SELECT rf_pk,rf_shortname FROM license_candidate WHERE group_fk=$groupId"));
    
    $options2 = array('extraCondition'=>'rf_pk<100');
    $query2 = $method->invoke($licenseViewProxy,$options2);
    assertThat($query2, is("SELECT * FROM license_candidate WHERE group_fk=$groupId AND rf_pk<100"));
  }

  public function testConstruct()
  {
    $licenseViewProxy0 = new LicenseViewProxy(0);
    $query0 = $licenseViewProxy0->getDbViewQuery();
    $expected0 = 'SELECT *,0 AS group_fk FROM ONLY license_ref';
    assertThat($query0,is($expected0));
    
    $licenseViewProxy123 = new LicenseViewProxy(123,array('diff'=>true));
    $query123 = $licenseViewProxy123->getDbViewQuery();
    $expected123 = "SELECT * FROM license_candidate WHERE group_fk=123";
    assertThat($query123,is($expected123));

    $licenseViewProxy0123 = new LicenseViewProxy(123);
    $query0123 = $licenseViewProxy0123->getDbViewQuery();
    $expected0123 = "$expected123 UNION $expected0";
    assertThat($query0123,is($expected0123));
  }
  
}