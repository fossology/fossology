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
    'CECILL(dual)'       => 'CECILL-2.1',
    'GPL(rms)'           => 'GPL-2.0-only',
    'Public-domain(C)'   => 'Public-domain',
    'CopyLeft[1]'        => 'Copyleft',
    'CopyLeft[2]'        => 'Copyleft',
    'GPL-2.1[sic]'       => 'GPL-2.0-only',
    'GPL-2.1+[sic]'      => 'GPL-2.0-or-later',
    'Alliance for Open Media Patent License 1.0' => 'AOM-Patent-1.0',
    'unRAR restriction'  => 'unRAR-restriction',
    'SGI_GLX'            => 'SGI-B-2.0',
    'X/Open'             => 'X11',
    'ImageMagick(Apache)' => 'ImageMagick',
    'ANT+SharedSource'   => 'ANT-SharedSource',
    'M-Plus-Project' => 'M-Plus',
    'AgainstDRM' => 'Against-DRM',
    'GPL-2.0+-with-bison-exception' => 'GPL-2.0-or-later WITH Bison-exception-2.2',
    'GPL-3.0+-with-bison-exception' => 'GPL-3.0-or-later WITH Bison-exception-2.2',
    'GPL-2.0+-with-classpath-exception' => 'GPL-2.0-or-later WITH Classpath-exception-2.0',
    'GPL-3.0+-with-classpath-exception' => 'GPL-3.0-or-later WITH Classpath-exception-2.0',
  );
  renameLicenses($shortname_array, $verbose);
}
