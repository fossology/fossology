<?php
/*
 SPDX-FileCopyrightText: © 2022 Siemens AG
 SPDX-FileContributor: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Migrate DB from release 4.2.1 to 4.3.0
 */

require_once("$LIBEXECDIR/fo_mapping_license.php");

/**
 * Migration from FOSSology 4.2.1 to 4.3.0
 * @param bool $verbose Verbose?
 */
function Migrate_42_43(bool $verbose): void
{
  $shortname_array = array(
    /* old_shortname => new_shortname */
    "GPL-1.0" => "GPL-1.0-only",
    "GPL-1.0+" => "GPL-1.0-or-later",
    "GPL-2.0" => "GPL-2.0-only",
    "GPL-2.0+" => "GPL-2.0-or-later",
    "GPL-3.0" => "GPL-3.0-only",
    "GPL-3.0+" => "GPL-3.0-or-later",
    "GPL-3.0-possibility" => "GPL-3.0-only-possibility",
    "GPL-2.0+KDEupgradeClause" => "GPL-2.0-or-laterKDEupgradeClause",
    "LGPL-1.0" => "LGPL-1.0-only",
    "LGPL-1.0+" => "LGPL-1.0-or-later",
    "LGPL-2.0" => "LGPL-2.0-only",
    "LGPL-2.0+" => "LGPL-2.0-or-later",
    "LGPL-2.1" => "LGPL-2.1-only",
    "LGPL-2.1+" => "LGPL-2.1-or-later",
    "LGPL-3.0" => "LGPL-3.0-only",
    "LGPL-3.0+" => "LGPL-3.0-or-later",
    "LGPL-3.0-possibility" => "LGPL-3.0-only-possibility",
    "LGPL-2.1+-KDE-exception" => "LGPL-2.1-or-later-KDE-exception",
    "AGPL-1.0" => "AGPL-1.0-only",
    "AGPL-1.0+" => "AGPL-1.0-or-later",
    "AGPL-3.0" => "AGPL-3.0-only",
    "AGPL-3.0+" => "AGPL-3.0-or-later",
    "GFDL-1.1" => "GFDL-1.1-only",
    "GFDL-1.1+" => "GFDL-1.1-or-later",
    "GFDL-1.2" => "GFDL-1.2-only",
    "GFDL-1.2+" => "GFDL-1.2-or-later",
    "GFDL-1.3" => "GFDL-1.3-only",
    "GFDL-1.3+" => "GFDL-1.3-or-later",
    "GFDL-1.1-invariants+" => "GFDL-1.1-invariants-or-later",
    "GFDL-1.2-invariants+" => "GFDL-1.2-invariants-or-later",
    "GFDL-1.2-no-invariants+" => "GFDL-1.2-no-invariants-or-later"
  );
  renameLicenses($shortname_array, $verbose);
}
