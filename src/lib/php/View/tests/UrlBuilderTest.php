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

namespace Fossology\Lib\View;

use Fossology\Lib\Data\LicenseRef;
use Mockery as M;

function Traceback_uri()
{
  return "http://localhost/repo";
}

class UrlBuilderTest extends \PHPUnit_Framework_TestCase
{
  /** @var UrlBuilder */
  private $urlBuilder;

  public function setUp()
  {
    $this->urlBuilder = new UrlBuilder();
  }

  public function testGetLicenseTextLink()
  {
    $id = 432;
    $shortName = "<shortName>";
    $fullName = "<fullName>";

    $licenseRef = M::mock(LicenseRef::classname());
    $licenseRef->shouldReceive("getId")->once()->withNoArgs()->andReturn($id);
    $licenseRef->shouldReceive("getShortName")->once()->withNoArgs()->andReturn($shortName);
    $licenseRef->shouldReceive("getFullName")->once()->withNoArgs()->andReturn($fullName);

    $linkUrl = $this->urlBuilder->getLicenseTextLink($licenseRef);

    assertThat($linkUrl, is("<a title=\"$fullName\" href=\"javascript:;\" " .
        "onclick=\"javascript:window.open('http://localhost/repo?mod=popup-license&rf=$id'," .
        "'License text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');\">$shortName</a>"));
  }
}
 