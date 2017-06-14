<?php

require __DIR__.'/../vendor/autoload.php';

if (! function_exists('app')) {
    function app($abstract)
    {
        return $abstract;
    }
}
