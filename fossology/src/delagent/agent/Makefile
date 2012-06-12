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

TOP=../../..

VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
TESTDIRS=../agent_tests

CFLAGS_LOCAL=$(ALL_CFLAGS) -lpq -lssl
CONFDIR = $(DESTDIR)$(SYSCONFDIR)

EXE=delagent
HDRS=delagent.h

all: $(EXE)
	@echo "make delagent agent and test"

$(EXE): %: %.c $(FOLIB) delagent.o $(VARS) $(HDRS)
	@echo "make delagent agent"
	$(CC) $< delagent.o $(CFLAGS_LOCAL) -o $@

delagent.o: util.c $(HDRS)
	$(CC) -c $< $(CFLAGS_LOCAL) -o $@

install: all
	@echo "make delagent agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)	

uninstall:
	@echo "make delagent agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

test: all
	$(MAKE) -C $(TESTDIRS) test

coverage: delagent_cov.o
	$(MAKE) -C $(TESTDIRS) coverage

delagent_cov.o: util.c $(HDRS)
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

clean:
	@echo "make delagent agent clean"
	rm -f $(EXE) *.o core *.gc*
	$(MAKE) -C $(TESTDIRS) clean

include $(DEPS)

.PHONY: all install uninstall clean test coverage
