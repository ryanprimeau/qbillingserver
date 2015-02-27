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


// Development
/* Development
$connectionParams = array(
    'user' => 'pbuser',
    'password' => 'pbpassword',
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'paladinbilling',
    'unix_socket' => '/tmp/mysql.sock',
    'driver' => 'pdo_mysql'
);
*/
// Production
$connectionParams = array(
    'user' => 'pbuser',
    'password' => 'pbpassword',
    'host' => 'qbilling.cesofi1lg9dc.us-east-1.rds.amazonaws.com',
    'port' => '3306',
    'dbname' => 'innodb',
    'driver' => 'pdo_mysql'
);


$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => $connectionParams,
));

$app->get('/', function() use($app){
  return $app->json("hello");
});

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



//Billable Get API
$app->get('/billables', function (Request $request) use($app){
  
  $email = $request->headers->get("Php-Auth-User");
  $password = $request->headers->get("Php-Auth-Pw");
  
  $sql = "SELECT id FROM user_profiles WHERE emailAddress = ? AND password = ?";
  $check = $app['db']->fetchAll($sql, array($email,$password));

  if(count($check) == 1){
    $sql = "SELECT * FROM patient_bill WHERE userID = ?";
    $results = $app['db']->fetchAll($sql,array((int)$check[0]["id"]));
    return $app->json(array("success"=>1,"results"=>$results));
  }
  return $app->json(array("success"=>0));
});



//Billable Manipulation API
$app->post('/billables', function (Request $request) use($app){
  
  
  
  $email = $request->headers->get("Php-Auth-User");
  $password = $request->headers->get("Php-Auth-Pw");
  
  $sql = "SELECT id FROM user_profiles WHERE emailAddress = ? AND password = ?";
  $check = $app['db']->fetchAll($sql, array($email,$password));
  
  if(count($check) != 1){
    return $app->json(array("success"=>0));
  }
  
  $id = (int)$check[0]["id"];
  
  
  $billings = $request->request->get('billings');
  $returnArray = array();  
  $returnArray["created"] = array();
  $returnArray["updated"] = array();
  $returnArray["deleted"] = array();

  foreach ($billings as $bill) {
    $bill_array = array("billedFlag" => $bill["billed"],
    "completedFlag" => $bill["completed"],
    "date" => $bill["date"],
    "diagnosis" => $bill["diagnosis"],
    "endTime" => $bill["endTime"],
    "hospital" => $bill["hospital"],
    "image" => $bill["labelPhotoData"],
    "location" => $bill["location"],
    "ramq" => $bill["medicalKey"],
    "patientFullName" => $bill["name"],
    "phone" => $bill["phone"],
    "precedures" => $bill["precedures"],
    "referringphysician" => $bill["referringphysician"],
    "note" => $bill["specialNote"],
    "startTime" => $bill["startTime"],
    "visitCode" => $bill["visitcode"]);
    
    if($bill['status'] == "create"){
      $bill_array['userID'] = $id ;
      $s = $app['db']->insert('patient_bill',$bill_array);
      $returnArray["created"][] = array("localKey" => $bill["localKey"],"serverPrimaryKey" => (int)$app['db']->lastInsertId()); 
    }
    if($bill['status'] == "update"){
      $app['db']->update('patient_bill',$bill_array,array('billId' => (int)$bill["serverPrimaryKey"]));
      $returnArray["updated"][] = array("localKey" => $bill["localKey"]);
    }
    if($bill['status'] == "delete"){
      $app['db']->delete('patient_bill', array('billId' => (int)$bill["serverPrimaryKey"]));
      $returnArray["deleted"][] = array("localKey" => $bill["localKey"]); 
    }
  }
  return $app->json(array("success"=>1,"results"=>$returnArray));
});


$app->run(); 
