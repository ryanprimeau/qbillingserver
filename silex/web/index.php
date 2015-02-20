<?php


// web/index.php
require_once __DIR__.'/../vendor/autoload.php'; 

use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

// set the error handling
ini_set('display_errors', 1);
error_reporting(-1);
ErrorHandler::register();
if ('cli' !== php_sapi_name()) {
  ExceptionHandler::register();
}

$app = new Silex\Application();
$app['debug'] = true;

$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

$config = new \Doctrine\DBAL\Configuration();
$connectionParams = array(
    'user' => 'pbuser',
    'password' => 'pbpassword',
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'paladinbilling',
    'unix_socket' => '/tmp/mysql.sock',
    'driver' => 'pdo_mysql'
);
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => $connectionParams,
));


$app->get('/getAllusers', function() use($app){
  $sql = "SELECT id,fullName FROM user_profiles";
  //var_dump($conn);
  $results = $app['db']->fetchAll($sql);
//  var_dump($results);
  return $app->json($results);
});


//Authentication API
$app->get('/authentication', function (Request $request) use($app){
  $email = $request->headers->get("Php-Auth-User");
  $sql = "SELECT count(*) FROM user_profiles WHERE emailAddress = ?";
  //Is this safe from SQL Injection??
  $post = $app['db']->fetchAssoc($sql, array($email));
  $returnArray = array("success"=>0);
  if($post["count(*)"]>0){
    $returnArray["success"] = 1;
  }
  return $app->json($returnArray);
});

//Billable Manipulation API
$app->post('/billables', function (Request $request) use($app){
  $post = array(
      'email' => $request->request->get('email'),
  );
  
  $billings = $request->request->get('billings');
  
  foreach ($billings as $bill) {
    var_dump($bill);
  }
    
  return $app->json($post);
});


$app->run(); 
