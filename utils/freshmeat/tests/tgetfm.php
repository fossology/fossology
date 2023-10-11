#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Try out GetFreshmeatRdf
 *
 * @param
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Jun 6, 2008
 */

require_once('GetFreshMeatRdf.php');

$Rdf = new GetFreshMeatRdf();
$rname = $Rdf->rdf_name;
echo "TG: \$rname is:$rname\n";

$Rdf->get_rdf($rname);
