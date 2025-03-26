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

if (file_exists(TMP_DIR . "dbModels.db")) {
    unlink(TMP_DIR . "dbModels.db");
}
touch(TMP_DIR . "dbModels.db");
if (file_exists(TMP_DIR . "dbModelsComplex.db")) {
    unlink(TMP_DIR . "dbModelsComplex.db");
}
touch(TMP_DIR . "dbModelsComplex.db");
if (file_exists(TMP_DIR . "dbQuery.db")) {
    unlink(TMP_DIR . "dbQuery.db");
}
touch(TMP_DIR . "dbQuery.db");
if (!file_exists(ROOT . "tests/tmp/dbc.db")) {
    touch(ROOT . "tests/tmp/dbc.db");
}

// Clear model cache
ModelRepository::$modelConfig = [];
$files = glob(TMP_DIR.'models/*.php');
if ($files !== false) {
    foreach ($files as $file) {
        unlink($file);
    }
}
