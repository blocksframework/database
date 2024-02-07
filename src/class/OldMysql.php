<?php

namespace System\Database;

use System\Database\Query;

final class Mysql {
    private $link;
    private $log;
    private $debug;

    public function connect($config) {
        $hostname = $config['hostname'];
        $port     = $config['port'];
        $username = $config['username'];
        $password = $config['password'];
        $database = $config['database'];
        $charset  = get_var( $config['charset'], 'utf8' );
        $debug    = get_var( $config['debug'], 0 );

        $this->link = mysqli_connect($hostname, $username, $password, $database, $port);

        if ( !$this->link ) {
            trigger_error('MySQL->__construct(): Could not make a MySQL database link using ' . $username . '@' . $hostname);

            exit('Database connection error');
        }

        if ( mysqli_connect_error() ) {
            trigger_error( 'MySQL->__construct(): MySQL connection failed: (' . mysqli_connect_errno() . ') '. mysqli_connect_error() );

            exit('Database connection error');
        }

        if ( !mysqli_select_db($this->link, $database) ) {
            trigger_error('MySQL->__construct(): Could not connect to database ' . $database);

            exit('Database connection error');
        }

        if ($charset) {
            mysqli_query($this->link, "SET NAMES '$charset'");
            mysqli_query($this->link, "SET CHARACTER SET $charset");
            mysqli_query($this->link, "SET CHARACTER_SET_CONNECTION=$charset");
        }

        mysqli_query($this->link, "SET SQL_MODE = ''");

        $this->debug = $debug;

        if ($debug) {
            $this->log = [];
        }
    }

    public function escape($value) {
        if ($this->link) {
            return mysqli_real_escape_string($this->link, $value);
        } else {
            throw new Exception('MySQL->escape(): Datatbase link is not connected');
        }
    }

    public function countAffected() {
        if ($this->link) {
            return mysqli_affected_rows($this->link);
        } else {
            throw new Exception('MySQL->countAffected(): Datatbase link is not connected');
        }
    }

    public function getLastId() {
        if ($this->link) {
            return mysqli_insert_id($this->link);
        } else {
            throw new Exception('MySQL->getLastId(): Datatbase link is not connected');
        }
    }

    public function query($sql) {
        if ( $this->debug ) {
            $this->log[] = $sql;
        }

        if ($this->link) {
            $resource = mysqli_query($this->link, $sql);

            if ($resource) {
                if (is_object($resource) && get_class($resource) == 'mysqli_result') {
                    $i = 0;

                    $data = [];

                    while ($result = mysqli_fetch_assoc($resource) ) {
                        $data[$i] = $result;

                        $i++;
                    }

                    mysqli_free_result($resource);

                    $query = new Query();
                    $query->row = isset( $data[0] )? $data[0] : [];
                    $query->rows = $data;
                    $query->num_rows = $i;

                    unset($data);

                    return $query;

                } else {
                    $count = mysqli_affected_rows($this->link);

                    if ($count < 0) {
                        return false;
                    } else {
                        return $count;
                    }
                }

            } else {
                trigger_error_in_class('' . mysqli_error($this->link) . '<br />Error No: ' . mysqli_errno($this->link) . '<br />' . $sql, E_USER_ERROR);
            }

        } else {
            throw new Exception('MySQL->query(): Datatbase link is not connected');
        }
    }

    public function insert($table, $data, $replace = false) {
        if ( empty($table) ) {
            throw new Exception('Exception in class MySQLi: $table cannot be empty');
        }

        if ( empty($data) ) {
            throw new Exception('Exception in class MySQLi: $data cannot be empty');
        }

        $test_key = true;
        $fields = [];
        $values = [];

        if ($replace) {
            $sql = "replace into `$table` ";
        } else {
            $sql = "insert into `$table` ";
        }

        foreach ($data as $key => $value) {
            if ( is_numeric($key) ) {
                $test_key = false;
            }

            $fields[] = "`$key`";
            $values[] = $this->escape($value);
        }

        if ($test_key) {
            $sql .= '(' . join(', ', $fields) . ')';
        }

        $sql .= " values ('" . join("', '", $values) . "')";

        delete($sql, $test_key, $fields, $values);

        return $this->query($sql);
    }

    public function update($table, $data, $condition = false){
        if ( empty($table) ) {
            throw new Exception('Exception in class MySQLi: $table cannot be empty');
        }

        if ( empty($data) ) {
            throw new Exception('Exception in class MySQLi: $data cannot be empty');
        }

        $arr = [];
        $sql = "update `$table` set ";

        // TODO: To make it similar to the insert one, with $fields and $values
        foreach ($data as $key=>$value){
            $arr[] = "`$key` = '" . $this->escape($value). "'";
        }

        $sql .= " " . join(", ", $arr);

        if ($condition) {
            $sql .= " where $condition";
        }

        return $this->query($sql);
    }

    public function delete($table, $condition) {
        if ( empty($table) ) {
            throw new Exception('Exception in class MySQLi: $table cannot be empty');
        }

        if ( empty($condition) ) {
            throw new Exception('Exception in class MySQLi: $condition cannot be empty');
        }

        $sql = "delete from `$table` where $condition";

        return $this->query($sql);
    }

    public function getLog() {
        if ( $this->debug ) {
            return $this->log;
        } else {
            return false;
        }
    }

    public function isConnected() {
        return (bool)$this->link;
    }

    public function __destruct() {
        if ($this->link) {
            mysqli_close($this->link);
        } else {
            throw new Exception('MySQL->__destruct(): Datatbase link is not connected');
        }
    }

}
