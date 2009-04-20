<?php


// This sets up the template set global variable
function SetUpTemplates()
{
    global $TemplateSet, $NewTemplateSet;
    
    if (isset($NewTemplateSet))
        $TemplateSet = $NewTemplateSet;
    
    // Only allow -_a-zA-Z0-9 in $TemplateSet
    $TemplateSet = ereg_replace("[^-_a-zA-Z0-9]", "", $TemplateSet);
    
    if (! is_dir("templates/$TemplateSet") || $TemplateSet == '')
        $TemplateSet = 'standard';
}


?>
