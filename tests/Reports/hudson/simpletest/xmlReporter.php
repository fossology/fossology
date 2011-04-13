require_once(“common.php”);

	

// “Configuration”
$GLOBALS['TLD’] = 'local.ch’;
$GLOBALS['SELENIUM_SERVER’] = 'localhost’;


	

if (file_exists('config_developer.php’)) { include_once('config_developer.php’);
}
if (getenv('SELENIUM_SERVER’)) { $GLOBALS['SELENIUM_SERVER’] = getenv('SELENIUM_SERVER’);
}
if (getenv('TLD’)) { $GLOBALS['TLD’] = getenv('TLD’);
}


	

/** * $case: Only run this test case * $test: Only run this test within the case */
function runAllTests($onlyCase = false, $onlyTest = false) { $test = &new TestSuite('All tests’); $dirs = array(“unit”, “selenium”, “selenium/*”);


    foreach ($dirs as $dir) {
        foreach (glob($dir . ‘*.php’) as $file) {
            $test->addTestFile($file);
        }
    }

    if (!empty($onlyCase))
        $result = $test->run(new SelectiveReporter(new TextReporter(), $onlyCase, $onlyTest));
    else
        $result = $test->run(new XMLReporter());
    return ($result ? 0 : 1);
}

	

return runAllTests($argv[1], $argv2);
?>
