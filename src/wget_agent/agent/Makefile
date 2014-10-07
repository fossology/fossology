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

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

TESTDIR = ../agent_tests
CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS)
EXE = wget_agent
CHKHDR = ../../ununpack/agent/checksum.h ../../ununpack/agent/md5.h ../../ununpack/agent/sha1.h
CHKSRC = $(CHKHDR:%.h=%.c)
OBJECTS = wget_agent.o
HDIR = $(OBJS:.o=.h)
COVERAGE = $(OBJECTS:%.o=%_cov.o)

all: $(EXE)

$(EXE): main.c $(FOLIB) libwget_agent.a $(CHKHDR) $(CHKSRC) $(DB) $(REPO) $(VARS)
	@echo "make wget_agent"
	$(CC) main.c libwget_agent.a $(CHKSRC) $(CFLAGS_LOCAL) $(DEFS) -o $@

install: all
	@echo "make wget_agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	@echo "make wget_agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

$(OBJECTS): %.o: %.c
	$(CC) -c $< $(CFLAGS_LOCAL)

$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) -o $@

libwget_agent.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libwget_agent_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

test: libwget_agent.a
	@echo "make test wget_agent"
	$(MAKE) -C $(TESTDIR) test

coverage: libwget_agent_cov.a
	@echo "make coverage wget_agent"
	$(MAKE) -C $(TESTDIR) coverage

clean:
	@echo "make wget_agent clean"
	rm -f $(EXE) *.o core *.a *.gc*

include $(DEPS)

.PHONY: all install uninstall clean test
