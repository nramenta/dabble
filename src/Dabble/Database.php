<?php
/**
 * Dabble - A lightweight wrapper and collection of helpers for MySQLi.
 *
 * @author  Nofriandi Ramenta <nramenta@gmail.com>
 * @license http://en.wikipedia.org/wiki/MIT_License MIT
 */

namespace Dabble;

use Dabble\Result;
use Dabble\Literal;

/**
 * Main database class
 */
class Database
{
    protected $link;
    protected $host;
    protected $username;
    protected $password;
    protected $database;
    protected $charset;
    protected $port;
    protected $socket;
    protected $ssl;
    protected $clientkey;
    protected $clientcert;
    protected $cacert;

    protected $transaction = false;

    public $last_query;
    public $affected_rows;
    public $last_error;
    public $last_errno;
    public $queries = array();

    /**
     * Object constructor.
     *
     * @param string $host        Server hostname or IP address
     * @param string $username    Server username
     * @param string $password    Server password
     * @param string $database    Database name
     * @param string $charset     Server connection character set
     * @param int    $port        Server connection port
     * @param string $socket      Server connection socket
     * @param boolean $ssl        Use SSL connection
     * @param string $clientkey   clientkey file
     * @param string $clientcert  clientcert file
     * @param string $cacert      cacert file
     */
    public function __construct($host, $username, $password, $database,
        $charset = 'utf8', $port = 3306, $socket = null, $ssl = false,
        $clientkey = '', $clientcert = '', $cacert = '')
    {
        $this->link     = null;
        $this->host     = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->charset  = $charset;
        $this->port     = $port;
        $this->socket   = $socket;
        $this->ssl      = $ssl;
        $this->cacert   = $cacert;
        $this->clientcert = $clientcert;
        $this->clientkey = $clientkey;
    }

    /**
     * Opens the database connection.
     *
     * @return bool Boolean true on success, throws a RuntimeException otherwise
     */
    public function open()
    {
        if (is_object($this->link)) {
            return true;
        }

        if ($this->ssl == true) {
            if (empty($this->clientcert))
                throw new \RuntimeException("clientcert not defined");
            if (empty($this->clientkey))
                throw new \RuntimeException("clientkey not defined");
            if (empty($this->cacert))
                throw new \RuntimeException("cacert not defined");

            $this->link = mysqli_init();
            $this->link->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
            $this->link->ssl_set($this->clientkey, $this->clientcert,
                $this->cacert, NULL, NULL);
            $this->link->real_connect($this->host, $this->username,
                $this->password, $this->database, $this->port, $this->socket,
                MYSQLI_CLIENT_SSL);
        } else {
            $this->link = mysqli_connect($this->host, $this->username,
                $this->password, $this->database, $this->port, $this->socket);
        }
        if (mysqli_connect_errno()) {
            throw new \RuntimeException(sprintf(
                'could not connect to %s : (%d) %s',
                $this->database, mysqli_connect_errno(), mysqli_connect_error()
            ));
        }

        if (!mysqli_set_charset($this->link, $this->charset)) {
            throw new \RuntimeException(sprintf(
                'error loading character set %s : (%d) %s',
                $this->charset, mysqli_errno($this->link),
                mysqli_error($this->link)
            ));
        }

        return true;
    }

    /**
     * Selects a different database than the one specified on construction.
     *
     * @param string $database Database name to switch to
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function select_db($database)
    {
        $this->open();

        return mysqli_select_db($this->link, $database);
    }

    /**
     * Pings the database connection.
     *
     * @return bool Boolean true if the connection is alive, false otherwise
     */
    public function ping()
    {
        $this->open();

        return mysqli_ping($this->link);
    }

    /**
     * Starts a new transaction.
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function begin()
    {
        if ($this->transaction) {
            throw new \LogicException('database transaction in progress');
        }

        if ($this->query('START TRANSACTION')) {
            $this->transaction = true;
            return true;
        }

        return false;
    }

    /**
     * Commits the current transaction.
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function commit()
    {
        if (!$this->transaction) {
            throw new \LogicException('database commit not in transaction');
        }

        if (mysqli_commit($this->link)) {
            $this->transaction = false;
            return true;
        }

        return false;
    }

    /**
     * Rolls back the current transaction.
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function rollback()
    {
        if (!$this->transaction) {
            throw new \LogicException('database rollback not in transaction');
        }

        if (mysqli_rollback($this->link)) {
            $this->transaction = false;
            return true;
        }

        return false;
    }

    /**
     * Execute a callback inside a transaction.
     *
     * @param callback $callback The callback to run inside the transaction
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function transact($callback)
    {
        try {
            $this->begin();
            call_user_func($callback, $this);
            return $this->commit();
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * Returns last insert ID.
     *
     * @return int
     */
    public function insert_id()
    {
        $this->open();

        return mysqli_insert_id($this->link);
    }

    /**
     * Escapes data using mysqli_escape_string. Traversable objects will be
     * converted into arrays. Literal objects will return their literal values.
     * Any other objects will be cast into arrays using `(array)`. If the second
     * parameter is `true`, then returned values will be SQL-friendly meaning
     * they can be inserted straight into queries as is.
     *
     * @param mixed $data   The data to be escaped
     * @param bool  $sqlize Format data as SQL-friendly, defaults to false
     *
     * @return mixed escaped data
     */
    public function escape($data, $sqlize = false)
    {
        $this->open();

        if (is_null($data)) {
            return $sqlize ? 'null' : $data;
        }

        if (is_object($data)) {
            if ($data instanceof \Traversable) {
                $data = iterator_to_array($data);
            } elseif (!($data instanceof Literal)) {
                $data = (array) $data;
            }
        }

        if (is_scalar($data)) {
            if (is_string($data)) {
                if ($sqlize) {
                    return "'" . mysqli_escape_string($this->link, $data) . "'";
                } else {
                    return mysqli_escape_string($this->link, $data);
                }
            } elseif (is_bool($data)) {
                if ($data) {
                    return $sqlize ? "'1'" : '1';
                } else {
                    return $sqlize ? "'0'" : '0';
                }
            } else {
                return $data;
            }
        } elseif ($data instanceof Literal) {
            return $data->__toString();
        } elseif (is_array($data)) {
            $escaped = array();
            foreach ($data as $i => $datum) {
                $escaped[$i] = $this->escape($datum, $sqlize);
            }
            return $escaped;
        } else {
            throw new \InvalidArgumentException(
                'supplied data is not supported'
            );
        }
    }

    /**
     * Strips SQL from optional fragments.
     *
     * @param string $sql  The SQL string
     * @param array  $keys Required parameter binding keys
     *
     * @return string
     */
    public function strip($sql, array $keys)
    {
        $pattern = '/:([a-zA-Z_][a-zA-Z0-9_]*)\b/';
        $s = array();
        $stack = array();
        $level = -1;

        $sql = str_split($sql);

        foreach ($sql as $i => $char) {
            if ($char == '[') {
                $level += 1;
                $stack[] = array();
            } elseif ($char == ']') {
                if ($level < 0) {
                    throw new \RuntimeException('unmatched [ and ] characters');
                }
                preg_match_all($pattern, implode('', $stack[$level]), $matches);
                $params = array_unique($matches[1]);
                $level -= 1;
                if (count(array_intersect($params, $keys)) == count($params)) {
                    if ($level < 0) {
                        $s = array_merge($s, array_pop($stack));
                    } else {
                        $stack[$level] = array_merge($stack[$level],
                            array_pop($stack));
                    }
                } else {
                    array_pop($stack);
                }
            } else {
                if ($level < 0) {
                    $s[] = $char;
                } else {
                    $stack[$level][] = $char;
                }
            }
        }
        if ($level >= 0) {
            throw new \RuntimeException('unmatched [ and ] characters');
        }
        return trim(implode('', $s));
    }

    /**
     * Returns the SQL by replacing :placeholders with SQL-escaped values.
     *
     * @param mixed $sql      The SQL string
     * @param array $bindings An array of key-value bindings
     *
     * @return string
     */
    public function format($sql, array $bindings = array())
    {
        static $strtr;
        $strtr = function($a) {
            return strtr($a, array('\\' => '\\\\', '$' => '\$'));
        };

        $sql = $this->strip($sql, array_keys($bindings));

        $search = $replace = array();
        foreach ($bindings as $name => $value) {
            $search[] = '/:' . preg_quote($name) . '\b/';
            if (is_null($value)) {
                $replace[] = $this->escape($value, true);
            } elseif (is_scalar($value)) {
                $replace[] = strtr(
                    $this->escape($value, true),
                    array('\\' => '\\\\', '$' => '\$')
                );
            } elseif ($value instanceof Literal) {
                $replace[] = $this->escape($value);
            } elseif (is_array($value)) {
                foreach ($value as $i => $element) {
                    if (!is_scalar($element) && !is_null($element) &&
                        !($element instanceof Literal)) {
                        throw new \RuntimeException(
                            'could not format non-scalar value'
                        );
                    }
                }
                $replace[] = implode(
                    ',', array_map($strtr, $this->escape($value, true))
                );
            }
        }
        return preg_replace($search, $replace, $sql);
    }

    /**
     * Returns a Literal object to mark a string value so that it should not be
     * escaped.
     *
     * @param string $string   The literal string
     * @param array  $bindings Optional bindings for the literal string
     *
     * @return Literal A Literal object
     */
    public function literal($string, array $bindings = array())
    {
        return new Literal($this->format((string) $string, $bindings));
    }

    /**
     * Executes the query. Returns either a Result object or a boolean depending
     * on the type of query.
     *
     * @param string $sql      The SQL string
     * @param array  $bindings Array of key-value bindings
     *
     * @return Result|bool
     */
    public function query($sql, array $bindings = array())
    {
        $this->open();

        $this->last_query = $sql;
        $sql = $this->format($sql, $bindings);
        $this->last_query = $sql;

        $this->queries[]  = $sql;

        $this->affected_rows = null;

        $result = mysqli_query($this->link, $sql);

        if (is_object($result)) {
            $res = new Result($result);
            if (preg_match('/^(?:(?:EXPLAIN|DESC).+?)?SELECT(?:[A-Z_\s]+)?\s+SQL_CALC_FOUND_ROWS/i', $sql)) {
                $res->found_rows = $this->found_rows();
            } else {
                $res->found_rows = $res->num_rows;
            }
            if (preg_match('/\s+LIMIT\s+(\d+)\s+OFFSET\s+(\d+)(?:\s+(?:PROCEDURE|INTO|FOR|LOCK).+)?$/i', $sql, $match)) {
                $res->limit     = $match[1];
                $res->offset    = $match[2];
                $res->num_pages = ceil($res->found_rows / $res->limit);
                $res->page      = floor($res->offset / $res->limit) + 1;
            } elseif (preg_match('/\s+LIMIT\s+(\d+)\s*,\s*(\d+)(?:\s+(?:PROCEDURE|INTO|FOR|LOCK).+)?$/i', $sql, $match)) {
                $res->limit     = $match[2];
                $res->offset    = $match[1];
                $res->num_pages = ceil($res->found_rows / $res->limit);
                $res->page      = floor($res->offset / $res->limit) + 1;
            } elseif (preg_match('/\s+LIMIT\s+(\d+)(?:\s+(?:PROCEDURE|INTO|FOR|LOCK).+)?$/i', $sql, $match)) {
                $res->limit     = $match[1];
                $res->num_pages = 1;
                $res->page      = 1;
            } else {
                $res->num_pages = 1;
                $res->page      = 1;
            }
            return $res;
        } else {
            if ($result) {
                $this->affected_rows = mysqli_affected_rows($this->link);
            } else {
                $this->last_error = mysqli_error($this->link);
                $this->last_errno = mysqli_errno($this->link);
                throw new \RuntimeException($this->last_error .
                    ' (' . $this->last_errno . ')');
            }
            return $result;
        }
    }

    /**
     * Returns the number of FOUND_ROWS() from the last query.
     *
     * @return int
     */
    protected function found_rows()
    {
        $result = $this->query('SELECT FOUND_ROWS() AS `total`');
        $found_rows = $result->fetch_one('total');
        $result->free();
        return $found_rows;
    }

    /**
     * Closes the database connection.
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function close()
    {
        if (!is_object($this->link)) {
            return false;
        }

        if (mysqli_close($this->link)) {
            $this->link = null;
            return true;
        }

        return false;
    }

    /**
     * A quick and simple way to select all columns from a table according to a
     * where clause. The where clauses are all joined with AND.
     *
     * @param string $table Table name
     * @param string $where Where-clause; can contain placeholders
     * @param array  $args  Array of key-value bindings for the where-clause
     *
     * @return Result|bool
     */
    public function select($table, $where = null, array $args = array())
    {
        if (isset($where)) {
            if (is_array($where)) {
                $conditions = array();
                foreach ($where as $column => $value) {
                    $conditions[] = is_null($value) ?
                        "`$column` IS NULL" : "`$column` = :$column";
                    $args[$column] = $value;
                }
                $where = implode(' AND ', $conditions);
            }
            $sql = "SELECT * FROM `$table` WHERE $where";
        } else {
            $sql = "SELECT * FROM `$table`";
        }

        return $this->query($sql, $args);
    }

    /**
     * Helper method to insert a row.
     *
     * @param string $table     Table name
     * @param array  $data      Array of column-value data to be inserted
     * @param int    $insert_id Last inserted id, if available (optional)
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function insert($table, array $data, &$insert_id = null)
    {
        $columns = array();
        $values  = array();

        foreach ($data as $column => $value) {
            $columns[] = '`' . $column . '`';
            $values[]  = ':' . $column;
        }

        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)',
            $table, implode(',', $columns), implode(',', $values));

        if ($this->query($sql, $data)) {
            $insert_id = $this->insert_id();
            return true;
        }

        return false;
    }

    /**
     * Helper method to update a row.
     *
     * @param string $table Table name
     * @param array  $data  Array of column-value data to be updated
     * @param string $where Where-clause; can contain placeholders
     * @param array  $args  Array of key-value bindings for the where-clause
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function update($table, array $data, $where = null,
        array $args = array())
    {
        $updates = array();

        $bindings = array();
        foreach ($data as $column => $value) {
            $updates[] = "`$column` = :_$column";
            $bindings['_' . $column] = $value;
        }

        $updates = implode(',', $updates);

        if (isset($where)) {
            if (is_array($where)) {
                $conditions = array();
                foreach ($where as $column => $value) {
                    $conditions[] = is_null($value) ?
                        "`$column` IS NULL" : "`$column` = :$column";
                    $args[$column] = $value;
                }
                $where = implode(' AND ', $conditions);
            }
            $sql = "UPDATE `$table` SET $updates WHERE $where";
        } else {
            $sql = "UPDATE `$table` SET $updates";
        }

        return $this->query($sql, array_merge($bindings, $args));
    }

    /**
     * Helper method to upsert a row. Upsert is the equivalent of insert or
     * update if row exists.
     *
     * @param string $table     Table name
     * @param array  $data      Array of column-value data to be updated
     * @param string $update    Update-clause; can contain placeholders
     * @param array  $args      Parameters for the update-clause
     * @param int    $insert_id Last inserted id, if available (optional)
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function upsert($table, array $data, $update = null,
        array $args = array(), &$insert_id = null)
    {
        $columns = array();
        $values  = array();

        $bindings = array();
        foreach ($data as $column => $value) {
            $columns[] = "`$column`";
            $values[]  = ":_$column";
            $bindings['_' . $column] = $value;
        }

        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)',
            $table, implode(',', $columns), implode(',', $values));

        if (isset($update)) {
            if (is_array($update)) {
                $updates = array();
                foreach ($update as $column => $value) {
                    $updates[] = is_null($value) ?
                        "`$column` IS NULL" : "`$column` = :$column";
                    $args[$column] = $value;
                }
                $update = implode(', ', $updates);
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . $update;
        }

        if ($this->query($sql, array_merge($bindings, $args))) {
            $insert_id = $this->insert_id();
            return true;
        }

        return false;
    }

    /**
     * Helper method to delete a row.
     *
     * @param string $table Table name
     * @param string $where Where-clause; can contain placeholders
     * @param array  $args  Array of key-value bindings for the where-clause
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function delete($table, $where = null, array $args = array())
    {
        if (isset($where)) {
            if (is_array($where)) {
                $conditions = array();
                foreach ($where as $column => $value) {
                    $conditions[] = is_null($value) ?
                        "`$column` IS NULL" : "`$column` = :$column";
                    $args[$column] = $value;
                }
                $where = implode(' AND ', $conditions);
            }
            $sql = "DELETE FROM `$table` WHERE $where";
        } else {
            $sql = "DELETE FROM `$table`";
        }

        return $this->query($sql, $args);
    }

    /**
     * Helper method to replace a row. Replace either inserts or deletes and
     * then re-inserts.
     *
     * @param string $table     Table name
     * @param array  $data      Array of column-value pairs of data
     * @param int    $insert_id Last inserted id, if available (optional)
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function replace($table, array $data, &$insert_id = null)
    {
        $columns = array();
        $values  = array();

        foreach ($data as $column => $value) {
            $columns[] = "`$column`";
            $values[]  = ":$column";
        }

        $sql = sprintf('REPLACE INTO `%s` (%s) VALUES (%s)',
            $table, implode(',', $columns), implode(',', $values));

        if ($this->query($sql, $data)) {
            $insert_id = $this->insert_id();
            return true;
        }

        return false;
    }

    /**
     * Helper method to truncate a table.
     *
     * @param string $table          Table name
     * @param int    $auto_increment Auto-increment number; defaults to 1
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function truncate($table, $auto_increment = 1)
    {
        $ok = $this->query("TRUNCATE `$table`");

        if ($ok && isset($auto_increment)) {
            $this->query("ALTER TABLE `$table` AUTO_INCREMENT = :number",
            array(
                'number' => $auto_increment,
            ));
        }

        return $ok;
    }

    /**
     * Alias for `query()`.
     */
    public function __invoke($sql = null, array $bindings = array())
    {
        return isset($sql) ? $this->query($sql, $bindings) : $this->link;
    }

    /**
     * Object destructor.
     */
    public function __destruct()
    {
        $this->close();
    }
}

