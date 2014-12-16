# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = monk monkbulk

DIRS = agent ui
TESTDIR = agent_tests

DIR_LOOP = @set -e; for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done

all: VERSIONFILE
	$(call DIR_LOOP, )

test: all
	$(MAKE) -C $(TESTDIR) test

coverage: all
	$(MAKE) -C $(TESTDIR) coverage

VERSIONFILE: $(MOD_NAME:%=VERSIONFILE-%)

$(MOD_NAME:%=VERSIONFILE-%): NAME = $(@:VERSIONFILE-%=%)
$(MOD_NAME:%=VERSIONFILE-%): VERSIONFILE = $(@:VERSIONFILE-%=VERSION-%)
$(MOD_NAME:%=VERSIONFILE-%):
	$(call WriteVERSIONFile,$(NAME))
	mv VERSION $(VERSIONFILE)

install: all $(MOD_NAME:%=install_%)

$(MOD_NAME:%=install_%): NAME = $(@:install_%=%)
$(MOD_NAME:%=install_%):
	$(call DIR_LOOP,install)
	$(INSTALL_DATA) VERSION-$(NAME) $(DESTDIR)$(MODDIR)/$(NAME)/VERSION
	$(INSTALL_DATA) $(NAME).conf $(DESTDIR)$(MODDIR)/$(NAME)/$(NAME).conf
	mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	if test ! -e $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(NAME); then \
		ln -s $(MODDIR)/$(NAME) $(DESTDIR)$(SYSCONFDIR)/mods-enabled; \
	fi

uninstall: $(MOD_NAME:%=uninstall_%)
	$(call DIR_LOOP,uninstall)

$(MOD_NAME:%=uninstall_%): NAME = $(@:uninstall_%=%)
$(MOD_NAME:%=uninstall_%): uninstall_% :
	rm -rf $(DESTDIR)$(MODDIR)/$(NAME)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(NAME)

clean:
	$(call DIR_LOOP,clean)
	rm -f $(MOD_NAME:%=VERSION-%)

.PHONY: all test coverage $(MOD_NAME:%=VERSION-%) install uninstall clean
