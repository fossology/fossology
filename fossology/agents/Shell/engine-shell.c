/*****************************************************************
 Engine-Shell: This is an engine for the scheduler, but it spawns
 another process for doing the work.  (Consider this to be a generic
 wrapper.)

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
 
 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*********************
 Due to the spawning costs, you don't want to call this millions of
 times.  It is best used when:
   - The actual process spawns lots of applications (e.g. ununpack)
   - There is no time to create a proper engine that handes stdin/stdout.
   - The actual process is a massive system that cannot be trivially
     converted (but can be glued in place with a script wrapper).

 ARGV[1] = Agent name assigned to this spawner.
 ARGV[2] = The command-line that needs to be executed.
 The percent signs are replaced by the spawner.
  %{%} = percent sign
  %{P} = PID of the spawner!
  %{PP} = PPID of the spawner!  (This is the pid of the scheduler.)
  %{A} = Agent name assigned to the spawner (e.g., "license")
  %{U} = Agent-unique string assigned by the scheduler.
  %{*} = data from the scheduler as sent to the spawner.
  %{#n} = if the argument is a number (${1}, ${5}, etc.) then replace with
	this argument number from the scheduler.
	NOTE: This does not take spaces or quoting into account!
	Examples:
	  abc def = 2 args  :: first arg is %{1}, second is %{2}
	  a "b c" d = 4 args
 If you forget the "%{" or "}", or the middle part is unknown, then it is
 treated as a regular character.

 If the command returns "0", then is engine marks it as a success.
 If the command returns non-0, then is engine marks it as an error.

 If the input contains field=value pairs, then they are placed in
 environment variables, prefixed with "ARG_".
 *****************************************************************/

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <signal.h>

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

#define MAXCMD	65536
char Cmd[MAXCMD];
char *Pattern;
int PatternLen=0;
char *AgentName=NULL;
int AgentNameLen=0;

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void	ShowHeartbeat	(int Sig)
{
  printf("Heartbeat\n");
  fflush(stdout);
  /* re-schedule itself */
  alarm(60);
} /* ShowHeartbeat() */

/**************************************************
 IsArgQuery(): Determine if the input matches "%{###}"
 where "###" are one or more digits.
 Returns 1=yes, 0=no
 **************************************************/
int	IsArgQuery	(char *S)
{
  int i;
  if (S[0] != '%') return(0);
  if (S[1] != '{') return(0);
  for(i=2; isdigit(S[i]); i++)
	;
  if (S[i] == '}') return(1);
  return(0);
} /* IsArgQuery() */

/**************************************************
 GetArgLen(): Given an arg from GetArg(), return strlen.
 **************************************************/
int	GetArgLen	(char *S)
{
  int i;
  if (!S) return(0);
  if (S[0]=='\0') return(0);
  for(i=0; (S[i]!='\0') && !isspace(S[i]); i++)
	;
  return(i);
} /* GetArgLen() */

/**************************************************
 GetArg(): Returns a pointer the this arg.
 Returns NULL if arg does not exist.
 **************************************************/
char *	GetArg	(int Num, char *S)
{
  int a;
  int i;

  i=0;
  a=1;
  while(isspace(S[i])) i++; /* skip initial spaces */
  if (Num == 1)
    {
    if (S[i] == '\0') return(NULL);
    return(S+i);
    }

  while(S[i] != '\0')
    {
    if (isspace(S[i]))
      {
      while(isspace(S[i])) i++; /* skip initial spaces */
      a++;
      if (a==Num)
	{
	if (S[i] == '\0') return(NULL);
	return(S+i);
	}
      }
    else i++;
    }
  if (a==Num)
	{
	if (S[i] == '\0') return(NULL);
	return(S+i);
	}
  return(NULL);
} /* GetArg() */

/**************************************************
 NumLength(): Return the string length of a number.
 **************************************************/
int	NumLength	(int Num)
{
  int Len=1;
  if (Num < 0)
    {
    Len++;
    Num = -Num;
    }
  while(Num / 10 > 0) { Len++; Num=Num/10; }
  return(Len);
} /* NumLength() */

/**************************************************
 Num2Ascii(): Like snprintf(S,"%d",Num) but faster.
 This returns the length of the string.
 **************************************************/
int	Num2Ascii	(int Num, char *S)
{
  int Len;
  int Scale=1;
  int i;
  int NegFlag=0;

  if (Num < 0)
    {
    Num = -Num;
    S[0]='-';
    S++;
    NegFlag=1;
    }

  Len = NumLength(Num);
  for(i=0; i<Len-1; i++) Scale=Scale*10;
  for(i=0; i<Len; i++)
    {
    S[i] = (Num / Scale) + '0';
    Num = Num % Scale;
    Scale = Scale / 10;
    }
  return(Len+NegFlag);
} /* Num2Ascii() */

/**************************************************
 FillCmd(): Populate the global Cmd string.
 This will be ready for spawning to cmd
 Returns: 0=OK!  1=FAILED (not enough space in the buffer)
 **************************************************/
int	FillCmd	(char *Parms)
{
  int i;
  int c;	/* index into c */
  int n;	/* used for numbers */
  int ParmsLen;

  /* idiot checking */
  if (!Pattern || !Parms) return(1);

  /* init */
  memset(Cmd,'\0',MAXCMD);
  ParmsLen=strlen(Parms);

  /* process every letter, handle macros */
  c=0;
  i=0;
  while((c<MAXCMD-1) && (Pattern[i]!='\0'))
    {
    if (!strncmp(Pattern+i,"%{%}",4))
	{ Cmd[c] = '%'; c++; i+=4; }
    else if (!strncmp(Pattern+i,"%{A}",4))  /* agent name */
	{
	if (c+AgentNameLen >= MAXCMD) return(1);
	strcat(Cmd,AgentName);
	c+=AgentNameLen;
	i+=4;
	}
    else if (!strncmp(Pattern+i,"%{*}",4))  /* all parameters */
	{
	if (c+ParmsLen >= MAXCMD) return(1);
	strcpy(Cmd+c,Parms);
	c += ParmsLen;
	i+=4;
	}
    else if (!strncmp(Pattern+i,"%{P}",4))  /* pid */
	{
	n = getpid();
	if (c+NumLength(n) >= MAXCMD) return(1);
	c+=Num2Ascii(n,Cmd+c);
	i+=4;
	}
    else if (!strncmp(Pattern+i,"%{PP}",5))  /* parent pid */
	{
	n = getppid();
	if (c+NumLength(n) >= MAXCMD) return(1);
	c+=Num2Ascii(n,Cmd+c);
	i+=5;
	}
    else if (!strncmp(Pattern+i,"%{U}",4)) /* thread unique value */
	{
	char *V;
	int Vlen;
	V = getenv("THREAD_UNIQUE");
	if (V)
	  {
	  Vlen = strlen(V);
	  if (c+Vlen >= MAXCMD) return(1);
	  strcat(Cmd,V);
	  c += Vlen;
	  }
	else
	  {
	  if (c+1 >= MAXCMD) return(1);
	  strcat(Cmd,"0");
	  c++;
	  }
	i+=4;
	}
    else if (IsArgQuery(Pattern+i))
	{
	char *Arg;
	char ArgLen;
	n = atoi(Pattern+i+2);
	Arg = GetArg(n,Parms);
	ArgLen = GetArgLen(Arg);
	if (ArgLen > 0)
	  {
	  if (i+ArgLen >= MAXCMD) return(1);
	  strncpy(Cmd+c,Arg,ArgLen);
	  c+=ArgLen;
	  }
	i+=NumLength(n)+3;
	}
    else
	{
	/* just a character... */
	Cmd[c]=Pattern[i];
	c++;
	i++;
	}
    }
  return(0);
} /* FillCmd() */

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or
 NULL at \0.
 **********************************************/
char *  GetFieldValue   (char *Sin, char *Field, int FieldMax,
                         char *Value, int ValueMax)
{
  int s,f,v;
  int GotQuote;

  memset(Field,0,FieldMax);
  memset(Value,0,ValueMax);

  while(isspace(Sin[0])) Sin++; /* skip initial spaces */
  if (Sin[0]=='\0') return(NULL);
  strcpy(Field,"ARG_");
  f=4; v=0;

  for(s=0; (Sin[s] != '\0') && !isspace(Sin[s]) && (Sin[s] != '='); s++)
    {
    Field[f++] = Sin[s];
    }
  while(isspace(Sin[s])) s++; /* skip spaces after field name */
  if (Sin[s] != '=') /* if it is not a field, then just return it. */
    {
    return(Sin+s);
    }
  if (Sin[s]=='\0') return(NULL);
  s++; /* skip '=' */
  while(isspace(Sin[s])) s++; /* skip spaces after '=' */
  if (Sin[s]=='\0') return(NULL);

  GotQuote='\0';
  if ((Sin[s]=='\'') || (Sin[s]=='"'))
    {
    GotQuote = Sin[s];
    s++; /* skip quote */
    if (Sin[s]=='\0') return(NULL);
    }
  if (GotQuote)
    {
    for( ; (Sin[s] != '\0') && (Sin[s] != GotQuote); s++)
      {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
      }
    }
  else
    {
    /* if it gets here, then there is no quote */
    for( ; (Sin[s] != '\0') && !isspace(Sin[s]); s++)
      {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
      }
    }
  while(isspace(Sin[s])) s++; /* skip spaces */
  return(Sin+s);
} /* GetFieldValue() */

/**********************************************
 SetEnv(): Convert field=value pairs into
 environment variables.
 Env = what to do: 0=unsetenv(Field), 1=setenv(Field=Value), -1=nothing
 **********************************************/
void	SetEnv	(char *S, int Env)
{
  char Field[256];
  char Value[1024];
  while(S && (S[0] != '\0'))
    {
    S = GetFieldValue(S,Field,256,Value,1024);
    if (Value[0] != '\0')
      {
      switch(Env)
        {
	case 0:	unsetenv(Field);	break;
	case 1:	setenv(Field,Value,1);	break;
	default:	break;
	}
      }
    }
} /* SetEnv() */

/**********************************************
 ReadLine(): Read a command from stdin.
 If the line is empty, then try again.
 Returns line length, or -1 of EOF.
 **********************************************/
int     ReadLine (FILE *Fin, char *Line, int MaxLine)
{
  int C;
  int i;

  memset(Line,'\0',MaxLine);
  if (feof(Fin)) return(-1);
  i=0;
  C=fgetc(Fin);
  if (C<0) return(-1);
  while(!feof(Fin) && (C>=0) && (i<MaxLine))
    {
    if (C=='\n')
	{
	if (i > 0) return(i);
	/* if it is a blank line, then ignore it. */
	}
    else
	{
	Line[i]=C;
	i++;
	}
    C=fgetc(Fin);
    }
  return(i);
} /* ReadLine() */

/*********************************************************
 Usage():
 *********************************************************/
void    Usage   (char *Name)
{
  printf("Usage:  %s agent_name command < args\n",Name);
  printf("  agent_name :: a name to call this agent.\n");
  printf("  command    :: the process to execute.\n");
  printf("Usage:  %s -i\n",Name);
  printf("  -i         :: initialize the database, then exit.\n");
} /* Usage() */


/*********************************************************************/
/*********************************************************************/
int	main	(int argc, char *argv[])
{
  int rc;
  char Parm[MAXCMD];
  int c;

  /* Process command-line */
  while((c = getopt(argc,argv,"i")) != -1)
    {
    switch(c)
        {
        case 'i':
		/* nothing to initialize */
                return(0);
        default:
                Usage(argv[0]);
                exit(-1);
        }
    }

  if (argc - optind != 2)
    {
    Usage(argv[0]);
    exit(-1);
    }

  AgentName = argv[optind];
  AgentNameLen = strlen(argv[optind]);
  Pattern = argv[optind+1];
  PatternLen = strlen(argv[optind+1]);

  /* ok, infinite loop time! */
  signal(SIGALRM,ShowHeartbeat);
  printf("OK\n"); /* inform scheduler that we are ready */
  alarm(60);
  fflush(stdout);
  while(ReadLine(stdin,Parm,MAXCMD) >= 0)
    {
    SetEnv(Parm,1); /* set environment (as appropriate) */
    if (Parm[0] != '\0')
	{
	rc = FillCmd(Parm);
	if (!rc)
	  {
	  rc = system(Cmd);
	  if (WIFSIGNALED(rc))
	    {
	    printf("ERROR: Process killed by signal (%d): %s\n",WTERMSIG(rc),Cmd);
	    exit(-1);
	    }
	  if (WIFEXITED(rc)) rc = WEXITSTATUS(rc);
	  else rc=1;
	  }
	if (rc)
		{
		printf("ERROR: Shell terminated\n");
		printf("LOG: Shell terminated: Cmd\n");
		fflush(stdout);
		exit(-1);
		}
	else printf("Success\n");
	printf("OK\n"); /* inform scheduler that we are ready */
	fflush(stdout);
	}
    SetEnv(Parm,0); /* clear environment (as appropriate) */
    }
  return(0);
} /* main() */

