# FOSSology Makefile - agents/copyright_analysis/ui-plugin
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

install:
	mkdir -p $(DESTDIR)$(WEBDIR)/plugins/
	mkdir -p $(DESTDIR)$(WEBDIR)/plugins/copyright/
	$(INSTALL_DATA) copyright.php $(DESTDIR)$(WEBDIR)/plugins/copyright.php
	$(INSTALL_DATA) library.php $(DESTDIR)$(WEBDIR)/plugins/copyright/library.php
	$(INSTALL_DATA) hist.php $(DESTDIR)$(WEBDIR)/plugins/copyright/hist.php
	$(INSTALL_DATA) list.php $(DESTDIR)$(WEBDIR)/plugins/copyright/list.php
	$(INSTALL_DATA) view.php $(DESTDIR)$(WEBDIR)/plugins/copyright/view.php
	$(INSTALL_DATA) agent.php $(DESTDIR)$(WEBDIR)/plugins/copyright/agent.php
	$(INSTALL_DATA) oneshot.php $(DESTDIR)$(WEBDIR)/plugins/copyright/oneshot.php
	
uninstall:
	rm -f $(DESTDIR)$(WEBDIR)/plugins/copyright.php
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/copyright/
	@if [ -d $(DESTDIR)$(WEBDIR)/plugins ]; then \
   	   if [ "`ls -A $(DESTDIR)$(WEBDIR)/plugins`" ]; then \
	      echo "WARNING: $(DESTDIR)$(WEBDIR)/plugins not empty, not removing";\
           else \
              rmdir $(DESTDIR)$(WEBDIR)/plugins; \
           fi; \
        fi
    
.PHONY: install uninstall
