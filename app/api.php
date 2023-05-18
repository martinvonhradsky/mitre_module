<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Origin, Authorization");

require_once('database.php');

function isID($id) {
  // Check if the ID matches the regular expression /T[0-9]+\.[0-9]+/i
  if (preg_match('/T[0-9]+\.[0-9]+/i', $id)) {
    return true;
  }
  if (preg_match('/T[0-9]+/i', $id)) {
    return true;
  }
  return false;
}


# Get execution output
function getTestOutput($str) {
  // remove newline characters
  $str = str_replace("\n", '', $str);
  $str = str_replace("\r", '', $str);
  // remove extra whitespace
  $str = preg_replace('/\s+/', ' ', $str);
  // split string into parts
  $parts = explode("stdout_lines", $str);
  return substr($parts[1], 3, -2);
}

# Get timespamp of start and end of execution from 
# remove execution output from string and edit string to be valid json 
function getResult($str, $metadata) {
  $metadata = "'".substr($metadata, 3, -1)."'";
  $metadata = str_replace("\\", "", $metadata);
  $hack = explode('"', $metadata);
  $metadata = json_decode($metadata, true);

  $test_output = getTestOutput($str);
  $str = explode("stdout", $str)[0];
  $new_str = "";
  $last_comma_pos = strrpos($str, ",");
  if ($last_comma_pos !== false) {
    $new_str = substr($str, 0, $last_comma_pos) . "}}";
  }
  if ($new_str !== ""){
      $json_execution = json_decode($new_str);
      $metadata = json_decode($metadata);
      $output = array (
          "test_id" => $hack[3],
          "target" => $hack[7],
          "start" => $json_execution->result->start,
          "end" => $json_execution->result->end,
          "output" => $test_output,
      );
      return $output;
  }else{
      echo '{"Error" : "Data processing failed"}';
      return null;
  }
}
# Parse execution string
function parseExecution($response) {
  $response = urldecode($response);
  # Get metadata from response
  $metadata = preg_split("/\[start\]/", $response, 0)[0];
  # Get test-result JSON from response
  $test_result = preg_split("/\*{5,}/", $response);
  # Get PLAY RECAP part of output to validate execution
  $validation = $test_result[count($test_result) - 1];
  # Get Execution output
  $test_result = $test_result[count($test_result) - 2];
  # filter out validation part
  $test_result = explode("PLAY RECAP", $test_result, 2)[0];
  # get clean JSON data
  $test_result = explode(">", $test_result, 2)[1];

  ##### VALIDATE EXECUTION
  # If output contains unreachable=0 => target device is not reachable => exit
  if (strpos($validation, 'unreachable=0') == false){
    echo "Unreachable: " . $test_result ;
    return null;
  }
  else{
    # If execution failed, display message
    if (strpos($validation, 'failed=0') == false){
        echo "Execution failed: " . $test_result ;
        return null;
    }
    else{
        $json = getResult($test_result, $metadata);        
        $json = json_encode($json);      
    }
  }
    if ($json == null){
      echo '{"Error" : "Saving test history failed: Unable to parse response."}';
    } else {
      return $json;
    }

}

function saveHistory($json, $detected) {
  try {
      // Create a new database object
      $db = new Database();
      $db->connect();
      
      // Prepare the insert statement with placeholders
      $sql = "INSERT INTO history (test_id, target, start_time, end_time, output, detected)
              VALUES (:test_id, :target, :start, :end, :output, :detected)";

      $stmt = $db->prepare($sql);
      // Extract the parameters from the JSON data and bind the values to the placeholders
      $data = json_decode($json, true);
      $test_id = $data['test_id'];
      $target = $data['target'];
      $start = $data['start'];
      $end = $data['end'];
      $output = $data['output'];
      $detected_param = $detected ? 'true' : 'false';

      $stmt->bindValue(':test_id', $test_id);
      $stmt->bindValue(':target', $target);
      $stmt->bindValue(':start', $start);
      $stmt->bindValue(':end', $end);
      $stmt->bindValue(':output', $output);
      $stmt->bindValue(':detected', $detected_param);

      // Execute the statement and return the test ID
      $check = $stmt->execute();
      if($check){
        echo "History saving was successfull";
      }else{
        echo "History saving failed.";
      }

      $db = null;
      // Return test_id for status update for this id
      return explode('-', $test_id)[0];
  } catch (PDOException $e) {
      echo '{"status": "error","message":  " Failed to save test history: ' . $e->getMessage() . '"}';
      return null;
  }
}

function executeQuery($select) {
  try {
      // Create a new database object
      $db = new Database();
      $db->connect();
      
      // Prepare the select statement with placeholders
      $stmt = $db->prepare($select);
      $stmt->execute();

      // Fetch all the rows and return as JSON
      $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if($result == null){          
          echo '[{"Error":"Nothing to return."}]';
          return null;
      }
      return json_encode($result);
  } catch (PDOException $e) {
      return "Connection failed: " . $e->getMessage();
  }
}
function editUser($ip, $username, $password, $platform, $alias) {
  try {
    // Create a new database object
    $db = new Database();
    $db->connect();
      
    $query = "SELECT * FROM target WHERE alias = :alias ;";
    $stmt = $db->prepare($query);    
    $stmt->bindValue('alias', $alias);
    $stmt->execute();

    if ($stmt->fetchAll(PDO::FETCH_ASSOC) == null){
      echo "No such user available";
      var_dump(http_response_code(204));
      return;
    }
      $query = "UPDATE target SET ";
      $params = array();

      if ($ip !== null && $ip !== "") {
          $query .= "IP = :IP, ";
          $params['IP'] = $ip;
      }
      if ($username !== null && $username !== "") {
          $query .= "sudo_user = :sudo_user, ";
          $params['sudo_user'] = $username;
      }
      if ($password !== null && $password !== "") {
          $query .= "password = :password, ";
          $params['password'] = $password;
      }
      if ($platform !== null && $platform !== "") {
          $query .= "platform = :platform, ";
          $params['platform'] = $platform;
      }
      
      // Remove trailing comma
      $query = rtrim($query, ", ");
      $query .= " WHERE alias = :alias";
      $params['alias'] = $alias;
      
      $stmt = $db->prepare($query);
      $stmt->execute($params);      
      echo '{"status": "success","message": "User updated successfully"}';
  } catch (PDOException $e) {
      echo '{"status": "error","message":  " Update Target failed: ' . $e->getMessage() . '"}';
  }
  
}

function deleteUser($alias) {

  try {
      // Create a new database object
      $db = new Database();
      $db->connect();
      
      $query = "DELETE FROM target WHERE alias = :alias";
      

      $stmt = $db->prepare($query);
      $stmt->bindValue('alias', $alias);
      $stmt->execute();
      
      echo '{"status": "success","message": " Target deleted successfully"}';
  } catch (PDOException $e) {
      echo '{"status": "error","message": " Target deletion failed failed: ' . $e->getMessage() . '"}';
  }

}

function insertTest($num, $filename, $executable, $description, $local, $name, $technique_id) {
  // Validate inputs
  echo "0";
  if (strcasecmp($local, "TRUE") === 0) {
    $local = true;
  } else {
    $local = false;
  }
  if (empty($name)) {
    throw new InvalidArgumentException("Name cannot be empty");
  }
  if (empty($technique_id)) {
    throw new InvalidArgumentException("Technique ID cannot be empty");
  }
  echo "1";
  try {
    echo "2";
    $db = new Database();
    $db->connect();

      echo "3";
    $stmt = $db->prepare("INSERT INTO tests ( technique_id, test_number, name, executable, local_execution, description, file_name) VALUES
     (:technique_id, :test_number, :name, :executable, :local_execution, :description, :file_name)");

    $stmt->bindParam(':technique_id', $technique_id);
    $stmt->bindParam(':test_number', $num);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':executable', $executable);
    $stmt->bindParam(':local_execution', $local, PDO::PARAM_BOOL);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':file_name', $filename);
    echo "4";
    $result = $stmt->execute();
    echo "5";
    if ($result) {
      return true;
    } else {
      return false;
    }
  } catch (PDOException $e) {
    // Log error message
    error_log("Error inserting test into database: " . $e->getMessage());
    return false;
  }
}

function getAnsibleOutput() {
  // Check if the output file exists
  if (!file_exists('output.txt')) {
    return json_encode(['end' => false, 'output' => 'Output file not found.']);
  }
  
  // Read the output file contents
  $output = file_get_contents('output.txt');
  
  // Check if the output contains the end marker
  $end = strpos($output, 'PLAY RECAP') !== false;
  if($end){
    // Remove Ansible hosts content
    exec('echo "" > /etc/ansible/hosts');
  }
  // Construct the response object
  $response = ['end' => $end, 'output' => $output];
  
  // Return the response as JSON
  return json_encode($response);
}



function saveTest($json_data){

  // Create a new database object
  $db = new Database();
  $db->connect();

  $url = $json_data->url;
  $filename = $json_data->filename;
  $executable = $json_data->executable;
  $description = $json_data->desc;
  $local = $json_data->local;
  $name = $json_data->name;
  $id = $json_data->id;
  if(!isID($id)){
    echo "Error: ID is no valid";
    exit;
  } else {
    $query = "SELECT COUNT(*) > 0 AS exists FROM mitre WHERE id = '$id';";
    $result = executeQuery($query); 
    if(strpos($result,"false")){
      echo "Error: ID does not match any technique ID";
      exit;
    }
  }
  
  $query = "SELECT MAX(test_number) FROM tests WHERE technique_id = '$id';";
  $result = executeQuery($query);  
  $data = json_decode($result, true);
  $max = (int) $data[0]['max'];  

  if($max == null){
      $num=1;
  }else{
      $num = $max + 1;
  }
  
  if($json_data->git){
      $command = "../engine/custom_test.sh -i " . $id . " -u " . $url . " -n " . $num . " -g true";
  }else{
      $command = "../engine/custom_test.sh -i " . $id . " -u " . $url . " -n " . $num . " -f ". $filename;
  }
  
  echo shell_exec($command);

  $result = insertTest($num, $filename, $executable, $description, $local, $name, $id);
  
  if($result){
      echo "Test saving was successful.";
  }else{
      echo "Test saving failed.";
  }
}

if (isset($_GET['action'])) {
  switch ($_GET['action']) {
    case 'startpage':
      $query = "SELECT id, name, tactics, startpage FROM mitre WHERE id NOT LIKE '%.%';";
      $result = executeQuery($query);
      echo $result;
      break;

    case 'specific':
      if (isset($_GET['id'])) {
        $id = $_GET['id'];
        if(isID($id)){
          $query = "SELECT id, name, description, url, tactics, platforms        
                    FROM mitre
                    WHERE id LIKE '$id' OR id LIKE '$id.%'
                    ORDER BY id;";
          
          $result = executeQuery($query);
          echo $result;
        } else {
          echo "ID: " . $id ." is not valid ID";
        }
        
      }
      break;
    case 'test_by_id':
      if (isset($_GET['id'])) {
        $id = $_GET['id'];
        if(isID($id)){
          $query = "SELECT technique_id, test_number, name FROM tests WHERE technique_id = '$id' ORDER BY test_number;";
          $result = executeQuery($query);
          echo $result;
        } else {
          echo "ID: " . $id ." is not valid ID";
        }
      }
    break;
    case 'targets':
      $query = "SELECT alias, ip FROM target";
      $result = executeQuery($query);
      echo $result; 
      break;
    case 'target_detail':
      if(isset($_GET['alias'])){
        $alias = $_GET['alias'];
        $query = "SELECT IP, sudo_user, alias, platform FROM target WHERE alias = '$alias'";
        $result = executeQuery($query);
        echo $result; 
      
        
      }
      break;
    case 'history':
      $target = null;
      $id = $_GET['id'];
      if(isID($id)){
        $id = explode(".", $id)[0];
        echo executeQuery("SELECT * FROM history WHERE test_id LIKE '$id%' ORDER BY test_id;");
        
      } else {
        echo "ID: " . $id ." is not valid ID";
      }
      break;
    case 'result':
      echo getAnsibleOutput();
      break;

  }
  
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // retrieve the data sent with the request
  $request_body = file_get_contents('php://input');
  $json_data = json_decode($request_body);
  if (isset($json_data->action)) {
    switch($json_data->action){
      case 'history':
        $processed = parseExecution($json_data->execution);
        if ($processed !== null){
          $id = saveHistory($processed, $json_data->detected);
          # get top level id
          $root_id = explode('.',$id)[0];
          
          # handle Coloring
          if($json_data->detected){
            # update status of particular technique to DETECTED            
            $query="UPDATE mitre SET status = 'detected' WHERE id = '$id' ;";
            executeQuery($query);          
            
            # check if any subtechiques have status EXECUTED
            $query="SELECT status FROM mitre WHERE status = 'executed' AND id LIKE '$root_id';";
            $result = executeQuery($query);
            echo "result: " . $result;
            if(strlen($result) == null){
              echo "detected";
              $query="UPDATE mitre SET startpage = 'detected' WHERE id = '$root_id' ;";

            }
            
          } else {
            echo "executed";
            # update status of particular technique to EXECUTED            
            $query="UPDATE mitre SET status = 'executed' WHERE id = '$id' ;";
            executeQuery($query);
            # check get top level id 
            $root_id = explode('.',$id)[0];
            
            $query="UPDATE mitre SET startpage = 'executed' WHERE id = '$root_id' ";            
          }
          executeQuery($query);
        }
        break;
      case 'edit_target':
        $ip = $json_data->ip;
        $username = $json_data->username;
        $password = $json_data->password;
        $platform = $json_data->platform;
        $alias = $json_data->alias;            
        editUser($ip, $username, $password, $platform, $alias);
        break;
      case 'create_target':              
        // Get user data from POST request
        $ip = $json_data->ip;
        $username = $json_data->username;
        $password = $json_data->password;
        $alias = $json_data->alias;
        $platform = $json_data->platform;
        $query = "INSERT INTO target(IP, sudo_user, password, alias, platform) VALUES ('$ip', '$username', '$password', '$alias', '$platform')";
        $result = executeQuery($query);
        echo $result;
        break;
      case 'test': 
        saveTest($json_data);
        break;
      default:
        echo "Unknown action";
        break;
    }  
  } else {
    echo "Action parameter is missing";
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  // retrieve the data sent with the request
  $request_body = file_get_contents('php://input');
  $json_data = json_decode($request_body);
  if ($json_data && isset($json_data->action)) {
    switch($json_data->action){
      case 'target':
        if (isset($json_data->alias)) {
          $alias = (string) $json_data->alias;
          deleteUser($alias);
        } else {
          echo "Alias parameter is missing";
        }
        break;
      default:
        echo "Unknown action";
        break;
    }
  } else {
    echo "Action parameter is missing";
  }
}

?>
