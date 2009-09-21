<?php
$Fin = fopen("php://input","r");
$Ftmp = tempnam(NULL,"/tmp");
$Fout = fopen($Ftmp,"w");
while (!feof($Fin)) {
    $line = fgets($Fin);
    fwrite($Fout,$line);
}
fclose($Fout);
if (filesize($Ftmp) > 0) {
    $_FILES['uploadedfile']['tmp_name'] = $Ftmp;
    $_FILES['uploadedfile']['size'] = filesize($Ftmp);
    $_FILES['uploadedfile']['unlink_flag'] = 1;
} else {
    unlink($Ftmp);
}
fclose($Fin);

if (!empty($_FILES['uploadedfile'])) {

    header('Content-type: text/html');

    $handle = fopen("training.txt", "r");
    $files = array();
    if ($handle) {
        while (!feof($handle)) {
            $files[] = fgets($handle, 4096);
        }
        fclose($handle);
    }

    // Where the file is going to be placed 
    $target_path = "uploads/";

    /* Add the original filename to our target path.  
    Result is "uploads/filename.extension" */
    $target = $_FILES['uploadedfile']['tmp_name'];

    $licenses = array();
    $starts = array();
    $ends = array();
    $colors = array("ffff66", "aaffff", "99ff99", "ff9999", "ff66ff", "880000", "00aa00", "886600", "004499", "990099",);
    $handle = popen('./f1 '.$target, "r");
    if ($handle) {
        print("<pre>");
        while (!feof($handle)) {
            $buffer = fgets($handle);
            if (strlen($buffer)>0) {
                ereg("([[:alnum:]]+) \[([[:alnum:]]+), ([[:alnum:]]+)\]", $buffer, $regs);
                $licenses[] = $files[$regs[1]];
                $starts[] = $regs[2];
                $ends[] = $regs[3];
                print($regs[0]."\n");
            }
        }
        fclose($handle);
        print("</pre>\n");
    }

    $lcolors = array();
    $j = 0;
    for ($i = 0; $i<sizeof($licenses); $i++) {
        if (!array_key_exists($licenses[$i],$lcolors)) {
            $lcolors[$licenses[$i]] = $colors[$j%10];
            $j++;
        }
    }

    print("<h1>License Report</h1>\n");
    foreach ($lcolors as $key => $value) {
        print("<table><tr><td bgcolor=\"#".$value."\" width=\"16px\">&nbsp;</td><td>".$key."</td></tr></table>\n");
    }
    print("<pre>");
    $handle = fopen($target, "r");
    if ($handle) {
        $index = 0;
        while (!feof($handle)) {
            $key = array_search($index,$ends);
            if ($key !== FALSE) {
                print("</span>");
            }
            $key = array_search($index,$starts);
            if ($key !== FALSE) {
                print("<span style=\"background:#".$lcolors[$licenses[$key]]."\">");
            }
            $char = fgetc($handle);
            if ($char == '<') {
                print("&lt;");
            } elseif ($char == '>') {
                print("&gt;");
            } else {
                print($char);
            }
            

            $index++;
        }
        fclose($handle);
    }
    print("</pre>\n");

    if (!empty($_FILES['uploadedfile']['unlink_flag'])) {
        unlink($_FILES['uploadedfile']['tmp_name']);
    }

    exit();

}
?>
<html>
<head><title>compare</title></head>
<body>
<form enctype="multipart/form-data" action="f1.php" method="POST">
<input type="hidden" name="MAX_FILE_SIZE" value="100000" />
Choose a file to upload: <input name="uploadedfile" type="file" /><br />
<input type="submit" value="Upload File" />
</form>
</body>
</html>
