#include

#include "lid1.h"
#include "lid2.h"
#include "lid3.h"
#include "lid4.h"
#include "lid5.h"
#include "lid6.h"
#include "lid8.h"
#include "lid9.h"
#include "lid10.h"

#define NUM_LIDS 9

GLint lidLists[NUM_LIDS];

void initLids(){
	GLint test;
	int i;
	
	GLint[0]=Gen3DObjectListLid1();
	GLint[1]=Gen3DObjectListLid2();
	GLint[2]=Gen3DObjectListLid3();
	GLint[3]=Gen3DObjectListLid4();
	GLint[4]=Gen3DObjectListLid5();
	GLint[5]=Gen3DObjectListLid6();
	GLint[6]=Gen3DObjectListLid8();
	GLint[7]=Gen3DObjectListLid9();
	GLint[8]=Gen3DObjectListLid10();

};

void drawLids(int left, right) {
	//draw left
	GLfloat offset = .5;

	glPushMatrix();
	glTranslatef(offset, 0, 0);
	glCallList(lidLists[left]);
	glPopMatrix();

	//draw right
	glPushMatrix();
	glTranslatef(-offset, 0, 0);
	glScalef(-1, 1, 1);
	glCallList(lidLists[right]);
	glPopMatrix();
}