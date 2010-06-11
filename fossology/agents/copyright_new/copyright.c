#include <stdio.h>
#include <string.h>
#include <stdlib.h>

/* max bytes to scan */
#define MAXBUF 1024*1024 

/* find the beginning of line from ptext[idx] 
   But don't go more than 50 chars back.
 */
int bol(char *ptext, int idx)
{
  int maxback = 50;
  int minidx = idx - maxback;

  while (idx-- && (idx > minidx))
  {
    if (ptext[idx] == '\n') return idx;
  }
  return idx;
}


/* find the end of line from ptext[idx] */
int eol(char *ptext, int idx, int bufsize)
{
  int maxchars = 200;
  int last = idx + maxchars;

  for (; (idx < bufsize) && (idx < last); idx++)
  {
    if (ptext[idx] == '.') return idx;
  }
  return idx;
}

/* find the index of of a string match */
int stridx(char *buf, int bufidx, char *str)
{
  char *pstr;
  int   tmpidx;

  for (; buf[bufidx]; bufidx++)
  {
    pstr = str;
    tmpidx = bufidx;
    while(buf[tmpidx] && *pstr && (buf[tmpidx] == *pstr)) 
    {
      pstr++;
      tmpidx++;
    }
    if (*pstr == 0) return(bufidx);
  }
  return (0);
}

int main(int arcg, char **argv)
{
  FILE *fp;
  char buf[MAXBUF];
  int  bufsize;
  int  i,j, bufidx;
  int  beg, end;
  int  matchidx;

  /* for speed these should be turned into a suffix tree */
  char *copystrings[] = {"copyright ", "(c)", "by ", "contributed ", "&copy;", "&#169;", "&#xa9;", 0};
  //char *copystrings[] = {"by ", 0};
  char *pstr;
  char  found[1024];

  fp = fopen(argv[1], "r");
  if (!fp)
  {
    printf("error opening %s\n", argv[1]);
    perror("");
    exit(-1);
  }
  bufsize = fread(buf, sizeof(char), sizeof(buf), fp);
  buf[bufsize-1] = 0;

  /* convert all to lower case */
  for (i=0; i<bufsize; i++) buf[i] = tolower(buf[i]);

/*
i = 0;
while (copystrings[i]) printf("%d. %s\n", i++, copystrings[i]);
*/

  /* look for each copystring in file */
  for (j=0; copystrings[j]; j++)
  {
  printf("\nLooking for: %s\n", copystrings[j]);
    bufidx=0;
    while (bufidx < bufsize)
    {
      matchidx = stridx(buf, bufidx, copystrings[j]);
      if (matchidx)
      {
        beg = bol(buf, matchidx);
        end = eol(buf, matchidx, bufsize);
        memcpy(found, &buf[beg+1], end-beg);
        found[end-beg]=0;
        printf("\nFOUND: %s, beg: %d, end: %d\n", found, beg, end);
        bufidx = end+1;
      }
      else
        bufidx = bufsize;
    } 
  }

  fclose(fp);
}
