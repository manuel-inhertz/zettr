<?php

namespace Zettr\Handler\Modx;

use Zettr\Message;

/**
 * Abstract Modx database handler class
 *
 * @author Manuel InHertz
 */
abstract class AbstractDatabase extends \Zettr\Handler\AbstractDatabase
{
    /**
     * Actions to apply on row
     *
     * @var string
     */
    const ACTION_NO_ACTION = 0;
    const ACTION_INSERT = 1;
    const ACTION_UPDATE = 2;
    const ACTION_DELETE = 3;

    /**
     * Table prefix
     *
     * @var string
     */
    protected $_tablePrefix = '';

    /**
     * Read database connection parameters from local.xml file
     *
     * @return array
     * @throws \Exception
     */
    protected function _getDatabaseConnectionParameters()
    {
        // We load the Modx Core Path from the configuration file
        $configPhpFile = 'config.core.php';

        if (is_file($configPhpFile)) {

            @include($configPhpFile);

            if (!defined('MODX_CORE_PATH')) {
                throw new \Exception(sprintf('Could not load php file "%s"', $configPhpFile));
            }

            $coreConfigIncFile = MODX_CORE_PATH . 'config/config.inc.php';

            if (!is_file($coreConfigIncFile)) {
                throw new \Exception(sprintf('Could not load php file "%s"', $coreConfigIncFile));
            }

            @include($coreConfigIncFile);

            if (
                isset($database_server) &&
                isset($database_user) &&
                isset($database_password) &&
                isset($dbase)
            ) {
                return array(
                    'host' => $database_server,
                    'database' => $dbase,
                    'username' => $database_user,
                    'password' => $database_password
                );
            }

            throw new \Exception('Could not load database configurations.');
        }

        throw new \Exception('No valid configuration found.');
    }

    /**
     * Check if at least one of the paramters contains a wildcard
     *
     * @param array $parameters
     * @return bool
     */
    protected function _containsPlaceholder(array $parameters)
    {
        foreach ($parameters as $value) {
            if (strpos($value, '%') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $table
     * @throws \Exception
     */
    protected function _checkIfTableExists($table)
    {
        $result = $this->getDbConnection()
                       ->query("SHOW TABLES LIKE \"{$this->_tablePrefix}{$table}\"");
        if ($result->rowCount() == 0) {
            throw new \Exception("Table \"{$this->_tablePrefix}{$table}\" doesn't exist");
        }
    }

    /**
     * Output constructed csv
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws \Exception
     * @return string
     */
    protected function _outputQuery($query, array $sqlParameters)
    {
        $rows = $this->_getAllRows($query, $sqlParameters);

        $buffer = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            array_unshift($row, get_class($this));
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $output = stream_get_contents($buffer);
        fclose($buffer);

        return $output;
    }

    /**
     * Get first row query
     *
     * @param string $query
     * @param array $sqlParameters
     * @return mixed
     */
    protected function _getFirstRow($query, array $sqlParameters)
    {
        $statement = $this->getDbConnection()->prepare($query);
        $statement->execute($sqlParameters);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        return $statement->fetch();
    }

    /**
     * Get all rows
     *
     * @param string $query
     * @param array $sqlParameters
     * @return mixed
     */
    protected function _getAllRows($query, array $sqlParameters)
    {
        $statement = $this->getDbConnection()->prepare($query);
        $statement->execute($sqlParameters);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        return $statement->fetchAll();
    }

    /**
     * Process delete query
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws \Exception
     */
    protected function _processDelete($query, array $sqlParameters)
    {
        $pdoStatement = $this->getDbConnection()->prepare($query);
        $result       = $pdoStatement->execute($sqlParameters);

        if ($result === false) {
            throw new \Exception('Error while deleting rows');
        }

        $rowCount = $pdoStatement->rowCount();
        if ($rowCount > 0) {
            $this->addMessage(new Message(sprintf('Deleted "%s" row(s)', $rowCount)));
        } else {
            $this->addMessage(new Message('No rows deleted.', Message::SKIPPED));
        }
    }

    /**
     * Process insert query
     *
     * @param string $query
     * @param array $sqlParameters
     * @throws \Exception
     */
    protected function _processInsert($query, array $sqlParameters)
    {
        $pdoStatement = $this->getDbConnection()->prepare($query);
        $result       = $pdoStatement->execute($sqlParameters);

        if ($result === false) {
            $info = $pdoStatement->errorInfo();
            $code = $pdoStatement->errorCode();
            throw new \Exception("Error while updating value (Info: $info, Code: $code)");
        }

        $this->addMessage(new Message(sprintf('Inserted new value "%s"', $this->value)));
    }

    /**
     * Process update query
     *
     * @param string $query
     * @param array $sqlParameters
     * @param string $oldValue
     * @throws \Exception
     */
    protected function _processUpdate($query, array $sqlParameters, $oldValue=null, $addMessage=true)
    {
        $pdoStatement = $this->getDbConnection()->prepare($query);
        $result       = $pdoStatement->execute($sqlParameters);

        if ($result === false) {
            $info = $pdoStatement->errorInfo();
            $code = $pdoStatement->errorCode();
            throw new \Exception("Error while updating value (Info: $info, Code: $code)");
        }

        $rowCount = $pdoStatement->rowCount();

        if ($addMessage) {
            if (!is_null($oldValue)) {
                $this->addMessage(new Message(sprintf('Updated value from "%s" to "%s" (%s row(s) affected)', $oldValue, $this->value, $rowCount)));
            } else {
                $this->addMessage(new Message(sprintf('Updated value to "%s" (%s row(s) affected)', $this->value, $rowCount)));
            }
        }
    }
}
