<?php

use Dabble\Database;
use Dabble\Result;

/**
 * @requires extension mysqli
 */
class ResultTest extends Dabble_TestCase
{
    public function testResult()
    {
        $result = $this->db->query('SELECT * FROM `post` ORDER BY `id` ASC');
        $this->assertTrue($result instanceof Result);
        return $result;
    }

    /**
     * @depends testResult
     */
    public function testSeek($result)
    {
        $result->seek(1);
        $this->assertEquals(2, $result->fetch(null, 'id'));
        $result->seek();
        $this->assertEquals(1, $result->fetch(null, 'id'));
    }

    /**
     * @depends testResult
     */
    public function testSeek2($result)
    {
        $this->assertFalse($result->seek(3));
    }

    public function testSeek3()
    {
        $result = $this->db->query('SELECT * FROM `post` WHERE id > 100');
        $this->assertFalse($result->seek());
    }

    /**
     * @depends testResult
     */
    public function testFetchFields($result)
    {
        $fields = $result->fetch_fields(true);
        $this->assertTrue(is_array($fields));
        $this->assertEquals(5, count($fields));
    }

    /**
     * @depends testResult
     */
    public function testFetch($result)
    {
        $result->seek();
        $this->assertTrue(is_array($result->fetch()));
        $this->assertEquals(2, $result->fetch(null, 'id'));
        $this->assertEquals(3, $result->fetch(2, 'id'));
    }

    /**
     * @depends testResult
     */
    public function testFetchOne($result)
    {
        $result->seek();
        $this->assertTrue(is_array($result->fetch_one()));
        $result->seek();
        $this->assertEquals(1, $result->fetch_one('id'));
    }

    /**
     * @depends testResult
     */
    public function testFetchAll($result)
    {
        $posts = $result->fetch_all();
        $this->assertTrue(is_array($posts));
        $this->assertEquals(3, count($posts));

        $post_ids = $result->fetch_all('id');
        $this->assertTrue(is_array($posts));
        $this->assertEquals(3, count($posts));
    }

    /**
     * @depends testResult
     */
    public function testFetchTranspose($result)
    {
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

    /**
     * @depends testResult
     */
    public function testFetchPairs($result)
    {
        $pairs = $result->fetch_pairs('id');
        foreach ($pairs as $id => $row) {
            $this->assertEquals($id, $row['id']);
        }

        $pairs = $result->fetch_pairs('id', 'title');
        foreach ($pairs as $id => $title) {
            $this->assertEquals("Title #{$id}", $title);
        }
    }

    /**
     * @depends testResult
     */
    public function testFetchGroups($result)
    {
        $groups = $result->fetch_groups('id');
        foreach ($groups as $id => $group) {
            $this->assertEquals(1, count($group));
            $this->assertEquals($id, $group[0]['id']);
        }
        $groups = $result->fetch_groups('id', 'title');
        $this->assertEquals(array(1 => array('Title #1'), 2 => array('Title #2'), 3 => array('Title #3')), $groups);
    }

    /**
     * @depends testResult
     */
    public function testFirst($result)
    {
        $this->assertTrue(is_array($result->first()));
        $this->assertEquals(1, $result->first('id'));
    }

    public function testFirst2()
    {
        $result = $this->db->query('SELECT * FROM `post` WHERE id > 100');
        $this->assertNull($result->first());
    }

    /**
     * @depends testResult
     */
    public function testLast($result)
    {
        $this->assertTrue(is_array($result->last()));
        $this->assertEquals(3, $result->last('id'));
    }

    public function testLast2()
    {
        $result = $this->db->query('SELECT * FROM `post` WHERE id > 100');
        $this->assertNull($result->last());
    }

    /**
     * @depends testResult
     */
    public function testSlice($result)
    {
        $slice = $result->slice(1);
        $this->assertTrue(is_array($slice));
        $this->assertEquals(2, count($slice));
        $this->assertEquals(3, $slice[1]['id']);

        $slice = $result->slice(-1);
        $this->assertTrue(is_array($slice));
        $this->assertEquals(1, count($slice));
        $this->assertEquals(3, $slice[0]['id']);

        $slice = $result->slice();
        $this->assertTrue(is_array($slice));
        $this->assertEquals(3, count($slice));
        $this->assertEquals(2, $slice[1]['id']);

        $slice = $result->slice(0, 100);
        $this->assertTrue(is_array($slice));
        $this->assertEquals(3, count($slice));
        $this->assertEquals(2, $slice[1]['id']);

        $slice = $result->slice(-100, 100);
        $this->assertTrue(is_array($slice));
        $this->assertEquals(3, count($slice));
        $this->assertEquals(2, $slice[1]['id']);

        $slice = $result->slice(-100);
        $this->assertTrue(is_array($slice));
        $this->assertEquals(3, count($slice));
        $this->assertEquals(2, $slice[1]['id']);

        $slice = $result->slice(100, 100);
        $this->assertTrue(is_array($slice));
        $this->assertEquals(0, count($slice));

        $slice = $result->slice(1, 1);
        $this->assertTrue(is_array($slice));
        $this->assertEquals(1, count($slice));
        $this->assertEquals(2, $slice[0]['id']);

        $slice = $result->slice(1, 1, true);
        $this->assertTrue(is_array($slice));
        $this->assertEquals(1, count($slice));
        $this->assertEquals(2, $slice[1]['id']);
    }

    /**
     * @depends testResult
     */
    public function testCount($result)
    {
        $this->assertEquals(3, $result->count());
        $this->assertEquals(3, count($result));
    }

    /**
     * @depends testResult
     */
    public function testNumRows($result)
    {
        $this->assertEquals(3, $result->num_rows());
        $this->assertEquals(3, $result->num_rows);
    }

    public function testFoundRows()
    {
        $result = $this->db->query("SELECT SQL_CALC_FOUND_ROWS * FROM `post` LIMIT 1 OFFSET 1");
        $this->assertEquals(3, $result->found_rows);
    }

    public function testPagination()
    {
        $result = $this->db->query("SELECT SQL_CALC_FOUND_ROWS * FROM `post` LIMIT 2 OFFSET 0");
        $this->assertEquals(1, $result->page);
        $this->assertEquals(2, $result->num_pages);

        $result = $this->db->query("SELECT SQL_CALC_FOUND_ROWS * FROM `post` LIMIT 2 OFFSET 2");
        $this->assertEquals(2, $result->page);
        $this->assertEquals(2, $result->num_pages);
    }

    /**
     * @depends testResult
     */
    public function testIterator($result)
    {
        foreach ($result as $row) {
            $this->assertTrue(is_array($row));
        }
    }

    /**
     * @depends testResult
     */
    public function testFetchInIterator($result)
    {
        foreach ($result as $i => $row) {
            $this->assertEquals($i + 1, $row['id']);
            $this->assertEquals(3, count($result->fetch_all()));
            $this->assertEquals(1, $result->first('id'));
            $this->assertEquals(2, $result->fetch(1, 'id'));
            $this->assertEquals(3, $result->last('id'));
        }
    }

    /**
     * @depends testResult
     */
    public function testArrayAccess($result)
    {
        $this->assertTrue(isset($result[0]));
        $this->assertTrue(is_array($result[0]));
        $this->assertEquals($result[0]['id'], 1);
    }

    /**
     * @depends testResult
     * @expectedException \LogicException
     */
    public function testArrayAccess2($result)
    {
        $result[0] = array('id' => 1);
    }

    /**
     * @depends testResult
     * @expectedException \LogicException
     */
    public function testArrayAccess3($result)
    {
        unset($result[1]);
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

    public function testMap()
    {
        $result = $this->db->query('SELECT * FROM `post`');

        $row = $result->fetch(0);
        $this->assertFalse($row instanceof \stdClass);

        $result->map(function($row) {
            return (object) $row;
        });
        $row = $result->fetch(0);
        $this->assertTrue($row instanceof \stdClass);

        $result->map(null);
        $row = $result->fetch(0);
        $this->assertFalse($row instanceof \stdClass);
    }
}

