<?php

use Utopia\Database\Document;
use Utopia\Database\Role;
use Utopia\Database\Validator\UID;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require __DIR__ . '/vendor/autoload.php';
}

use Utopia\Cache\Cache;
use Utopia\Database\Adapter\MySQL;
use Utopia\Database\Database;
use Utopia\Cache\Adapter\None as NoCache;

use Utopia\App;
use Utopia\Swoole\Request;
use Utopia\Swoole\Response;
use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Utopia\Database\Permission;
use Utopia\Validator\Integer;
use Utopia\Validator\Text;

$http = new Server("0.0.0.0", 8000);

App::setResource('database', function () {

  $dbHost = 'mysql';
  $dbPort = '3306';
  $dbUser = 'root';
  $dbPass = $_ENV['DB_PASS'];


  $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_TIMEOUT => 3, // Seconds
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => true,
    PDO::ATTR_STRINGIFY_FETCHES => true,
  ]);

  $cache = new Cache(new NoCache()); // or use any cache adapter you wish

  $database = new Database(new MySQL($pdo), $cache);

  $database->setNamespace('crud')->create('crud');

  return $database;
});

App::error()
  ->inject('error')
  ->inject('response')
  ->action(function ($error, $response) {
    $response
      ->setStatusCode(500)
      ->send('Error occurred ' . $error);
  });


App::get('/users/:id')
  ->param('id', '', new UID())
  ->inject('request')
  ->inject('response')
  ->inject('database')
  ->action(function (string $id, Request $request, Response $response, Database $database) {
    if (!strlen($id)) {
      return $response->setStatusCode(400)->json([
        "data" => null
      ]);
    }
    $user  = $database->getDocument('users', $id);
    if ($user->isEmpty()) {
      return $response->setStatusCode(404)->json([
        "data" => null
      ]);
    }
    $response->json([
      "data" => $user
    ]);
  });



App::post('/users/create')
  ->param('fname', '', new Text(100))
  ->param('lname', '', new Text(100))
  ->param('age', '', new Integer())
  ->inject('request')
  ->inject('response')
  ->inject('database')
  ->action(function (string $fname, string $lname, int $age, Request $request, Response $response, Database $database) {
    $user = $database->createDocument('users', new Document([
      '$permissions' => [
        Permission::read(Role::any()),
        Permission::update(Role::any()),
        Permission::delete(Role::any()),
      ],
      "fname" => $fname,
      "lname" => $lname,
      "age" => $age
    ]));
    if ($user->isEmpty()) {
      $response->setStatusCode(404)->json([
        "data" => null
      ]);
      return;
    }
    $response->setStatusCode(200)->json([
      "data" => $user
    ]);
  });

App::put('/users/:id')
  ->param('id', '', new UID())
  ->param('fname', '', new Text(100))
  ->param('lname', '', new Text(100))
  ->param('age', '', new Integer())
  ->inject('request')
  ->inject('response')
  ->inject('database')
  ->action(function (string $id, string $fname, string $lname, int $age, Request $request, Response $response, Database $database) {
    $user = $database->getDocument('users', $id);
    if($user->isEmpty()){
      return $response->setStatusCode(404)->send("user not found");
    }

    $user->setAttribute('fname', $fname);
    $user->setAttribute('lname', $lname);
    $user->setAttribute('age', $age);
    $updatedUser = $database->updateDocument('users', $id, $user);
    if ($updatedUser->isEmpty()) {
      $response->setStatusCode(404)->json([
        "data" => null
      ]);
      return;
    }
    $response->setStatusCode(200)->json([
      "data" => $updatedUser
    ]);
  });

App::delete('/users/:id')
  ->param('id', '', new UID())
  ->inject('request')
  ->inject('response')
  ->inject('database')
  ->action(function (string $id, Request $request, Response $response, Database $database) {
    $isDeleted  = $database->deleteDocument('users', $id);
    if (!$isDeleted) {
      return $response->setStatusCode(404)->send("User not found");
    }
    $response->send('user deleted');
  });


$http->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) {
  $request = new Request($swooleRequest);
  $response = new Response($swooleResponse);
  $app = new App('Asia/Tel_Aviv');

  try {
    $app->run($request, $response);
  } catch (\Throwable $th) {
    $swooleResponse->end('500: Server Error');
  }
});

$http->start();
