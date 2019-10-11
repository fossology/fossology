# Copyright 2019
# Author: Vivek Kumar<vvksindia@gmail.com>
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.


TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAMES = spasht
MOD_SUBDIR = ui

all:
	@echo "nothing to do"

install:
	@for MOD_NAME in $(MOD_NAMES); do \
		echo "make $$MOD_NAME ui $@"; \
		mkdir -p $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
		$(INSTALL_DATA) ./*-$$MOD_NAME.php $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
	done
	#TODO deduplicate php files
	#TODO this is broken but currently works
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/spasht/$(MOD_SUBDIR)
	find template/ -type f -exec $(INSTALL_DATA) {} $(DESTDIR)$(MODDIR)/spasht/$(MOD_SUBDIR)/{} \;

uninstall:
	@for MOD_NAME in $(MOD_NAMES); do \
		echo "make $$MOD_NAME ui $@"; \
		rm -rf $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
	done

clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
