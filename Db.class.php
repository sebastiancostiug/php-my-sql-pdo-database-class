<?php
/**
 *
 * @package     PHP-MySQL-PDO-Database-Class
 *
 * @subpackage  SB
 * @author      Vivek Wicky Aswal. (https://twitter.com/#!/VivekWickyAswal)
 * @copyright   Beerware @ http://people.freebsd.org/~phk/
 * @version     0.2ab
 * @license     Beerware @ http://people.freebsd.org/~phk/
 *
 * @category    database
 *
 * @since       2022.08.10
 *
 * @description A database class for PHP-MySQL which uses the PDO extension.
 * @git         https://github.com/wickyaswal/PHP-MySQL-PDO-Database-Class
 *
 * @forked      Curated by Sebastian Costiug (sebastian@overbyte.dev)
 */

namespace WickyAswal\Database;

/**
 * PHP-MySQL-PDO-Database-Class
 */
class DB
{
    /**
     * @var \PDO $_pdo The PDO object
     */
    private $_pdo;

    /**
     * @var object $_sQuery The PDO statement object
     */
    private $_sQuery;

    /**
     * @var array $_settings The database settings
     */
    private $_settings;

    /**
     * @var boolean $_bConnected Connection status (to the database)
     */
    private $_bConnected = false;

    /**
     * @var object $_log Object for logging exceptions
     */
    private $_log;

    /**
     * @var array $_parameters The parameters of the SQL query
     */
    private $_parameters;

    /**
     * contruct()
     *
     * Instantiate Log class.
     * Connect to database.
     * Creates the parameter array.
     *
     * @return void
     */
    public function __construct()
    {
        require_once('Log.class.php');

        $this->_log = new Log();
        $this->connect();
        $this->_parameters = [];
    }

    /**
     * connect()
     *
     * This method makes connection to the database.
     *
     * Reads the database settings from a ini file.
     * Puts  the ini content into the settings array.
     * Tries to connect to the database.
     * If connection failed, exception is displayed and a log file gets created.
     *
     * @return void
     */
    private function connect()
    {
        $this->_settings = parse_ini_file('settings.ini.php');
        $dsn            = 'mysql:dbname=' . $this->_settings['dbname'] . ';host=' . $this->_settings['host'] . '';
        try {
            // Read settings from INI file, set UTF8
            $this->_pdo = new \PDO($dsn, $this->_settings['user'], $this->_settings['password'], [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
            ]);

            // We can now log any exceptions on Fatal error.
            $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Disable emulation of prepared statements, use REAL prepared statements instead.
            $this->_pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

            // Connection succeeded, set the boolean to true.
            $this->_bConnected = true;
        } catch (\PDOException $e) {
            // Write into log
            echo $this->exceptionLog($e->getMessage());
            die();
        }
    }

    /**
     * closeConnection()
     *
     * You can use this little method if you want to close the PDO connection
     *
     * @return void
     */
    public function closeConnection()
    {
        // Set the PDO object to null to close the connection
        // http://www.php.net/manual/en/pdo.connections.php
        $this->_pdo = null;
    }

    /**
     * init()
     *
     * Every method which needs to execute a SQL query uses this method.
     *
     * If not connected, connect to the database.
     * Prepare Query.
     * Parameterize Query.
     * Execute Query.
     * On exception : Write Exception into the log + SQL query.
     * Reset the Parameters.
     *
     * @param string $query      Sql query
     * @param array  $parameters Parameters
     *
     * @return void
     */
    private function init($query, array $parameters = [])
    {
        // Connect to database
        if (!$this->_bConnected) {
            $this->connect();
        }
        try {
            // Prepare query
            $this->_sQuery = $this->_pdo->prepare($query);

            // Add parameters to the parameter array
            $this->bindMore($parameters);

            // Bind parameters
            if (!empty($this->_parameters)) {
                foreach ($this->_parameters as $param => $value) {
                    if (is_int($value[1])) {
                        $type = \PDO::PARAM_INT;
                    } elseif (is_bool($value[1])) {
                        $type = \PDO::PARAM_BOOL;
                    } elseif (is_null($value[1])) {
                        $type = \PDO::PARAM_NULL;
                    } else {
                        $type = \PDO::PARAM_STR;
                    }
                    // Add type when binding the values to the column
                    $this->_sQuery->bindValue($value[0], $value[1], $type);
                }
            }

            // Execute SQL
            $this->_sQuery->execute();
        } catch (\PDOException $e) {
            // Write into log and display Exception
            echo $this->exceptionLog($e->getMessage(), $query);
            die();
        }

        // Reset the parameters
        $this->_parameters = [];
    }

    /**
     * bind()
     * Add the parameter to the parameter array
     *
     * @param string $para  Parameter
     * @param string $value Value
     *
     * @return void
     */
    public function bind($para, $value)
    {
        $this->_parameters[sizeof($this->_parameters)] = [':' . $para , $value];
    }

    /**
     * bindMore()
     *
     * Add more parameters to the parameter array
     *
     * @param array $parray Parameters array
     *
     * @return void
     */
    public function bindMore(array $parray)
    {
        if (empty($this->_parameters) && is_array($parray)) {
            $columns = array_keys($parray);
            foreach ($columns as $i => &$column) {
                $this->bind($column, $parray[$column]);
            }
        }
    }

    /**
     * query()
     *
     * If the SQL query  contains a SELECT or SHOW statement it returns an array containing all of the result set row
     * If the SQL statement is a DELETE, INSERT, or UPDATE statement it returns the number of affected rows
     *
     * @param  string  $query     Query
     * @param  array   $params    Params
     * @param  integer $fetchmode Fetch mode
     *
     * @return mixed
     */
    public function query($query, array $params = [], $fetchmode = \PDO::FETCH_ASSOC)
    {
        $query = trim(str_replace("\r", ' ', $query));

        $this->init($query, $params);

        $rawStatement = explode(' ', preg_replace("/\s+|\t+|\n+/", ' ', $query));

        // Which SQL statement is used
        $statement = strtolower($rawStatement[0]);

        if (in_array($statement, ['select', 'show'])) {
            return $this->_sQuery->fetchAll($fetchmode);
        } elseif (in_array($statement, ['insert', 'update', 'delete'])) {
            return $this->_sQuery->rowCount();
        } else {
            return null;
        }
    }

    /**
     * lastInsertId()
     *
     * Returns the last inserted id.
     *
     * @return string
     */
    public function lastInsertId()
    {
        return $this->_pdo->lastInsertId();
    }

    /**
     * beginTransaction()
     *
     * Starts the transaction
     *
     * @return boolean, true on success or false on failure
     */
    public function beginTransaction()
    {
        return $this->_pdo->beginTransaction();
    }

    /**
     * executeTransaction()
     *
     * Execute Transaction
     *
     * @return boolean, true on success or false on failure
     */
    public function executeTransaction()
    {
        return $this->_pdo->commit();
    }

    /**
     * rollBack()
     *
     * Rollback of Transaction
     *
     * @return boolean, true on success or false on failure
     */
    public function rollBack()
    {
        return $this->_pdo->rollBack();
    }

    /**
     * column()
     *
     * Returns an array which represents a column from the result set
     *
     * @param  string $query  Query
     * @param  array  $params Params
     *
     * @return array
     */
    public function column($query, array $params = null)
    {
        $this->init($query, $params);
        $Columns = $this->_sQuery->fetchAll(\PDO::FETCH_NUM);

        $column = null;

        foreach ($Columns as $cells) {
            $column[] = $cells[0];
        }

        return $column;
    }
    /**
     * row()
     *
     * Returns an array which represents a row from the result set
     *
     * @param  string  $query     Query
     * @param  array   $params    Params
     * @param  integer $fetchmode Fetch mode
     *
     * @return array
     */
    public function row($query, array $params = null, $fetchmode = \PDO::FETCH_ASSOC)
    {
        $this->init($query, $params);
        $result = $this->_sQuery->fetch($fetchmode);
        $this->_sQuery->closeCursor(); // Frees up the connection to the server so that other SQL statements may be issued,

        return $result;
    }
    /**
     * single()
     *
     * Returns the value of one single field/column
     *
     * @param  string $query  Query
     * @param  array  $params Params
     *
     * @return string
     */
    public function single($query, array $params = null)
    {
        $this->init($query, $params);
        $result = $this->_sQuery->fetchColumn();
        $this->_sQuery->closeCursor(); // Frees up the connection to the server so that other SQL statements may be issued

        return $result;
    }
    /**
     * exceptionLog()
     *
     * Writes the log and returns the exception
     *
     * @param  string $message Message
     * @param  string $sql     Sql
     *
     * @return string
     */
    private function exceptionLog($message, $sql = '')
    {
        $exception = 'Unhandled Exception. <br />';
        $exception .= $message;
        $exception .= '<br /> You can find the error back in the log.';

        if (!empty($sql)) {
            # Add the Raw SQL to the Log
            $message .= "\r\nRaw SQL : " . $sql;
        }
        # Write into log
        $this->_log->write($message);

        return $exception;
    }
}
