# Dabble

Dabble is a lightweight wrapper and collection of helpers for MySQLi.

## Installation

The prefered way to install Dabble is through [composer][Composer]; the minimum
composer.json configuration is:

```
{
    "require": {
        "dabble/dabble": "@stable"
    }
}
```

PHP 5.3 or newer with the `mysqli` extension enabled is required. Dabble is
developed and tested against MySQL 5.1+.

## Usage

The following is a typical Dabble usage:

```php
<?php
require_once 'path/to/Dabble/Database.php';
require_once 'path/to/Dabble/Result.php';

use Dabble\Database;

$db = new Database('localhost', 'user', 'pass', 'test');

$posts = $db->query(
    'SELECT `title`, `body` FROM `posts` WHERE `tenant_id` = :tenant_id',
    array('tenant_id' => 42)
);

echo 'There are ' . count($posts) . 'posts:' . PHP_EOL;

foreach ($posts as $post) {
    echo $post['title'] . PHP_EOL;
    echo str_repeat('=', strlen($post['title'])) . PHP_EOL;
    echo $post['body'] . PHP_EOL;
}
```

The full constructor parameters are:
- `$host`: Server host.
- `$username`: Server username.
- `$password`: Server password.
- `$database`: Database name.
- `$charset`: Server connection character set; defaults to utf8.
- `$port`: Server connection port; defaults to 3306
- `$socket`: Server connection socket, optional.

While the `query()` method's parameters are:
- `$sql`: SQL string.
- `$bindings`: Array of key-value bindings.

Every parameter binding will be escaped using the `mysqli_real_escape_string()`
function. String parameters will be properly quoted before inserted into the
query while `true` and `false` will be converted into `1` and `0` respectively.
The `Result` object returned from `query()` implements `Iterator` and
`Count`. Errors will yield a `RuntimeException`.

Parameters in the form of arrays will automatically be transformed and inserted
into the query as a comma separated list. The following:

```php
<?php
$posts = $db->query('SELECT * FROM `posts` WHERE `id` IN (:search)', array(
    'search' => array(12, 24, 42, 68, 75)
));
```

Will execute the SQL:

```
SELECT * FROM `posts` WHERE `id` IN (12,24,42,68,75)
```

## Optional SQL fragments

SQL passed to the `query()` method and CRUD helper methods may contain optional
SQL fragments delimited by `[` and `]`. These fragments will be removed from the
final SQL if not all placeholders used inside them exist inside the parameter
binding. This results in a more coherent way of building queries:

```php
<?php
$params = array();

$params['tenant_id'] = $_SESSION['tenant_id'];
if (isset($_GET['title'])) $params['title'] = '%' . $_GET['title'] . '%';

$posts = $db->query(
    'SELECT * FROM `posts` WHERE `tenant_id` = :tenant_id [AND title = :title]',
    $params
);
```

In the above example, the `[AND title = :title]` part will be removed if
`$params['title']` does not exist. You can nest as many of these optional SQL
fragments as you need. Unbalanced `[` and `]` delimiters is considered to be
an error and will yield a `RuntimeException`.

## Transactions

Use `begin()`, `commit()`, and `rollback()` to manage transactions:

```php
<?php
try {
    $db->begin();
    $db->query('UPDATE `users` SET `bal` = `bal` - :amount WHERE id = :id',
        array('amount' => 100, 'id' => 1));
    $db->query('UPDATE `users` SET `bal` = `bal` + :amount WHERE id = :id',
        array('amount' => 100, 'id' => 2));
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
}
```

Any SQL errors between `begin()` and `commit()` will yield a `RuntimeException`.

## Result querying

### $num_rows

Even though the `Result` object implements `Countable`, the number of rows is
also available as a public member:

```php
<?php
$posts = $db->query('SELECT * FROM `posts`');
echo 'This result has ' . $posts->num_rows . ' rows.';
```

### $found_rows

If you use `SQL_CALC_FOUND_ROWS` in your SELECT queries, you can find the number
of rows the result would have returned without the LIMIT clause:

```php
<?php
$posts = $db->query(
    'SELECT SQL_CALC_FOUND_ROWS * FROM `posts` LIMIT 10 OFFSET 0'
);
echo 'Showing ' . $posts->num_rows . ' posts out of ' . $posts->found_rows;
```

This is very useful for things like paginations. If your query does not use
`SQL_CALC_FOUND_ROWS`, accessing `$found_rows` will give you the same number as
`$num_rows`.

### fetch

Fetches a row or a single column within a row:

```php
<?php
$data = $result->fetch($row_number, $column);
```

This method forms the basis of all fetch_ methods. All forms of fetch_ advances
the internal row pointer to the next row. `null` will be returned when there are
no more rows to be fetched.

### fetch_one

Fetches the next row:

```php
<?php
$next_row = $result->fetch_one();
```

Pass a column name as argument to return a single column from the next row:

```php
<?php
$name = $result->fetch_one('name');
```

### fetch_all

Returns all rows at once as an array:

```php
<?php
$users = $result->fetch_all();
```

Pass a column name as argument to return an array of scalar column values:

```php
<?php
$all_tags = $posts->fetch_all('tags');
```

### fetch_pairs

Returns all rows at once as key-value pairs using the column in the first
argument as the key:

```php
<?php
$countries = $result->fetch_pairs('id');
```

Pass a column name as the second argument to only return a single column as the
value in each pair:

```php
<?php
$countries = $result->fetch_pairs('id', 'name');
```

### fetch_groups

Returns all rows at once as a grouped array:

```php
<?php
$students_grouped_by_gender = $result->fetch_groups('gender');
```

Pass a column name as the second argument to only return single columns as the
values in each groups:

```php
<?php
$student_names_grouped_by_gender = $result->fetch_groups('gender', 'name');
```

### first

Returns the first row element from the result:

```php
<?php
$first = $result->first();
```

Pass a column name as argument to return a single column from the first row:

```php
<?php
$name = $result->first('name');
```

### last

Returns the last row element from the result:

```php
<?php
$last = $result->last();
```

Pass a column name as argument to return a single column from the last row:

```php
<?php
$name = $result->last('name');
```

## CRUD helpers

### Insert

Inserts a row into a table. Returns `true` on success, `false` otherwise.

Parameters:
- `$table`: The table name.
- `$data`: The row array to insert.
- `$insert_id`: The last insert ID, optional.

The following:

```php
<?php
$db->insert('posts', array(
    'title' => 'This is a new post!',
    'body'  => 'How convenient.',
), $id);
echo 'Last insert id = ' . $id;
```

Will execute the SQL:

```
INSERT INTO `posts` (`title`, `body`) VALUES ('This is a new post!',
'How convenient.')
```

To manually get the last insert ID:

```php
<?php
if ($db->insert('posts', $post)) {
    $id = $db->insert_id();
    echo 'The last insert ID is ' . $id;
}
```

### Update

Updates a row in a table. Returns `true` on success, `false` otherwise.

Parameters:
- `$table`: The table name.
- `$data`: The row array to insert.
- `$where`: Where-clause; can contain placeholders.
- `$args`: Array of key-value bindings for the where-clause.

The following:

```php
<?php
$db->update('posts',
    array(
        'title' => 'Lets change the title',
    ),
    '`id` = :id AND `published` = :published',
    array('id' => 42, 'published' => true)
);
```

Will execute the SQL:

```
UPDATE `posts` SET `title` = 'Lets change the title' WHERE `id` = 42 AND
`published` = 1
```

The `$where` parameter can also be an array of simple key-value comparisons. The
following is equivalent to the above:

```php
<?php
$db->update('posts',
    array('title' => 'Lets change the title'),
    array('id' => 42, 'published' => true)
);
```

### Upsert

Upsert is MySQL's INSERT INTO ... ON DUPLICATE KEY UPDATE ... construct. Returns
`true` on success, `false` otherwise.

Parameters:
- `$table`: The table name.
- `$data`: The row array to insert.
- `$duplicate`: Duplicate-clause; can contain placeholders.
- `$args`: Array of key-value bindings for the duplicate-clause.
- `$insert_id`: The last insert ID, optional.

The following:

```php
<?php
$db->upsert('posts',
    array(
        'id' => 1,
        'title' => 'First Post!'
    ),
    '`title` = :title',
    array('title' => 'Update: First Post!')
);
```

Will execute the SQL:

```
INSERT INTO `posts` (`id`, `title`) VALUES (1, 'First Post!') ON DUPLICATE KEY
UPDATE `title` = 'Update: First Post!'
```

As in the `Database::update()` method, the `$duplicate` parameter can also be an
array of simple key-value comparisons. The following is equivalent to the above:

```php
<?php
$db->upsert('posts',
    array(
        'id' => 1,
        'title' => 'First Post!'
    ),
    array('title' => 'Update: First Post!')
);
```

### Delete

Deletes a row in a table. Returns `true` on success, `false` otherwise.

Parameters:
- `$table`: The table name.
- `$where`: Where-clause; can contain placeholders.
- `$args`: Array of key-value bindings for the where-clause.

The following:

```php
<?php
$db->delete('posts', '`published` = :published', array('published' => true));
```

Will execute the SQL:

```
DELETE `posts` WHERE `published` = 1
```

As in the `Database::update()` method, the `$where` parameter can also be an
array of simple key-value comparisons. The following is equivalent to the above:

```php
<?php
$db->delete('posts', array('published' => true));
```

### Replace

Replace is MySQL's extension of SQL which is equivalent to insert or delete and
then re-insert if row exists. Returns `true` on success, `false` otherwise.

Parameters:
- `$table`: The table name.
- `$data`: Array of column-value pairs of data.
- `$insert_id`: The last insert ID, optional.

The following:

```php
<?php
$db->replace('posts',
    array('id' => 1, 'title' => 'Override', 'body' => 'test.')
);
```

Will execute the SQL:

```
REPLACE INTO `posts` (`id`, `title`, `body`) VALUES (1, 'Override', 'test.')
```

### Truncate

Truncates a table. Returns `true` on success, `false` otherwise.

Parameters:
- `$table`: The table name.
- `$auto_increment`: Auto-increment number; optional, defaults to 1.

The following:

```php
<?php
$db->truncate('posts');
```

Will execute the SQL:

```
TRUNCATE `posts`;
ALTER TABLE `posts` AUTO_INCREMENT = 1
```

## License

Dabble is released under the [MIT License][MIT].

[Composer]: http://getcomposer.org/
[MIT]: http://en.wikipedia.org/wiki/MIT_License

