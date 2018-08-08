# CKFinder 3 Database Storage Plugin

This is a CKFinder 3 plugin that adds support for storing files in the database.

This plugin is based on the PHP [PDO extension](http://php.net/manual/en/book.pdo.php).

## Plugin Installation

See the [Plugin Installation and Configuration](https://ckeditor.com/docs/ckfinder/ckfinder3-php/plugins.html#plugins_installation_and_configuration) documentation.

## Database Schema

At the beginning you have to create a table that will be used to store files. SQL table schema examples for MySQL and SQLite are presented below.

**MySQL**

```sql
CREATE TABLE files (
  id int(11) NOT NULL AUTO_INCREMENT,
  path varchar(255) NOT NULL,
  type enum('file','dir') NOT NULL,
  contents longblob,
  size int(11) NOT NULL DEFAULT 0,
  mimetype varchar(127),
  timestamp int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY path_unique (path)
);
```

**SQLite**

```sql
CREATE TABLE files (
    id INTEGER PRIMARY KEY,
    path TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL,
    contents BLOB,
    size INTEGER NOT NULL DEFAULT 0,
    mimetype TEXT,
    timestamp INTEGER NOT NULL DEFAULT 0
);
```

## Configuration Options

This plugin registers a new [backend adapter type](https://ckeditor.com/docs/ckfinder/ckfinder3-php/configuration.html#configuration_options_backends) named `database`. To use the adapter, define a new backend with
the `adapter` option set to `database`, and provide required configuration options as presented below:

```php
$config['backends'][] = array(
    'name'         => 'database_backend',
    'adapter'      => 'database',
    'dsn'          => 'mysql:host=hostname;dbname=dbname',
    'tableName'    => 'dbtable',
    'username'     => 'username',
    'password'     => 'password'
);
```

**Adapter-specific Configuration Options**

| Option name | Description |
|-------------|-------------|
| `dsn`       | The Data Source Name, or DSN, contains the information required to connect to the database. Have a look at the [PDO driver-specific documentation](http://php.net/manual/en/pdo.drivers.php) for details. |
| `tableName` | The name of the table in the database. |
| `username`  | The user name for the DSN string (optional for some PDO drivers). |
| `password`  | The password for the DSN string (optional for some PDO drivers). |

When the backend is configured, you can use it in the [resource type](https://ckeditor.com/docs/ckfinder/ckfinder3-php/configuration.html#configuration_options_resourceTypes):

```php
$config['resourceTypes'][] = array(
    'name'              => 'Database Files',
    'backend'           => 'database_backend'
);
```

## Note

This plugin emulates a tree-structured file system, therefore some of the operations (like renaming or deleting a folder)
may produce quite a lot of database queries, which results in a poor performance for some scenarios.

## License

Copyright (c) 2007-2016, CKSource - Frederico Knabben. All rights reserved.
For license details see: [LICENSE.md](https://github.com/ckfinder/ckfinder-plugin-database-adapter-php/blob/master/LICENSE.md).
