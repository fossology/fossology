#ifndef __LIST_H
#define __LIST_H

#define MEM 20
#define DECLEN 256


struct node{
   char *lics;
   char *lics_fullName;
   int count;
   struct node *link;
};
typedef struct node *NODE;
//extern node *NODE;


NODE getnode(void);
void freenode(NODE );
NODE insert(char *, NODE);
void display(NODE );
NODE processList(NODE );
void deleteList(NODE );
NODE update_fullName(NODE );
int search(char*, NODE );
int traverseList(NODE);

#endif

