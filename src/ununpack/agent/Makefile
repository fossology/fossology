######################################################################
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
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

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LDFLAGS_LOCAL = $(FO_LDFLAGS) -lmagic $(shell libgcrypt-config --libs)
CFLAGS_LOCAL = $(FO_CFLAGS) $(shell libgcrypt-config --cflags)
EXE = departition ununpack

CHKHDR = checksum.h
CHKSRC = $(CHKHDR:%.h=%.c) traverse.c utils.c
UUHDR = ununpack.h ununpack-iso.h ununpack-disk.h ununpack-ar.h $(CHKHDR) ununpack_globals.h
UUSRC = $(UUHDR:%.h=%.c)

OBJECTS = checksum.o traverse.o ununpack-iso.o ununpack-ar.o ununpack-disk.o utils.o
COVERAGE = $(OBJECTS:%.o=%_cov.o)

all: $(FOLIB) $(EXE)

coverage: $(FOLIB) $(EXE:%=%-coverage)

departition: %: %.c $(VARS)
	$(CC) $< $(FO_CFLAGS) $(FO_LDFLAGS) -o $@

departition-coverage: departition.c $(VARS)
	$(CC) $< $(FO_CFLAGS) $(FO_LDFLAGS) $(FLAG_COV) -o $(@:%-coverage=%)

ununpack: ununpack.c libununpack.a $(VARS) $(DB) $(REPO) $(AGENTLIB) $(UUHDR)
	$(CC) ununpack.c libununpack.a $(FO_CFLAGS) $(LDFLAGS_LOCAL) $(DEFS) -o $@

ununpack-coverage: ununpack.c libununpack_cov.a $(VARS) $(DB) $(REPO) $(AGENTLIB) $(UUHDR)
	$(CC) ununpack.c libununpack_cov.a $(FO_CFLAGS) $(LDFLAGS_LOCAL) $(FLAG_COV) $(DEFS) -o $(@:%-coverage=%)

install: all $(VARS)
	$(INSTALL_PROGRAM) departition $(DESTDIR)$(MODDIR)/ununpack/agent/departition
	$(INSTALL_PROGRAM) ununpack $(DESTDIR)$(MODDIR)/ununpack/agent/ununpack

# create wrapper for departition in BINDIR
	mkdir -p $(DESTDIR)$(BINDIR)
	@echo "#!/bin/bash" > $(DESTDIR)$(BINDIR)/departition
	@echo "MODDIR=$(MODDIR)" >> $(DESTDIR)$(BINDIR)/departition
	@echo "\$$MODDIR/ununpack/agent/departition \$$@" >> $(DESTDIR)$(BINDIR)/departition
	chmod a+x $(DESTDIR)$(BINDIR)/departition

uninstall: $(VARS)
	rm -f $(DESTDIR)$(BINDIR)/departition
	rm -rf $(DESTDIR)$(MODDIR)/ununpack/agent

$(OBJECTS): %.o: %.c $(UUHDR)
	$(CC) -c $< $(CFLAGS_LOCAL)

$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) -o $@

libununpack.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libununpack_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

clean:
	rm -f $(EXE) *.o core *.a *.gc*

.PHONY: all install uninstall clean

include $(DEPS)
