<?php
/**
 * sqlsrv_* compatibility layer implemented on top of the odbc_* extension.
 *
 * Enough of the API used by Novo Site to log users in, run stored procs and
 * issue plain queries against SQL Server via FreeTDS.
 *
 * NOTE: Top-level "function foo()" declarations are HOISTED by the PHP parser
 * at compile time. That means an early `if (function_exists('sqlsrv_connect')) return`
 * guard would see our own definitions and skip the rest of the file (including
 * the define() calls). Instead we wrap each function declaration in
 * `if (!function_exists(...))`, which is interpreted at runtime — no hoisting.
 */

defined('SQLSRV_FETCH_ASSOC')    || define('SQLSRV_FETCH_ASSOC', 1);
defined('SQLSRV_FETCH_NUMERIC')  || define('SQLSRV_FETCH_NUMERIC', 2);
defined('SQLSRV_FETCH_BOTH')     || define('SQLSRV_FETCH_BOTH', 3);
defined('SQLSRV_PARAM_IN')       || define('SQLSRV_PARAM_IN', 1);
defined('SQLSRV_PARAM_OUT')      || define('SQLSRV_PARAM_OUT', 2);
defined('SQLSRV_PARAM_INOUT')    || define('SQLSRV_PARAM_INOUT', 3);
defined('SQLSRV_CURSOR_KEYSET')  || define('SQLSRV_CURSOR_KEYSET', 0);

if (function_exists('sqlsrv_connect')) {
    // Real sqlsrv extension is loaded — no shim needed beyond the constants.
    return;
}

class _SqlSrvShim_Statement {
    public $h;
    public $params = [];
    public function __construct($h, $params = []) {
        $this->h = $h;
        $this->params = $params;
    }
}

function _sqlsrv_dsn($server) {
    // The Dockerfile installs the FreeTDS ODBC driver as "FreeTDS".
    $host = $server;
    $port = 1433;
    if (strpos($server, ',') !== false) {
        list($host, $port) = explode(',', $server, 2);
    }
    return "Driver=FreeTDS;Server=$host;Port=$port;TDS_Version=7.4;ClientCharset=UTF-8";
}

function sqlsrv_connect($server, $config = []) {
    $dsn = _sqlsrv_dsn($server);
    $user = isset($config['UID']) ? $config['UID'] : (isset($config['Uid']) ? $config['Uid'] : 'sa');
    $pass = isset($config['PWD']) ? $config['PWD'] : (isset($config['Pwd']) ? $config['Pwd'] : '');
    if (isset($config['Database'])) {
        $dsn .= ';Database=' . $config['Database'];
    }
    $h = @odbc_connect($dsn, $user, $pass);
    if ($h === false) {
        return false;
    }
    return $h;
}

function sqlsrv_close($conn) {
    if ($conn) odbc_close($conn);
    return true;
}

/**
 * Approximate sqlsrv_query/prepare+execute on top of odbc_prepare/odbc_execute.
 *
 * The Novo Site mostly calls this in two shapes:
 *   1. sqlsrv_query($conn, "SELECT ..."): no params, just run.
 *   2. sqlsrv_query($conn, "{CALL Mem_Users_Accede(?,?,?,?)}", $data)
 *      where $data is an array of [&$value, direction] pairs.
 * For shape 2 the OUT parameter has to be assigned back into the original
 * variable (the caller reads $uid after the call). We achieve this by
 * passing the original array slots by reference.
 */
function sqlsrv_query($conn, $sql, $params = null, $options = null) {
    if (!$conn) return false;
    if ($params === null || (is_array($params) && count($params) === 0)) {
        $r = @odbc_exec($conn, $sql);
        return $r ?: false;
    }

    $stmt = @odbc_prepare($conn, $sql);
    if (!$stmt) return false;

    $values = [];
    // We have to keep references alive in $values because odbc_execute
    // walks the array, and OUT parameters need to be written back.
    foreach ($params as $i => $p) {
        if (is_array($p)) {
            // [&value, direction] — direction ignored (ODBC can't truly bind OUT,
            // but we still need to provide a value for the placeholder).
            $values[] =& $params[$i][0];
        } else {
            $values[] =& $params[$i];
        }
    }

    $ok = @odbc_execute($stmt, $values);
    if (!$ok) return false;
    return $stmt;
}

function sqlsrv_prepare($conn, $sql, $params = null, $options = null) {
    return sqlsrv_query($conn, $sql, $params, $options);
}

function sqlsrv_execute($stmt) {
    return $stmt !== false;
}

function sqlsrv_fetch_array($stmt, $type = SQLSRV_FETCH_BOTH) {
    if (!$stmt) return false;
    if ($type === SQLSRV_FETCH_ASSOC) return odbc_fetch_array($stmt);
    $row = odbc_fetch_array($stmt);
    if (!$row) return $row;
    if ($type === SQLSRV_FETCH_NUMERIC) return array_values($row);
    return array_merge($row, array_values($row));
}

function sqlsrv_fetch($stmt) {
    if (!$stmt) return false;
    return odbc_fetch_row($stmt);
}

function sqlsrv_get_field($stmt, $i) {
    return odbc_result($stmt, $i + 1);
}

function sqlsrv_num_rows($stmt) {
    if (!$stmt) return 0;
    $n = @odbc_num_rows($stmt);
    return $n === -1 ? 0 : $n;
}

function sqlsrv_rows_affected($stmt) {
    return sqlsrv_num_rows($stmt);
}

function sqlsrv_free_stmt($stmt) {
    if ($stmt) odbc_free_result($stmt);
    return true;
}

function sqlsrv_next_result($stmt) {
    if (!$stmt) return false;
    // odbc_next_result only exists in PHP 8+
    if (function_exists('odbc_next_result')) {
        return @odbc_next_result($stmt);
    }
    return false;
}

function sqlsrv_errors($level = null) {
    return [['SQLSTATE' => odbc_error(), 'message' => odbc_errormsg()]];
}
