<?php
session_start(); // Стартуем сессии

/**
 * Описание библиотеки для работы я Яндекс-диском
 * https://github.com/nixsolutions/yandex-php-library/wiki/Yandex-Disk
 *
 * Документация по API Яндекс-диска
 * https://tech.yandex.ru/disk/doc/dg/concepts/quickstart-docpage/
 */

// Подключение автозагрузчика от Composer
require_once dirname(__FILE__) . '/vendor/autoload.php';

use Yandex\OAuth\OAuthClient;
use Yandex\OAuth\Exception\AuthRequestException;
use Yandex\Disk\DiskClient;

/**
 * Регистрация приложения
 * https://oauth.yandex.ru/client/new
 */
$settings = [
    'clientId' => '72e71c9455ef410ba4aeef934f75ff8e',     // ID приложения
    'clientSecret' => 'ef98af12eece46448c17212f73a96b11', // Никому не называть
    'callbackUrl' => 'http://' . $_SERVER ['HTTP_HOST'],   // Url переадресации
    'uriPath' => 'https://disk.yandex.ru/client/disk' // Используется для навигации по структуре диска
];

// Подключаемся к клиенту API Яндекса
$client = new OAuthClient($settings['clientId'], $settings['clientSecret']);

// Если в URL передана переменная с значением "code"
if (isset($_REQUEST['code'])) {
   // Попытка получения токена, иначе вывод ошибок
   try {
       $client->requestAccessToken($_REQUEST['code']);
   } catch (AuthRequestException $e) {
       echo $e->getMessage();
   }

   // Сохранение токена в сессию
   $_SESSION['accessToken'] = $client->getAccessToken();

   // Переадресация на главную страницу
   header('Location: ' . $settings['callbackUrl']); exit();

} elseif (!isset($_SESSION['accessToken'])) {
    $client->authRedirect(true, OAuthClient::CODE_AUTH_TYPE);
}

// Проверяем, если существует токен
if (isset($_SESSION['accessToken'])){
    $diskClient = new DiskClient($_SESSION['accessToken']);

    // Информация о пользователе
    $login = explode("\n", $diskClient->getLogin());

    // Извлекаем информацию о свободном и занятом месте
    $diskSpace = $diskClient->diskSpaceInfo();

    // Получаем информацию о содержимом диска
    $dirContent = $diskClient->directoryContents('/');

    // Если были отправлены POST-запросы
    if ($_POST){

        /**
         * Загрузка файла на диск
         */
        if (isset($_POST['upload']) && isset($_FILES['files'])){
            if ($_FILES['files']['error'][0] == 0) {
                $files = normalizeFilesArray($_FILES);

                foreach ($files as $file) {
                    $tmp = file_get_contents($file['tmp_name']);

                    $diskClient->uploadFile('/',
                        [
                            'path' => $file['tmp_name'],
                            'size' => $file['size'],
                            'name' => $file['name']
                        ]
                    );
                }

                // Переадресация на главную страницу
                header('Location: ' . $settings['callbackUrl']); exit();
            }
        }

        /**
         * Удаление выбранного файла
         */
        if (isset($_POST['delete']) && isset($_POST['delFile'])){

            $diskClient->delete($_POST['delFile']);

            // Переадресация на главную страницу
            header('Location: ' . $settings['callbackUrl']); exit();
        }

    }
}

/**
 * Нормальный вид для отображения размера файлов
 * @param $size
 * @return string
 */
function humanBytes($size)
{
    $fileSizeName = [' байт', ' Кб', ' Мб', ' Гб', ' Тб', ' Пб', ' Эб', ' Зб', ' Йб'];
    return $size ? round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $fileSizeName[$i] : '0 байт';
}

/**
 * Приведение к нормальному виду глобального массива $_FILES,
 * который передали в качестве параметра $files
 * @param array $files
 * @return array
 */
function normalizeFilesArray($files = [])
{
    $result = array();

    foreach ($files as $file) {
        if (!is_array($file['name'])) {
            $result[] = $file;
            continue;
        }

        foreach ($file['name'] as $key => $filename) {
            $result[$key] = array(
                'name'      => $filename,
                'type'      => $file['type'][$key],
                'tmp_name'  => $file['tmp_name'][$key],
                'error'     => $file['error'][$key],
                'size'      => $file['size'][$key]
            );
        }
    }

    return $result;
}

// Подключение файла с версткой
include_once 'index.phtml';