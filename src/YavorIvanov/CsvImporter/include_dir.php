<?php

if (! function_exists('create_dir_if_not_found'))
{
    function create_dir_if_not_found($path, $mode=0755, $recursive=true, $warn=true)
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (file_exists($path))
            return;
        mkdir($path, $mode, $recursive);
        if (! $warn)
            return;
        // NOTE <Yavor>: I manually check if the dir exists instead of using the mkdir
        // return value, because mkdir is bugged on some PHP versions and returns false
        // even when it succeeds.
        if (file_exists($path))
        {
            Log::warning("The `$path` directory doesn't exist. Creating automatically...");
        }
        else
        {
            Log::error("The `$path` directory doesn't exist and couldn't be automatically created.");
            die;
        }
    }
}

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
