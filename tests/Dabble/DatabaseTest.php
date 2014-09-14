<?php

use Dabble\Database;
use Dabble\Result;

/**
 * @requires extension mysqli
 */
class DatabaseTest extends Dabble_TestCase
{
    public function testOpenConnection()
    {
        $this->assertNotNull($this->db->open());
    }

    public function testPingConnection()
    {
        $this->assertEquals('boolean', gettype($this->db->ping()));
    }

    public function testBeginTransaction()
    {
        $this->assertTrue($this->db->begin());
    }

    public function testCommitTransaction()
    {
        $before = $this->db->query('SELECT COUNT(1) AS `total` FROM `post`')->first('total');
        $this->db->begin();
        $this->db->insert('post', array(
            'title' => 'post ' . mt_rand(),
            'body' => 'lolzors!',
        ));
        $this->assertTrue($this->db->commit());
        $after = $this->db->query('SELECT COUNT(1) AS `total` FROM `post`')->first('total');
        $this->assertTrue($after == $before + 1);
    }

    public function testRollbackTransaction()
    {
        $before = $this->db->query('SELECT COUNT(1) AS `total` FROM `post`')->first('total');
        $this->db->begin();
        $this->db->insert('post', array(
            'title' => 'post ' . mt_rand(),
            'body' => 'lolzors!',
        ));
        $this->assertTrue($this->db->rollback());
        $after = $this->db->query('SELECT COUNT(1) AS `total` FROM `post`')->first('total');
        $this->assertTrue($after == $before);
    }

    public function testTransaction()
    {
        $this->assertTrue($this->db->transact(function($db) {
            $db->insert('post', array(
                'title' => 'post' . mt_rand(),
                'body' => 'lolzors!',
            ));
        }));

        $this->assertFalse($this->db->transact(function($db) {
            throw new \Exception;
        }));

        $this->assertFalse($this->db->transact(function($db) {
            $db->begin();
        }));
    }

    /**
     * @expectedException \LogicException
     */
    public function testTransactionLogicException()
    {
        $this->db->begin();
        $this->db->begin();
    }

    /**
     * @expectedException \LogicException
     */
    public function testTransactionLogicException2()
    {
        $this->db->commit();
    }

    /**
     * @expectedException \LogicException
     */
    public function testTransactionLogicException3()
    {
        $this->db->rollback();
    }

    public function testReturnInsertId()
    {
        $this->db->insert('post', array(
            'title' => 'post ' . mt_rand(),
            'body' => 'lolzors!',
        ), $id);
        $this->assertNotNull($this->db->insert_id());
        $this->assertEquals($id, $this->db->insert_id());
    }

    public function testEscapeData()
    {
        $this->assertEquals(null, $this->db->escape(null));
        $this->assertEquals('null', $this->db->escape(null, true));
        $this->assertEquals(mysqli_real_escape_string($this->link, "O'Toole"), $this->db->escape("O'Toole"));
        $this->assertEquals("'" . mysqli_real_escape_string($this->link, "O'Toole") . "'", $this->db->escape("O'Toole", true));
        $this->assertEquals(1, $this->db->escape(true));
        $this->assertEquals(0, $this->db->escape(false));
        $this->assertEquals("'1'", $this->db->escape(true, true));
        $this->assertEquals("'0'", $this->db->escape(false, true));
        $this->assertEquals('NOW()', $this->db->escape($this->db->literal('NOW()')));
        $this->assertEquals("CONCAT('foo', 'bar')", $this->db->escape($this->db->literal('CONCAT(:foo, :bar)', array('foo' => 'foo', 'bar' => 'bar'))));
        $this->assertEquals("CONCAT('foo', 'bar')", $this->db->escape($this->db->literal('CONCAT(:foo, :bar[, :baz])', array('foo' => 'foo', 'bar' => 'bar'))));
        $this->assertEquals(array(mysqli_real_escape_string($this->link, "O'Toole"), 1, null), $this->db->escape(array("O'Toole", true, null)));
        $this->assertEquals(array("'" . mysqli_real_escape_string($this->link, "O'Toole") . "'", "'1'", 'null'), $this->db->escape(array("O'Toole", true, null), true));
    }

    public function testStripQuery()
    {
        $this->assertEquals('SELECT * FROM `post` WHERE 1=1', $this->db->strip('SELECT * FROM `post` WHERE 1=1 [AND `title` = :title]', array()));
        $this->assertEquals("SELECT * FROM `post` WHERE 1=1 AND `title` = :title", $this->db->strip('SELECT * FROM `post` WHERE 1=1 [AND `title` = :title [AND `body` = :body]]', array('title')));
        $this->assertEquals("SELECT * FROM `post` WHERE 1=1", $this->db->strip('SELECT * FROM `post` WHERE 1=1 [AND `title` = :title [AND `body` = :body]]', array('body')));
        $this->assertEquals("SELECT * FROM `post` WHERE 1=1 AND `title` = :title AND `body` = :body", $this->db->strip('SELECT * FROM `post` WHERE 1=1 [AND `title` = :title AND `body` = :body]', array('title', 'body')));
    }

    public function testFormatQuery()
    {
        $this->assertEquals('SELECT * FROM `post` WHERE `id` = 1', $this->db->format('SELECT * FROM `post` WHERE `id` = :id', array('id' => 1)));
    }

    public function testFormatLiterals()
    {
        $format = $this->db->format('views = :views', array('views' => $this->db->literal('views + 1')));
        $this->assertEquals('views = views + 1', $format);

        $format = $this->db->format(':literal', array(
            'literal' => $this->db->literal('CONCAT(:foo, :bar)', array(
                'foo' => 'foo',
                'bar' => 'bar',
            )),
        ));
        $this->assertEquals("CONCAT('foo', 'bar')", $format);
    }

    public function testQueryResult()
    {
        $this->assertEquals('Dabble\Result', get_class($this->db->query('SELECT * FROM `post`')));
    }

    public function testFoundAndNumRows()
    {
        $n = 2;
        $total = $this->db->query('SELECT COUNT(1) AS `total` FROM `post`')->first('total');
        $posts = $this->db->query("SELECT SQL_CALC_FOUND_ROWS * FROM `post` LIMIT $n OFFSET 1");
        $this->assertEquals($total, $posts->found_rows);
        $this->assertEquals($n, $posts->num_rows);
    }

    public function testCloseConnection()
    {
        $this->db->open();
        $this->assertTrue($this->db->close());
        $db = $this->db;
        $this->assertEquals(null, $db());
    }

    public function testSelectFromTable()
    {
        $this->assertEquals('Dabble\Result', get_class($this->db->select('post')));
    }

    public function testInsertRow()
    {
        $before = $this->db->query('SELECT COUNT(1) AS `total` FROM `post`')->first('total');
        $ret = $this->db->insert('post', array(
            'title' => 'post ' . mt_rand(),
            'body' => 'lolzors!',
        ));
        $this->assertTrue($ret);
        $after = $this->db->query('SELECT COUNT(1) AS `total` FROM `post`')->first('total');
        $this->assertTrue($after == $before + 1);
    }

    public function testUpdateRow()
    {
        $row = $this->db->query('SELECT * FROM `post` LIMIT 1')->first();
        $row['title'] = 'New Title #' . mt_rand();
        $this->db->update('post', array('title' => $row['title']), array('id' => $row['id']));
        $update = $this->db->query('SELECT * FROM `post` WHERE `id` = :id LIMIT 1', array('id' => $row['id']))->first();
        $this->assertEquals($row['title'], $update['title']);
    }

    public function testUpsertRow()
    {
        $before = $this->db->query('SELECT * FROM `post` WHERE `id` = 1 LIMIT 1')->first();
        $this->db->upsert('post', array(
                'id' => 1,
                'title' => 'First Post!',
            ),
            '`title` = :title',
            array('title' => 'Update: First Post!')
        );
        $after = $this->db->query('SELECT * FROM `post` WHERE `id` = 1 LIMIT 1')->first();
        $this->assertNotEquals($before['title'], $after['title']);
    }

    public function testDeleteRow()
    {
        $row = $this->db->query('SELECT * FROM `post` LIMIT 1')->first();
        $this->db->delete('post', array('id' => $row['id']));
        $exists = $this->db->query('SELECT COUNT(1) AS `total` FROM `post` WHERE `id` = :id', array('id' => $row['id']))->first('total');
        $this->assertEquals(0, $exists);
    }

    public function testReplaceRow()
    {
        $before = $this->db->query('SELECT * FROM `post` WHERE `id` = 1 LIMIT 1')->first();
        $this->db->replace('post', array('id' => 1, 'title' => 'Replaced title', 'body' => 'Replaced body'));
        $after = $this->db->query('SELECT * FROM `post` WHERE `id` = 1 LIMIT 1')->first();
        $this->assertEquals($after['title'], 'Replaced title');
        $this->assertEquals($after['body'], 'Replaced body');
    }

    public function testTruncateTable()
    {
        $this->db->truncate('post');
        $total = $this->db->query('SELECT COUNT(1) AS `total` FROM `post`')->first('total');
        $this->assertEquals(0, $total);
    }

    public function testInvoke()
    {
        $this->db->open();
        $db = $this->db;
        $this->assertTrue($db() instanceof \MySQLi);
        $this->assertTrue($db('SELECT * FROM `post`') instanceof Result);
    }
}

