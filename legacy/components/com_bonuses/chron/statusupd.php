<?php 

$mysqli = new mysqli("localhost", "freeextr_dfiocch", "<REDACTED:password>", "freeextr_fec25");

/* check connection */
if ($mysqli->connect_errno) {
    printf("Connect failed: %s\n", $mysqli->connect_error);
    exit();
} else {


}

$datetimenow = date('Y-m-d H:i:s');


$status = 'expired';

$statement = $mysqli->prepare("UPDATE jos_bonuses SET status=? WHERE end IS NOT NULL AND end != '0000-00-00 00:00:00' AND end <= NOW()");

//bind parameters for markers, where (s = string, i = integer, d = double,  b = blob)
$statement->bind_param('s', $status);
$results =  $statement->execute();
if($results){
    print 'Success! record expired updated'; 
}else{
    print 'Error : ('. $mysqli->errno .') '. $mysqli->error;
}

$statuspend = 'active';
$added = $datetimenow;

$statementpend = $mysqli->prepare("UPDATE jos_bonuses SET status=?,added=? WHERE start IS NOT NULL AND start != '0000-00-00 00:00:00' AND start <= NOW() AND status LIKE 'pending'");

//bind parameters for markers, where (s = string, i = integer, d = double,  b = blob)
$statementpend->bind_param('ss', $statuspend,$added);
$resultspend =  $statementpend->execute();
if($resultspend){
    print 'Success! record expired updated'; 
}else{
    print 'Error : ('. $mysqli->errno .') '. $mysqli->error;
}
 
  
    if ($mysqli->error) {
       printf("Errormessage: %s\n", $mysqli->error);
    }
 
 
 // Frees the memory associated with a result
//$results->free();

// close connection 
$mysqli->close();

?>  