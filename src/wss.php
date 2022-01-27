<?php

// require necessary files here

// create new server instance
$server = new \Bloatless\WebSocket\Server('127.0.0.1', 1198, '/tmp/phpwss.sock');

// server settings
$server->setMaxClients(10);
$server->setCheckOrigin(false);
$server->setAllowedOrigin('example.com');
$server->setMaxConnectionsPerIp(20);

// add your applications
$server->registerApplication('status', \Bloatless\WebSocket\Application\StatusApplication::getInstance());
//$server->registerApplication('chat', \Bloatless\WebSocket\Examples\Application\Chat::getInstance());

// start the server
$server->run();
