# pull in all our default variables
include Makefile.conf

all: model.dat

model.dat: training_files
	./copyright.py --model model.dat --training training_files
	
training_files:
	find data -type f > training_files

install: all
	$(INSTALL_PROGRAM) copyright.py $(DESTDIR)$(AGENTDIR)/copyright.py
	$(INSTALL_PROGRAM) copyright_library.py $(DESTDIR)$(AGENTDIR)/copyright_library.py
	$(INSTALL_PROGRAM) model.dat $(DESTDIR)$(AGENTDIR)/model.dat

uninstall: 
	rm -rf $(DESTDIR)$(AGENTDIR)/copyright.py
	rm -rf $(DESTDIR)$(AGENTDIR)/copyright_library.py
	rm -rf $(DESTDIR)$(AGENTDIR)/model.dat
