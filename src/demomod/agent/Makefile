# SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
# SPDX-FileCopyrightText: © 2016 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

CFLAGS_LOCAL = $(FO_CFLAGS) -Werror
CFLAGS_LOCALO = $(FO_CFLAGS)

EXE=demomod
OBJS=usage.o utils.o process.o
HDRS=demomod.h

all: $(EXE)
	@echo "make demomod agent"

demomod: demomod.o $(OBJS)
	$(CC) demomod.o $(OBJS) $(CFLAGS_LOCAL) $(FO_LDFLAGS) -o $@

demomod.o: demomod.c $(HDRS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c $< $(CFLAGS_LOCALO) $(DEFS)

$(OBJS): %.o: %.c $(HDRS) $(DB) $(VARS)
	$(CC) -c $< $(CFLAGS_DBO) $(FO_CFLAGS)

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	@echo "make demomod  agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	@echo "make demomod  agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean $(FOLIB)
