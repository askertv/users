<?php

if (substr(php_sapi_name(), 0, 3) !== 'cli') {
    echo "\nСкрипт запускается только из командной строки\n";
}

require_once 'DatabaseManager.php';

$importSuccess = false;

if ($argc == 2) {
    $fileName = $argv[1];

    if (!file_exists($fileName)) {
        echo "\nУказанный файл не найден. Укажите правильное название файла, либо путь к файлу, либо скопируйте в текущюю папку, файл с данными (csv)\n\n";
        exit;
    }

    $importSuccess = DatabaseManager::getInstance()->importData($fileName);
} elseif ($argc == 1) {
    $files = glob('*.csv');
    $findedFiles = count($files);

    if ($findedFiles == 0) {
        echo "\nФайл для импорта данных, не найден. Скопируйте файл в папку с текущим скриптом, либо укажите путь к файлу\n\n";
        exit;
    } elseif ($findedFiles > 1) {
        echo "\nНайдено больше одного файла. Оставьте только один с данными (csv), либо укажите путь к файлу, либо его название в текущей папке\n\n";
        exit;
    } elseif ($findedFiles == 1) {
        $importSuccess = DatabaseManager::getInstance()->importData($files[0]);
    } else {
        echo "\nНепонятная ошибка\n\n";
        exit;
    }
} else {
    echo "\nСлишком много параметров в командной строке. Возможно, нужно обернуть имя файла в кавычки.\n\n";
    exit;
}

if ($importSuccess) {
    echo "\nИмпорт данных прошёл успешно\n";
} else {
    echo "\nИмпорт данных завершился с ошибкой\n";
}

echo "\n";