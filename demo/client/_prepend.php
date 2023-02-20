<?php

// Hackish code used to make the demos both viewable as source and runnable
if (isset($_GET['showSource']) && $_GET['showSource']) {
    $file = debug_backtrace()[0]['file'];
    highlight_file($file);
    die();
}

// support being installed both as top-level project and as dependency
if (file_exists(__DIR__ . '/../../../../vendor/autoload.php')) {
    include_once __DIR__ . '/../../../../vendor/autoload.php';
} else {
    include_once __DIR__ . '/../../vendor/autoload.php';
}
