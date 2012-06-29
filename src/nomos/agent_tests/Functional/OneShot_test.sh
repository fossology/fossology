#! /bin/sh
#/***********************************************************
# Copyright (C) 2012 Hewlett-Packard Development Company, L.P.
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
# ***********************************************************/

#
# \file OneShot_test.sh:testOneShotaffero
# \brief Perform a one-shot license analysis on a file containing an affero license
#       License returned should be: Affero_v1
#


testOneShotaffero()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/Affero-v1.0' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

  out=`/usr/local/etc/fossology/mods-enabled/nomos/agent/nomos ../../../testing/dataFiles/TestData/licenses/Affero-v1.0`
  assertEquals "File Affero-v1.0 contains license(s) Affero_v1" "${out}"
}

#
# \file OneShot_test.sh:testOneShotempty
# \brief Perform a one-shot license analysis on an empty file
#       License returned should be: No_license_found
#

testOneShotempty() 
{
# test to see if the file exists
  if [ ! -f '../testdata/empty' ]; then
    fail "ERROR: test file not found...aborting test"
  fi
       
#    $sysconf = getenv('SYSCONFDIR');
#    //echo "DB: sysconf is:$sysconf\n";
#    $this->nomos = $sysconf . '/mods-enabled/nomos/agent/nomos';
#    //echo "DB: nomos is:$this->nomos\n";


# echo "starting testOneShotempty"
  out=`../../agent/nomos ../testdata/empty`
  assertEquals "File empty contains license(s) No_license_found" "${out}"
}

#
# \file OneShot_test.sh:testOneShotgpl
# \brief Perform a one-shot license analysis on a glpv3 license
#       License returned should be: FSF,GPL_v3,Public-domain

testOneShotgpl3() 
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/gpl-3.0.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi
 
# echo "starting testOneShotgpl3"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/gpl-3.0.txt`
  assertEquals "File gpl-3.0.txt contains license(s) FSF,GPL_v3,Public-domain" "${out}"
}

#
# \file OneShot_test.sh:testOneShotgplv2.1
# \brief Perform a one-shot license analysis on a lgpl v2.1 license
#       License returned should be LGPL_v2.1

testOneShotgplv2dot1()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/gplv2.1' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotgplv2.1"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/gplv2.1`
  assertEquals "File gplv2.1 contains license(s) LGPL_v2.1" "${out}"
}

#
# \file OneShot_test.sh:testOneShotnone
# \brief Perform a one-shot license analysis on a file with no licenses
#       License returned should be: No_license_found

testOneShotnone()
{
# test to see if the file exists
  if [ ! -f '../testdata/noLic' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotnone"
  out=`../../agent/nomos ../testdata/noLic`
  assertEquals "File noLic contains license(s) No_license_found" "${out}"
}

#
# \file OneShot_test.sh:testOneShotApacheLicense-v2dot0
# \brief Perform a one-shot license analysis on an Apache v2.0 license
#       License returned should be: Apache_v2.0

testOneShotApacheLicensev2dot0()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/ApacheLicense-v2.0' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotApacheLicensev2dot0"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/ApacheLicense-v2.0`
  assertEquals "File ApacheLicense-v2.0 contains license(s) Apache_v2.0" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_a
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD

testOneShotBSD_style_a()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_a.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_a"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_a.txt`
  assertEquals "File BSD_style_a.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_b
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_b()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_b.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_b"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_b.txt`
  assertEquals "File BSD_style_b.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_c
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_c()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_c.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_c"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_c.txt`
  assertEquals "File BSD_style_c.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_d
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_d()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_d.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_d"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_d.txt`
  assertEquals "File BSD_style_d.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_e
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD

testOneShotBSD_style_e()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_e.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_e"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_e.txt`
  assertEquals "File BSD_style_e.txt contains license(s) BSD" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_f
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_f()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_f.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_f"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_f.txt`
  assertEquals "File BSD_style_f.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_g
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_g()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_g.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_g"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_g.txt`
  assertEquals "File BSD_style_g.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_h
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD

testOneShotBSD_style_h()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_h.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_h"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_h.txt`
  assertEquals "File BSD_style_h.txt contains license(s) BSD" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_i
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_i()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_i.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_i"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_i.txt`
  assertEquals "File BSD_style_i.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_j
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_j()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_j.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_j"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_j.txt`
  assertEquals "File BSD_style_j.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_k
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_k()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_k.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_k"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_k.txt`
  assertEquals "File BSD_style_k.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_l
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_l()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_l.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_l"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_l.txt`
  assertEquals "File BSD_style_l.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_m
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD

testOneShotBSD_style_m()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_m.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_m"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_m.txt`
  assertEquals "File BSD_style_m.txt contains license(s) BSD" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_n
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_n()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_n.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_n"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_n.txt`
  assertEquals "File BSD_style_n.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_o
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD

testOneShotBSD_style_o()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_o.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_o"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_o.txt`
  assertEquals "File BSD_style_o.txt contains license(s) BSD" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_p
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_p()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_p.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_p"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_p.txt`
  assertEquals "File BSD_style_p.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_q
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_q()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_q.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_q"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_q.txt`
  assertEquals "File BSD_style_q.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_s
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_s()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_s.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_s"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_s.txt`
  assertEquals "File BSD_style_s.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_t
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_t()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_t.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_t"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_t.txt`
  assertEquals "File BSD_style_t.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_u
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_u()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_u.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_u"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_u.txt`
  assertEquals "File BSD_style_u.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_v
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_v()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_v.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_v"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_v.txt`
  assertEquals "File BSD_style_v.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_w
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_w()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_w.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_w"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_w.txt`
  assertEquals "File BSD_style_w.txt contains license(s) BSD-style" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_x
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style,Gov't-work

testOneShotBSD_style_x()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_x.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_x"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_x.txt`
  assertEquals "File BSD_style_x.txt contains license(s) BSD-style,Gov't-work" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_y
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: PHP_v3.0

testOneShotBSD_style_y()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_y.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_y"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_y.txt`
  assertEquals "File BSD_style_y.txt contains license(s) PHP_v3.0" "${out}"
}

#
# \file OneShot_test.sh:testOneShotBSD_style_z
# \brief Perform a one-shot license analysis on a BSD license
#       License returned should be: BSD-style

testOneShotBSD_style_z()
{
# test to see if the file exists
  if [ ! -f '../../../testing/dataFiles/TestData/licenses/BSD_style_z.txt' ]; then
    fail "ERROR: test file not found...aborting test"
  fi

# echo "starting testOneShotBSD_style_z"
  out=`../../agent/nomos ../../../testing/dataFiles/TestData/licenses/BSD_style_z.txt`
  assertEquals "File BSD_style_z.txt contains license(s) BSD-style" "${out}"
}

# load shunit2
# uncomment line below to run standalone
# . ${HOME}/shunit2-2.1.6/src/shunit2
