<?php
namespace Dabble;

/**
 * Database result class
 */
class Result implements \Countable, \SeekableIterator, \ArrayAccess
{
    protected $result;
    protected $row;

    protected $mapper;

    public $num_rows;
    public $found_rows;

    public $limit;
    public $offset;
    public $num_pages;
    public $page;

    /**
     * Object constructor.
     *
     * @param mixed $result    Resource returned by db::query or mysqli_query
     * @param callable $mapper Optional callback mapper for the fetch method
     */
    public function __construct(\MySQLi_Result $result, $mapper = null)
    {
        $this->result = $result;
        $this->row = 0;

        $this->mapper = $mapper;

        $this->num_rows = mysqli_num_rows($result);
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
        if (is_int($row) && $row >= 0 && $row < $this->num_rows) {
            return mysqli_data_seek($this->result, $row);
        } else {
            return false;
        }
    }

    /**
     * Return rows of field information in a result set. This function is a
     * basically a wrapper on the native mysqli_fetch_fields function.
     *
     * @param bool $as_array Return each field info as array; defaults to false
     *
     * @return array Array of field information each as an associative array
     */
    public function fetch_fields($as_array = false)
    {
        if ($as_array) {
            return array_map(function($object) {
                return (array) $object;
            }, mysqli_fetch_fields($this->result));
        } else {
            return mysqli_fetch_fields($this->result);
        }
    }

    public function map($callable)
    {
        $this->mapper = $callable;
        return $this;
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
        if (!$this->num_rows) {
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
            return is_callable($this->mapper) ?
                call_user_func($this->mapper, $row) : $row;
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
     * Returns all rows at once, transposed as an array of arrays. Instead of
     * returning rows of columns, this method returns columns of rows.
     *
     * @param string $column The column name to use as keys (optional)
     *
     * @return mixed A transposed array of arrays
     */
    public function fetch_transpose($column = null)
    {
        $keys = isset($column) ? $this->fetch_all($column) : array();
        $rows = array();
        $pos  = $this->row;
        foreach ($this as $row) {
            foreach ($row as $key => $value) {
                $rows[$key][] = $value;
            }
        }
        $this->rewind($pos);
        return empty($keys) ? $rows : array_map(function($values) use ($keys) {
            return array_combine($keys, $values);
        }, $rows);
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
        $last = $this->fetch($this->num_rows - 1, $column);
        $this->rewind($pos);
        return $last;
    }

    public function slice($offset = 0, $length = null, $preserve_keys = false)
    {
        $slice = array();
        $offset = (int) $offset;

        if ($offset < 0) {
            if (abs($offset) > $this->num_rows) {
                $offset = 0;
            } else {
                $offset = $this->num_rows - abs($offset);
            }
        }

        $length = isset($length) ? (int) $length : $this->num_rows;

        $n = 0;
        for ($i = $offset; $i < $this->num_rows && $n < $length; $i++) {
            if ($preserve_keys) {
                $slice[$i] = $this->fetch($i);
            } else {
                $slice[] = $this->fetch($i);
            }
            $n += 1;
        }
        return $slice;
    }

    /**
     * Countable interface implementation.
     *
     * @return int The number of rows in the result
     */
    public function count()
    {
        return $this->num_rows;
    }

    /**
     * Alias of count(). Deprecated.
     *
     * @return int The number of rows in the result
     */
    public function num_rows()
    {
        return $this->count();
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
        return $this->row < $this->num_rows;
    }

    /**
     * ArrayAccess interface implementation.
     *
     * @param int $offset Offset number
     *
     * @return bool Boolean true if offset exists, false otherwise
     */
    public function offsetExists($offset)
    {
        return is_int($offset) && $offset >= 0 && $offset < $this->num_rows;
    }

    /**
     * ArrayAccess interface implementation.
     *
     * @param int $offset Offset number
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->fetch($offset);
        } else {
            throw new \OutOfBoundsException("undefined offset ($offset)");
        }
    }

    /**
     * ArrayAccess interface implementation. Not implemented by design.
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('trying to change result item');
    }

    /**
     * ArrayAccess interface implementation. Not implemented by design.
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException('trying to unset result item');
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
     * Runs a user-provided callback with the MySQLi_Result object given as
     * argument and returns the result, or returns the MySQLi_Result object if
     * called without an argument.
     *
     * @param callable $callback User-provided callback (optional)
     *
     * @return mixed|MySQLi_Result
     */
    public function __invoke($callback = null)
    {
        if (isset($callback)) {
            return call_user_func($callback, $this->result);
        } else {
            return $this->result;
        }
    }

    /**
     * Object destructor.
     */
    public function __destruct()
    {
        $this->free();
    }
}

