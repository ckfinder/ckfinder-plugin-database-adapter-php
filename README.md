# CKFinder 3 Database Storage Plugin

This is an CKFinder 3 plugin that adds a support for storing files in the database.

This plugin is based on PHP [PDO extension](http://php.net/manual/en/book.pdo.php).

## Plugin Installation

See the [Plugin Installation and Configuration](http://docs.cksource.com/ckfinder3-php/plugins.html#plugins_installation_and_configuration) documentation.

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

This plugin registers a new backend adapter type named `database`. To make use of the adapter, define a new backend with
`adapter` set to `database`, and provide required configuration options like presented below:

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

**Adapter-specific configuration options**

| Option name | Description |
|-------------|-------------|
| `dsn`       | The Data Source Name, or DSN, contains the information required to connect to the database. Please have a look at [PDO driver-specific documentation](http://php.net/manual/en/pdo.drivers.php) for details. |
| `tableName` | The name of the table in the database. |
| `username`  | The user name for the DSN string (optional for some PDO drivers). |
| `password`  | The password for the DSN string (optional for some PDO drivers). |

When the backend is configured, you can use it in the resource type:

```php
$config['resourceTypes'][] = array(
    'name'              => 'Database Files',
    'backend'           => 'database_backend'
);
```

# Note

This plugin emulates a tree structured filesystem, therefore some of the operations (like renaming or deleting a folder)
may produce quite a lot of database queries, which results in a poor performance for some scenarios.