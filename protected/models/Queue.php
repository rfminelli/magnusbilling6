<?php
/**
 * Modelo para a tabela "Queue".
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v3
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 19/09/2012
 */

class Queue extends Model
{
    protected $_module = 'queue';
    /**
     * Retorna a classe estatica da model.
     * @return Prefix classe estatica da model.
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return nome da tabela.
     */
    public function tableName()
    {
        return 'pkg_queue';
    }

    /**
     * @return nome da(s) chave(s) primaria(s).
     */
    public function primaryKey()
    {
        return 'id';
    }

    /**
     * @return array validacao dos campos da model.
     */
    public function rules()
    {
        return array(
            array('name, id_user', 'required'),
            array('id_user, timeout, retry, wrapuptime, weight, periodic-announce-frequency', 'numerical', 'integerOnly' => true),
            array('language, joinempty, leavewhenempty, musiconhold,announce-holdtime,leavewhenempty,strategy, ringinuse, announce-position, announce-holdtime, announce-frequency', 'length', 'max' => 128),
            array('periodic-announce ', 'length', 'max' => 200),
            array('ring_or_moh ', 'length', 'max' => 4),
            array('name ', 'length', 'max' => 25),
            array('name', 'checkname'),
        );
    }

    /**
     * @return array regras de relacionamento.
     */
    public function relations()
    {
        return array(
            'idUser' => array(self::BELONGS_TO, 'User', 'id_user'),
        );
    }

    public function checkname($attribute, $params)
    {
        if (preg_match('/ /', $this->name)) {
            $this->addError($attribute, Yii::t('yii', 'No space allow in name'));
        }

        if (!preg_match('/^[0-9]|^[A-Z]|^[a-z]/', $this->name)) {
            $this->addError($attribute, Yii::t('yii', 'Name need start with numbers or letters'));
        }
    }

    public function truncateQueueStatus()
    {
        $sql = "TRUNCATE pkg_queue_status";
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function deleteQueueStatus($id)
    {
        $sql = "DELETE FROM pkg_queue_status WHERE callId = " . $id;
        Yii::app()->db->createCommand($sql)->execute();
    }

    public function updateQueueStatus($operator, $id_queue, $oldtime, $uniqueid)
    {
        $sql = "UPDATE pkg_queue_status SET status = 'answered', id_agent =
                (SELECT id FROM pkg_queue_agent_status WHERE agentName = :key AND id_queue = :key1),
                    oldtime = :key2  WHERE callId = :key3 ";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":key", $operator, PDO::PARAM_STR);
        $command->bindValue(":key1", $id_queue, PDO::PARAM_INT);
        $command->bindValue(":key2", $oldtime, PDO::PARAM_STR);
        $command->bindValue(":key3", $uniqueid, PDO::PARAM_STR);
        $command->execute();
    }
    public function getQueueStatus($id)
    {
        $sql     = "SELECT * FROM pkg_queue_status WHERE id_agent = :key";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":key", $id, PDO::PARAM_STR);
        return $command->queryAll();
    }

    public function getQueueAgentStatus($id)
    {
        $sql     = "SELECT agentName FROM pkg_queue_agent_status WHERE id = :key";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":key", $id, PDO::PARAM_STR);
        return $command->queryAll();
    }

    public function insertQueueStatus($id_queue, $uniqueid, $queueName, $callerId, $channel)
    {
        $sql = "INSERT INTO pkg_queue_status (id_queue, callId, queue_name, callerId, time, channel, status)
                        VALUES (" . $id_queue . ", '" . $uniqueid . "', '$queueName', '" . $callerId . "',
                        '" . date('Y-m-d H:i:s') . "', '" . $channel . "', 'ringing')";
        Yii::app()->db->createCommand($sql)->execute();
    }
}
