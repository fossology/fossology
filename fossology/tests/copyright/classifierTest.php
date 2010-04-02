<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
// regx to parse output of adams test program
// ^Test[0-9]+\spassed\s([0-9]+).*?([0-9]+)
/*
 * the above will not capture the test number my thoughts are if the two
 * captured numbers do not match then just print that line as part of the
 * failure.
 * 
 * Need to cd to the copyright dir in the sources.
 */
/**
 * classifierTest
 * \brief run the python test that determines if the naive Bayes 
 * classifier can correctly classify data that it has already seen.
 * 
 * @version "$Id $"
 */
?>