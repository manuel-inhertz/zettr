<?php

namespace Zettr\Handler\Modx;

use Zettr\Message;

/**
 * Parameters
 *
 * - key
 */
class SystemSettings extends AbstractDatabase
{
    /**
     * Protected method that actually applies the settings. This method is implemented in the inheriting classes and
     * called from ->apply
     *
     * @throws \Exception
     * @return bool
     */
    protected function _apply()
    {
        $this->_checkIfTableExists('modx_system_settings');

        $key   = $this->param1;

        $sqlParameters       = $this->_getSqlParameters($key);
        $containsPlaceholder = $this->_containsPlaceholder($sqlParameters);
        $action              = self::ACTION_NO_ACTION;

        if (strtolower(trim($this->value)) == '--delete--') {
            $action = self::ACTION_DELETE;
        } else {
            $query = 'SELECT `value` FROM `' . $this->_tablePrefix . 'modx_system_settings` WHERE `key` LIKE :key';
            $firstRow = $this->_getFirstRow($query, $sqlParameters);

            if ($containsPlaceholder) {
                // key or value contains '%' char - we can't build an insert query, only update is possible
                if ($firstRow === false) {
                    $this->addMessage(
                        new Message('Trying to update using placeholders but no rows found in the db', Message::SKIPPED)
                    );
                } else {
                    $action = self::ACTION_UPDATE;
                }
            } else {
                if ($firstRow === false) {
                     $action = self::ACTION_INSERT;
                } elseif ($firstRow['value'] == $this->value) {
                    $this->addMessage(
                        new Message(sprintf('Value "%s" is already in place. Skipping.', $firstRow['value']), Message::SKIPPED)
                    );
                } else {
                     $action = self::ACTION_UPDATE;
                }
            }
        }

        switch ($action) {
            case self::ACTION_DELETE:
                $query = 'DELETE FROM `' . $this->_tablePrefix . 'modx_system_settings` WHERE `key` LIKE :key';
                $this->_processDelete($query, $sqlParameters);
                break;
            case self::ACTION_INSERT:
                //$sqlParameters[':value'] = $this->value;
                //$query = 'INSERT INTO `' . $this->_tablePrefix . 'modx_system_settings` (`scope`, `scope_id`, `path`, value) VALUES (:scope, :scopeId, :path, :value)';
                //$this->_processInsert($query, $sqlParameters);
                break;
            case self::ACTION_UPDATE:
                $sqlParameters[':value'] = $this->value;
                $query = 'UPDATE `' . $this->_tablePrefix . 'modx_system_settings` SET `value` = :value WHERE `key` LIKE :key';
                $this->_processUpdate($query, $sqlParameters, $firstRow['value']);
                break;
            case self::ACTION_NO_ACTION;
            default:
                break;
        }

        $this->destroyDb();

        return true;
    }

    /**
     * Protected method that actually extracts the settings. This method is implemented in the inheriting classes and
     * called from ->extract and only echos constructed csv
     */
    protected function _extract()
    {
        $this->_checkIfTableExists('modx_system_settings');

        $key   = $this->param1;

        $sqlParameters = $this->_getSqlParameters($key, $value);

        $query = 'SELECT key, value FROM `' . $this->_tablePrefix
                 . 'modx_system_settings` WHERE `key` LIKE :key';

        return $this->_outputQuery($query, $sqlParameters);
    }

    /**
     * Constructs the sql parameters
     *
     * @param string $key
     * @return array
     * @throws \Exception
     */
    protected function _getSqlParameters($key)
    {
        if (empty($key)) {
            throw new \Exception("No Key found");
        }

        return array(
            ':key'   => $key,
        );
    }
}
