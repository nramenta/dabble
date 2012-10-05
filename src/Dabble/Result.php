<?php
/**
 * Dabble - A lightweight wrapper and collection of helpers for MySQLi.
 *
 * @author  Nofriandi Ramenta <nramenta@gmail.com>
 * @license http://en.wikipedia.org/wiki/MIT_License MIT
 */

namespace Dabble;

/**
 * Database result class
 */
class Result implements \Countable, \Iterator
{
    protected $result;
    protected $count;
    protected $row;

    /**
     * Object constructor.
     *
     * @param mixed $result Resource returned by db::query or mysqli_query
     */
    public function __construct($result)
    {
        $this->result = $result;
        $this->count  = mysqli_num_rows($result);
        $this->row    = 0;
    }

    /**
     * Moves the internal pointer to the specified row position.
     *
     * @param int $row Row position; zero-based and set to 0 by default
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function seek($row = 0)
    {
        if (is_int($row) && $row >= 0 && $row <= $this->count - 1) {
            return mysqli_data_seek($this->result, $row);
        } else {
            return false;
        }
    }

    /**
     * Fetches a row or a single column within a row. Returns null if there are
     * no more rows in the result.
     *
     * @param int    $row    The row number (optional)
     * @param string $column The column name (optional)
     *
     * @return mixed An associative array or a scalar value
     */
    public function fetch($row = null, $column = null)
    {
        if (!$this->count) {
            return null;
        }

        if (isset($row)) {
            $this->seek($row);
        }

        $row = mysqli_fetch_assoc($this->result);

        if ($column) {
            return is_array($row) && isset($row[$column]) ?
                $row[$column] : null;
        } else {
            return $row;
        }
    }

    /**
     * Fetches the next row or a single column within the next row.
     *
     * @param string $column The column name (optional)
     *
     * @return mixed An associative array or a scalar value
     */
    public function fetch_one($column = null)
    {
        return $this->fetch(null, $column);
    }

    /**
     * Returns all rows at once as an array of scalar values or arrays.
     *
     * @param string $column The column name to use as values (optional)
     *
     * @return mixed An array of scalar values or arrays
     */
    public function fetch_all($column = null)
    {
        $rows = array();
        $pos  = $this->row;
        foreach ($this as $row) {
            if (isset($column)) {
                if (!array_key_exists($column, $row)) continue;
                $rows[] = $row[$column];
            } else {
                $rows[] = $row;
            }
        }
        $this->rewind($pos);
        return $rows;
    }

    /**
     * Returns all rows at once as key-value pairs.
     *
     * @param string $key    The column name to use as keys
     * @param string $column The column name to use as values (optional)
     *
     * @return array An array of key-value pairs
     */
    public function fetch_pairs($key, $column = null)
    {
        $pairs = array();
        $pos   = $this->row;
        foreach ($this as $row) {
            if (!array_key_exists($key, $row)) continue;
            if (isset($column)) {
                if (!array_key_exists($column, $row)) continue;
                $pairs[$row[$key]] = $row[$column];
            } else {
                $pairs[$row[$key]] = $row;
            }
        }
        $this->rewind($pos);
        return $pairs;
    }

    /**
     * Returns all rows at once as a grouped array of scalar values or arrays.
     *
     * @param string $group  The column name to use for grouping
     * @param string $column The column name to use as values (optional)
     *
     * @return array A grouped array of scalar values or arrays
     */
    public function fetch_groups($group, $column = null)
    {
        $groups = array();
        $pos    = $this->row;
        foreach ($this as $row) {
            if (!array_key_exists($group, $row)) continue;
            if (isset($column)) {
                if (!array_key_exists($column, $row)) continue;
                $groups[$row[$group]][] = $row[$column];
            } else {
                $groups[$row[$group]][] = $row;
            }
        }
        $this->rewind($pos);
        return $groups;
    }

    /**
     * Returns the first row element from the result.
     *
     * @param string $column The column name to use as value (optional)
     *
     * @return mixed A row array or a single scalar value
     */
    public function first($column = null)
    {
        $pos   = $this->row;
        $first = $this->fetch(0, $column);
        $this->rewind($pos);
        return $first;
    }

    /**
     * Returns the last row element from the result.
     *
     * @param string $column The column name to use as value (optional)
     *
     * @return mixed A row array or a single scalar value
     */
    public function last($column = null)
    {
        $pos  = $this->row;
        $last = $this->fetch($this->count - 1, $column);
        $this->rewind($pos);
        return $last;
    }

    /**
     * Countable interface implementation
     *
     * @return int The number of rows in the result
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * Iterator interface implementation.
     *
     * @return mixed The current element
     */
    public function current()
    {
        return $this->fetch($this->row);
    }

    /**
     * Iterator interface implementation.
     *
     * @return int The current element key (row index; zero-based)
     */
    public function key()
    {
        return $this->row;
    }

    /**
     * Iterator interface implementation.
     *
     * @return void
     */
    public function next()
    {
        $this->row++;
    }

    /**
     * Iterator interface implementation.
     *
     * @param int $row Row position to rewind to; defaults to 0
     *
     * @return void
     */
    public function rewind($row = 0)
    {
        if ($this->seek($row)) {
            $this->row = $row;
        }
    }

    /**
     * Iterator interface implementation.
     *
     * @return bool Boolean true if the current index is valid, false otherwise
     */
    public function valid()
    {
        return $this->row < $this->count;
    }

    /**
     * Frees the result.
     *
     * @return bool Boolean true on success, false otherwise
     */
    public function free()
    {
        if (isset($this->result)) {
            mysqli_free_result($this->result);
            $this->result = null;
            return true;
        }
        return false;
    }

    /**
     * Object destructor.
     */
    public function __destruct()
    {
        $this->free();
    }
}

