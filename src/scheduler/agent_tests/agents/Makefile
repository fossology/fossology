# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(FO_CFLAGS) -Werror
DEF =

MODDIR = mods-enabled

FO_CONF = fossology.conf
V_LIST = variables.list
C_FILES = $(wildcard *.c)
EXES = $(patsubst %.c, %, $(C_FILES))

USER = `whoami`
GROUP = `id -ng`

all: $(FOLIB)

test: $(FO_CONF) $(EXES) fossology_testconfig
	cp $(FOSSOLOGY_TESTCONFIG)/Db.conf .
	cp $(FOSSOLOGY_TESTCONFIG)/VERSION .

coverage:
	@echo "nothing to do"

$(EXES): %: %.c $(FOLIB)
	@echo $@:
	@if [ ! -d $(MODDIR)/$@ ]; then \
		mkdir -p $(MODDIR)/$@/agent; \
	fi
	$(CC) $< -o $(MODDIR)/$@/agent/$@ $(CFLAGS_LOCAL) $(FO_LDFLAGS)
	@echo "[$@]\nVERSION=test\nCOMMIT_HASH=0000" > $(MODDIR)/$@/VERSION
	@echo "[default]\ncommand=$@\nmax=-1\nspecial[]=\n" > $(MODDIR)/$@/$@.conf

$(FO_CONF): $(V_LIST)
	$(TOP)/utils/preprocess_php $(V_LIST) $@.in

$(V_LIST):
	@echo "PROJECTGROUP=$(GROUP)\nPROJECTUSER=$(USER)" > $(V_LIST)

clean:
	rm -rf $(MODDIR) $(V_LIST) $(FO_CONF) *.log Db.conf VERSION

.PHONY: all test coverage clean $(MODDIR) $(EXE) $(FOLIB)

include $(DEPS)
