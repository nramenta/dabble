# Dabble

Dabble is a lightweight wrapper and collection of helpers for MySQLi.

## Installation

The prefered way to install Dabble is through [composer][Composer]; the minimum
composer.json configuration is:

```
{
    "require": {
        "dabble/dabble": "@dev"
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

Every parameter binding will be escaped using the `mysqli_real_escape_string()`
function. String parameters will be properly quoted before inserted into the
query while `true` and `false` will be converted into `1` and `0` respectively.
The `Result` object returned from `query()` implements `Iterator` and
`Count`.

Parameters in the form of arrays will automatically be transformed and inserted
into the query as a comma separated list. The following call:

```php
<?php
$posts = $db->query('SELECT * FROM `posts` WHERE `id` IN (:search)', array(
    'search' => array(12, 24, 42, 68, 75)
));
```

will result in the execution of:

    SELECT * FROM `posts` WHERE `id` IN (12,24,42,68,75)

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

```php
<?php
$db->insert('posts', array(
    'title' => 'This is a new post!',
    'body'  => 'How convenient.',
), $id);
echo 'Last insert id = ' . $id;
```

### Update

```php
<?php
$db->update('posts', array(
        'title' => 'Lets change the title',
    ),
    '`id` = :id AND `published` = :published',
    array('id' => 42, 'published' => true)
);
```

### Upsert

Upsert is MySQL's INSERT INTO ... ON DUPLICATE KEY UPDATE ... construct:

```php
<?php
$db->upsert('posts', array(
    'id' => 1,
    'title' => 'First Post!'
), '`title` = :title', array('title' => 'Update: First Post!'));
```

### Delete

```php
<?php
$db->delete('posts', '`published` = :published', array('published' => true));
```

### Replace

Replace is MySQL's extension of SQL which is equivalent to insert or delete and
then re-insert if row exists:

```php
<?php
$db->replace('posts',
    array('id' => 1, 'title' => 'Override', 'body' => 'test.'));
```

## License

Dabble is released under the [MIT License][MIT].

[Composer]: http://getcomposer.org/
[MIT]: http://en.wikipedia.org/wiki/MIT_License

