# FOSSology Makefile - agents/copyright_analysis/ui-plugin
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

install:
	mkdir -p $(DESTDIR)$(WEBDIR)/plugins/
	mkdir -p $(DESTDIR)$(WEBDIR)/plugins/copyright/
	$(INSTALL_PROGRAM) copyright.php $(DESTDIR)$(WEBDIR)/plugins/copyright.php
	$(INSTALL_PROGRAM) library.php $(DESTDIR)$(WEBDIR)/plugins/copyright/library.php
	$(INSTALL_PROGRAM) hist.php $(DESTDIR)$(WEBDIR)/plugins/copyright/hist.php
	$(INSTALL_PROGRAM) list.php $(DESTDIR)$(WEBDIR)/plugins/copyright/list.php
	$(INSTALL_PROGRAM) view.php $(DESTDIR)$(WEBDIR)/plugins/copyright/view.php
	$(INSTALL_PROGRAM) agent.php $(DESTDIR)$(WEBDIR)/plugins/copyright/agent.php
	$(INSTALL_PROGRAM) oneshot.php $(DESTDIR)$(WEBDIR)/plugins/copyright/oneshot.php
	
uninstall:
	rmdir $(DESTDIR)$(WEBDIR)/plugins/
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/copyright/
    
.PHONY: install uninstall
