#/***********************************************************
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
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
# Makefile for fosscp (in php!)
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

include ../../Makefile.conf

#CC=gcc
#CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
#EXE=wc_agent
#INC=-I../../$(BUILDINC)
#LIB$=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq$

all:	
	chmod ug+x fosscp.php
	$(CP) fosscp.php fosscp_agent

InstallationCreate:
	# Uncomment to install
	$(CP) fosscp_agent ../../install/$(AGENTDIR)

install:
	# Uncomment to install
	$(CP) fosscp_agent $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/fosscp_agent

clean:
	$(RM) fosscp_agent

