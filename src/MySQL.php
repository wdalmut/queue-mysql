<?php
namespace Corley\Queue\MySQL;

use PDO;
use RuntimeException;
use Corley\Queue\QueueInterface;

class MySQL implements QueueInterface
{
    private $pdo;
    private $options;

    public function __construct(PDO $pdo, array $options = [])
    {
        $this->pdo = $pdo;
        $this->options = array_replace_recursive([
            "table_queue" => "queues",
            "table_message" => "messages",
        ], $options);
    }

    public function send($queueName, $message, array $options)
    {
        $queue = $this->getQueue($queueName);

        $insertedOn = time() + ((array_key_exists("delay", $options)) ? $options["delay"] : $queue["default_delay"]);

        $sql = "INSERT INTO `{$this->options["table_message"]}` (queue_id, body, inserted_on) VALUES (?,?,?)";
        $this->pdo->prepare($sql)->execute([
            $queue["id"],
            $message,
            $insertedOn,
        ]);
    }

    public function receive($queueName, array $options)
    {
        $queue = $this->getQueue($queueName);

        $timeout = array_key_exists("timeout", $options) ? $options["timeout"] : $queue["default_timeout"];

        $consumer = mt_rand() . "-" . time();

        $sql = <<<EOF
UPDATE `{$this->options["table_message"]}` SET consumer=?, timeout=?, read_on=UNIX_TIMESTAMP(NOW())
WHERE (read_on IS NULL OR (UNIX_TIMESTAMP(NOW()) > read_on + timeout)) AND UNIX_TIMESTAMP(NOW()) >= inserted_on
ORDER BY inserted_on, id ASC
LIMIT 1
EOF
        ;

        $query = $this->pdo->prepare($sql);
        $query->execute([$consumer, $timeout]);

        if (!$query->rowCount()) {
            return [false, false];
        }

        $query = "SELECT * FROM `{$this->options["table_message"]}` WHERE consumer = ? LIMIT 1";
        $query = $this->pdo->prepare($query);
        $query->execute([$consumer]);
        $message = $query->fetch(PDO::FETCH_ASSOC);

        $query = "UPDATE `{$this->options["table_message"]}` SET read_times = ? WHERE id = ?";
        $this->pdo->prepare($query)->execute([++$message["read_times"], $message["id"]]);

        return [$message["id"], $message["body"]];
    }

    public function delete($queueName, $receipt, array $options)
    {
        $queue = $this->getQueue($queueName);

        $query = "DELETE FROM `{$this->options["table_message"]}` WHERE id = ? OR (inserted_on < (UNIX_TIMESTAMP(NOW()) - ?) AND read_times > 0)";
        $query = $this->pdo->prepare($query);
        $query->execute([$receipt, $queue["retention"]]);

        return $query->rowCount();
    }

    private function getQueue($queueName)
    {
        $queueQuery = "SELECT * FROM `{$this->options["table_queue"]}` WHERE name = ?";
        $queueQuery = $this->pdo->prepare($queueQuery);

        $queueQuery->execute([$queueName]);

        $queue = $queueQuery->fetch(PDO::FETCH_ASSOC);

        if (!$queue) {
            throw new RuntimeException("Missing queue with name: '{$queueName}'");
        }

        return $queue;
    }
}
