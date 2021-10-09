<?php

    class TaskException extends Exception {}

    class Task {
        private $_id;
        private $_title;
        private $_description;
        private $_deadline;
        private $_completed;

        public function __construct($id, $title, $description, $deadline, $completed) {
            $this->setId($id);
            $this->setTitle($title);
            $this->setDescription($description);
            $this->setDeadline($deadline);
            $this->setCompleted($completed);
        }

        public function getId() {
            return $this->_id;
        }

        public function getTitle() {
            return $this->_title;
        }

        public function getDescription() {
            return $this->_description;
        }

        public function getDeadline() {
            return $this->_deadline;
        }

        public function getCompleted() {
            return $this->_completed;
        }

        public function setId($id) {
            if(($id !== null) && (!is_numeric($id) || $id < 0 || $this->_id !== null)) {
                throw new TaskException('Correct task id');
            }

            $this->_id = $id;
        }

        public function setTitle($title) {
            if(strlen($title) <= 0 || strlen($title) > 255) {
                throw new TaskException('Title must be between 1 to 255 characters long.');
            }

            $this->_title = $title;
        }

        public function setDescription($description) {
            if($description !== null && strlen($description) > 16777215) {
                throw new TaskException('Description to long.');
            }

            $this->_description = $description;
        }

        public function setDeadline($deadline) {
            if(($deadline !== null) && date_format(date_create_from_format('d/m/Y H:i', $deadline), 'd/m/Y H:i') !== $deadline) {
                throw new TaskException("Date format must be 'd/m/Y H:i'");
            }

            $this->_deadline = $deadline;
        }

        public function setCompleted($completed) {
            if(strtoupper($completed) !== 'Y' && strtoupper($completed) !== 'N') {
                throw new TaskException("Completed if must be 'Y' or 'N'");
            }

            $this->_completed = $completed;
        }

        public function returnTaskArray() {
            $task = array();
            $task['id'] = $this->getId();
            $task['title'] = $this->getTitle();
            $task['description'] = $this->getDescription();
            $task['deadline'] = $this->getDeadline();
            $task['completed'] = $this->getCompleted();

            return $task;
        }

    }

?>