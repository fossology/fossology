<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\View;

use Fossology\Lib\Data\LicenseRef;
use Mockery as M;

function Traceback_uri()
{
  return "http://localhost/repo";
}

class UrlBuilderTest extends \PHPUnit\Framework\TestCase
{
  /** @var UrlBuilder */
  private $urlBuilder;

  protected function setUp() : void
  {
    $this->urlBuilder = new UrlBuilder();
  }

  public function testGetLicenseTextLink()
  {
    $id = 432;
    $shortName = "<shortName>";
    $fullName = "<fullName>";

    $licenseRef = M::mock(LicenseRef::class);
    $licenseRef->shouldReceive("getId")->once()->withNoArgs()->andReturn($id);
    $licenseRef->shouldReceive("getShortName")->once()->withNoArgs()->andReturn($shortName);
    $licenseRef->shouldReceive("getFullName")->once()->withNoArgs()->andReturn($fullName);

    $linkUrl = $this->urlBuilder->getLicenseTextUrl($licenseRef);

    assertThat($linkUrl, is("<a title=\"$fullName\" href=\"javascript:;\" " .
        "onclick=\"javascript:window.open('http://localhost/repo?mod=popup-license&rf=$id'," .
        "'License text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\">$shortName</a>"));
  }
}
