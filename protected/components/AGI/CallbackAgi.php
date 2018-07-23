<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2018 MagnusSolution. All rights reserved.
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

class CallbackAgi
{
    public function callbackCID($agi, $MAGNUS, $Calc, $mydnid)
    {
        $agi->verbose("MAGNUS CID CALLBACK");
        $MAGNUS->agiconfig['cid_enable'] = 1;

        if ($MAGNUS->dnid == 'failed' || !is_numeric($MAGNUS->dnid)) {
            $agi->verbose("Hangup becouse dnid is OutgoingSpoolFailed", 25);
            $MAGNUS->hangup($agi);
            exit;
        }

        $agi->verbose('CallerID ' . $MAGNUS->CallerID);

        if (strlen($MAGNUS->CallerID) > 1 && is_numeric($MAGNUS->CallerID)) {
            $cia_res = AuthenticateAgi::authenticateUser($agi, $MAGNUS);

            if ($cia_res == 1) {

                $MAGNUS->destination = $MAGNUS->countryCode . $MAGNUS->CallerID;

                $agi->verbose('$MAGNUS->destination =>' . $MAGNUS->destination);

                /*protabilidade*/
                $MAGNUS->destination = $MAGNUS->number_translation($agi, $MAGNUS->destination);

                $SearchTariff = new SearchTariff();
                $resfindrate  = $SearchTariff->find($MAGNUS->destination, $MAGNUS->id_plan, $MAGNUS->id_user, $agi);

                $Calc->tariffObj    = $resfindrate;
                $Calc->number_trunk = count($resfindrate);

                if (substr("$MAGNUS->destination", 0, 4) == 1111) {
                    $MAGNUS->destination = str_replace(substr($MAGNUS->destination, 0, 7), "", $MAGNUS->destination);
                }

                $Calc->usedratecard = 0;
                if ($resfindrate != 0) {
                    $res_all_calcultimeout = $Calc->calculateAllTimeout($MAGNUS, $MAGNUS->credit, $agi);
                    if ($res_all_calcultimeout) {
                        $destination  = $MAGNUS->destination;
                        $providertech = $Calc->tariffObj[0]['rc_providertech'];
                        $ipaddress    = $Calc->tariffObj[0]['rc_providerip'];
                        $removeprefix = $Calc->tariffObj[0]['rc_removeprefix'];
                        $prefix       = $Calc->tariffObj[0]['rc_trunkprefix'];

                        if (strncmp($destination, $removeprefix, strlen($removeprefix)) == 0) {
                            $destination = substr($destination, strlen($removeprefix));
                        }

                        $dialstr = "$providertech/$ipaddress/$prefix$destination";

                        $call = "Channel: " . $dialstr . "\n";
                        $call .= "Callerid: " . $MAGNUS->CallerID . "\n";
                        $call .= "Context: billing\n";
                        $call .= "Extension: " . $MAGNUS->destination . "\n";
                        $call .= "Priority: 1\n";
                        $call .= "Set:CALLED=" . $MAGNUS->destination . "\n";
                        $call .= "Set:TARRIFID=" . $Calc->tariffObj[0]['id_rate'] . "\n";
                        $call .= "Set:SELLCOST=" . $Calc->tariffObj[0]['rateinitial'] . "\n";
                        $call .= "Set:BUYCOST=" . $Calc->tariffObj[0]['buyrate'] . "\n";
                        $call .= "Set:CIDCALLBACK=1\n";
                        $call .= "Set:IDUSER=" . $MAGNUS->id_user . "\n";
                        $call .= "Set:IDPREFIX=" . $Calc->tariffObj[0]['id_prefix'] . "\n";
                        $call .= "Set:IDTRUNK=" . $Calc->tariffObj[0]['id_trunk'] . "\n";
                        $call .= "Set:IDPLAN=" . $MAGNUS->id_plan . "\n";
                        AsteriskAccess::generateCallFile($call, 5);
                        $agi->answer();

                    }
                } else {
                    $agi->verbose("NO TARIFF FOUND");
                }
            }
        }

        $MAGNUS->hangup($agi);
        exit;
    }

    public function callback0800($agi, $MAGNUS, $Calc, $modelDestination)
    {

        $agi->verbose("MAGNUS 0800 CALLBACK");

        if ($MAGNUS->dnid == 'failed' || !is_numeric($MAGNUS->dnid)) {
            $agi->verbose("Hangup becouse dnid is OutgoingSpoolFailed", 25);
            $MAGNUS->hangup($agi);
            exit;
        }
        $destination = $MAGNUS->CallerID;

        $removeprefix = $MAGNUS->config['global']['callback_remove_prefix'];
        if (strncmp($destination, $removeprefix, strlen($removeprefix)) == 0) {
            $destination = substr($destination, strlen($removeprefix));
        }

        $addprefix   = $MAGNUS->config['global']['callback_add_prefix'];
        $destination = $addprefix . $destination;

        $user = $modelDestination[0]->idUser->username;

        $MAGNUS->modelSip = Sip::model()->find('id_user = :key', array(':key' => $modelDestination[0]->id_user));

        if (!count($MAGNUS->modelSip)) {
            $agi->verbose("Username not have SIP ACCOUNT");
            $MAGNUS->hangup($agi);
            return;
        }
        $destino = $MAGNUS->modelSip->name;
        $id_user = $modelDestination[0]->id_user;

        if ($MAGNUS->config['global']['answer_callback'] == 1) {
            $agi->answer();
            sleep(2);
            $agi->stream_file('prepaid-callback', '#');
        }

        $dialstr = "SIP/$destino";
        // gerar os arquivos .call
        $call = "Channel: " . $dialstr . "\n";
        $call .= "Callerid: " . $destination . "\n";
        $call .= "Context: billing\n";
        $call .= "Extension: " . $user . "\n";
        $call .= "Priority: 1\n";
        $call .= "Set:IDUSER=" . $id_user . "\n";
        $call .= "Set:SECCALL=" . $destination . "\n";

        AsteriskAccess::generateCallFile($call, 5);
        $agi->evaluate("ANSWER 0");
        $MAGNUS->hangup($agi);

    }

    public static function chargeFistCall($agi, $MAGNUS, $Calc, $sessiontime = 0)
    {

        if ($MAGNUS->dnid == 'failed' || !is_numeric($MAGNUS->dnid)) {
            $agi->verbose("Hangup becouse dnid is OutgoingSpoolFailed", 25);
            $MAGNUS->hangup($agi);
            exit;
        }

        if ($agi->get_variable("IDPREFIX", true) > 0) {

            $agi->verbose("Callback: CHARGE FOR THE 1ST LEG callback_username=$MAGNUS->username", 10);
            $sell             = $agi->get_variable("SELLCOST", true);
            $buycost          = $agi->get_variable("BUYCOST", true);
            $MAGNUS->id_user  = $agi->get_variable("IDUSER", true);
            $called           = $agi->get_variable("CALLED", true);
            $MAGNUS->id_plan  = $agi->get_variable("IDPLAN", true);
            $MAGNUS->id_trunk = $agi->get_variable("IDTRUNK", true);
            $idPrefix         = $agi->get_variable("IDPREFIX", true);
            $called           = $agi->get_variable("CALLED", true);

            $MAGNUS->modelUser = User::model()->findByPk((int) $MAGNUS->id_user);

            if ($sessiontime == 0) {

                $sell30    = $sell / 2;
                $buycost30 = $buycos / 2;

                //desconto 1 minuto assim que o cliente atende a chamada
                User::model()->updateByPk($MAGNUS->modelUser->id,
                    array(
                        'credit' => new CDbExpression('credit - ' . $MAGNUS->round_precision(abs($sell30))),
                    )
                );

                $sessiontime1fsLeg = 30;

                $modelCall                   = new Call();
                $modelCall->uniqueid         = $MAGNUS->uniqueid;
                $modelCall->id_user          = $MAGNUS->id_user;
                $modelCall->starttime        = date("Y-m-d H:i:s", time() - $sessiontime1fsLeg);
                $modelCall->sessiontime      = $sessiontime1fsLeg;
                $modelCall->real_sessiontime = intval($sessiontime1fsLeg);
                $modelCall->calledstation    = $called;
                $modelCall->terminatecauseid = 1;
                $modelCall->sessionbill      = $sell30;
                $modelCall->id_plan          = $MAGNUS->id_plan;
                $modelCall->id_trunk         = $MAGNUS->id_trunk;
                $modelCall->src              = $MAGNUS->CallerID;
                $modelCall->sipiax           = 4;
                $modelCall->buycost          = $buycost30;
                $modelCall->id_prefix        = $idPrefix;
                $modelCall->save();
                $modelError = $modelCall->getErrors();
                if (count($modelError)) {
                    $agi->verbose(print_r($modelError, true), 25);
                }

                $Calc->idCallCallBack = $modelCall->id;
            } elseif ($sessiontime) {

                $selltNew = ($sell / 60) * $sessiontime;

                $sessiontime = $sessiontime + 30;
                $sell        = ($sell / 60) * $sessiontime;
                $buycost     = ($buycost / 60) * $sessiontime;

                $modelCall              = Call::model()->findByPk((int) $$Calc->idCallCallBack);
                $modelCall->sessiontime = $sessiontime;
                $modelCall->sessionbill = $sell;
                $modelCall->buycost     = $buycost;
                $modelCall->save();
                $modelError = $modelCall->getErrors();
                if (count($modelError)) {
                    $agi->verbose(print_r($modelError, true), 25);
                }
                User::model()->updateByPk($MAGNUS->modelUser->id,
                    array(
                        'credit' => new CDbExpression('credit - ' . $MAGNUS->round_precision(abs($selltNew))),
                    )
                );
            }
        }
    }

    public function advanced0800CallBack($agi, $MAGNUS, $modelDestination)
    {
        $MAGNUS->prefix_local = $modelDestination->idUser->prefix_local;
        $MAGNUS->CallerID     = preg_replace("/\+/", '', $MAGNUS->CallerID);
        $MAGNUS->number_translation($agi, $MAGNUS->CallerID);
        $callerID = $MAGNUS->destination;

        //adiciona o 55 se o callerid tiver somente com DDD numero
        if ((strtoupper($MAGNUS->config['global']['base_country']) == 'BRL' || strtoupper($MAGNUS->config['global']['base_country']) == 'ARG')
            && (strlen($callerID) == 10 || strlen($callerID) == 11)) {
            $callerID = "55" . $callerID;
        }
        $work = $MAGNUS->checkIVRSchedule($modelDestination->idDid);

        $modelCallBack = CallBack::model()->find('exten = :key AND status IN (1,4) AND id_did = :key1',
            array(
                ':key'  => $callerID,
                ':key1' => $modelDestination->id_did,
            ));
        if (!count($modelCallBack)) {
            $modelCallBack = new CallBack();
        }

        $modelCallBack->id_did  = $modelDestination->id_did;
        $modelCallBack->exten   = $callerID;
        $modelCallBack->id_user = $modelDestination->id_user;
        $modelCallBack->status  = $work != 'open' ? 4 : 1;
        $modelCallBack->save();

        $agi->verbose($callerID, 25);

        //audio enable
        if ($modelDestination->idDid->cbr_ua == 1) {

            //esta dentro do hario de atencao
            $audioURA = $work == 'open' ? 'idDidAudioProWork_' : 'idDidAudioProNoWork_';
            $audio    = $MAGNUS->magnusFilesDirectory . '/sounds/' . $audioURA . $modelDestination->id_did;
            //early_media enable
            if ($modelDestination->idDid->cbr_em == 1) {
                $agi->verbose('earl ok');
                $agi->execute('Ringing');
                $agi->execute("Progress");
                $agi->execute('Wait', '1');
                $agi->execute('Playback', "$audio,noanswer");
            } else {
                $agi->answer();
                $agi->execute('Wait', '1');
                $agi->stream_file($audio, '#');
            }
        }

        $agi->execute('Congestion', '5');
        $MAGNUS->hangup($agi);
        exit;
    }
}
