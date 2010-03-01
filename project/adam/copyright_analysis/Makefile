# pull in all our default variables
include Makefile.conf

all: model.dat copyright

copyright:
	cat copyright.py > copyright
	echo -n "    sys.path.append('" >> copyright
	echo -n $(DESTDIR)$(LIBDIR) >> copyright
	echo -n "/libfosspython/')\n    import libfosspython\n    main()\n" >> copyright
	chmod a+x copyright

model.dat: copyright training_files
	./copyright --model model.dat --training training_files
	
training_files:
	find data -type f > training_files

install: all
	./copyright --setup
	$(INSTALL_PROGRAM) copyright $(DESTDIR)$(AGENTDIR)/copyright
	$(INSTALL_PROGRAM) copyright_library.py $(DESTDIR)$(LIBDIR)/copyright_library.py
	$(INSTALL_PROGRAM) model.dat $(DATADIR)/model.dat
	mkdir -p $(DESTDIR)$(WEBDIR)/plugins/
	$(INSTALL_DATA) agent-copyright.php $(DESTDIR)$(WEBDIR)/plugins/agent-copyright.php
	$(INSTALL_DATA) agent-copyright-once.php $(DESTDIR)$(WEBDIR)/plugins/agent-copyright-once.php
	$(INSTALL_DATA) ui-copyright-analysis.php $(DESTDIR)$(WEBDIR)/plugins/ui-copyright-analysis.php
	$(INSTALL_DATA) ui-view-copyright.php $(DESTDIR)$(WEBDIR)/plugins/ui-view-copyright.php
	

uninstall: 
	rm -rf $(DESTDIR)$(AGENTDIR)/copyright
	rm -rf $(DESTDIR)$(LIBDIR)/copyright_library.py
	rm -rf $(DATADIR)/model.dat
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/agent-copyright.php
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/agent-copyright-once.php
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/ui-copyright-analysis.php
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/ui-view-copyright.php
	rmdir $(DESTDIR)$(WEBDIR)/plugins/

clean:
	rm copyright model.dat training_files
