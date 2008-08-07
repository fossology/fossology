# FOSSology Makefile - common/
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CONFFILES=Depth.conf RepPath.conf Hosts.conf Proxy.conf
CONFPATH=$(SYSCONFDIR)/fossology

all: fo-postinstall

# include the preprocessing stuff
include $(TOP)/Makefile.process
# generate the postinstall script
fo-postinstall: fo-postinstall-process
	chmod +x fo-postinstall


install: all
	$(INSTALL_PROGRAM) fo-postinstall $(DESTDIR)$(LIBEXECDIR)/fo-postinstall

	@ echo "Installing configuration files..."
	if [ ! -e $(DESTDIR)$(CONFPATH) ] ; then \
		mkdir -p $(DESTDIR)$(CONFPATH); \
	fi

	@for file in $(CONFFILES); do \
		if [ ! -f $(DESTDIR)$(CONFPATH)/$$file -o "$(OVERWRITE)" ] ; then \
			echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/$$file"; \
		 	$(INSTALL_DATA) defconf/$$file $(DESTDIR)$(CONFPATH)/$$file; \
		else \
			echo "WARNING: $(DESTDIR)$(CONFPATH)/$$file already exists."; \
			echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
		fi \
	done


uninstall:
	rm -f $(DESTDIR)$(LIBEXECDIR)/fo-postinstall

	@if [ -d $(DESTDIR)$(LIBEXECDIR) ]; then \
		if [ "`ls -A $(DESTDIR)$(LIBEXECDIR)`" ]; then \
			echo "WARNING: $(DESTDIR)$(LIBEXECDIR) not empty, not removing";\
		else \
			rmdir $(DESTDIR)$(LIBEXECDIR); \
		fi; \
	fi

	@echo "WARNING: Cowardly refusing to remove the following"
	@echo "      $(DESTDIR)$(CONFPATH)/Depth.conf"
	@echo "      $(DESTDIR)$(CONFPATH)/Hosts.conf"
	@echo "      $(DESTDIR)$(CONFPATH)/Proxy.conf"
	@echo "      $(DESTDIR)$(CONFPATH)/RepPath.conf"
	@echo "  Remove by hand if you desire."


clean:
	rm -f fo-postinstall

test: all
	@echo "NOTICE: no tests available for common"

.PHONY: all install uninstall clean test
