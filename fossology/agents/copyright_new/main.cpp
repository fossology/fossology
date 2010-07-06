/*
 * main.c
 *
 *  Created on: Jun 24, 2010
 *      Author: norton
 */

#include <cvector.h>

int main(int argc, char** argv) {
  char* strs[] = {"one","two","three","four","five","six","seven","eight","nine"};

  for(int j = 0; j < 10; j++) {
    printf("== NUMBER = %d ==\n",j);
    cvector to_test;
    cvector_init(&to_test, string_cvector_registry());

    for(int i = 0; i < j; i++) {
      cvector_push_back(to_test, strs[i]);
    }

    cvector_destroy(to_test);
  }
}
