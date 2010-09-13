EXE=cunitFrameworkStart
all: $(EXE)
$(EXE): ./cunitFrameworkStartMain.c ./cunitFrameworkStart.c
	gcc -o $@ -lxml2 -I/usr/include/libxml2 ./cunitFrameworkStart.c ./cunitFrameworkStartMain.c -g

clean:
	rm  -fr $(EXE) *.xml
