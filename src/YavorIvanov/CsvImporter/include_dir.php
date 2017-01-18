<?php

if (! function_exists('include_dir'))
{
    function include_dir($path)
    {
        $path = app_path() . $path;
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        foreach (glob($path) as $filename)
        {
            if (! in_array($filename, get_included_files()))
                include $filename;
        }
    }
}

?>
