# MySQL Adapter

To use this package you need the `pdo_mysql` extension

```php
$adapter = new Corley\Queue\MySQL\MySQL($pdo);
```

Features:

 * `delay` - A message is visible after a delay
 * `timeout` - A message is invisible for a timeout after a receive
 * `retention` - A message is removed after a given amount of time

In the base queue you can set those parameters as defaults but you can override
them using options

```php
$queue->send("message", [ "delay" => 20 ]); // visible after 20 seconds
list($rpt, $msg) = $queue->receive(["timeout" => 900]); // invisible for 900 seconds
```

## Database schema

```sql
CREATE TABLE queues (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    default_delay INT(11) NOT NULL DEFAULT 0,
    default_timeout INT(11) NOT NULL,
    retention INT(11) NOT NULL,
    PRIMARY KEY(id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

ALTER TABLE `queues` ADD UNIQUE KEY `queues_unique_name` (`name`);

CREATE TABLE messages (
    id INT(11) NOT NULL AUTO_INCREMENT,
    queue_id INT(11) NOT NULL,
    body LONGBLOB NOT NULL,
    consumer VARCHAR(255) DEFAULT NULL,
    timeout INT(11) DEFAULT NULL,
    read_on INT(11) DEFAULT NULL,
    inserted_on INT(11) NOT NULL,
    read_times INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY(id),
    KEY `queue_idx` (`queue_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

ALTER TABLE `messages` ADD UNIQUE KEY `consumer_unique_name` (`consumer`);

ALTER TABLE messages
    ADD CONSTRAINT fk_queue_id
    FOREIGN KEY (queue_id) REFERENCES queues(id)
    ON DELETE CASCADE ON UPDATE CASCADE;
```

## Create a new queue

```sql
INSERT INTO `queues`
    (name, default_timeout, default_delay, retention)
VALUES
    ('example', 900, 20, 5760);
```

# Example

You should use `corley/queue:~1`

```php
$pdo = new PDO("mysql:dbname=test;host=127.0.0.1", "root", "root");
$adapter = new Corley\Queue\MySQL\MySQL($pdo);
$queue = new Corley\Queue\Queue("example", $adapter);

$queue->send("Hello");

list($receipt, $message) = $queue->receive(); // [1, "Hello"]

$queue->delete($receipt);
```

