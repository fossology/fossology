# FOSSology Makefile - devel dir
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
#

# the directories we do things in by default
DIRS = libfossdb libfossrepo libfossagent libfosspython

# create lists of targets for various operations
# these are phony targets (declared at bottom) of convenience so we can
# run 'make $(operation)-$(subdir)'.
BUILDDIRS = $(DIRS:%=build-%)
INSTALLDIRS = $(DIRS:%=install-%)
INSTALLEXEDIRS = $(DIRS:%=installexe-%)
INSTALLLIBDIRS = $(DIRS:%=installlib-%)
INSTALLDEVDIRS = $(DIRS:%=installdev-%)
UNINSTALLDIRS = $(DIRS:%=uninstall-%)
UNINSTALLEXEDIRS = $(DIRS:%=uninstallexe-%)
UNINSTALLLIBDIRS = $(DIRS:%=uninstalllib-%)
UNINSTALLDEVDIRS = $(DIRS:%=uninstalldev-%)
CLEANDIRS = $(DIRS:%=clean-%)
TESTDIRS = $(DIRS:%=test-%)

all: $(BUILDDIRS)
$(DIRS): $(BUILDDIRS)
$(BUILDDIRS):
	$(MAKE) -C $(@:build-%=%)

# install depends on everything being built first
install: all $(INSTALLDIRS)
$(INSTALLDIRS):
	$(MAKE) -C $(@:install-%=%) install

install-runtime: install-exe install-lib

# just install the executables
install-exe: all $(INSTALLEXEDIRS)
$(INSTALLEXEDIRS):
	$(MAKE) -C $(@:installexe-%=%) install-exe

# just install the libs
install-lib: all $(INSTALLLIBDIRS)
$(INSTALLLIBDIRS):
	$(MAKE) -C $(@:installlib-%=%) install-lib

# just install the development stuff
install-dev: all $(INSTALLDEVDIRS)
$(INSTALLDEVDIRS):
	$(MAKE) -C $(@:installdev-%=%) install-dev


uninstall: $(UNINSTALLDIRS)
$(UNINSTALLDIRS):
	$(MAKE) -C $(@:uninstall-%=%) uninstall

uninstall-runtime: uninstall-exe uninstall-lib

uninstall-exe: $(UNINSTALLEXEDIRS)
$(UNINSTALLEXEDIRS):
	$(MAKE) -C $(@:uninstallexe-%=%) uninstall-exe

uninstall-lib: $(UNINSTALLLIBDIRS)
$(UNINSTALLLIBDIRS):
	$(MAKE) -C $(@:uninstalllib-%=%) uninstall-lib

uninstall-dev: $(UNINSTALLDEVDIRS)
$(UNINSTALLDEVDIRS):
	$(MAKE) -C $(@:uninstalldev-%=%) uninstall-dev

# test depends on everything being built first
test: all $(TESTDIRS)
$(TESTDIRS):
	$(MAKE) -C $(@:test-%=%) test

coverage:
	@echo "NOTICE: no coverage available for devel"

clean: $(CLEANDIRS)
$(CLEANDIRS):
	$(MAKE) -C $(@:clean-%=%) clean

.PHONY: subdirs $(BUILDDIRS)
.PHONY: subdirs $(DIRS)
.PHONY: subdirs $(INSTALLDIRS)
.PHONY: subdirs $(INSTALLEXEDIRS)
.PHONY: subdirs $(INSTALLLIBDIRS)
.PHONY: subdirs $(INSTALLDEVDIRS)
.PHONY: subdirs $(UNINSTALLDIRS)
.PHONY: subdirs $(UNINSTALLEXEDIRS)
.PHONY: subdirs $(UNINSTALLLIBDIRS)
.PHONY: subdirs $(UNINSTALLDEVDIRS)
.PHONY: subdirs $(TESTDIRS)
.PHONY: subdirs $(CLEANDIRS)
.PHONY: all clean test
.PHONY: install install-exe install-lib install-dev install-runtime
.PHONY: uninstall uninstall-exe uninstall-lib uninstall-dev uninstall-runtime
