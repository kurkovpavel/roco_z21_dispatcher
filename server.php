<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

$ws_worker = new Worker("websocket://0.0.0.0:8000");

$ws_worker->onWorkerStart = function($ws_worker)
{
    $con = new TcpConnection(STDIN);
    $con->onMessage = function($con, $msg) use ($ws_worker) {
		foreach($ws_worker->connections as $connection)
		{
			$connection->send($msg);
		}
    };
};

$ws_worker->count = 1;
$ws_worker->onConnect = function($connection)
{
    echo "New connection\n";
 };

$ws_worker->onMessage = function($connection, $data)
{
	echo '[DATA'.$data.'DATA]';
};

$ws_worker->onClose = function($connection)
{
    echo "Connection closed\n";
};

Worker::runAll();

