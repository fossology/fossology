#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"
#include "testRun.h"

extern CU_SuiteInfo suites[];

void AddTests(void)
{
        assert(NULL != CU_get_registry());
        assert(!CU_is_test_running());


        if(CUE_SUCCESS != CU_register_suites(suites)){
                fprintf(stderr, "Register suites failed - %s ", CU_get_error_msg());
                exit(EXIT_FAILURE);
        }
}

int main( int argc, char *argv[] )
{
       printf("test start\n");
       if(CU_initialize_registry()){

                fprintf(stderr, "\nInitialization of Test Registry failed.\n");
                exit(EXIT_FAILURE);
        }else{
                AddTests();

                CU_set_output_filename("ununpack");
                CU_list_tests_to_file();
                CU_automated_run_tests();
                CU_cleanup_registry();
        }
        printf("end\n");
        return 0;
}
