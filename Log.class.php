<?php
/**
 *
 * @package     PHP-MySQL-PDO-Database-Class
 *
 * @subpackage  Log
 * @author      Vivek Wicky Aswal. (https://twitter.com/#!/VivekWickyAswal)
 * @copyright   Beerware @ http://people.freebsd.org/~phk/
 * @version     0.1a
 * @license     Beerware @ http://people.freebsd.org/~phk/
 *
 * @category    logger
 *
 * @since       2022.08.10
 *
 * @description A logger class which creates logs when an exception is thrown.
 * @git         https://github.com/wickyaswal/PHP-MySQL-PDO-Database-Class
 *
 * @forked      Curated by Sebastian Costiug (sebastian@overbyte.dev)
 */

namespace WickyAswal\Database;

/**
 * A logger class which creates logs when an exception is thrown.
 */
class Log
{
    /**
     * @var string $_path Log directory name
     */
    private $_path;

    /**
     * contructor()
     *
     * Sets the timezone and path of the log files.
     *
     * @param string $path Log file folder
     *
     * @return void
     */
    public function __construct($path = 'logs')
    {
        date_default_timezone_set('Europe/Bucharest');
        $this->_path  = dirname(__DIR__) . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR;
    }

    /**
     * write()
     *
     * Checks if directory exists, if not, create one and call this method again.
     * Checks if log already exists.
     * If not, new log gets created. Log is written into the logs folder.
     * Logname is current date(Year - Month - Day).
     * If log exists, edit method called.
     * Edit method modifies the current log.
     *
     * @param string $message The message which is written into the log.
     *
     * @return void
     */
    public function write($message)
    {
        $date = new \DateTime();
        $log  = $this->_path . $date->format('Y-m-d') . '.txt';
        if (is_dir($this->_path)) {
            if (!file_exists($log)) {
                $fh         = fopen($log, 'a+') or die('Fatal Error !');
                $logcontent = 'Time : ' . $date->format('H:i:s') . "\r\n" . $message . "\r\n";
                fwrite($fh, $logcontent);
                fclose($fh);
            } else {
                $this->edit($log, $date, $message);
            }
        } else {
            if (mkdir($this->_path, 0777) === true) {
                $this->write($message);
            }
        }
    }

    /**
     * edit()
     *
     * Gets called if log exists.
     * Modifies current log and adds the message to the log.
     *
     * @param string    $log     Log file path
     * @param \DateTime $date    DateTimeObject
     * @param string    $message Message
     *
     * @return void
     */
    private function edit($log, \DateTime $date, $message)
    {
        $logcontent = 'Time : ' . $date->format('H:i:s') . "\r\n" . $message . "\r\n\r\n";
        $logcontent = $logcontent . file_get_contents($log);
        file_put_contents($log, $logcontent);
    }
}
