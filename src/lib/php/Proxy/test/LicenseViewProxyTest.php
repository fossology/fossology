<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Proxy;

use Fossology\Lib\Db\DbManager;
use Mockery as M;

class LicenseViewProxyTest extends \PHPUnit\Framework\TestCase
{
  private $dbManagerMock;

  protected function setUp() : void
  {
    global $container;
    $container = M::mock('ContainerBuilder');
    $this->dbManagerMock = M::mock(DbManager::class);
    $container->shouldReceive('get')->withArgs(array('db.manager'))->andReturn($this->dbManagerMock);
    $this->almostAllColumns = 'rf_pk,rf_shortname,rf_text,rf_url,rf_add_date,rf_copyleft,rf_fullname,rf_notes,marydone,rf_active,rf_text_updatable,rf_md5,rf_detector_type,rf_source';
  }

  protected function tearDown() : void
  {
    M::close();
  }

  public function testQueryOnlyLicenseRef()
  {
    $licenseViewProxy = new LicenseViewProxy(0);

    $reflection = new \ReflectionClass(get_class($licenseViewProxy));
    $method = $reflection->getMethod('queryOnlyLicenseRef');
    $method->setAccessible(true);

    $options1 = array('columns'=>array('rf_pk','rf_shortname'));
    $query1 = $method->invoke($licenseViewProxy,$options1);
    assertThat($query1, is("SELECT rf_pk,rf_shortname FROM ONLY license_ref"));

    $options2 = array('extraCondition'=>'rf_pk<100');
    $query2 = $method->invoke($licenseViewProxy,$options2);
    assertThat($query2, is("SELECT $this->almostAllColumns,0 AS group_fk FROM ONLY license_ref WHERE rf_pk<100"));
  }

  public function testQueryLicenseCandidate()
  {
    $groupId = 123;
    $licenseViewProxy = new LicenseViewProxy($groupId);

    $reflection = new \ReflectionClass(get_class($licenseViewProxy));
    $method = $reflection->getMethod('queryLicenseCandidate');
    $method->setAccessible(true);

    $options1 = array('columns'=>array('rf_pk','rf_shortname'));
    $query1 = $method->invoke($licenseViewProxy,$options1);
    assertThat($query1, is("SELECT rf_pk,rf_shortname FROM license_candidate WHERE group_fk=$groupId"));

    $options2 = array('extraCondition'=>'rf_pk<100');
    $query2 = $method->invoke($licenseViewProxy,$options2);
    assertThat($query2, is("SELECT $this->almostAllColumns,group_fk FROM license_candidate WHERE group_fk=$groupId AND rf_pk<100"));

    $prefix = '#';
    $options3 = array(LicenseViewProxy::CANDIDATE_PREFIX=>$prefix,'columns'=>array('rf_shortname'));
    $query3 = $method->invoke($licenseViewProxy,$options3);
    assertThat($query3, is("SELECT '". pg_escape_string($prefix). "'||rf_shortname AS rf_shortname FROM license_candidate WHERE group_fk=$groupId"));
  }

  public function testConstruct()
  {
    $licenseViewProxy0 = new LicenseViewProxy(0);
    $query0 = $licenseViewProxy0->getDbViewQuery();
    $expected0 = "SELECT $this->almostAllColumns,0 AS group_fk FROM ONLY license_ref";
    assertThat($query0,is($expected0));

    $licenseViewProxy123 = new LicenseViewProxy(123,array('diff'=>true));
    $query123 = $licenseViewProxy123->getDbViewQuery();
    $expected123 = "SELECT $this->almostAllColumns,group_fk FROM license_candidate WHERE group_fk=123";
    assertThat($query123,is($expected123));

    $licenseViewProxy0123 = new LicenseViewProxy(123);
    $query0123 = $licenseViewProxy0123->getDbViewQuery();
    $expected0123 = "$expected123 UNION $expected0";
    assertThat($query0123,is($expected0123));
  }
}
