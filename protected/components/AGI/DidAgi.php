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

class DidAgi
{
    public $voip_call;
    public $did;
    public $sell_price;
    public $modelDestination;

    public function checkIfIsDidCall(&$agi, &$MAGNUS, &$Calc)
    {

        //check if did call
        $mydnid = substr($MAGNUS->dnid, 0, 1) == '0' ? substr($MAGNUS->dnid, 1) : $MAGNUS->dnid;
        $agi->verbose('Check If Is Did ' . $mydnid, 10);
        $modelDid = Did::model()->find('did = :key', array(':key' => $mydnid));
        if (count($modelDid)) {
            $agi->verbose("Is a DID call", 5);
            $this->modelDestination = Diddestination::model()->findAll(
                array(
                    'condition' => "t.activated = 1 AND id_did = :key",
                    'order'     => 'priority ASC',
                    'params'    => array(':key' => $modelDid->id),
                )
            );
            if (count($this->modelDestination)) {
                $agi->verbose("Did have destination", 15);

                if ($modelDid->calllimit > 0) {
                    $agi->verbose('Check DID channels');
                    $calls = AsteriskAccess::getCallsPerDid($modelDid->did);
                    $agi->verbose('Did ' . $modelDid->did . ' have ' . $calls . ' Calls');
                    if ($calls > $modelDid->calllimit) {
                        if ($modelDid->idUser->calllimit_error == 403) {
                            $agi->execute((busy), busy);
                        } else {
                            $agi->execute((congestion), Congestion);
                        }

                        $MAGNUS->hangup($agi);
                        exit;
                    }
                }
                $this->checkDidDestinationType($agi, $MAGNUS, $Calc);
            } else {
                $agi->verbose("Is a DID call But not have destination Hangup Call");
                $MAGNUS->hangup($agi);
            }
        }
    }
    public function checkDidDestinationType(&$agi, &$MAGNUS, &$Calc)
    {
        $this->didCallCost($agi, $MAGNUS);

        $this->did = $this->modelDestination[0]->idDid->did;
        $agi->verbose('DID ' . $this->did, 5);

        //if DID option charge of was = 0 only allow call from existent callerid
        if ($this->modelDestination[0]->idDid->charge_of == 0) {
            $modelCallerId = Callerid::model()->find('cid = :key AND activated = 1', array(':key' => $MAGNUS->CallerID));
            if (count($modelCallerId)) {
                $agi->verbose('found callerid, new user = ' . $modelCallerId->idUser->username . ' ' . $this->sell_price);
                $Calc->did_charge_of_id_user                      = $modelCallerId->idUser->id;
                $Calc->did_charge_of_answer_time                  = time();
                $Calc->didAgi                                     = $this->modelDestination[0]->idDid;
                $this->modelDestination[0]->idDid->selling_rate_1 = $this->sell_price;

            } else {
                $agi->verbose('NOT found callerid, = ' . $MAGNUS->CallerID . ' to did ' . $this->did . ' and was selected charge_of to callerID');
                $MAGNUS->hangup($agi);
            }
        }

        //check if is a call betewen 2 sipcounts.
        if (strlen($MAGNUS->accountcode) > 0) {
            $modelSip = Sip::model()->find('name = :did', array(':did' => $this->did));
        }

        if (!isset($modelSip) || !count($modelSip)) {

            $MAGNUS->record_call = $this->modelDestination[0]->idDid->idUser->record_call;
            $MAGNUS->accountcode = $MAGNUS->username = $this->modelDestination[0]->idDid->idUser->username;

            $this->voip_call = $this->modelDestination[0]->voip_call;
            $this->checkBlockCallerID($agi, $MAGNUS);

            $agi->verbose('voip_call ' . $this->voip_call, 5);
            switch ($this->voip_call) {
                case 2:
                    $MAGNUS->mode = 'ivr';
                    IvrAgi::callIvr($agi, $MAGNUS, $Calc, $this->modelDestination[0], $this);
                    break;
                case 3:
                    //callingcard
                    $MAGNUS->mode = 'standard';
                    $agi->answer();
                    sleep(1);
                    $MAGNUS->callingcardConnection = $this->modelDestination[0]->idDid->connection_sell;

                    $MAGNUS->agiconfig['use_dnid']        = 0;
                    $MAGNUS->agiconfig['answer']          = $MAGNUS->agiconfig['callingcard_answer'];
                    $MAGNUS->agiconfig['cid_enable']      = $MAGNUS->agiconfig['callingcard_cid_enable'];
                    $MAGNUS->agiconfig['number_try']      = $MAGNUS->agiconfig['callingcard_number_try'];
                    $MAGNUS->agiconfig['say_rateinitial'] = $MAGNUS->agiconfig['callingcard_say_rateinitial'];
                    $MAGNUS->agiconfig['say_timetocall']  = $MAGNUS->agiconfig['callingcard_say_timetocall'];

                    $MAGNUS->CallerID = is_numeric($MAGNUS->CallerID) ? $MAGNUS->CallerID : $agi->request['agi_calleridname'];
                    $agi->verbose('CallerID ' . $MAGNUS->CallerID);
                    break;
                case 4:
                    $MAGNUS->mode = 'portalDeVoz';
                    $agi->verbose('PortalDeVozAgi');
                    PortalDeVozAgi::send($agi, $MAGNUS, $Calc, $this->modelDestination);
                    break;
                case 5:
                    $agi->verbose('RECEIVED ANY CALLBACK', 5);
                    CallbackAgi::callbackCID($agi, $MAGNUS, $Calc, $this->modelDestination);
                    break;
                case 6:
                    if (!$agi->get_variable("SECCALL", true)) {
                        $agi->verbose('RECEIVED 0800 CALLBACK', 5);
                        CallbackAgi::callback0800($agi, $MAGNUS, $Calc, $this->modelDestination);
                    }
                    break;
                case 7:
                    $MAGNUS->mode = 'queue';
                    QueueAgi::callQueue($agi, $MAGNUS, $Calc, $this->modelDestination[0], $this);
                    break;
                default:
                    $agi->verbose('Mode = did', 5);
                    $MAGNUS->mode = 'did';
                    $this->call_did($agi, $MAGNUS, $Calc);
                    break;
            }

        }
    }

    public function call_did(&$agi, &$MAGNUS, &$Calc, $destinationIvr = false)
    {

        //sip call, group, custom or PSTN destination
        if ($MAGNUS->agiconfig['answer_call'] == 1) {
            $agi->verbose("ANSWER CALL", 6);
            $agi->answer();
        }

        $Calc->init();
        $MAGNUS->init();

        $agi->verbose("DID CALL - CallerID=" . $MAGNUS->CallerID . " -> DID=" . $this->did, 6);

        $res = 0;

        $MAGNUS->agiconfig['say_timetocall'] = 0;

        //altera o destino do did caso ele venha de uma IVR
        $this->modelDestination[0]->destination = $destinationIvr ? $destinationIvr : $this->modelDestination[0]->destination;

        $callcount = 0;

        foreach ($this->modelDestination as $inst_listdestination) {

            $agi->verbose(print_r($inst_listdestination->getAttributes(), true), 10);

            $callcount++;

            $MAGNUS->agiconfig['cid_enable'] = 0;
            $MAGNUS->accountcode             = $MAGNUS->username             = $inst_listdestination->idUser->username;
            $MAGNUS->id_plan                 = $inst_listdestination->idUser->id_plan;
            $did                             = $inst_listdestination->idDid->did;

            $msg = "[Magnus] DID call friend: FOLLOWME=$callcount (username:" . $MAGNUS->username . "
                    | destination type:" . $this->voip_call . "| id_plan:" . $MAGNUS->id_plan . ")";
            $agi->verbose($msg, 10);

            if (AuthenticateAgi::authenticateUser($agi, $MAGNUS) != 1) {
                $msg = "DID AUTHENTICATION ERROR";
            } else {

                $MAGNUS->record_call = $inst_listdestination->idDid->idUser->record_call;

                /* IF SIP CALL*/
                if ($inst_listdestination['voip_call'] == 1) {
                    $agi->verbose("DID call friend: IS LOCAL !!!", 10);
                    $modelSip            = Sip::model()->findByPk((int) $inst_listdestination['id_sip']);
                    $MAGNUS->destination = $modelSip['name'];
                    $MAGNUS->voicemail   = count($modelSip) ? $modelSip->voicemail : false;
                    if (count($modelSip)) {
                        $inst_listdestination['destination'] = "SIP/" . $modelSip->name;
                    } else {
                        $agi->stream_file('prepaid-dest-unreachable', '#');
                        continue;
                    }

                    $MAGNUS->startRecordCall($agi, $did);

                    $dialstr = $inst_listdestination['destination'] . $dialparams;

                    $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->agiconfig['dialcommand_param_call_2did']);
                    $agi->verbose("DIAL $dialstr", 6);

                    $answeredtime = $agi->get_variable("ANSWEREDTIME");
                    $answeredtime = $answeredtime['data'];
                    $dialstatus   = $agi->get_variable("DIALSTATUS");
                    $dialstatus   = $dialstatus['data'];

                    $MAGNUS->stopRecordCall($agi);

                    $agi->verbose($inst_listdestination['destination'] . " Friend -> followme=$callcount : ANSWEREDTIME=" . $answeredtime . "-DIALSTATUS=" . $dialstatus, 6);

                    if ($this->parseDialStatus($agi, $dialstatus, $answeredtime) != true) {
                        $answeredtime = 0;
                        continue;
                    }
                }
                /* Call to group*/
                else if ($inst_listdestination['voip_call'] == 8) {

                    $agi->verbose("Call group $group ", 6);

                    $modelSip = Sip::model()->findAll('`group` = :key', array(':key' => $inst_listdestination['destination']));
                    $agi->verbose("Call group $group ", 6);
                    if (!count($modelSip)) {
                        $answeredtime = 0;
                        continue;
                    }

                    $group = '';
                    foreach ($modelSip as $key => $value) {
                        $group .= "SIP/" . $value->name . "&";
                    }
                    $dialstr = substr($group, 0, -1) . $dialparams;

                    $MAGNUS->startRecordCall($agi, $did);

                    $agi->verbose("DIAL $dialstr", 6);
                    $myres = $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->agiconfig['dialcommand_param_call_2did']);

                    $answeredtime = $agi->get_variable("ANSWEREDTIME");
                    $answeredtime = $answeredtime['data'];
                    $dialstatus   = $agi->get_variable("DIALSTATUS");
                    $dialstatus   = $dialstatus['data'];

                    $MAGNUS->stopRecordCall($agi);

                    if ($this->parseDialStatus($agi, $dialstatus, $answeredtime) != true) {
                        $answeredtime = 0;
                        continue;
                    }

                }
                /* Call to custom dial*/
                else if ($inst_listdestination['voip_call'] == 9) {
                    //SMS@O numero %callerid% acabou de  ligar.
                    if (strtoupper(substr($inst_listdestination['destination'], 0, 3)) == 'SMS') {

                        $MAGNUS->destination = $inst_listdestination->idUser->mobile;
                        $text                = substr($inst_listdestination['destination'], 4);
                        $text                = preg_replace("/\%callerid\%/", $MAGNUS->CallerID, $text);

                        if (file_exists('/var/lib/asterisk/sounds/' . $inst_listdestination->idDid->did . '.gsm')) {
                            $agi->answer();
                            sleep(2);
                            $agi->stream_file($inst_listdestination->idDid->did, '#');
                        } else {
                            $agi->evaluate("ANSWER 0");
                        }

                        SmsSend::send($inst_listdestination->idUser, $MAGNUS->destination, $text);
                        $agi->verbose(print_r($result, true));
                        $answeredtime = 60;
                        $dialstatus   = 'ANSWER';

                        break;
                    } else {
                        $agi->verbose("Ccall group $group ", 6);
                        $dialstr = $inst_listdestination['destination'];

                        $MAGNUS->startRecordCall($agi, $did);

                        $agi->verbose("DIAL $dialstr", 6);
                        $myres = $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->agiconfig['dialcommand_param_call_2did']);
                        $MAGNUS->stopRecordCall($agi);

                        $answeredtime = $agi->get_variable("ANSWEREDTIME");
                        $answeredtime = $answeredtime['data'];
                        $dialstatus   = $agi->get_variable("DIALSTATUS");
                        $dialstatus   = $dialstatus['data'];

                        if ($this->parseDialStatus($agi, $dialstatus, $answeredtime) != true) {
                            $answeredtime = 0;
                            continue;
                        }
                    }

                } else {
                    /* CHECK IF DESTINATION IS SET*/
                    if (strlen($inst_listdestination['destination']) == 0) {
                        continue;
                    }

                    $MAGNUS->agiconfig['use_dnid']       = 1;
                    $MAGNUS->agiconfig['say_timetocall'] = 0;

                    //if is a PSTN call can destination format is number@callerID, set the CallID.
                    if (preg_match("/@/", $inst_listdestination['destination'])) {
                        $destinationCallerID                 = explode('@', $inst_listdestination['destination']);
                        $inst_listdestination['destination'] = $destinationCallerID[0];
                        $agi->set_callerid($destinationCallerID[1]);
                    }

                    $MAGNUS->extension = $MAGNUS->dnid = $MAGNUS->destination = $inst_listdestination['destination'];

                    if ($MAGNUS->checkNumber($agi, $Calc, 0) == true) {

                        /* PERFORM THE CALL*/
                        $result_callperf = $Calc->sendCall($agi, $MAGNUS->destination, $MAGNUS);
                        if (!$result_callperf) {
                            $prompt = "prepaid-callfollowme";
                            $agi->verbose($prompt, 10);
                            $agi->stream_file($prompt, '#');
                            continue;
                        }

                        $dialstatus   = $Calc->dialstatus;
                        $answeredtime = $Calc->answeredtime;

                        if (($Calc->dialstatus == "NOANSWER") || ($Calc->dialstatus == "BUSY") || ($Calc->dialstatus == "CHANUNAVAIL") || ($Calc->dialstatus == "CONGESTION")) {
                            continue;
                        }

                        if ($Calc->dialstatus == "CANCEL") {
                            break;
                        }

                        /* INSERT CDR  & UPDATE SYSTEM*/
                        $Calc->updateSystem($MAGNUS, $agi, 1, 1);

                        Diddestination::model()->updateByPk($this->modelDestination[0]->id,
                            array(
                                'secondusedreal' => new CDbExpression('secondusedreal + ' . $Calc->answeredtime),
                            )
                        );

                        /* THEN STATUS IS ANSWER*/
                        break;
                    }
                }
            }
            /* END IF AUTHENTICATE*/
        }

        $answeredtime = $MAGNUS->executeVoiceMail($agi, $dialstatus, $answeredtime);

        $agi->verbose('answeredtime =' . $answeredtime);
        if ($answeredtime > 0) {
            $this->call_did_billing($agi, $MAGNUS, $Calc, $answeredtime, $dialstatus);
            return 1;
        }
    }
    public function checkBlockCallerID(&$agi, &$MAGNUS)
    {
        $agi->verbose("try blocked", 5);
        $block_expression_1 = $this->modelDestination[0]->idDid->block_expression_1;
        $block_expression_2 = $this->modelDestination[0]->idDid->block_expression_2;
        $block_expression_3 = $this->modelDestination[0]->idDid->block_expression_3;

        $send_to_callback_1 = $this->modelDestination[0]->idDid->send_to_callback_1;
        $send_to_callback_2 = $this->modelDestination[0]->idDid->send_to_callback_2;
        $send_to_callback_3 = $this->modelDestination[0]->idDid->send_to_callback_3;

        $expression_1 = $this->modelDestination[0]->idDid->expression_1;
        $expression_2 = $this->modelDestination[0]->idDid->expression_2;
        $expression_3 = $this->modelDestination[0]->idDid->expression_3;

        if ($block_expression_1 == 1 || $send_to_callback_1) {
            $agi->verbose("try blocked number match with expression 1, " . $MAGNUS->CallerID . ' ' . $expression_2, 1);
            if (strlen($expression_1) > 1 && ereg($expression_1, $MAGNUS->CallerID)) {

                if ($block_expression_1 == 1) {
                    $agi->verbose("Call blocked becouse this number match with expression 1, " . $MAGNUS->CallerID . ' FROM did ' . $this->did, 1);
                    $MAGNUS->hangup($agi);
                } elseif ($send_to_callback_1 == 1) {
                    $agi->verbose('Send to Callback expression 1', 10);
                    $this->voip_call = 6;
                }
            }
        }

        if ($block_expression_2 == 1 || $send_to_callback_2) {
            $agi->verbose("try blocked number match with expression 2, " . $MAGNUS->CallerID . ' ' . $expression_2, 1);
            if (strlen($expression_2) > 1 && ereg($expression_2, $MAGNUS->CallerID)) {
                if ($block_expression_2 == 1) {
                    $agi->verbose("Call blocked becouse this number match with expression 2, " . $MAGNUS->CallerID . ' FROM did ' . $this->did, 1);
                    $MAGNUS->hangup($agi);
                } elseif ($send_to_callback_2 == 1) {
                    $agi->verbose('Send to Callback expression 2', 10);
                    $this->voip_call = 6;
                }
            }
        }

        if ($block_expression_3 == 1 || $send_to_callback_3) {
            $agi->verbose("try blocked number match with expression 3, " . $MAGNUS->CallerID . ' ' . $expression_3, 1);
            if (strlen($expression_3) > 0 && (ereg($expression_3, $MAGNUS->CallerID) || $expression_3 == '*') &&
                strlen($expression_1) > 1 && !ereg($expression_1, $MAGNUS->CallerID) &&
                strlen($expression_2) > 1 && !ereg($expression_2, $MAGNUS->CallerID)
            ) {

                if ($block_expression_1 == 3) {
                    $agi->verbose("Call blocked becouse this number match with expression 3, " . $MAGNUS->CallerID . ' FROM did ' . $this->did, 1);
                    $MAGNUS->hangup($agi);
                } elseif ($send_to_callback_3 == 1) {
                    $agi->verbose('Send to Callback expression 3', 10);
                    $this->voip_call = 6;
                }
            }
        }
    }

    public function parseDialStatus(&$agi, $dialstatus, $answeredtime)
    {
        $agi->verbose('parseDialStatus', 25);
        if ($dialstatus == "BUSY") {
            $agi->stream_file('prepaid-isbusy', '#');
            return false;
        } elseif ($dialstatus == "NOANSWER") {
            $agi->stream_file('prepaid-callfollowme', '#');
            return false;
        } elseif ($dialstatus == "CANCEL") {
            return true;
        } elseif ($dialstatus == "ANSWER") {
            $agi->verbose("[Magnus] DID call friend: dialstatus : $dialstatus, answered time is " . $answeredtime . " ", 10);
            return true;
        } elseif (($dialstatus == "CHANUNAVAIL") || ($dialstatus == "CONGESTION")) {
            return false;
        } else {
            $agi->stream_file('prepaid-callfollowme', '#');
            return false;
        }
    }

    public function didCallCost(&$agi, &$MAGNUS)
    {
        $agi->verbose('didCallCost', 10);

        //brazil mobile - ^[4,5,6][1-9][7-9].{7}$|^[1,2,3,7,8,9][1-9]9.{8}$
        //brazil fixed - ^[1-9][0-9][1-5].
        $agi->verbose(print_r($this->modelDestination[0]->getAttributes(), true), 25);
        if (strlen($this->modelDestination[0]->idDid->expression_1) > 0 && ereg($this->modelDestination[0]->idDid->expression_1, $MAGNUS->CallerID) || $this->modelDestination[0]->idDid->expression_1 == '*') {
            $agi->verbose("CallerID Match regular expression 1 " . $MAGNUS->CallerID, 10);
            $selling_rate = $this->modelDestination[0]->idDid->selling_rate_1;

        } elseif (strlen($this->modelDestination[0]->idDid->expression_2) > 0 && ereg($this->modelDestination[0]->idDid->expression_2, $MAGNUS->CallerID) || $this->modelDestination[0]->idDid->expression_2 == '*') {
            $agi->verbose("CallerID Match regular expression 2 " . $MAGNUS->CallerID, 10);
            $selling_rate = $this->modelDestination[0]->idDid->selling_rate_2;
        } elseif (strlen($this->modelDestination[0]->idDid->expression_3) > 0 && ereg($this->modelDestination[0]->idDid->expression_3, $MAGNUS->CallerID) || $this->modelDestination[0]->idDid->expression_3 == '*') {
            $agi->verbose("CallerID Match regular expression 3 " . $MAGNUS->CallerID, 10);
            $selling_rate = $this->modelDestination[0]->idDid->selling_rate_3;
        } else {
            $selling_rate = 0;
        }

        if ($this->modelDestination[0]->idDid->connection_sell == 0 && $selling_rate == 0) {
            $this->sell_price = 0;
        } else {
            $this->sell_price = $selling_rate;
        }

        $credit = $this->modelDestination[0]->idDid->idUser->typepaid == 1
        ? $this->modelDestination[0]->idDid->idUser->credit + $this->modelDestination[0]->idDid->idUser->creditlimit
        : $this->modelDestination[0]->idDid->idUser->credit;

        if ($this->sell_price > 0 && $credit <= 0) {
            $agi->verbose(" USER NO CREDIT FOR CALL " . $username, 10);
            $MAGNUS->hangup($agi);
        }

    }

    public function billDidCall(&$agi, &$MAGNUS, $answeredtime)
    {
        $agi->verbose('billDidCall, sell_price=' . $this->sell_price, 1);

        $this->sell_price = $MAGNUS->roudRatePrice($answeredtime, $this->sell_price, $this->modelDestination[0]->idDid->initblock, $this->modelDestination[0]->idDid->increment);

        $this->sell_price = $this->sell_price + $this->modelDestination[0]->idDid->connection_sell;

        if ($answeredtime < $this->modelDestination[0]->idDid->minimal_time_charge) {
            $this->sell_price = 0;
        }

        $agi->verbose(' answeredtime = ' . $answeredtime . ' sell_price = ' . $this->sell_price . ' connection_sell = ' . $this->modelDestination[0]->idDid->connection_sell, 1);
    }

    public function call_did_billing(&$agi, &$MAGNUS, &$Calc, $answeredtime, $dialstatus)
    {
        if (strlen($MAGNUS->dialstatus_rev_list[$dialstatus]) > 0) {
            $terminatecauseid = $MAGNUS->dialstatus_rev_list[$dialstatus];
        } else {
            $terminatecauseid = 0;
        }

        /*recondeo call*/
        if ($MAGNUS->config["global"]['bloc_time_call'] == 1 && $this->sell_price > 0) {
            $initblock    = $this->modelDestination[0]->idDid->initblock;
            $billingblock = $this->modelDestination[0]->idDid->increment;

            if ($answeredtime > $initblock) {
                $restominutos   = $answeredtime % $billingblock;
                $calculaminutos = ($answeredtime - $restominutos) / $billingblock;
                if ($restominutos > '0') {
                    $calculaminutos++;
                }

                $answeredtime = $calculaminutos * $billingblock;

            } elseif ($answeredtime < '1') {
                $sessiontime = 0;
            } else {
                $answeredtime = $initblock;
            }

        }

        $modelPrefix = Prefix::model()->find('prefix = SUBSTRING(:key,1,length(prefix))',
            array(':key' => $this->did));
        if (!count($modelPrefix)) {
            $agi->verbose('Not found prefix to DID ' . $this->did);
        }

        $this->billDidCall($agi, $MAGNUS, $answeredtime);

        $modelCall                   = new Call();
        $modelCall->uniqueid         = $MAGNUS->uniqueid;
        $modelCall->id_user          = $MAGNUS->id_user;
        $modelCall->starttime        = date("Y-m-d H:i:s", time() - $answeredtime);
        $modelCall->sessiontime      = $answeredtime;
        $modelCall->real_sessiontime = intval($answeredtime);
        $modelCall->calledstation    = $this->did;
        $modelCall->terminatecauseid = $terminatecauseid;
        $modelCall->sessionbill      = $this->sell_price;
        $modelCall->id_plan          = $MAGNUS->id_plan;
        $modelCall->id_trunk         = null;
        $modelCall->src              = $MAGNUS->CallerID;
        $modelCall->sipiax           = 3;
        $modelCall->buycost          = 0;
        $modelCall->id_prefix        = $modelPrefix->id;
        $modelCall->save();
        $modelError = $modelCall->getErrors();
        if (count($modelError)) {
            $agi->verbose(print_r($modelError, true), 25);
        }

        User::model()->updateByPk($MAGNUS->modelUser->id,
            array(
                'credit' => new CDbExpression('credit - ' . $MAGNUS->round_precision(abs($this->sell_price))),
            )
        );

        $this->modelDestination[0]->secondusedreal += $answeredtime;
        $this->modelDestination->save();

        return;
    }
}
