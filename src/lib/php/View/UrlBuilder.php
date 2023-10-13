<?php
/*
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\View;

use Fossology\Lib\Data\LicenseRef;

class UrlBuilder
{

  /**
   * @param LicenseRef $licenseRef
   * @return string
   */
  public function getLicenseTextUrl(LicenseRef $licenseRef)
  {
    $uri = Traceback_uri() . '?mod=popup-license&rf=' . $licenseRef->getId();
    $title = _('License text');
    return '<a title="'. $licenseRef->getFullName() .'" href="javascript:;" onclick="javascript:window.open(\''
        .$uri. '\',\''.$title.'\',\'width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes\');">'
        .$licenseRef->getShortName(). '</a>';
  }
}
