<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG
 SPDX-FileContributor: Krrish Biswas <krrishbiswas175@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Migrate DB from release 4.6.0 to 4.7.0
 */

require_once("$LIBEXECDIR/fo_mapping_license.php");

/**
 * Migration from FOSSology 4.6.0 to 4.7.0
 * @param bool $verbose Verbose?
 */
function Migrate_46_47(bool $verbose): void
{
  $shortname_array = array(
    /* old_shortname => new_shortname */
    /* Style-suffixed licenses */
    'BSD-style'          => 'LicenseRef-BSD-style',
    'MIT-style'          => 'LicenseRef-MIT-style',
    'ISC-style'          => 'LicenseRef-ISC-style',
    'Apache-style'       => 'LicenseRef-Apache-style',
    'Apache-1.1-style'   => 'LicenseRef-Apache-1.1-style',
    'CMU-style'          => 'LicenseRef-CMU-style',
    'HP-DEC-style'       => 'LicenseRef-HP-DEC-style',
    'ImageMagick-style'  => 'LicenseRef-ImageMagick-style',
    'MIT-CMU-style'      => 'LicenseRef-MIT-CMU-style',
    'MPL-1.1-style'      => 'LicenseRef-MPL-1.1-style',
    'NotreDame-style'    => 'LicenseRef-NotreDame-style',
    'OPL-style'          => 'LicenseRef-OPL-style',
    'OSF-style'          => 'LicenseRef-OSF-style',
    'W3C-style'          => 'LicenseRef-W3C-style',
    'X11-style'          => 'LicenseRef-X11-style',
    'X/Open-style'       => 'LicenseRef-X-Open-style',

    /* Parentheses/brackets/special chars */
    'CECILL(dual)'       => 'LicenseRef-CECILL-dual',
    'GPL(rms)'           => 'LicenseRef-GPL-rms',
    'Public-domain(C)'   => 'LicenseRef-Public-domain-C',
    'CopyLeft[1]'        => 'LicenseRef-CopyLeft-1',
    'CopyLeft[2]'        => 'LicenseRef-CopyLeft-2',
    'GPL-2.1[sic]'       => 'LicenseRef-GPL-2.1-sic',
    'GPL-2.1+[sic]'      => 'LicenseRef-GPL-2.1-or-later-sic',

    /* Spaces */
    'Alliance for Open Media Patent License 1.0' => 'LicenseRef-AOM-Patent-1.0',
    'unRAR restriction'  => 'LicenseRef-unRAR-restriction',
    'UnclassifiedLicense' => 'LicenseRef-UnclassifiedLicense',

    /* Underscores/slashes */
    'SGI_GLX'            => 'SGI-GLX',
    'X/Open'             => 'X-Open',
    'ImageMagick(Apache)' => 'ImageMagick',

    /* Other */
    'ANT+SharedSource'   => 'LicenseRef-ANT-SharedSource',
    
    /* Updated licenseRef.json entries */
    'M+' => 'LicenseRef-M-Plus',
    'GPL-2.0+-with-bison-exception' => 'GPL-2.0-or-later WITH Bison-exception-2.2',
    'GPL-3.0+-with-bison-exception' => 'GPL-3.0-or-later WITH Bison-exception-2.2',
    'GPL-2.0+-with-classpath-exception' => 'GPL-2.0-or-later WITH Classpath-exception-2.0',
    'GPL-3.0+-with-classpath-exception' => 'GPL-3.0-or-later WITH Classpath-exception-2.0',
  );
  renameLicenses($shortname_array, $verbose);
}
