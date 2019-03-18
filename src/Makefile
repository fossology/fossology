# FOSSology Makefile - src dir
# Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.
# Copyright (C) 2015 Siemens AG

TOP = ..
VARS = $(TOP)/Makefile.conf
COMPOSER_PHAR ?= composer
include $(VARS)

# the directories we do things in by default
DIRS = \
	adj2nest \
	buckets \
	cli \
	copyright \
	debug \
	decider \
	deciderjob \
	delagent \
	lib \
	maintagent \
	mimetype \
	monk \
	ninka \
	nomos \
	pkgagent \
	readmeoss \
	unifiedreport \
	reuser \
	scheduler \
	spdx2 \
	reportImport \
	ununpack \
	wget_agent \
	www

# create lists of targets for various operations
# these are phony targets (declared at bottom) of convenience so we can
# run 'make $(operation)-$(subdir)'. Yet another convenience, a target of
# '$(subdir)' is equivalent to 'build-$(subdir)'
BUILDDIRS = $(DIRS:%=build-%)
INSTALLDIRS = $(DIRS:%=install-%)
UNINSTALLDIRS = $(DIRS:%=uninstall-%)
CLEANDIRS = $(DIRS:%=clean-%)
TESTDIRS = $(DIRS:%=test-%)
COVDIRS = $(DIRS:%=cov-%)

all: $(BUILDDIRS)
$(DIRS): $(BUILDDIRS)
$(BUILDDIRS):
	$(MAKE) -s -C $(@:build-%=%)

# install depends on everything being built first
install: install_offline composer_install
install_offline: all $(INSTALLDIRS)
$(INSTALLDIRS):
	$(MAKE) -s -C $(@:install-%=%) install

uninstall: $(UNINSTALLDIRS)
$(UNINSTALLDIRS):
	$(MAKE) -s -C $(@:uninstall-%=%) uninstall

# test depends on everything being built first
test: all $(TESTDIRS)
$(TESTDIRS):
	$(MAKE) -s -C $(@:test-%=%) test

coverage-report-html:
	$(call coverage-report-html,.)

coverage:
	$(MAKE) -k $(COVDIRS)
	$(MAKE) coverage-report-html
$(COVDIRS):
	$(MAKE) -s -C $(@:cov-%=%) coverage

clean: $(CLEANDIRS)
$(CLEANDIRS):
	$(MAKE) -s -C $(@:clean-%=%) clean

phpvendors:
	$(COMPOSER_PHAR) install -q

composer_install:
	@echo "current dir: '$${PWD}'"
	$(INSTALL_DATA) composer.json composer.lock $(DESTDIR)$(MODDIR)
	(cd $(DESTDIR)$(MODDIR); export COMPOSER_HOME=/dev/null; $(COMPOSER_PHAR) install --no-plugins --no-scripts --no-dev)

.PHONY: subdirs $(BUILDDIRS)
.PHONY: subdirs $(DIRS)
.PHONY: subdirs $(UNINSTALLDIRS)
.PHONY: subdirs $(TESTDIRS)
.PHONY: subdirs $(CLEANDIRS)
.PHONY: all install uninstall clean test
