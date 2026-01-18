<?php

require_once dirname(__FILE__) . '/../../../../install/license_ref_utils.php';

class test_license_ref_utils extends \PHPUnit\Framework\TestCase
{
  public function testCandidateTextUpdatesWhenIncomingFlagZero()
  {
    $result = buildLicenseTextUpdate(1, 0, 'License by OJO.', 'Actual license text');

    $this->assertNotNull($result);
    $this->assertSame('Actual license text', $result['text']);
    $this->assertSame(0, $result['rf_flag']);
  }

  public function testSkipsWhenTextUnchanged()
  {
    $this->assertNull(buildLicenseTextUpdate(1, 0, 'Same text', 'Same text'));
  }

  public function testSkipsWhenIncomingTextIsEmpty()
  {
    $this->assertNull(buildLicenseTextUpdate(1, 0, 'Has text', ''));
  }
}
