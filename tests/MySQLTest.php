<?php
namespace Corley\Queue\MySQL;

use PDO;
use Corley\Queue\MySQL\MySQL;

class MySQLTest extends \PHPUnit_Framework_TestCase
{
    private $pdo;

    public function setUp()
    {
        $this->pdo = new PDO("mysql:dbname=test;host=127.0.0.1", "root", "root");
        $this->pdo->query("DELETE FROM messages");
    }

    public function testSendMessage()
    {
        $adapter = new MySQL($this->pdo);
        $adapter->send("testqueue", "test", []);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testNotifyMissingQueue()
    {
        $adapter = new MySQL($this->pdo);
        $adapter->send("not exists", "test", []);
    }

    public function testReceiveMessage()
    {
        $adapter = new MySQL($this->pdo);
        $adapter->send("testqueue", "test", ['delay' => 0]);

        list($receipt, $message) = $adapter->receive("testqueue", []);

        $this->assertNotFalse($receipt);
        $this->assertNotFalse($message);
    }

    public function testDeleteMessage()
    {
        $adapter = new MySQL($this->pdo);
        $adapter->send("testqueue", "test", ['delay' => 0]);

        list($receipt, $message) = $adapter->receive("testqueue", []);

        $this->assertSame(1, $adapter->delete("testqueue", $receipt, []));
    }
}
