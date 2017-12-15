<?php
/**
 * Acoes do modulo "Queue".
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Todos os direitos reservados.
 * ###################################
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 17/08/2012
 */

class QueueDashBoardController extends Controller
{

    public $attributeOrder = 'callId';
    public $extraValues    = array('idQueue' => 'name');
    private $host          = 'localhost';
    private $user          = 'magnus';
    private $password      = 'magnussolution';

    public function init()
    {
        $this->instanceModel = new QueueDashBoard;
        $this->abstractModel = QueueDashBoard::model();
        $this->titleReport   = Yii::t('yii', 'Queue DashBoard');

        parent::init();
    }

    public function actionRead($asJson = true, $condition = null)
    {
        $this->getQueue();
        parent::actionRead($asJson = true, $condition = null);
    }

    public function extraFilterCustomClient($filter)
    {

        //se for cliente filtrar pelo queue.id_id_user
        if (array_key_exists('idQueue', $this->relationFilter)) {
            $this->relationFilter['idQueue']['condition'] .= " AND idQueue.id_user LIKE :agfby";
        } else {
            $this->relationFilter['idQueue'] = array(
                'condition' => "idQueue.id_user LIKE :agfby",
            );
        }
        $this->paramsFilter[':agfby'] = Yii::app()->session['id_user'];

        return $filter;
    }

    public function applyFilterToLimitedAdmin()
    {
        if (Yii::app()->session['user_type'] == 1 && Yii::app()->session['adminLimitUsers'] == true) {
            $this->join .= ' JOIN pkg_queue q ON t.id_queue = q.id
                            JOIN pkg_user u ON u.id = q.id_user';
            $this->filter .= " AND u.id_group IN (SELECT gug.id_group F
                                ROM pkg_group_user_group gug
                                WHERE gug.id_group_user = :idgA0)";
            $this->paramsFilter['idgA0'] = Yii::app()->session['id_group'];
        }
    }

    public function getQueue()
    {

        QueueMember::model()->truncateQueueAgentStatus();

        $asmanager       = new AGI_AsteriskManager;
        $conectaServidor = $conectaServidor = $asmanager->connect($this->host, $this->user, $this->password);

        $resultQueue = Queue::model()->findAll();

        foreach ($resultQueue as $key => $queue) {
            //echo $queue->name."\n";

            $server = $asmanager->Command("queue show " . $queue->name);
            $arr    = explode("\n", $server["data"]);
            //echo '<pre>';
            foreach ($arr as $key => $line) {
                $line = trim($line);

                if (preg_match("/^$queue->name/", $line)) {
                    $holdtime      = $this->get_string_between($line, 'strategy (', 's holdtime');
                    $talktime      = $this->get_string_between($line, ',', 's talktime');
                    $totalCalls    = $this->get_string_between($line, 'C:', ',');
                    $answeredCalls = $this->get_string_between($line, 'A:', ',');
                    $sql           = "UPDATE pkg_queue SET var_holdtime = $holdtime, var_talktime = $talktime
                                    , var_totalCalls = $totalCalls, var_answeredCalls = $answeredCalls
                                    WHERE id = :id";
                    $command = Yii::app()->db->createCommand($sql);
                    $command->bindValue(":id", $queue->id, PDO::PARAM_STR);
                    $command->execute();
                    continue;
                } elseif (preg_match("/^Members/", $line)) {
                    continue;
                } elseif (preg_match("/^SIP/", $line)) {

                    $username    = $this->get_string_between($line, 'SIP/', ' ');
                    $totalCalls  = $this->get_string_between($line, 'has taken ', 'calls');
                    $totalCalls  = is_numeric($totalCalls) ? $totalCalls : 0;
                    $agentStatus = preg_replace("/\(|\)/", '', $this->get_string_between($line, 'realtime) (', ' has taken'));
                    $last_call   = $this->get_string_between($line, '(last was ', ' secs ago)');
                    $last_call   = is_numeric($last_call) ? $last_call : 0;
                    $resultSIP   = Sip::model()->findAll(array('condition' => "name = '$username'"));
                    $id_user     = $resultSIP[0]->id_user;

                    /*echo $agentStatus."\n";
                    echo $totalCalls."\n";
                    echo $id_user."\n";
                    echo $username."\n";*/
                    $valuesInsert = ":id_user, :username, :agentStatus, :totalCalls, :last_call, :queueId";
                    $sql          = "INSERT INTO pkg_queue_agent_status
                                (id_user, agentName, agentStatus, totalCalls, last_call, id_queue)
                                VALUES ($valuesInsert) ";
                    $command = Yii::app()->db->createCommand($sql);
                    $command->bindValue(":id_user", $id_user, PDO::PARAM_INT);
                    $command->bindValue(":username", $username, PDO::PARAM_STR);
                    $command->bindValue(":agentStatus", $agentStatus, PDO::PARAM_STR);
                    $command->bindValue(":totalCalls", $totalCalls, PDO::PARAM_STR);
                    $command->bindValue(":last_call", $last_call, PDO::PARAM_STR);
                    $command->bindValue(":queueId", $queue->id, PDO::PARAM_STR);
                    $command->execute();

                }

                //if (preg_match("/stripslashes($member)/", $line)) {
                //echo ($line)."\n      ";
                //}
            }

        }

    }

    public function setAttributesModels($attributes, $models)
    {

        for ($i = 0; $i < count($attributes) && is_array($attributes); $i++) {
            if ($attributes[$i]['id_agent'] > 0) {
                $result = Queue::model()->getQueueAgentStatus($attributes[$i]['id_agent']);

                $agentName = $result[0]['agentName'];
                $time      = $attributes[$key]['time'];
                $now       = date('Y-m-d H:i:s');
                $duration  = strtotime($now) - strtotime($time) - $attributes[$key]['oldtime'];

            } else {
                $agentName = '';

                $time    = $attributes[$key]['time'];
                $now     = date('Y-m-d H:i:s');
                $oldtime = strtotime($now) - strtotime($time);

                $oldtime = $oldtime;
            }

            $attributes[$i]['agentName'] = $agentName;
            $attributes[$i]['duration']  = $duration;
        }
        return $attributes;
    }

    public function get_string_between($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini    = strpos($string, $start);
        if ($ini == 0) {
            return '';
        }

        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return trim(substr($string, $ini, $len));
    }

}
