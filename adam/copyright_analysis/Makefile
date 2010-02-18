# pull in all our default variables
include Makefile.conf

all: model.dat

model.dat: training_files
	./copyright.py --model model.dat --training training_files
	
training_files:
	find data -type f > training_files

install: all
	$(INSTALL_PROGRAM) copyright.py $(DESTDIR)$(AGENTDIR)/copyright
	$(INSTALL_PROGRAM) copyright_library.py $(DESTDIR)$(AGENTDIR)/copyright_library.py
	$(INSTALL_PROGRAM) model.dat $(DESTDIR)$(AGENTDIR)/model.dat
	$(INSTALL_PROGRAM) libfosspython.so $(DESTDIR)$(AGENTDIR)/libfosspython.so
	mkdir -p $(DESTDIR)$(WEBDIR)/plugins/
	$(INSTALL_DATA) agent-copyright.php $(DESTDIR)$(WEBDIR)/plugins/agent-copyright.php
	$(INSTALL_DATA) agent-copyright-once.php $(DESTDIR)$(WEBDIR)/plugins/agent-copyright-once.php
	$(INSTALL_DATA) ui-copyright-analysis.php $(DESTDIR)$(WEBDIR)/plugins/ui-copyright-analysis.php
	$(INSTALL_DATA) ui-view-copyright.php $(DESTDIR)$(WEBDIR)/plugins/ui-view-copyright.php
	

uninstall: 
	rm -rf $(DESTDIR)$(AGENTDIR)/copyright
	rm -rf $(DESTDIR)$(AGENTDIR)/copyright_library.py
	rm -rf $(DESTDIR)$(AGENTDIR)/model.dat
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/agent-copyright.php
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/agent-copyright-once.php
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/ui-copyright-analysis.php
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/ui-view-copyright.php
