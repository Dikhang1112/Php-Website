<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Mail\Email;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    public static function mongoClient($getShared = true)
    {
        if ($getShared)
            return static::getSharedInstance('mongoClient');
        $uri = env('MONGO_URI') ?: 'mongodb://127.0.0.1:27017';
        return new \MongoDB\Client($uri);
    }

    public static function mongoDB($getShared = true)
    {
        if ($getShared)
            return static::getSharedInstance('mongoDB');
        $client = static::mongoClient(false);
        $dbName = env('MONGO_DB') ?: 'igwebsite';
        return $client->selectDatabase($dbName);
    }

    //Dang ki mail service
    public static function emailConfig($getShared = true): Email
    {
        if ($getShared)
            return static::getSharedInstance('emailConfig');

        $config = [
            'protocol' => env('email.protocol', 'smtp'),
            'SMTPHost' => env('email.SMTPHost', 'smtp.gmail.com'),
            'SMTPUser' => env('email.SMTPUser'),
            'SMTPPass' => env('email.SMTPPass'),
            'SMTPPort' => (int) env('email.SMTPPort', 587),
            'SMTPCrypto' => env('email.SMTPCrypto', 'tls'),
            'mailType' => env('email.mailType', 'html'),
            'charset' => env('email.charset', 'utf-8'),
            'wordWrap' => true,
            'newline' => "\r\n"
        ];

        return new Email($config);
    }
}
