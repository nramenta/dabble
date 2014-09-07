<?php

use Dabble\Database;
use Dabble\Result;

/**
 * @requires extension mysqli
 */
class ResultTest extends Dabble_TestCase
{
    public function testSeek()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $result->seek(1);
        $this->assertEquals(2, $result->fetch(null, 'id'));
        $result->seek();
        $this->assertEquals(1, $result->fetch(null, 'id'));
    }

    public function testFetchFields()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $fields = $result->fetch_fields(true);
        $this->assertTrue(is_array($fields));
        $this->assertEquals(5, count($fields));
    }

    public function testFetch()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $this->assertTrue(is_array($result->fetch()));
        $this->assertEquals(2, $result->fetch(null, 'id'));
        $this->assertEquals(3, $result->fetch(2, 'id'));
    }

    public function testFetchOne()
    {
        $result = $this->db->query('SELECT * FROM `post` LIMIT 1');
        $this->assertTrue(is_array($result->fetch_one()));
        $result->seek();
        $this->assertEquals(1, $result->fetch_one('id'));
    }

    public function testFetchAll()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $posts = $result->fetch_all();
        $this->assertTrue(is_array($posts));
        $this->assertEquals(3, count($posts));

        $post_ids = $result->fetch_all('id');
        $this->assertTrue(is_array($posts));
        $this->assertEquals(3, count($posts));
    }

    public function testFetchTranspose()
    {
        $result = $this->db->query('SELECT * FROM `post` ORDER BY `id` ASC');

        $transposed = $result->fetch_transpose();
        $this->assertEquals(5, count($transposed));
        foreach ($transposed as $column => $rows) {
            $this->assertEquals(3, count($rows));
        }

        $transposed = $result->fetch_transpose('id');
        foreach ($transposed as $column => $rows) {
            $this->assertEquals(3, count($rows));
            $this->assertEquals(array(1,2,3), array_keys($rows));
        }
    }

    public function testFetchPairs()
    {
        $result = $this->db->query('SELECT * FROM `post`');

        $pairs = $result->fetch_pairs('id');
        foreach ($pairs as $id => $row) {
            $this->assertEquals($id, $row['id']);
        }

        $pairs = $result->fetch_pairs('id', 'title');
        foreach ($pairs as $id => $title) {
            $this->assertEquals("Title #{$id}", $title);
        }
    }

    public function testFetchGroups()
    {
        $result = $this->db->query('SELECT * FROM `post` ORDER BY `id` ASC');
        $groups = $result->fetch_groups('id');
        foreach ($groups as $id => $group) {
            $this->assertEquals(1, count($group));
            $this->assertEquals($id, $group[0]['id']);
        }
        $groups = $result->fetch_groups('id', 'title');
        $this->assertEquals(array(1 => array('Title #1'), 2 => array('Title #2'), 3 => array('Title #3')), $groups);
    }

    public function testFirst()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $this->assertTrue(is_array($result->first()));
        $this->assertEquals(1, $result->first('id'));
    }

    public function testLast()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $this->assertTrue(is_array($result->last()));
        $this->assertEquals(3, $result->last('id'));
    }

    public function testCount()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $this->assertEquals(3, $result->count());
        $this->assertEquals(3, count($result));
    }

    public function testNumRows()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $this->assertEquals(3, $result->num_rows());
        $this->assertEquals(3, $result->num_rows);
    }

    public function testFoundRows()
    {
        $result = $this->db->query("SELECT SQL_CALC_FOUND_ROWS * FROM `post` LIMIT 1 OFFSET 1");
        $this->assertEquals(3, $result->found_rows);
    }

    public function testIterator()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        foreach ($result as $row) {
            $this->assertTrue(is_array($row));
        }
    }

    public function testFetchInIterator()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        foreach ($result as $i => $row) {
            $this->assertEquals($i + 1, $row['id']);
            $this->assertEquals(3, count($result->fetch_all()));
            $this->assertEquals(1, $result->first('id'));
            $this->assertEquals(2, $result->fetch(1, 'id'));
            $this->assertEquals(3, $result->last('id'));
        }
    }

    public function testFree()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $this->assertTrue($result->free());
    }

    public function testInvoke()
    {
        $result = $this->db->query('SELECT * FROM `post`');
        $this->assertTrue($result() instanceof \MySQLi_Result);

        $ids = array();
        $result(function($result) use (&$ids) {
            while ($row = mysqli_fetch_assoc($result)) {
                $ids[] = $row['id'];
            }
        });
        $this->assertEquals(3, count($ids));
    }
}

