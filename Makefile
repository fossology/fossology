# FOSSology Makefile
# SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

# pull in all our default variables
include Makefile.conf

# the directories we do things in by default
DIRS = install src

# create lists of targets for various operations
# these are phony targets (declared at bottom) of convenience so we can
# run 'make $(operation)-$(subdir)'. Yet another convenience, a target of
# '$(subdir)' is equivalent to 'build-$(subdir)'
BUILDDIRS = $(DIRS:%=build-%)
INSTALLDIRS = $(DIRS:%=install-%)
INSTALLDIRSOFFLINE = $(DIRS:%=install_offline-%)
UNINSTALLDIRS = $(DIRS:%=uninstall-%)
CLEANDIRS = $(DIRS:%=clean-%)
TESTDIRS = $(DIRS:%=test-%)
COVDIRS = $(DIRS:%=cov-%)
CONFPATH = $(SYSCONFDIR)

## Targets
# build
all: $(BUILDDIRS) VERSIONFILE
$(DIRS): $(BUILDDIRS)
$(BUILDDIRS):
	$(MAKE) -C $(@:build-%=%)

# high level dependencies:
# the scheduler and agents need the library built first
build-scheduler: build-src
build-agents: build-src

# utils is a separate target, since it isn't built by default yet
utils: build-utils

# generate the VERSION file
TOP = .
VERSIONFILE:
	@$(call WriteVERSIONFile,"BUILD")

# install depends on everything being built first
install: all $(INSTALLDIRS)
$(INSTALLDIRS):
	$(INSTALL) -m 666 VERSION $(DESTDIR)$(CONFPATH)/VERSION
	$(MAKE) -C $(@:install-%=%) install

# offline install (without composer)
install_offline: all $(INSTALLDIRSOFFLINE)
	$(INSTALL) -m 666 VERSION $(DESTDIR)$(CONFPATH)/VERSION

$(INSTALLDIRSOFFLINE):
	$(MAKE) -C $(@:install_offline-%=%) install_offline

uninstall: $(UNINSTALLDIRS)
$(UNINSTALLDIRS):
	$(MAKE) -C $(@:uninstall-%=%) uninstall

# test depends on everything being built first
test: all $(TESTDIRS)
$(TESTDIRS):
	$(MAKE) -C $(@:test-%=%) test

coverage: $(COVDIRS)
$(COVDIRS):
	$(MAKE) -C $(@:cov-%=%) coverage

clean: $(CLEANDIRS)
	rm -f variable.list VERSION
$(CLEANDIRS):
	$(MAKE) -C $(@:clean-%=%) clean

phpvendors:
	$(MAKE) -C $(FOSRCDIR) phpvendors

# release stuff
tar: dist-testing
dist-testing:
	utils/fo-mktar -s

tar-release: dist
dist:
	utils/fo-mktar

empty-cache:
	$(MAKE) -C $(FOWWWDIR) empty-cache

%:
	$(MAKE) -C $(FOSRCDIR) $@

.PHONY: $(BUILDDIRS) $(DIRS) $(INSTALLDIRS) $(UNINSTALLDIRS)
.PHONY: $(TESTDIRS) $(CLEANDIRS)
.PHONY: all install install_offline uninstall clean test utils preparetest
.PHONY: dist dist-testing tar tar-release
