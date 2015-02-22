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
  $password = $request->headers->get("Php-Auth-Pw");

  $sql = "SELECT count(*) FROM user_profiles WHERE emailAddress = ? AND password = ?";
  //Is this safe from SQL Injection??
  $post = $app['db']->fetchAssoc($sql, array($email,$password));
  $returnArray = array("success"=>false);
  if($post["count(*)"]>0){
    $returnArray["success"] = true;
  }
  return $app->json($returnArray);
});

//Billable Manipulation API
$app->post('/billables', function (Request $request) use($app){
  
  $billings = $request->request->get('billings');
  
  foreach ($billings as $bill) {
    //$date = new DateTime();
    //var_dump($bill["medicalKey"]);
    $app['db']->insert('patient_bill', array('userID' => 1,'ramq' => $bill["medicalKey"],'patientFullName'=>$bill["name"], 'phone' => $bill["phone"], 'date' => $bill["date"], 'precedures' => $bill["precedures"],
    'diagnosis'=>$bill["diagnosis"], 'referringphysician' => $bill["referringphysician"]));
  }
  
  return $app->json(array("top"=>$billings));
});


$app->run(); 
