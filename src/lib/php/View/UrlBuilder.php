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

class UrlBuilder {

  /**
   * @param LicenseRef $licenseRef
   * @return string
   */
  public function getLicenseTextUrl(LicenseRef $licenseRef)
  {
    $uri = Traceback_uri() . '?mod=popup-license&rf=' . $licenseRef->getId();
    $title = _('License text');
    $licenseShortNameWithLink = '<a title="'. $licenseRef->getFullName() .'" href="javascript:;" onclick="javascript:window.open(\''
        .$uri. '\',\''.$title.'\',\'width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes\');">'
        .$licenseRef->getShortName(). '</a>';
    return $licenseShortNameWithLink;
  }
}
