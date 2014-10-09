#include<stdio.h>
#include<stdlib.h>
#include<string.h>
#include "list.h"

NODE getnode()
{
  NODE x;
  x=(NODE)malloc(sizeof(struct node));
  if(x == NULL)
    return NULL;

  x->lics = (char*)malloc(MEM);
  if(x->lics == NULL)
     return NULL;
						  
  x->lics_fullName = (char*)malloc(5*MEM);
  if(x->lics_fullName == NULL)
     return NULL;

  return x;
}

void freenode(NODE x)
{    
   if(x->lics_fullName){
      free(x->lics_fullName);
      x->lics_fullName = NULL;
    }
   if(x->lics){
      free(x->lics);
      x->lics = NULL;
    }
 free(x);
 x = NULL;
}

NODE insert(char *license, NODE first)
{
   NODE temp;
   temp = getnode();
   if(license!=NULL);
   strcpy(temp->lics, license);
   temp->count = 1;
   temp->link = first;
   return temp;
}

void display(NODE first)
{
   NODE temp;
   temp = first;
   if(first == NULL)
   {
      printf("Nothing to print");
   }
   while(temp!=NULL)
   {
      printf("%s\t", temp->lics);
      printf("%s\t", temp->lics_fullName);
      printf("%d\t", temp->count);
      temp = temp->link;
   }
printf("\n");
}

NODE processList(NODE first)
{
    NODE start, cur, temp, prev;
    for(start=first; start; start=start->link)
    {
       prev=start;
       cur=start->link;
       while(cur!=NULL)
       {
          if(!(strcmp(start->lics,cur->lics)))
          {
              start->count+=1;
              temp=cur;
              cur=cur->link;
	      prev->link=cur;
	      freenode(temp);
	   }
	   else{	
	       prev=cur;
	       cur = cur->link;
	   }
	}
     }
return first;
}


void deleteList(NODE first)
{  
  NODE cur = first;
  NODE temp;
  while (cur != NULL) {
     temp = cur->link;
     freenode(cur);
     cur = temp;
   }
first = NULL;
}

int search(char* licenseName, NODE first)
{
  NODE cur;
  int newCount =0;
  cur = first;
  while(cur!= NULL)
  {
    if(!(strcmp(licenseName,cur->lics)))
      {
         newCount = cur->count;
	 cur->count = 0;
	 cur= cur ->link;
      }
     else{
	  cur = cur->link;
      }
   }
return newCount;
}


int traverseList(NODE first)
{  
   int count=0;
   NODE cur = first;
   while (cur != NULL) {
     count++;
     cur = cur->link;
   }
return count ;
}
