<?php

require __DIR__ . '/../src/Dabble/Database.php';
require __DIR__ . '/../src/Dabble/Result.php';
require __DIR__ . '/../src/Dabble/Literal.php';

use Dabble\Database;
use Dabble\Result;

/**
 * @requires extension mysqli
 */
class Dabble_TestCase extends PHPUnit_Framework_TestCase
{
    protected $db;
    protected $link;

    public static function setUpBeforeClass()
    {
        $link = mysqli_connect($GLOBALS['DB_HOST'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], $GLOBALS['DB_NAME'], $GLOBALS['DB_PORT'], $GLOBALS['DB_SOCK']);
        mysqli_query($link, 'DROP TABLE `post` IF EXISTS');
        $sql =<<<SQL
CREATE TABLE `post` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL DEFAULT '',
    `body` text NOT NULL,
    `comments_count` int(11) NOT NULL DEFAULT 0,
    `when` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
SQL;
        mysqli_query($link, $sql);
    }

    public function setUp()
    {
        $this->link = mysqli_connect($GLOBALS['DB_HOST'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], $GLOBALS['DB_NAME'], $GLOBALS['DB_PORT'], $GLOBALS['DB_SOCK']);
        mysqli_query($this->link, "INSERT INTO `post` (`title`, `body`, `when`) VALUES('Title #1', 'Body #1', NOW())");
        mysqli_query($this->link, "INSERT INTO `post` (`title`, `body`, `when`) VALUES('Title #2', 'Body #2', NOW())");
        mysqli_query($this->link, "INSERT INTO `post` (`title`, `body`, `when`) VALUES('Title #3', 'Body #3', NOW())");
        $this->db = new Database($GLOBALS['DB_HOST'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], $GLOBALS['DB_NAME'], $GLOBALS['DB_CHAR'], $GLOBALS['DB_PORT'], $GLOBALS['DB_SOCK']);
    }

    public function tearDown()
    {
        $this->db->close();
        mysqli_query($this->link, 'TRUNCATE `post`');
        mysqli_query($this->link, 'ALTER TABLE `post` AUTO_INCREMENT 1');
        mysqli_close($this->link);
    }
}

