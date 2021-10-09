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
                $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') AS deadline, completed FROM tbltasks WHERE id = :taskId");
                $query->bindParam(':taskId',$taskId, PDO::PARAM_INT);
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
                $query = $readDB->prepare("DELETE FROM tbltasks WHERE id = :taskId");
                $query->bindParam(':taskId',$taskId, PDO::PARAM_INT);
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
            echo "Hello";
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
                $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') AS deadline, completed FROM tbltasks WHERE completed = :completed");
                $query->bindParam(':completed',$completed, PDO::PARAM_STR);
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
    else if(empty($_GET)) {
        
        if($_SERVER['REQUEST_METHOD'] == 'GET') {

            try {
                $query = $readDB->prepare("SELECT id, title, description, DATE_FORMAT(deadline, '%d/%m/%Y %H:%i') AS deadline, completed FROM tbltasks");
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
            echo "Hello";
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