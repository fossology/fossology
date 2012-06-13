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
TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DIRS = agent ui 
TESTDIR = agent_tests
MOD_NAME=wget_agent

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done

all: VERSIONFILE
	$(call dirloop, )

test: 
	$(MAKE) -C $(TESTDIR) test

coverage:
	$(MAKE) -C $(TESTDIR) coverage

# generate the VERSION file
VERSIONFILE:
	$(call WriteVERSIONFile,"wget_agent")

install: all
	echo "make $(MOD_NAME) install"
	$(call dirloop,install)
	$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$(MOD_NAME)/VERSION
	$(INSTALL_PROGRAM) mod_deps $(DESTDIR)$(MODDIR)/$(MOD_NAME)/mod_deps
	$(INSTALL_DATA) $(MOD_NAME).conf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_NAME).conf
	@mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	@/bin/chown $(PROJECTUSER):$(PROJECTGROUP) $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	@if test ! -e $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME); then \
	  ln -s $(MODDIR)/$(MOD_NAME) $(DESTDIR)$(SYSCONFDIR)/mods-enabled ; fi;

uninstall:
	echo "make $(MOD_NAME) uninstall"
	$(call dirloop,uninstall)
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME)

clean:
	rm -f VERSION
	$(call dirloop,clean)

.PHONY: all install uninstall clean test VERSIONFILE
