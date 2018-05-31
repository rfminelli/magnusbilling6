<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 *
 */

class QueueAgi
{
    public function callQueue(&$agi, &$MAGNUS, &$CalcAgi, $DidAgi = null, $type = 'queue')
    {
        $agi->verbose("Queue module", 5);

        if ($DidAgi->modelDid->cbr == 1) {
            CallbackAgi::advanced0800CallBack($agi, $MAGNUS, $DidAgi);
        }

        $agi->answer();
        $startTime           = time();
        $MAGNUS->destination = $DidAgi->modelDid->did;

        $sql = "SELECT * FROM pkg_queue
                            LEFT JOIN pkg_user ON pkg_queue.id_user = pkg_user.id
                            WHERE pkg_queue.id = " . $DidAgi->modelDestination[0]['id_queue'] . " LIMIT 1 ";
        $modelQueue = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

        $agi->set_variable("UNIQUEID", $MAGNUS->uniqueid);
        $agi->set_variable("QUEUCALLERID", $MAGNUS->CallerID);
        $agi->set_variable("IDQUEUE", $modelQueue->id);
        $agi->set_variable("USERNAME", $modelQueue->username);
        $agi->set_variable('CHANNEL(language)', $modelQueue->language);

        $queueName = $modelQueue->name;
        $agi->verbose($queueName);

        $sql = "INSERT INTO pkg_queue_status (id_queue, callId, queue_name, callerId, time, channel, status)
                        VALUES (" . $modelQueue->id . ", '" . $MAGNUS->uniqueid . "', '$queueName', '" . $MAGNUS->CallerID . "',
                        '" . date('Y-m-d H:i:s') . "', '" . $MAGNUS->channel . "', 'ringing')";
        $agi->exec($sql);

        $ring_or_moh = $modelQueue->ring_or_moh == 'ring' ? 'r' : '';
        $agi->verbose("Queue", $queueName . ',' . $ring_or_moh . 'tc,,,,/var/www/html/mbilling/agi.php');
        $agi->execute("Queue", $queueName . ',' . $ring_or_moh . 'tc,,,,/var/www/html/mbilling/agi.php');

        $MAGNUS->stopRecordCall($agi);

        $sql = "DELETE FROM pkg_queue_status WHERE callId = " . $MAGNUS->uniqueid;
        $agi->exec($sql);

        $stopTime = time();

        $CalcAgi->sessiontime = $stopTime - $startTime;

        $siptransfer = $agi->get_variable("SIPTRANSFER");

        $linha = exec(" egrep $MAGNUS->uniqueid /var/log/asterisk/queue_log | tail -1");
        $linha = explode('|', $linha);

        $agi->verbose(print_r($linha, true), 25);

        if ($linha[4] == 'ABANDON' || $linha[4] == 'EXITEMPTY' || $linha[4] == 'EXITWITHTIMEOUT') {
            $CalcAgi->terminatecauseid = 7;
        } else {
            $CalcAgi->terminatecauseid = 1;
        }
        if (!is_null($DidAgi)) {
            $DidAgi->billDidCall($agi, $MAGNUS, $CalcAgi->sessiontime);
        }

        $agi->verbose('$siptransfer => ' . $siptransfer['data'], 5);
        if ($siptransfer['data'] != 'yes' && $type == 'queue') {
            $sql = "SELECT id FROM pkg_prefix WHERE prefix = SUBSTRING('$MAGNUS->destination',1,length(prefix))
                                ORDER BY LENGTH(prefix) DESC  ";
            $modelPrefix = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

            $MAGNUS->id_user           = $modelQueue->id_user;
            $MAGNUS->id_plan           = $modelQueue->id_plan;
            $MAGNUS->id_trunk          = null;
            $CalcAgi->starttime        = date("Y-m-d H:i:s", time() - $CalcAgi->sessiontime);
            $CalcAgi->sessiontime      = $CalcAgi->sessiontime;
            $CalcAgi->real_sessiontime = intval($CalcAgi->sessiontime);
            $CalcAgi->terminatecauseid = $CalcAgi->terminatecauseid;
            $CalcAgi->sessionbill      = $DidAgi->sell_price;
            $CalcAgi->sipiax           = 8;
            $CalcAgi->buycost          = 0;
            $CalcAgi->id_prefix        = $modelPrefix->id;
            $CalcAgi->saveCDR($agi, $MAGNUS);

        }
        if ($type == 'queue') {
            $MAGNUS->hangup($agi);
            exit;
        } else {
            return;
        }

    }

    public function recIvrQueue($agi, $MAGNUS, $CalcAgi)
    {

        $agi->verbose('recIvrQueue');
        $operator = preg_replace("/SIP\//", "", $agi->get_variable("MEMBERNAME", true));

        $MAGNUS->uniqueid    = $agi->get_variable("UNIQUEID", true);
        $MAGNUS->destination = $agi->request['agi_extension'];
        $MAGNUS->accountcode = $agi->get_variable("USERNAME", true);
        $id_queue            = $agi->get_variable("IDQUEUE", true);
        $callerid            = $agi->get_variable("QUEUCALLERID", true);
        $oldtime             = $agi->get_variable("QEHOLDTIME", true);

        $sql      = "SELECT mohsuggest, record_call FROM pkg_sip WHERE name = '$operator' LIMIT 1";
        $modelSip = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
        if (isset($modelSip->mohsuggest) && strlen($modelSip->mohsuggest) > 1) {
            $agi->execute('SetMusicOnHold', $modelSip->mohsuggest);
        }

        $sql = "UPDATE pkg_queue_status SET status = 'answered', id_agent =
                (SELECT id FROM pkg_queue_agent_status WHERE agentName = '$operator' AND id_queue = $id_queue),
                    oldtime = '$oldtime'  WHERE callId = '$uniqueid' ";
        $agi->exec($sql);

        $agi->verbose("\n\n" . $MAGNUS->uniqueid . " $operator answer the call from QUEUE \n\n", 6);

        $sql       = "SELECT mix_monitor_format FROM pkg_user WHERE id = $modelSip->id_user LIMIT 1";
        $modelUser = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

        $MAGNUS->record_call        = $modelSip->record_call;
        $MAGNUS->mix_monitor_format = $modelUser->mix_monitor_format;
        $MAGNUS->startRecordCall($agi);
        exit;
    }
}
