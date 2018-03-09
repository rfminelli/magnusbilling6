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

class IvrAgi
{
    public function callIvr(&$agi, &$MAGNUS, &$Calc, $modelDestination, $DidAgi = null, $type = 'ivr')
    {

        $agi->verbose("Ivr module", 5);
        $agi->verbose("DID IVR - CallerID=" . $MAGNUS->CallerID . " -> DID=" . $modelDestination->idDid->did, 6);
        $agi->answer();
        $startTime = time();

        $MAGNUS->destination = $modelDestination->idDid->did;

        $modelIvr = Ivr::model()->findByPk($modelDestination->id_ivr);

        $username            = $modelIvr->idUser->username;
        $MAGNUS->id_user     = $modelIvr->id_user;
        $MAGNUS->id_plan     = $modelIvr->idUser->id_plan;
        $MAGNUS->record_call = $modelIvr->idUser->record_call;

        $monFriStart = $modelIvr->monFriStart;
        $monFriStop  = $modelIvr->monFriStop;
        $satStart    = $modelIvr->satStart;
        $satStop     = $modelIvr->satStop;
        $sunStart    = $modelIvr->sunStart;
        $sunStop     = $modelIvr->sunStop;
        $nowDay      = date('D');
        $nowHour     = date('H:i:s');

        if ($nowDay != 'Sun' && $nowDay != 'Sat') {
            if ($nowHour > $monFriStart && $nowHour < $monFriStop) {
                $agi->verbose("MonFri");
                $work = true;
            }
        }

        if ($nowDay == 'Sat') {
            if ($nowHour > $satStart && $nowHour < $satStop) {
                $agi->verbose("Sat");
                $work = true;
            }
        }

        if ($nowDay == 'Sun') {
            if ($nowHour > $sunStart && $nowHour < $sunStop) {
                $agi->verbose("Sun");
                $work = true;
            }
        }

        //esta dentro do hario de atencao
        if ($work) {
            $audioURA   = 'idIvrDidWork_';
            $optionName = 'option_';
        } else {
            $audioURA   = 'idIvrDidNoWork_';
            $optionName = 'option_out_';
        }

        $continue  = true;
        $insertCDR = false;
        $i         = 0;
        while ($continue == true) {

            $agi->verbose("EXECUTE IVR " . $modelIvr->name);
            $i++;

            if ($i == 10) {
                $continue = false;
                break;
            }
            $audio = $MAGNUS->magnusFilesDirectory . '/sounds/' . $audioURA . $modelDestination->id_ivr;
            if (file_exists($audio . ".gsm") || file_exists($audio . ".wav")) {
                $res_dtmf = $agi->get_data($audio, 3000, 1);
                $option   = $res_dtmf['result'];
            } else {
                $agi->verbose('NOT EXIST AUDIO TO IVR DEFAULT OPTION ' . $audio, 5);
                $option   = '10';
                $continue = false;
            }
            $agi->verbose('option' . $option, 10);
            //se nao marcou
            if (strlen($option) < 1) {
                $agi->verbose('DEFAULT OPTION');
                $option   = '10';
                $continue = false;
            }
            //se marca uma opÃ§ao que esta em branco
            else if ($modelIvr->{$optionName . $option} == '') {
                $agi->verbose('NUMBER INVALID');
                $agi->stream_file('prepaid-invalid-digits', '#');
                continue;
            }

            $dtmf        = explode(("|"), $modelIvr->{$optionName . $option});
            $optionType  = $dtmf[0];
            $optionValue = $dtmf[1];
            $agi->verbose("CUSTOMER PRESS $optionType -> $optionValue");

            if ($optionType == 'sip') // QUEUE
            {
                $agi->verbose('Sip call, active insertCDR', 25);
                $insertCDR = true;
                $modelSip  = Sip::model()->findByPk($optionValue);

                $dialparams = $dialparams = $MAGNUS->agiconfig['dialcommand_param_sipiax_friend'];
                $dialparams = str_replace("%timeout%", 3600, $dialparams);
                $dialparams = str_replace("%timeoutsec%", 3600, $dialparams);
                $dialstr    = 'SIP/' . $modelSip->name . $dialparams;
                $agi->verbose($dialstr, 25);

                $MAGNUS->startRecordCall($agi);

                $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->config["agi-conf1"]['dialcommand_param_sipiax_friend']);

                $dialstatus = $agi->get_variable("DIALSTATUS");
                $dialstatus = $dialstatus['data'];

                $modelSipForward = Sip::model()->find('name = :key', array(':key' => $modelSip->name));
                if (strlen($modelSipForward->forward) > 3 && $dialstatus != 'CANCEL' && $dialstatus != 'ANSWER') {
                    $credit = $modelSipForward->idUser->typepaid == 1
                    ? $modelSipForward->idUser->credit + $modelSipForward->idUser->creditlimit
                    : $modelSipForward->idUser->credit;

                    if (Sip::model()->find('name = :key', array(':key' => $modelSipForward->forward))) {
                        $agi->verbose('Forward to sipaccount ' . $modelSipForward->forward, 5);
                        $MAGNUS->dnid = $MAGNUS->destination = $modelSipForward->forward;
                        $sipCallAgi   = new SipCallAgi();
                        $sipCallAgi->processCall($MAGNUS, $agi, $Calc);
                    } elseif ($credit > 1) {
                        $agi->verbose('Forward to PSTN network. Number ' . $modelSipForward->forward, 5);
                        $MAGNUS->dnid        = $MAGNUS->destination        = $MAGNUS->extension        = $modelSipForward->forward;
                        $MAGNUS->accountcode = $modelSipForward->accountcode;

                        if (AuthenticateAgi::authenticateUser($agi, $MAGNUS)) {
                            if ($MAGNUS->checkNumber($agi, $Calc, 0, true) == 1) {
                                $standardCall = new StandardCallAgi();
                                $standardCall->processCall($MAGNUS, $agi, $Calc);

                                $dialstatus   = $Calc->dialstatus;
                                $answeredtime = $Calc->answeredtime;
                                /* INSERT CDR  & UPDATE SYSTEM*/
                                $Calc->updateSystem($this, $agi, $this->destination, 1, 1);
                            }

                        }
                    }
                }

                break;

            } else if ($optionType == 'repeat') // CUSTOM
            {
                $agi->verbose("repetir IVR");
                continue;
            } else if (preg_match("/hangup/", $optionType)) // hangup
            {
                $agi->verbose("Hangup IVR");
                $insertCDR = true;
                break;
            } else if ($optionType == 'group') // CUSTOM
            {
                $agi->verbose("Call to group " . $optionValue, 1);
                $modelSip = Sip::model()->findAll('`group` = :key', array(':key' => $optionValue));

                if (!count($modelSip)) {
                    $agi->verbose('GROUP NOT FOUND');
                    $agi->stream_file('prepaid-invalid-digits', '#');
                    continue;
                }
                $group = '';

                foreach ($modelSip as $key => $value) {
                    $group .= "SIP/" . $value->name . "&";
                }

                $dialstr = substr($group, 0, -1) . $dialparams;

                $MAGNUS->startRecordCall($agi);

                $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->agiconfig['dialcommand_param_call_2did']);
                $dialstatus = $agi->get_variable("DIALSTATUS");
                $dialstatus = $dialstatus['data'];
                $insertCDR  = true;
            } else if (preg_match("/custom/", $optionType)) // CUSTOM
            {
                $insertCDR = true;
                $MAGNUS->startRecordCall($agi);
                $myres      = $MAGNUS->run_dial($agi, $optionValue);
                $dialstatus = $agi->get_variable("DIALSTATUS");
                $dialstatus = $dialstatus['data'];
            } else if ($optionType == 'ivr') // QUEUE
            {
                $modelDestination->id_ivr = $optionValue;
                IvrAgi::callIvr($agi, $MAGNUS, $Calc, $modelDestination, null, $type);
            } else if ($optionType == 'queue') // QUEUE
            {
                $insertCDR                  = false;
                $modelDestination->id_queue = $optionValue;
                QueueAgi::callQueue($agi, $MAGNUS, $Calc, $modelDestination, null, $type);
            } else if (preg_match("/^number/", $optionType)) //envia para um fixo ou celular
            {
                $insertCDR = false;
                $agi->verbose("CALL number $optionValue");
                $DidAgi->call_did($agi, $MAGNUS, $Calc, $optionValue);
            }

            $agi->verbose("FIM do loop");

            $continue  = false;
            $insertCDR = true;

        }

        $stopTime = time();

        $answeredtime = $stopTime - $startTime;

        $terminatecauseid = 1;

        $siptransfer = $agi->get_variable("SIPTRANSFER");

        $tipo = 9;
        $MAGNUS->stopRecordCall($agi);

        if ($siptransfer['data'] != 'yes' && $insertCDR == true && $type == 'ivr') {
            $agi->verbose('Hangup IVR call, send to call_did_billing', 25);
            $DidAgi->call_did_billing($agi, $MAGNUS, $Calc, $answeredtime, $dialstatus);
        }

        if ($type == 'ivr') {
            $MAGNUS->hangup($agi);
        } else {
            return;
        }

    }
}
