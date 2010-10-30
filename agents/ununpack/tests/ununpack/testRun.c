#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include "CUnit/CUnit.h"
#include "CUnit/Automated.h"

extern CU_TestInfo TraverseStart_testcases[]; 
extern CU_TestInfo FindCmd_testcases[];
extern CU_TestInfo UnunpackEntry_testcases[];
extern CU_TestInfo Traverse_testcases[]; 
extern CU_TestInfo RunCommand_testcases[];
extern CU_TestInfo TraverseChild_testcases[];

/* FindCmd */
extern int FindCmdInit();
extern int FindCmdClean();

/* TraverseStart */
extern int TraverseStartInit();
extern int TraverseStartClean();

/* Traverse */
extern int TraverseInit();
extern int TraverseClean();

/* TraverseChildInit */
extern int TraverseChildInit();

CU_SuiteInfo suites[] = {
        {"Testing the function TraverseStart:", TraverseStartInit, TraverseStartClean, TraverseStart_testcases},
        {"Testing the function FindCmd:", FindCmdInit, FindCmdClean, FindCmd_testcases},
        {"Testing the function UnunpackEntry:", NULL, NULL, UnunpackEntry_testcases},
        {"Testing the function Traverse:", TraverseInit, TraverseClean, Traverse_testcases},
        {"Testing the function RunCommand:", NULL, NULL, RunCommand_testcases},
        {"Testing the function TraverseChild:", TraverseChildInit, NULL, TraverseChild_testcases},
        CU_SUITE_INFO_NULL
};

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
