/***************************************************************
 Copyright (C) 2006-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

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

 ***************************************************************/
#include "doctorBuffer_utils.h"
#define INVISIBLE       (int) '\377'

#include "nomos.h"
#include "list.h"
#include "util.h"
#include "nomos_regex.h"

int compressDoctoredBuffer( char* textBuffer)
{
  /*
   * garbage collect: eliminate all INVISIBLE characters in the buffer
   */

  int previous=strlen(textBuffer);

  if(cur.docBufferPositionsAndOffsets)
    g_array_free(cur.docBufferPositionsAndOffsets, TRUE);
  cur.docBufferPositionsAndOffsets=collapseInvisible(textBuffer,INVISIBLE);
//  cur.docBufferPositionsAndOffsets=collapseSpaces(textBuffer);

  int after = strlen(textBuffer);

  return previous - after;
}

void removeHtmlComments(char* buf)
{
  int f;
  int g;
  char* cp;
  f = 0;
  g = 0;
  for (cp = buf; cp && *cp; cp++)
  {
    if ((*cp == '<') && (*(cp + 1) != '<') && (*(cp + 1) != ' '))
    {
#if     (DEBUG>5) && defined(DOCTOR_DEBUG)
      int x = strncasecmp(cp, "<string", 7);
      printf("CHECK: %c%c%c%c%c%c%c == %d\n", *cp,
          *(cp+1), *(cp+2), *(cp+3), *(cp+4),
          *(cp+5), *(cp+6), x);
#endif  /* DEBUG>5 && DOCTOR_DEBUG */
      if (strncasecmp(cp, "<string", 7))
      {
        *cp = ' ';
        if (*(cp + 1) != '-' || *(cp + 2) != '-')
        {
          f = 1;
        }
      }
    }
    else if (*cp == '&')
    {
#if     (DEBUG>5) && defined(DOCTOR_DEBUG)
      int x = strncasecmp(cp, "&copy;", 6);
      printf("CHECK: %c%c%c%c%c%c == %d\n", *cp,
          *(cp+1), *(cp+2), *(cp+3), *(cp+4),
          *(cp+5), x);
#endif  /* DEBUG>5 && DOCTOR_DEBUG */
      if (strncasecmp(cp, "&copy;", 6))
      {
        *cp = ' ';
        g = 1;
      }
    }
    else if (f && (*cp == '>'))
    {
      *cp = ' ';
      f = 0;
    }
    else if (g && (*cp == ';'))
    {
      *cp = ' ';
      g = 0;
    }
    else if (isEOL(*cp))
    {
      g = 0;
    }
    /* Don't remove text in an HTML comment (e.g., turn the flag off) */
    else if ((*cp == '!') && f && (cp != buf) && (*(cp - 1) == ' '))
    {
      *cp = ' ';
      f = 0;
    }
    else if (f || g)
    {
      //  *cp = INVISIBLE;   larry comment out this line, I do not think this logic is correct
    }
    else if ((*cp == '<') || (*cp == '>'))
    {
      *cp = ' ';
    }
  }
}

void removeLineComments(char* buf)
{
  /*
   * step 2: remove comments that start at the beginning of a line, * like
   * ^dnl, ^xcomm, ^comment, and //
   */
  char* cp;
  char* MODULE_LICENSE = "MODULE_LICENSE";
  cp = buf;
  while (idxGrep(_UTIL_BOL_MAGIC, cp, REG_ICASE | REG_NEWLINE | REG_EXTENDED))
  {
#ifdef  DOCTOR_DEBUG
    dumpMatch(cp, "Found \"comment\"-text");
#endif  /* DOCTOR_DEBUG */
    cp += cur.regm.rm_so;
    switch (*cp)
    {
    case '>':
      *cp++ = ' ';
      break;
    case '@': /* texi special processing */
      *cp++ = INVISIBLE;
      if (strncasecmp(cp, "author", 6) == 0)
      {
        (void) memset(cp, ' ', 6);
        cp += 6;
      }
      else if (strncasecmp(cp, "comment", 7) == 0)
      {
        (void) memset(cp, ' ', 7);
        cp += 7;
      }
      else if (strncasecmp(cp, "center", 6) == 0)
      {
        (void) memset(cp, ' ', 6);
        cp += 6;
      }
      else if (strncasecmp(cp, "rem", 3) == 0)
      {
        (void) memset(cp, ' ', 3);
        cp += 3;
      }
      else if (*cp == 'c')
      {
        *cp++ = INVISIBLE;
        if (strncasecmp(cp, " essay", 6) == 0)
        {
          (void) memset(cp, ' ', 6);
          cp += 6;
        }
      }
      break;
    case '/': /* c++ style comment // */
      if (cp && cp[0])
      {
        /** when MODULE_LICENSE("GPL") is outcommented, do not get rid of this line. */
        if (strstr(cp, MODULE_LICENSE) && '/' == cp[0])
        {
          (void) memset(cp, INVISIBLE, strlen(cp));
          cp += strlen(cp);
        }
        else
        {
          (void) memset(cp, INVISIBLE, 2);
          cp += 2;
        }
      }
      break;
    case '\\': /* c++ style comment // */
      if (strncasecmp(cp + 1, "par ", 3) == 0)
      {
        (void) memset(cp, ' ', 4);
      }
      cp += 4;
      break;
    case 'r':
    case 'R': /* rem */
    case 'd':
    case 'D': /* dnl */
      (void) memset(cp, INVISIBLE, 3);
      cp += 3;
      break;
    case 'x':
    case 'X': /* xcomm */
      (void) memset(cp, INVISIBLE, 5);
      cp += 5;
      break;
    case 'c':
    case 'C': /* comment */
      (void) memset(cp, INVISIBLE, 7);
      cp += 7;
      break;
    case '%': /* %%copyright: */
      (void) memset(cp, INVISIBLE, 12);
      cp += 12;
      break;
    }
  }
}

void cleanUpPostscript(char* buf)
{
  char* cp;
  char* x;
  cp = buf;
  while (idxGrep(_UTIL_POSTSCR, cp, REG_EXTENDED | REG_NEWLINE))
  {
#ifdef  DOCTOR_DEBUG
    dumpMatch(cp, "FOUND postscript-thingy");
#endif  /* DOCTOR_DEBUG */
    x = cp + cur.regm.rm_so;
    cp += cur.regm.rm_eo;
    while (x < cp)
    {
      *x++ = ' '/*INVISIBLE*/;
    }
  }
}

void removeBackslashesAndGTroffIndicators(char* buf)
{
  /*
   *      - step 4: remove groff/troff font-size indicators, the literal
   *              string backslash-n and all backslahes, ala:
   *==>   perl -pe 's,\\s[+-][0-9]*,,g;s,\\s[0-9]*,,g;s/\\n//g;' |
   f*/
  char* cp;
  char* x;
  for (cp = buf; *cp; cp++)
  {
    if (*cp == '\\')
    {
      x = cp + 1;
      if (*x && (*x == 's'))
      {
        x++;
        if (*x && ((*x == '+') || (*x == '-')))
        {
          x++;
        }
        while (*x && isdigit(*x))
        {
          x++;
        }
      }
      else if (*x && *x == 'n')
      {
        x++;
      }
      memset(cp, /*INVISIBLE*/' ', (size_t) (x - cp));
    }
  }
}

void convertWhitespaceToSpaceAndRemoveSpecialChars(char* buf, int isCR )
{
  /*
   *      - step 5: convert white-space to real spaces, and remove
   *              unnecessary punctuation, ala:
   *==>   tr -d '*=+#$|%.,:;!?()\\][\140\047\042' | tr '\011\012\015' '   '
   *****
   * NOTE: we purposely do NOT process backspace-characters here.  Perhaps
   * there's an improvement in the wings for this?
   */
  char* cp;
  for (cp = buf; /*cp < end &&*/*cp; cp++)
  {
    if ((*cp == '\302') && (*(cp + 1) == '\251'))
    {
      cp += 2;
      continue;
    }
    if (*cp & (char) 0x80)
    {
      *cp = INVISIBLE;
      continue;
    }
    switch (*cp)
    {
    /*
     Convert eol-characters AND some other miscellaneous
     characters into spaces (due to comment-styles, etc.)
     */
    case '\a':
    case '\t':
    case '\n':
    case '\r':
    case '\v':
    case '\f':
    case '[':
    case ']':
    case '{':
    case '}':
    case '*':
    case '=':
    case '#':
    case '$':
    case '|':
    case '%':
    case '!':
    case '?':
    case '`':
    case '"':
    case '\'':
      *cp = ' ';
      break;
      /* allow + only within the regex " [Mm]\+ " */
    case '+':
      if (*(cp + 1) == 0 || *(cp + 1) == ' ' || *(cp + 1) == '\t' || *(cp + 1) == '\n' || *(cp + 1) == '\r')
        break;
      else if (cp > buf + 1 && (*(cp - 1) == 'M' || *(cp - 1) == 'm') && *(cp - 2) == ' ' && *(cp + 1) == ' ')
      {
        /* no-op */
      }
      else
      {
        *cp = ' ';
      }
      break;
    case '(':
      if ((*(cp + 1) == 'C' || *(cp + 1) == 'c') && *(cp + 2) == ')')
      {
        cp += 2;
        continue;
      }
      else
      {
        *cp = ' ';
      }
      break;
    case ')':
    case ',':
    case ':':
    case ';':
      if (!isCR)
      {
        *cp = ' ';
      }
      break;
    case '.':
      if (!isCR)
      {
        *cp = INVISIBLE;
      }
      break;
    case '<':
      if (strncasecmp(cp, "<string", 7) == 0)
      {
        (void) strncpy(cp, "          ", 7);
      }
      break;
      /* CDB - Big #ifdef 0 left out */
    case '\001':
    case '\002':
    case '\003':
    case '\004':
    case '\005':
    case '\006':
    case '\016':
    case '\017':
    case '\020':
    case '\021':
    case '\022':
    case '\023':
    case '\024':
    case '\025':
    case '\026':
    case '\027':
    case '\030':
    case '\031':
    case '\032':
    case '\033':
    case '\034':
    case '\035':
    case '\036':
    case '\037':
    case '~':
      *cp = INVISIBLE;
      break;
#ifdef  DOCTOR_DEBUG
      case ' ': case '/': case '-': case '@': case '&':
      case '>': case '^': case '_':
      case INVISIBLE:
      break;
      default:
      if (!isalpha(*cp) && !isdigit(*cp))
      {
        printf("DEBUG: \\0%o @ %ld\n",
            *cp & 0xff, cp-buf);
      }
      break;
#endif  /* DOCTOR_DEBUG */
    }
  }
}


void dehyphen(char* buf)
{
  /*
   * Look for hyphenations of words, to compress both halves into a sin-
   * gle (sic) word.  Regex == "[a-z]- [a-z]".
   *****
   * NOTE: not sure this will work based on the way we strip punctuation
   * out of the buffer above -- work on this later.
   */
  char* cp;

  for (cp = buf; idxGrep(_UTIL_HYPHEN, cp, REG_ICASE); /*nada*/)
  {
#ifdef  DOCTOR_DEBUG
    char* x;
    x = cp + cur.regm.rm_so;
    while ((x > cp) && !isspace(*x))
    {
      x--;
    }
    printf("Hey! hyphenated-word [");
    for (++x; x <= (cp + cur.regm.rm_eo); x++)
    {
      printf("%c", *x);
    }
    while (!isspace(*x))
    {
      printf("%c", *x++);
    }
    printf("]\n");

#endif  /* DOCTOR_DEBUG */
    cp += cur.regm.rm_so + 1;
    *cp++ = INVISIBLE;
    while (isspace(*cp))
    {
      *cp++ = INVISIBLE;
    }
  }

}

void removePunctuation(char* buf)
{
  /*
   *      - step 6: clean up miscellaneous punctuation, ala:
   *==>           perl -pe 's,[-_/]+ , ,g;s/print[_a-zA-Z]* //g;s/  / /g;'
   */
  char* cp;
  char* x;
  for (cp = buf; idxGrep(_UTIL_MISCPUNCT, cp, REG_EXTENDED); /*nada*/)
  {
    x = cp + cur.regm.rm_so;
    cp += cur.regm.rm_eo - 1; /* leave ' ' alone */
    while (x < cp)
    {
      *x++ = ' ';
    }
    cp++;
  }
  for (cp = buf; idxGrep(_UTIL_LATEX, cp, REG_ICASE); /*nada*/)
  {
    x = cp + cur.regm.rm_so;
    cp += cur.regm.rm_eo;
    while (x <= cp)
    {
      *x++ = ' ';
    }
    cp++;
  }
}

void ignoreFunctionCalls(char* buf)
{
  /*
   * Ignore function calls to print routines: only concentrate on what's being
   * printed (sometimes programs do print licensing information) -- but don't
   * ignore real words that END in 'print', like footprint and fingerprint.
   * Here, we take a risk and just look for a 't' (in "footprint"), or for an
   * 'r' (in "fingerprint").  If someone has ever coded a print routine that
   * is named 'rprint' or tprint', we're spoofed.
   */
  char* cp;
  char* x;
  for (cp = buf; idxGrep(_UTIL_PRINT, cp, REG_ICASE); /*nada*/)
  {
    x = cp + cur.regm.rm_so;
    cp += (cur.regm.rm_eo - 1);
    if ((x > buf) && ((*(x - 1) == 'r') || (*(x - 1) == 't')))
    {
      continue;
    }
    while (x < cp)
    {
      *x++ = ' ';
    }
    cp++;
  }
}

void convertSpaceToInvisible(char* buf)
{
  /*
   * Convert the regex ' [X ]+' (where X is really the character #defined as
   * INVISIBLE) to a single space (and a string of INVISIBLE characters).
   */
  char* cp;
  for (cp = buf; *cp; /*nada*/)
  {
    if (*cp++ == ' ')
    {
      while (*cp)
      {
        if (*cp == ' ')
        {
          *cp++ = INVISIBLE;
        }
        else if (*cp == INVISIBLE)
        {
          cp++;
        }
        else
        {
          break;
        }
      }
    }
  }
}

void doctorBuffer(char *buf, int isML, int isPS, int isCR)
{

//  printf("\n ==============doctorBuffer is called============================== \n");

 // char *cp;
 // char *x;
#if     defined(PROC_TRACE) || defined(DOCTOR_DEBUG)
  traceFunc("== doctorBuffer(%p, %d, %d, %d)\n", buf, isML, isPS, isCR);
#endif  /* PROC_TRACE || DOCTOR_DEBUG */

  /*
   * convert a buffer of multiple *stuff* to text-only, separated by spaces
   * We really only care about text "in a license" here, so strip out
   * comments and other unwanted punctuation.
   */
#ifdef  DOCTOR_DEBUG
  printf("***** Processing %p (%d data bytes)\n", buf, (int)strlen(buf));
  printf("----- [Dr-BEFORE:] -----\n%s\n[==END==]\n", buf);
#endif  /* DOCTOR_DEBUG */
  /*
   * step 1: take care of embedded HTML/XML and special HTML-chars like
   * &quot; and &nbsp; -- but DON'T remove the text in an HTML comment.
   * There might be licensing text/information in the comment!
   *****
   * Later on (in parseLicenses()) we search for URLs in the raw-text
   */
  if (isML)
  {
#ifdef  DOCTOR_DEBUG
    printf("DEBUG: markup-languange directives found!\n");
#endif  /* DOCTOR_DEBUG */
    removeHtmlComments(buf);
  }
  /*
   * step 2: remove comments that start at the beginning of a line, * like
   * ^dnl, ^xcomm, ^comment, and //
   */
   removeLineComments(buf);
  /*
   * Step 3 - strip out crap at end-of-line on postscript documents
   */

  if (isPS)
  {
    cleanUpPostscript(buf);
#ifdef  DOCTOR_DEBUG
    printf("DEBUG: postscript stuff detected!\n");
#endif  /* DOCTOR_DEBUG */
  }
  /*
   *      - step 4: remove groff/troff font-size indicators, the literal
   *              string backslash-n and all backslahes, ala:
   *==>   perl -pe 's,\\s[+-][0-9]*,,g;s,\\s[0-9]*,,g;s/\\n//g;' |
   f*/
   removeBackslashesAndGTroffIndicators(buf);
  /*
   *      - step 5: convert white-space to real spaces, and remove
   *              unnecessary punctuation, ala:
   *==>   tr -d '*=+#$|%.,:;!?()\\][\140\047\042' | tr '\011\012\015' '   '
   *****
   * NOTE: we purposely do NOT process backspace-characters here.  Perhaps
   * there's an improvement in the wings for this?
   */
 convertWhitespaceToSpaceAndRemoveSpecialChars(buf, isCR);
  /*
   * Look for hyphenations of words, to compress both halves into a sin-
   * gle (sic) word.  Regex == "[a-z]- [a-z]".
   *****
   * NOTE: not sure this will work based on the way we strip punctuation
   * out of the buffer above -- work on this later.
   */
   dehyphen(buf);
  /*
   *      - step 6: clean up miscellaneous punctuation, ala:
   *==>           perl -pe 's,[-_/]+ , ,g;s/print[_a-zA-Z]* //g;s/  / /g;'
   */
   removePunctuation(buf);
  /*
   * Ignore function calls to print routines: only concentrate on what's being
   * printed (sometimes programs do print licensing information) -- but don't
   * ignore real words that END in 'print', like footprint and fingerprint.
   * Here, we take a risk and just look for a 't' (in "footprint"), or for an
   * 'r' (in "fingerprint").  If someone has ever coded a print routine that
   * is named 'rprint' or tprint', we're spoofed.
   */
   ignoreFunctionCalls(buf);
  /*
   * Convert the regex ' [X ]+' (where X is really the character #defined as
   * INVISIBLE) to a single space (and a string of INVISIBLE characters).
   */
  convertSpaceToInvisible(buf);
  /*
   * garbage collect: eliminate all INVISIBLE characters in the buffer
   */
#ifdef  DOCTOR_DEBUG
  int n =
#else
      (void)
#endif
      compressDoctoredBuffer(buf);

#ifdef  DOCTOR_DEBUG
  printf("***** Now buffer %p contains %d bytes (%d clipped)\n", buf,
      (int)strlen(buf), n);
  printf("+++++ [Dr-AFTER] +++++:\n%s\n[==END==]\n", buf);
#endif  /* DOCTOR_DEBUG */
  return;
}

#ifdef DOCTORBUFFER_OLD
void doctorBuffer_old(char *buf, int isML, int isPS, int isCR)
{
  printf("Doctor Buffer old \n");
  char *cp;
  char *x;
  int f;
  int g;
  int n;
  char *MODULE_LICENSE = "MODULE_LICENSE";

#if     defined(PROC_TRACE) || defined(DOCTOR_DEBUG)
  traceFunc("== doctorBuffer(%p, %d, %d, %d)\n", buf, isML, isPS, isCR);
#endif  /* PROC_TRACE || DOCTOR_DEBUG */

  /*
   * convert a buffer of multiple *stuff* to text-only, separated by spaces
   * We really only care about text "in a license" here, so strip out
   * comments and other unwanted punctuation.
   */
#ifdef  DOCTOR_DEBUG
  printf("***** Processing %p (%d data bytes)\n", buf, (int)strlen(buf));
  printf("----- [Dr-BEFORE:] -----\n%s\n[==END==]\n", buf);
#endif  /* DOCTOR_DEBUG */
  /*
   * step 1: take care of embedded HTML/XML and special HTML-chars like
   * &quot; and &nbsp; -- but DON'T remove the text in an HTML comment.
   * There might be licensing text/information in the comment!
   *****
   * Later on (in parseLicenses()) we search for URLs in the raw-text
   */
  if (isML) {
#ifdef  DOCTOR_DEBUG
    printf("DEBUG: markup-languange directives found!\n");
#endif  /* DOCTOR_DEBUG */
    f = 0;
    g = 0;
    for (cp = buf; cp && *cp; cp++) {
      if ((*cp == '<') &&
          (*(cp+1) != '<') &&
          (*(cp+1) != ' ')) {
#if     (DEBUG>5) && defined(DOCTOR_DEBUG)
        int x = strncasecmp(cp, "<string", 7);
        printf("CHECK: %c%c%c%c%c%c%c == %d\n", *cp,
            *(cp+1), *(cp+2), *(cp+3), *(cp+4),
            *(cp+5), *(cp+6), x);
#endif  /* DEBUG>5 && DOCTOR_DEBUG */
        if (strncasecmp(cp, "<string", 7)) {
          *cp = ' ';
          if (*(cp+1) != '-' || *(cp+2) != '-') {
            f = 1;
          }
        }
      } else if (*cp == '&') {
#if     (DEBUG>5) && defined(DOCTOR_DEBUG)
        int x = strncasecmp(cp, "&copy;", 6);
        printf("CHECK: %c%c%c%c%c%c == %d\n", *cp,
            *(cp+1), *(cp+2), *(cp+3), *(cp+4),
            *(cp+5), x);
#endif  /* DEBUG>5 && DOCTOR_DEBUG */
        if (strncasecmp(cp, "&copy;", 6)) {
          *cp = ' ';
          g = 1;
        }
      } else if (f && (*cp == '>')) {
        *cp = ' ';
        f = 0;
      } else if (g && (*cp == ';')) {
        *cp = ' ';
        g = 0;
      } else if (isEOL(*cp)) {
        g = 0;
      }
      /* Don't remove text in an HTML comment (e.g., turn the flag off) */
      else if ((*cp == '!') &&
          f &&
          (cp != buf) &&
          (*(cp-1) == ' ')) {
        *cp = ' ';
        f = 0;
      } else if (f || g) {
      //  *cp = INVISIBLE;   larry comment out this line, I do not think this logic is correct
      } else if ((*cp == '<') || (*cp == '>')) {
        *cp = ' ';
      }
    }
  }
  /*
   * step 2: remove comments that start at the beginning of a line, * like
   * ^dnl, ^xcomm, ^comment, and //
   */
  cp = buf;
  while (idxGrep(_UTIL_BOL_MAGIC, cp, REG_ICASE|REG_NEWLINE|REG_EXTENDED)) {
#ifdef  DOCTOR_DEBUG
    dumpMatch(cp, "Found \"comment\"-text");
#endif  /* DOCTOR_DEBUG */
    cp += cur.regm.rm_so;
    switch (*cp) {
      case '>':
        *cp++ = ' ';
        break;
      case '@':       /* texi special processing */
        *cp++ = INVISIBLE;
        if (strncasecmp(cp, "author", 6) == 0) {
          (void) memset(cp, ' ', 6);
          cp += 6;
        } else if (strncasecmp(cp, "comment", 7) == 0) {
          (void) memset(cp, ' ', 7);
          cp += 7;
        } else if (strncasecmp(cp, "center", 6) == 0) {
          (void) memset(cp, ' ', 6);
          cp += 6;
        }
        else if (strncasecmp(cp, "rem", 3) == 0) {
          (void) memset(cp, ' ', 3);
          cp += 3;
        } else if (*cp == 'c') {
          *cp++ = INVISIBLE;
          if (strncasecmp(cp, " essay", 6) == 0) {
            (void) memset(cp, ' ', 6);
            cp += 6;
          }
        }
        break;
      case '/':       /* c++ style comment // */
        if(cp && cp[0])
        {
          /** when MODULE_LICENSE("GPL") is outcommented, do not get rid of this line. */
          if (strstr(cp, MODULE_LICENSE) && '/' == cp[0])
          {
            (void) memset(cp, INVISIBLE, strlen(cp));
            cp += strlen(cp);
          }
          else {
            (void) memset(cp, INVISIBLE, 2);
            cp += 2;
          }
        }
        break;
      case '\\':      /* c++ style comment // */
        if (strncasecmp(cp+1, "par ", 3) == 0) {
          (void) memset(cp, ' ', 4);
        }
        cp += 4;
        break;
      case 'r':
      case 'R':       /* rem */
      case 'd':
      case 'D':       /* dnl */
        (void) memset(cp, INVISIBLE, 3);
        cp += 3;
        break;
      case 'x':
      case 'X':       /* xcomm */
        (void) memset(cp, INVISIBLE, 5);
        cp += 5;
        break;
      case 'c':
      case 'C':       /* comment */
        (void) memset(cp, INVISIBLE, 7);
        cp += 7;
        break;
      case '%':       /* %%copyright: */
        (void) memset(cp, INVISIBLE, 12);
        cp += 12;
        break;
    }
  }
  /*
   * Step 3 - strip out crap at end-of-line on postscript documents
   */
  if (isPS) {
#ifdef  DOCTOR_DEBUG
    printf("DEBUG: postscript stuff detected!\n");
#endif  /* DOCTOR_DEBUG */
    cp = buf;
    while (idxGrep(_UTIL_POSTSCR, cp, REG_EXTENDED|REG_NEWLINE)) {
#ifdef  DOCTOR_DEBUG
      dumpMatch(cp, "FOUND postscript-thingy");
#endif  /* DOCTOR_DEBUG */
      x = cp + cur.regm.rm_so;
      cp += cur.regm.rm_eo;
      while (x < cp) {
        *x++ = ' '/*INVISIBLE*/;
      }
    }
  }
  /*
   *      - step 4: remove groff/troff font-size indicators, the literal
   *              string backslash-n and all backslahes, ala:
   *==>   perl -pe 's,\\s[+-][0-9]*,,g;s,\\s[0-9]*,,g;s/\\n//g;' |
     f*/
  for (cp = buf; *cp; cp++) {
    if (*cp == '\\') {
      x = cp + 1;
      if (*x && (*x == 's')) {
        x++;
        if (*x && ((*x == '+') || (*x == '-'))) {
          x++;
        }
        while (*x && isdigit(*x)) {
          x++;
        }
      } else if (*x && *x == 'n') {
        x++;
      }
      memset(cp, /*INVISIBLE*/' ', (size_t) (x-cp));
    }
  }
  /*
   *      - step 5: convert white-space to real spaces, and remove
   *              unnecessary punctuation, ala:
   *==>   tr -d '*=+#$|%.,:;!?()\\][\140\047\042' | tr '\011\012\015' '   '
   *****
   * NOTE: we purposely do NOT process backspace-characters here.  Perhaps
   * there's an improvement in the wings for this?
   */
  for (cp = buf; /*cp < end &&*/ *cp; cp++) {
    if ((*cp == '\302') && (*(cp+1) == '\251')) {
      cp += 2;
      continue;
    }
    if (*cp & (char) 0x80) {
      *cp = INVISIBLE;
      continue;
    }
    switch (*cp) {
      /*
        Convert eol-characters AND some other miscellaneous
        characters into spaces (due to comment-styles, etc.)
       */
      case '\a': case '\t': case '\n': case '\r':
      case '\v': case '\f': case '[': case ']':
      case '{': case '}': case '*': case '=':
      case '#': case '$': case '|': case '%': case '!':
      case '?': case '`': case '"': case '\'':
        *cp = ' ';
        break;
        /* allow + only within the regex " [Mm]\+ " */
      case '+':
        if (*(cp+1) == 0 || *(cp+1) == ' ' || *(cp+1) == '\t' || *(cp+1) == '\n' || *(cp+1) == '\r') break;
        else if (cp > buf+1 && (*(cp-1) == 'M' ||
            *(cp-1) == 'm') && *(cp-2) == ' ' &&
            *(cp+1) == ' ') {
          f = 0; /* no-op */
        }
        else {
          *cp = ' ';
        }
        break;
      case '(':
        if ((*(cp+1) == 'C' || *(cp+1) == 'c') &&
            *(cp+2) == ')') {
          cp += 2;
          continue;
        }
        else {
          *cp = ' ';
        }
        break;
      case ')': case ',': case ':': case ';':
        if (!isCR) {
          *cp = ' ';
        }
        break;
      case '.':
        if (!isCR) {
          *cp = INVISIBLE;
        }
        break;
      case '<':
        if (strncasecmp(cp, "<string", 7) == 0) {
          (void) strncpy(cp, "          ", 7);
        }
        break;
        /* CDB - Big #ifdef 0 left out */
      case '\001': case '\002': case '\003': case '\004':
      case '\005': case '\006': case '\016': case '\017':
      case '\020': case '\021': case '\022': case '\023':
      case '\024': case '\025': case '\026': case '\027':
      case '\030': case '\031': case '\032': case '\033':
      case '\034': case '\035': case '\036': case '\037':
      case '~':
        *cp = INVISIBLE;
        break;
#ifdef  DOCTOR_DEBUG
      case ' ': case '/': case '-': case '@': case '&':
      case '>': case '^': case '_':
      case INVISIBLE:
        break;
      default:
        if (!isalpha(*cp) && !isdigit(*cp)) {
          printf("DEBUG: \\0%o @ %ld\n",
              *cp & 0xff, cp-buf);
        }
        break;
#endif  /* DOCTOR_DEBUG */
    }
  }
  /*
   * Look for hyphenations of words, to compress both halves into a sin-
   * gle (sic) word.  Regex == "[a-z]- [a-z]".
   *****
   * NOTE: not sure this will work based on the way we strip punctuation
   * out of the buffer above -- work on this later.
   */
  for (cp = buf; idxGrep(_UTIL_HYPHEN, cp, REG_ICASE); /*nada*/) {
#ifdef  DOCTOR_DEBUG
    x = cp + cur.regm.rm_so;
    while ((x > cp) && !isspace(*x)) {
      x--;
    }
    printf("Hey! hyphenated-word [");
    for (++x; x <= (cp + cur.regm.rm_eo); x++) {
      printf("%c", *x);
    }
    while (!isspace(*x)) {
      printf("%c", *x++);
    }
    printf("]\n");

#endif  /* DOCTOR_DEBUG */
    cp += cur.regm.rm_so + 1;
    *cp++ = INVISIBLE;
    while (isspace(*cp)) {
      *cp++ = INVISIBLE;
    }
  }
  /*
   *      - step 6: clean up miscellaneous punctuation, ala:
   *==>           perl -pe 's,[-_/]+ , ,g;s/print[_a-zA-Z]* //g;s/  / /g;'
   */
  for (cp = buf; idxGrep(_UTIL_MISCPUNCT, cp, REG_EXTENDED); /*nada*/) {
    x = cp + cur.regm.rm_so;
    cp += cur.regm.rm_eo - 1;  /* leave ' ' alone */
    while (x < cp) {
      *x++ = ' ';
    }
    cp++;
  }
  for (cp = buf; idxGrep(_UTIL_LATEX, cp, REG_ICASE); /*nada*/) {
    x = cp + cur.regm.rm_so;
    cp += cur.regm.rm_eo;
    while (x <= cp) {
      *x++ = ' ';
    }
    cp++;
  }
  /*
   * Ignore function calls to print routines: only concentrate on what's being
   * printed (sometimes programs do print licensing information) -- but don't
   * ignore real words that END in 'print', like footprint and fingerprint.
   * Here, we take a risk and just look for a 't' (in "footprint"), or for an
   * 'r' (in "fingerprint").  If someone has ever coded a print routine that
   * is named 'rprint' or tprint', we're spoofed.
   */
  for (cp = buf; idxGrep(_UTIL_PRINT, cp, REG_ICASE); /*nada*/) {
    x = cp + cur.regm.rm_so;
    cp += (cur.regm.rm_eo - 1);
    if ((x > buf) && ((*(x-1) == 'r') || (*(x-1) == 't'))) {
      continue;
    }
    while (x < cp) {
      *x++ = ' ';
    }
    cp++;
  }
  /*
   * Convert the regex ' [X ]+' (where X is really the character #defined as
   * INVISIBLE) to a single space (and a string of INVISIBLE characters).
   */
  for (cp = buf; *cp; /*nada*/) {
    if (*cp++ == ' ') {
      while (*cp) {
        if (*cp == ' ') {
          *cp++ = INVISIBLE;
        } else if (*cp == INVISIBLE) {
          cp++;
        } else {
          break;
        }
      }
    }
  }
  /*
   * garbage collect: eliminate all INVISIBLE characters in the buffer
   */
  x = cp = buf;
  n = 0;
  while (/*cp < end &&*/ *cp) {
    while (/*cp < end &&*/ *cp == INVISIBLE) {
      n++;
      cp++;
    }
    if (*cp) {
      *x++ = *cp++;
    }
  }
  *x = NULL_CHAR;
#ifdef  DOCTOR_DEBUG
  printf("***** Now buffer %p contains %d bytes (%d clipped)\n", buf,
      (int)strlen(buf), n);
  printf("+++++ [Dr-AFTER] +++++:\n%s\n[==END==]\n", buf);
#endif  /* DOCTOR_DEBUG */
  return;
}
#endif



