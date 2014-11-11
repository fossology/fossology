# FOSSology Makefile - src dir
# Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.
#

TOP = ..
VARS = $(TOP)/Makefile.conf
include $(VARS)

# the directories we do things in by default
DIRS = \
	adj2nest \
	buckets \
	cli \
	copyright \
	debug \
	decider \
	delagent \
	lib \
	maintagent \
	mimetype \
	monk \
	ninka \
	nomos \
	pkgagent \
	reportgen \
	scheduler \
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
install: all $(INSTALLDIRS) composer_install
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

coverage: $(COVDIRS)
	$(MAKE) coverage-report-html
$(COVDIRS):
	$(MAKE) -s -C $(@:cov-%=%) coverage

clean: $(CLEANDIRS)
$(CLEANDIRS):
	$(MAKE) -s -C $(@:clean-%=%) clean

phpvendors:
	composer install

composer_install:
	@echo "current dir: '$${PWD}'"
	$(INSTALL_DATA) composer.json composer.lock $(DESTDIR)$(MODDIR)
	(cd $(DESTDIR)$(MODDIR); composer install --no-dev)

.PHONY: subdirs $(BUILDDIRS)
.PHONY: subdirs $(DIRS)
.PHONY: subdirs $(UNINSTALLDIRS)
.PHONY: subdirs $(TESTDIRS)
.PHONY: subdirs $(CLEANDIRS)
.PHONY: all install uninstall clean test
