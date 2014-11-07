######################################################################
# Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.
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
LANG=C
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
TESTDIRS=../agent_tests
LOCAL_CFLAGS=$(ALL_CFLAGS) -I/usr/include/rpm -lpq -lrpm -lrpmio

EXE=pkgagent
HDRS=pkgagent.h
LOCAL_RPM_4_4_CFLAGS=$(LOCAL_CFLAGS) -D_RPM_4_4
LOCAL_RPM_CFLAGS=$(LOCAL_CFLAGS) -D_RPM_4_4_COMPAT

all: $(EXE)
	@echo "make pkgagent agent and test"

$(EXE): main.c $(FOLIB) pkgagent.o $(VARS) $(HDRS)
	@echo "make pkgagent agent"
	$(CC) main.c pkgagent.o  $(LOCAL_CFLAGS) $(FO_LDFLAGS) $(DEFS) -o $@

pkgagent.o: pkgagent.c pkgagent.h
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null ; then \
		$(CC) -c $< $(LOCAL_RPM_CFLAGS); \
	else \
		if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.10 >/dev/null ; then \
			$(CC) -c $< $(LOCAL_RPM_CFLAGS); \
		else \
			$(CC) -c $< $(LOCAL_RPM_4_4_CFLAGS); \
		fi \
	fi

install: all
	@echo "make pkgagent agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	@echo "make pkgagent agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

test: all
	$(MAKE) -C $(TESTDIRS) test

coverage: pkgagent_cov.o
	$(MAKE) -C $(TESTDIRS) coverage

pkgagent_cov.o: pkgagent.c pkgagent.h
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null ; then \
		$(CC) -c $< $(COV_CFLAGS) $(LOCAL_RPM_CFLAGS) -o $@; \
	else \
		$(CC) -c $< $(COV_CFLAGS) $(LOCAL_RPM_4_4_CFLAGS) -o $@; \
	fi

clean:
	@echo "make pkgagent agent clean"
	rm -f $(EXE) *.o core *.gc*; \
	$(MAKE) -C $(TESTDIRS) clean

include $(DEPS)

.PHONY: all install uninstall clean test
