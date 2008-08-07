# FOSSology Makefile - common/
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

PROXY=Proxy.conf
PROXYPATH=$(SYSCONFDIR)/fossology/$(PROXY)

all: fo-postinstall

# include the preprocessing stuff
include $(TOP)/Makefile.process
# generate the postinstall script
fo-postinstall: fo-postinstall-process
	chmod +x fo-postinstall


install: all
	@ echo "Installing $(PROXY) ..."
	if [ ! -f $(DESTDIR)$(PROXYPATH) -o "$(OVERWRITE)" ] ; then \
		mkdir -p $(DESTDIR)$(dir PROXYPATH); \
		echo "NOTE: using default version for $(DESTDIR)$(PROXYPATH)"; \
		$(INSTALL_DATA) $(PROXY) $(DESTDIR)$(PROXYPATH); \
	else \
		echo "WARNING: $(DESTDIR)$(PROXYPATH) already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi

	$(INSTALL_PROGRAM) fo-postinstall $(DESTDIR)$(LIBEXECDIR)/fo-postinstall


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
	@echo "      $(DESTDIR)$(SYSCONFDIR)/fossology/Proxy.conf"
	@echo "  Remove by hand if you desire."


clean:
	rm -f fo-postinstall

test: all
	@echo "NOTICE: no tests available for common"

.PHONY: all install uninstall clean test
