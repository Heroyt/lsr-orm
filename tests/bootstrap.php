<?php

/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpIllegalPsrClassPathInspection */


use Lsr\Orm\ModelRepository;

define('ROOT', dirname(__DIR__) . '/');
const TMP_DIR = ROOT . 'tests/tmp/';
const LOG_DIR = ROOT . 'tests/logs/';

ini_set('open_basedir', ROOT);

require_once ROOT . 'vendor/autoload.php';

if (
    !file_exists(TMP_DIR)
    && !is_dir(TMP_DIR)
    && !mkdir(TMP_DIR, 0777, true)
    && !is_dir(TMP_DIR)
) {
    throw new RuntimeException('Cannot create temporary folder');
}

if (
    !file_exists(LOG_DIR)
    && !is_dir(LOG_DIR)
    && !mkdir(LOG_DIR, 0777, true)
    && !is_dir(LOG_DIR)
) {
    throw new RuntimeException('Cannot create log folder');
}

// Remove all DB files from the temporary directory
$dbFiles = glob(TMP_DIR.'*.db');
if ($dbFiles !== false) {
    foreach ($dbFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

// Clear model cache
ModelRepository::$modelConfig = [];
$files = glob(TMP_DIR.'models/*.php');
if ($files !== false) {
    foreach ($files as $file) {
        unlink($file);
    }
}
