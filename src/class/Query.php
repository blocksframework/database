<?php

namespace System\Database;

// TODO: when moving to system module, switch to using namespaces

/* Refactored from this in the mysql.php class:
 * 
 * $query = new stdClass();
 * $query->row = isset( $data[0] )? $data[0] : [];
 * $query->rows = $data;
 * $query->num_rows = $i;
 * 
 * Because of this
 * 
 * https://stackoverflow.com/a/23999743
 * stdClass is evil.
 * https://stackoverflow.com/a/24527721
*/

final class Query {
    public $row;
    public $rows;
    public $num_rows;
}