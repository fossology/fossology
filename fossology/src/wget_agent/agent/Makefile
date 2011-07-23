######################################################################
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
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
######################################################################
# FOSSology Makefile - agents/wget_agent
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS)
PROXYDEF=-DPROXYFILE='"$(SYSCONFDIR)/fossology/Proxy.conf"'
EXE=wget_agent
CHKHDR=../../ununpack/agent/checksum.h ../../ununpack/agent/md5.h ../../ununpack/agent/sha1.h
CHKSRC=$(CHKHDR:%.h=%.c)
CONFDIR = $(DESTDIR)$(SYSCONFDIR)/fossology
MODULEDIR = $(shell dirname `pwd`)

all: $(EXE)

$(EXE): %: %.c $(CHKHDR) $(CHKSRC) $(DB) $(REPO) $(VARS)
	@echo "make pkgagent agent"
	$(CC) $< $(CHKSRC) $(PROXYDEF) $(CFLAGS_LOCAL) -o $@

install: all
	@echo "make wget_agent agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)
	@if test ! -e $(CONFDIR)/mods-enabled/wget_agent; then \
	ln -s $(MODULEDIR) $(CONFDIR)/mods-enabled/wget_agent ; fi;


uninstall:
	@echo "make wget_agent agent uninstall"
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "*** No tests available for agents/$(EXE) ***"

coverage:
	@echo "NOTICE: no coverage available for wget_agent"

clean:
	@echo "make wget_agent agent clean"
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
