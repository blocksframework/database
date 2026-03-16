<?php

namespace Blocks\Database;

use Blocks\Config\File as FileConfig;
use Assert\Assert;
use Assert\LazyAssertionException;
use Exception;

class MySQL {

    private static $link;

    private static function init() {
        if ( !is_file(DIR_PATH . "/config/mysql.php") ) {
            exit('The web-site seems to be unconfigured. The mysql config file is missing.');
        }

        $hostname = FileConfig::get('mysql', 'mysql_hostname');
        $port = (int)FileConfig::get('mysql', 'mysql_port');
        $username = FileConfig::get('mysql', 'mysql_username');
        $password = FileConfig::get('mysql', 'mysql_password');
        $database = FileConfig::get('mysql', 'mysql_database');
        $charset = FileConfig::get('mysql', 'mysql_charset');
 
        // TODO: to add more fields, except of password which can be empty
        if ( empty($hostname) || empty($database) ) {
             exit('The web-site seems to be unconfigured. The mysql config file contains empty params.');
        }

        try {
            Assert::lazy()->tryAll()
                    ->that($hostname, 'Database hostname')->string()->notEmpty("cannot be empty")->betweenLength(1, 255)
                    ->that($port, 'Database port')->integer()->notEmpty("cannot be empty")->between(1, 65535)
                    ->that($username, 'Database username')->string()->notEmpty("cannot be empty")->betweenLength(1, 32)
                    ->that($password, 'Database password')->string()->notEmpty("cannot be empty")->betweenLength(0, 255)
                    ->that($database, 'Database name')->string()->notEmpty("cannot be empty")->betweenLength(1, 64)
                    ->that($charset, 'Database charset')->string()->notEmpty("cannot be empty")->betweenLength(1, 32)
                    ->verifyNow();
        } catch (LazyAssertionException $e) {
            throw new Exception($e->getMessage());
        } catch (\Throwable $e) {
            throw new Exception("Fatal error: " . $e->getMessage());
        }

        $dsn = "mysql:host=$hostname;dbname=$database;port=$port;charset=$charset";

        $link = new \PDO($dsn, $username, $password);

        return $link;
    }

    public static function getLink() {
        if ( !isset(self::$link) ) {
            self::$link = self::init();
        }

        return self::$link;
    }
}
