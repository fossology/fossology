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
TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

CFLAGS_LOCAL = $(ALL_CFLAGS) -Werror
DEF = 

MODDIR = mods-enabled

FO_CONF = fossology.conf
V_LIST  = variables.list
C_FILES = $(wildcard *.c)
EXES    = $(patsubst %.c, %, $(C_FILES))

USER  = `whoami`
GROUP = `id -ng`

##########################
# executable build rules #
##########################

all: clean test
test: $(FO_CONF) $(EXES)
$(EXES): %: %.c $(FOLIB) $(VARS)
	@echo $@:
	@if [ ! -d $(MODDIR)/$@ ] ; then \
	  mkdir -p $(MODDIR)/$@/agent/; fi;
	$(CC) $< -o $(MODDIR)/$@/agent/$@ $(CFLAGS_LOCAL)
	@echo "[$@]\nVERSION=test\nSVN_REV=0M" > $(MODDIR)/$@/VERSION
	@echo "[default]\ncommand=$@\nmax=-1\nspecial[]=\n" > $(MODDIR)/$@/$@.conf

$(FO_CONF): $(V_LIST)
	$(TOP)/utils/preprocess_php $(V_LIST) $@.in

$(V_LIST):
	@echo "PROJECTGROUP=$(GROUP)\nPROJECTUSER=$(USER)" > $(V_LIST)

#####################
# other build rules #
#####################

clean:
	rm -rf $(MODDIR) $(V_LIST) $(FO_CONF) *.log

.PHONY: all $(MODDIR) $(EXE) $(FOLIB)
