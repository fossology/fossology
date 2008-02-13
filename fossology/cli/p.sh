#!/bin/bash
#/***********************************************************
# p.sh
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
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
# simple stub to get the shell to parse the input from the file, called
# by cp2foss when processing file input.
# 
while [ $# -ne 0 ]
do
	arg=$1
	shift
	printf "%s " $arg
	# need to do echo \n, if \n is part of printf -d comments get
	# compressedwithnospaces.
	echo
done
