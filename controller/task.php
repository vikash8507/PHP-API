<?php

    require_once('./db.php');
    require_once('./../model/task.php');
    require_once('./../model/Response.php');

    try {
        $writeDB = DB::connectWriteDB();
        $readDB = DB::connectReadDB();
    } catch (PDOException $ex) {
        error_log("Connection error - ".$ex, 0);
        $res = new Response();
        $res->setHttpStatusCode(500);
        $res->setSuccess(false);
        $res->addMessage("Database connection failed!");
        $res->send();
        exit();
    }

    // Authentication START

    if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $res->setHttpStatusCode(401);
        $res->setSuccess(false);
        (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $res->addMessage("Access token is missing from the header") : false);
        (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $res->addMessage("Access token cannot be blank") : false); 
        $res->send();
        exit();
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    try{
          // create db query to check access token is equal to the one provided
        $query = $writeDB->prepare('select userid, accesstokenexpiry, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken = :accesstoken');
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->execute();

        // get row count
        $rowCount = $query->rowCount();

        if($rowCount === 0) {
            // set up response for unsuccessful log out response
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Invalid access token");
            $response->send();
            exit;
        }
        
        // get returned row
        $row = $query->fetch(PDO::FETCH_ASSOC);

        // save returned details into variables
        $returned_userid = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];
        
        // check if account is active
        if($returned_useractive != 'Y') {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("User account is not active");
            $response->send();
            exit;
        }

        // check if account is locked out
        if($returned_loginattempts >= 3) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("User account is currently locked out");
            $response->send();
            exit;
        }

        // check if access token has expired
        if(strtotime($returned_accesstokenexpiry) < time()) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Access token has expired");
            $response->send();
            exit;
        } 
    }
    catch(PDOException $ex) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("There was an issue authenticating - please try again");
        $response->send();
        exit;
    }
    

    // AUthentication END

    if(array_key_exists("taskId", $_GET)) {
        $taskId = $_GET["taskId"];
        
        if($taskId === '' || !is_numeric($taskId)) {
            $res = new Response();
            $res->setHttpStatusCode(400);
            $res->setSuccess(false);
            $res->addMessage("Task id must be a numeric value");
            $res->send();
            exit();
        }

        if($_SERVER['REQUEST_METHOD'] === 'GET') {

            try {
                $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') AS deadline, completed FROM tbltasks WHERE id = :taskId AND userid = :userid");
                $query->bindParam(':taskId',$taskId, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();
    
                $rowCount = $query->rowCount();
    
                if($rowCount <= 0 || $rowCount === 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(404);
                    $res->setSuccess(false);
                    $res->addMessage("Task not found");
                    $res->send();
                    exit();
                }
    
                $taskArray = array();
    
                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }
    
                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;
    
                $res = new Response();
                $res->setHttpStatusCode(200);
                $res->setSuccess(true);
                $res->toCache(true);
                $res->addMessage('Task found');
                $res->setData($returnData);
                $res->send();
                exit();
    
            } catch (TaskException $ex) {
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage($ex->getMessage());
                $res->send();
                exit();
            } catch (PDOException $ex) {
                error_log("Internal server error - ".$ex, 0);
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage("Internal server error");
                $res->send();
                exit();
            }
    
        } else if($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            
            try {
                $query = $readDB->prepare("DELETE FROM tbltasks WHERE id = :taskId AND userid = :userid");
                $query->bindParam(':taskId',$taskId, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();
    
                $rowCount = $query->rowCount();
    
                if($rowCount <= 0 || $rowCount === 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(404);
                    $res->setSuccess(false);
                    $res->addMessage("Task not found");
                    $res->send();
                    exit();
                }
    
                $res = new Response();
                $res->setHttpStatusCode(200);
                $res->setSuccess(true);
                $res->addMessage("Task deleted successfully");
                $res->send();
                exit();
    
            }  catch (TaskException $ex) {
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage($ex->getMessage());
                $res->send();
                exit();
            } catch (PDOException $ex) {
                error_log("Internal server error - ".$ex, 0);
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage("Internal server error");
                $res->send();
                exit();
            }
    
        } else if($_SERVER['REQUEST_METHOD'] === 'PATCH') {
            
            try{

                if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                    $res = new Response();
                    $res->setHttpStatusCode(400);
                    $res->setSuccess(false);
                    $res->addMessage('Content type header is not set to JSON');
                    $res->send();
                    exit();
                }

                $rawPatchData = file_get_contents("php://input");

                if(!$jsonData = json_decode($rawPatchData)) {
                    $res = new Response();
                    $res->setHttpStatusCode(500);
                    $res->setSuccess(false);
                    $res->addMessage("Request body is not valid JSON");
                    $res->send();
                    exit();
                }

                $title_updated = false;
                $description_updated = false;
                $deadline_updated = false;
                $completed_updated = false;

                $queryFields = "";

                if(isset($jsonData->title)) {
                    $title_updated = true;
                    $queryFields .= "title = :title, ";
                }

                if(isset($jsonData->description)) {
                    $description_updated = true;
                    $queryFields .= "description = :description, ";
                }

                if(isset($jsonData->deadline)) {
                    $deadline_updated = true;
                    $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
                }

                if(isset($jsonData->completed)) {
                    $completed_updated = true;
                    $queryFields .= "completed = :completed, ";
                }

                $queryFields = rtrim($queryFields, ", ");

                if($title_updated===false && $description_updated===false && $deadline_updated===false && $completed_updated===false){
                    $res = new Response();
                    $res->setHttpStatusCode(400);
                    $res->setSuccess(false);
                    $res->addMessage('No field provide for update');
                    $res->send();
                    exit();
                }

                $query = $writeDB->prepare("SELECT id, title, description, deadline, completed FROM tbltasks WHERE id = :taskId AND userid = :userid");
                $query->bindParam(':taskId', $taskId, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount === 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(404);
                    $res->setSuccess(false);
                    $res->addMessage("Task not found");
                    $res->send();
                    exit();
                }

                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $dateTime = strtotime($row['deadline']);
                    $newDateFormat = date('d/m/Y H:i', $dateTime);
                    $task = new Task($row['id'], $row['title'], $row['description'], $newDateFormat, $row['completed']);
                }

                $queryString = "UPDATE tbltasks SET ".$queryFields." WHERE id = :taskId AND userid = :userid";
                $query = $writeDB->prepare($queryString);
                
                if($title_updated === true) {
                    $task->setTitle($jsonData->title);
                    $up_title = $task->getTitle();
                    $query->bindParam(":title", $up_title, PDO::PARAM_STR);
                }

                if($description_updated === true) {
                    $task->setDescription($jsonData->description);
                    $up_description = $task->getDescription();
                    $query->bindParam(":description", $up_description, PDO::PARAM_STR);
                }

                if($deadline_updated === true) {
                    $task->setDeadline($jsonData->deadline);
                    $up_deadline = $task->getDeadline();
                    $query->bindParam(":deadline", $up_deadline, PDO::PARAM_STR);
                }

                if($completed_updated === true) {
                    $task->setCompleted($jsonData->completed);
                    $up_completed = $task->getCompleted();
                    $query->bindParam(":completed", $up_completed, PDO::PARAM_STR);
                }

                $query->bindParam(":taskId", $taskId, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount === 0) {
                    // set up response for unsuccessful return
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage("Task not updated - given values may be the same as the stored values");
                    $response->send();
                    exit;
                }
                  
                // create db query to return the newly edited task - connect to master database
                $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid AND userid = :userid');
                $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();
                
                $rowCount = $query->rowCount();

                if($rowCount === 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(404);
                    $res->setSuccess(false);
                    $res->addMessage("No task found after update");
                    $res->send();
                    exit();
                }

                $taskArray = array();
    
                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    echo $row['id'];
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }
    
                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;
    
                $res = new Response();
                $res->setHttpStatusCode(200);
                $res->setSuccess(true);
                $res->toCache(true);
                $res->addMessage('Task updated');
                $res->setData($returnData);
                $res->send();
                exit();

            } catch (TaskException $ex) {
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage($ex->getMessage());
                $res->send();
                exit();
            } catch (PDOException $ex) {
                error_log("Internal server error - ".$ex, 0);
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage("Internal server error".$ex);
                $res->send();
                exit();
            }
            
        } else {
            $res = new Response();
            $res->setHttpStatusCode(405);
            $res->setSuccess(false);
            $res->addMessage("Request method not allowed.");
            $res->send();
            exit();
        }
    }
    else if(array_key_exists("completed", $_GET)) {
        $completed = $_GET["completed"];

        if ($completed !== 'Y' && $completed !== 'N') {
            $res = new Response();
            $res->setHttpStatusCode(400);
            $res->setSuccess(false);
            $res->addMessage("Completed must be 'Y' or 'N' ");
            $res->send();
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {

            try {
                $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') AS deadline, completed FROM tbltasks WHERE completed = :completed AND userid = :userid");
                $query->bindParam(':completed',$completed, PDO::PARAM_STR);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();
    
                $rowCount = $query->rowCount();
    
                if($rowCount <= 0 || $rowCount === 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(404);
                    $res->setSuccess(false);
                    $res->addMessage("No tasks found");
                    $res->send();
                    exit();
                }
    
                $taskArray = array();
    
                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }
    
                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;
    
                $res = new Response();
                $res->setHttpStatusCode(200);
                $res->setSuccess(true);
                $res->toCache(true);
                $res->addMessage('Task found');
                $res->setData($returnData);
                $res->send();
                exit();
    
            } catch (TaskException $ex) {
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage($ex->getMessage());
                $res->send();
                exit();
            } catch (PDOException $ex) {
                error_log("Internal server error - ".$ex, 0);
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage("Internal server error");
                $res->send();
                exit();
            }

        } else {
            $res = new Response();
            $res->setHttpStatusCode(405);
            $res->setSuccess(false);
            $res->addMessage("Request method not allowed.");
            $res->send();
            exit();
        }
    }
    else if(array_key_exists("page", $_GET)) {

        $page = $_GET["page"];

        if($page === '' || !is_numeric($page)) {
            $res = new Response();
            $res->setHttpStatusCode(400);
            $res->setSuccess(false);
            $res->addMessage("Page not be blank or must be a number");
            $res->send();
            exit();
        }

        if($_SERVER['REQUEST_METHOD'] == 'GET') {

            $limitPerPage = 1;

            try {

                $query = $readDB->prepare("SELECT COUNT(id) as totalNoOfTasks from tbltasks WHERE userid = :userid");
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();

                $row = $query->fetch(PDO::FETCH_ASSOC);
                $tasksCount = intval($row['totalNoOfTasks']);

                $numOfPages = ceil($tasksCount/$limitPerPage) === 0 ? 1 : ceil($tasksCount/$limitPerPage);

                if($page > $numOfPages || $page === 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(404);
                    $res->setSuccess(false);
                    $res->addMessage("Page not found");
                    $res->send();
                    exit();
                }

                $offset = ($page === 1 ? 0 : ($limitPerPage*($page-1)));

                $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') AS deadline, completed FROM tbltasks WHERE userid = :userid limit :limitPerPage offset :offset");
                $query->bindParam(':limitPerPage', $limitPerPage, PDO::PARAM_INT);
                $query->bindParam(':offset', $offset, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();
    
                $rowCount = $query->rowCount();
    
                if($rowCount <= 0 || $rowCount === 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(404);
                    $res->setSuccess(false);
                    $res->addMessage("No tasks found");
                    $res->send();
                    exit();
                }
    
                $taskArray = array();
    
                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }
    
                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['total_rows'] = $tasksCount;
                $returnData['total_pages'] = $numOfPages;
                $returnData['has_next_page'] = ($page < $numOfPages ? true : false);
                $returnData['has_previous_page'] = ($page > 1 ? true : false);
                $returnData['tasks'] = $taskArray;
    
                $res = new Response();
                $res->setHttpStatusCode(200);
                $res->setSuccess(true);
                $res->toCache(true);
                $res->addMessage('Task found');
                $res->setData($returnData);
                $res->send();
                exit();
    
            } catch (TaskException $ex) {
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage($ex->getMessage());
                $res->send();
                exit();
            } catch (PDOException $ex) {
                error_log("Internal server error - ".$ex, 0);
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage("Internal server error");
                $res->send();
                exit();
            }
        } else {
            $res = new Response();
            $res->setHttpStatusCode(405);
            $res->setSuccess(false);
            $res->addMessage("Request method not allowed.");
            $res->send();
            exit();
        }
    }
    else if(empty($_GET)) {
        if($_SERVER['REQUEST_METHOD'] == 'GET') {

            try {
                $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') AS deadline, completed FROM tbltasks AND userid = :userid");
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();
    
                $rowCount = $query->rowCount();
    
                if($rowCount <= 0 || $rowCount === 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(404);
                    $res->setSuccess(false);
                    $res->addMessage("No tasks found");
                    $res->send();
                    exit();
                }
    
                $taskArray = array();
    
                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }
    
                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;
    
                $res = new Response();
                $res->setHttpStatusCode(200);
                $res->setSuccess(true);
                $res->toCache(true);
                $res->addMessage('Task found');
                $res->setData($returnData);
                $res->send();
                exit();
    
            } catch (TaskException $ex) {
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage($ex->getMessage());
                $res->send();
                exit();
            } catch (PDOException $ex) {
                error_log("Internal server error - ".$ex, 0);
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage("Internal server error");
                $res->send();
                exit();
            }

        } else if($_SERVER['REQUEST_METHOD'] == 'POST') {
            try{

                if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                    $res = new Response();
                    $res->setHttpStatusCode(400);
                    $res->setSuccess(false);
                    $res->addMessage('Content type header is not set to JSON');
                    $res->send();
                    exit();
                }

                $rawPOSTData = file_get_contents("php://input");

                if(!$jsonData = json_decode($rawPOSTData)) {
                    $res = new Response();
                    $res->setHttpStatusCode(500);
                    $res->setSuccess(false);
                    $res->addMessage("Request body is not valid JSON");
                    $res->send();
                    exit();
                }

                if(!isset($jsonData->title) || !isset($jsonData->completed)) {
                    $res = new Response();
                    $res->setHttpStatusCode(500);
                    $res->setSuccess(false);
                    (!isset($jsonData->title) ? $res->addMessage("Title must be required") : false);
                    (!isset($jsonData->completed) ? $res->addMessage("Completed must be required") : false);
                    $res->send();
                    exit();
                }

                $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);

                $title = $newTask->getTitle();
                $description = $newTask->getDescription();
                $deadline = $newTask->getDeadline();
                $completed = $newTask->getCompleted();

                $query = $writeDB->prepare("INSERT INTO tbltasks (title, description, deadline, completed, userid) VALUES (:title, :description, STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), :completed, :userid)");
                $query->bindParam(':title', $title, PDO::PARAM_STR);
                $query->bindParam(':description', $description, PDO::PARAM_STR);
                $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
                $query->bindParam(':completed', $completed, PDO::PARAM_STR);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount == 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(500);
                    $res->setSuccess(false);
                    $res->addMessage('Failed to create task. Please try again later.');
                    $res->send();
                    exit();
                }

                $newTaskId = $writeDB->lastInsertId();

                $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') AS deadline, completed FROM tbltasks WHERE id = :newTaskId AND userid = :userid");
                $query->bindParam(':newTaskId',$newTaskId, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_STR);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount == 0) {
                    $res = new Response();
                    $res->setHttpStatusCode(500);
                    $res->setSuccess(false);
                    $res->addMessage('Failed to retrive task after creation.');
                    $res->send();
                    exit();
                }

                $taskArray = array();
    
                while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray[] = $task->returnTaskArray();
                }
    
                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;
    
                $res = new Response();
                $res->setHttpStatusCode(201);
                $res->setSuccess(true);
                $res->toCache(true);
                $res->addMessage('Task found');
                $res->setData($returnData);
                $res->send();
                exit();


            } catch (TaskException $ex) {
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage($ex->getMessage());
                $res->send();
                exit();
            } catch (PDOException $ex) {
                error_log("Internal server error - ".$ex, 0);
                $res = new Response();
                $res->setHttpStatusCode(500);
                $res->setSuccess(false);
                $res->addMessage("Internal server error".$ex);
                $res->send();
                exit();
            }
        } else {
            $res = new Response();
            $res->setHttpStatusCode(405);
            $res->setSuccess(false);
            $res->addMessage("Request method not allowed.");
            $res->send();
            exit();
        }
    }
    else {
        $res = new Response();
        $res->setHttpStatusCode(404);
        $res->setSuccess(false);
        $res->addMessage("End point not found.");
        $res->send();
        exit();
    }
?>