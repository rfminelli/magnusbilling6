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

class MassiveCall
{
    public function send($agi, &$MAGNUS, &$Calc)
    {
        $uploaddir = $MAGNUS->magnusFilesDirectory . 'sounds/';

        $agi->answer();
        $now = time();

        if ($MAGNUS->dnid == 'failed' || !is_numeric($MAGNUS->dnid)) {
            $agi->verbose("Hangup becouse dnid is OutgoingSpoolFailed", 25);
            $MAGNUS->hangup($agi);
        }

        $idPhonenumber    = $agi->get_variable("PHONENUMBER_ID", true);
        $phonenumberCity  = $agi->get_variable("PHONENUMBER_CITY", true);
        $idCampaign       = $agi->get_variable("CAMPAIGN_ID", true);
        $idRate           = $agi->get_variable("RATE_ID", true);
        $MAGNUS->id_user  = $agi->get_variable("IDUSER", true);
        $MAGNUS->username = $agi->get_variable("USERNAME", true);
        $MAGNUS->id_agent = $agi->get_variable("AGENT_ID", true);
        $destination      = $MAGNUS->dnid;

        $modelCampaign = Campaign::model()->findByPk((int) $idCampaign);

        if (!count($modelCampaign)) {
            return;
        }

        $modelPhoneNumber = PhoneNumber::model()->findByPk((int) $idPhonenumber);
        if (!count($modelPhoneNumber)) {
            exit;
        }

        $forward_number = $modelCampaign->forward_number;

        /*VERIFICA SE CAMPAÃ‘A TEM ENCUESTA*/
        $modelCampaignPoll = CampaignPoll::model()->findAll('id_campaign = :key', array(':key' => $idCampaign));

        if (isset($modelCampaign->audio_2) && strlen($modelCampaign->audio_2) > 5) {

            $executeAudio2 = true;

            //verifica se tem nome no numero
            if (isset($modelPhoneNumber->name) && strlen($modelPhoneNumber->name) > 3) {
                $agi->verbose("TTS", 10);
                $executeTTS = true;
                $name       = utf8_encode($modelPhoneNumber->name);
                $file       = $idPhonenumber . date("His");
                $name       = urlencode($name);

                //http://api.voicerss.org/?key=0ed8d233c8534591a7abf4b620606bc2&src=Adilson&hl=pt-br
                $tts_url = preg_replace('/\$name/', $name, $MAGNUS->config['global']['tts_url']);

                if (preg_match("/ttsgo/", $tts_url)) {

                    $ch = curl_init();
                    //Caso tenha dificuldade com a requisição via HTTPS, descomente a linha abaixo
                    //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($ch, CURLOPT_URL, $tts_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $retorno = curl_exec($ch);

                    $objJson = json_decode($retorno);

                    $agi->verbose(print_r($objJson->url, true));
                    $fp = fopen('/tmp/' . $file . '.wav', 'w');
                    fwrite($fp, file_get_contents($objJson->url));
                    fclose($fp);
                    exec('sox /tmp/' . $file . '.wav -c 1 -r 8000 /tmp/' . $file . '.sln && rm -rf /tmp/' . $file . '.wav');

                } else {

                    if (preg_match("/google/", $tts_url)) {
                        $token   = MassiveCall::make_token($resultPhoneNumber[0]['name']);
                        $tts_url = preg_replace("/tk=/", "tkold=", $tts_url);
                        $tts_url .= "&tk=$token";
                    }
                    $agi->verbose($tts_url, 8);
                    exec("wget -q -U Mozilla -O \"/tmp/$file.mp3\" \"$tts_url\"");
                    exec("mpg123 -w /tmp/$file.wav /tmp/$file.mp3 && rm -rf /tmp/$file.mp3");
                    exec("sox -v 2.0 /tmp/$file.wav /tmp/$file2.wav && rm -rf /tmp/$file.wav");
                    exec("sox /tmp/$file2.wav -c 1 -r 8000 /tmp/$file.wav && rm -rf /tmp/$file2.wav ");
                }
            }
        }

        /*AUDIO FOR CAMPAIN*/

        $audio = $uploaddir . "idCampaign_" . $idCampaign;

        //se tiver audio 2 passar direto
        if (isset($executeAudio2)) {
            $agi->stream_file($audio, '#');

        } else {
            // CHECK IF NEED AUTORIZATION FOR EXECUTE POLL OR IS EXISTE FORWARD NUMBER
            if (strlen($forward_number) > 2 || (count($modelCampaignPoll) && $modelCampaignPoll->request_authorize == 1)) {
                $res_dtmf = $agi->get_data($audio, 5000, 1);
            } else {
                $agi->stream_file($audio, ' #');
            }
        }

        if (isset($executeTTS)) {
            $agi->stream_file("/tmp/" . $file, ' #');
            exec("rm -rf /tmp/$file*");
        }

        if (isset($executeAudio2)) {
            /*Execute audio 2*/
            $audio = $uploaddir . "idCampaign_" . $idCampaign . "_2";

            // CHECK IF NEED AUTORIZATION FOR EXECUTE POLL OR IS EXISTE FORWARD NUMBER
            if (strlen($forward_number) > 2 || (count($modelCampaignPoll) && $modelCampaignPoll->request_authorize == 1)) {
                $res_dtmf = $agi->get_data($audio, 5000, 1);
            } else {
                $agi->stream_file($audio, ' #');
            }

        }

        $agi->verbose('RESULT DTMF ' . $res_dtmf['result'], 25);

        if (strlen($modelCampaign->audio) < 5 && strlen($forward_number) > 2) {
            $res_dtmf['result'] = 1;
            $agi->verbose('CAMPAIN SEM AUDIO, ENVIA DIRETO PARA ' . $forward_number);
        }

        //CHECK IF IS FORWARD EXTERNAL CALLL
        $agi->verbose("forward_number $forward_number , res_dtmf: " . $res_dtmf['result'] . ", digit_authorize: " . $modelCampaignPoll->digit_authorize, 10);

        if (strlen($forward_number) > 2 && ($res_dtmf['result'] == $modelCampaign->digit_authorize || $modelCampaign->digit_authorize == '-1')) {

            $agi->verbose("have Forward number $forward_number");
            $modelPhoneNumber->info = 'Forward DTMF 1';
            $modelPhoneNumber->save();

            $MAGNUS->record_call = $modelCampaign->idUser->record_call;

            $forwardOption     = explode("|", $forward_number);
            $forwardOptionType = $forwardOption[0];

            $agi->verbose(print_r($forwardOption, true));

            if ($forwardOptionType == 'sip') {

                $modelSip = Sip::model()->findByPk((int) $forwardOption[1]);

                $dialstr = 'SIP/' . $modelSip->name;

                $MAGNUS->startRecordCall($agi);

                $myres      = $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->agiconfig['dialcommand_param_sipiax_friend']);
                $dialstatus = $agi->get_variable("DIALSTATUS");
                $dialstatus = $dialstatus['data'];

                if ($dialstatus == "NOANSWER") {
                    $agi->stream_file('prepaid-callfollowme', '#');
                } elseif (($dialstatus == "BUSY" || $dialstatus == "CHANUNAVAIL") || ($dialstatus == "CONGESTION")) {
                    $agi->stream_file('prepaid-isbusy', '#');
                }
            } elseif ($forwardOptionType == 'queue') {

                $modelDiddestination             = new Diddestination();
                $modelDiddestination->id_queue   = $forwardOption[1];
                $modelDiddestination->idDid->did = $destination;
                $agi->set_variable("CALLERID(num)", $destination);
                $agi->set_callerid($destination);
                QueueAgi::callQueue($agi, $MAGNUS, $Calc, $modelDiddestination, null, 'torpedo');
            } elseif ($forwardOptionType == 'ivr') {
                $modelDiddestination             = new Diddestination();
                $modelDiddestination->id_ivr     = $forwardOption[1];
                $modelDiddestination->idDid->did = $destination;
                Ivr::callIvr($agi, $MAGNUS, $Calc, $modelDiddestination, null, 'torpedo');
            } elseif ($forwardOptionType == 'group') {

                $agi->verbose("Call group $group ", 25);
                $modelSip = Sip::model()->findAll('`group` = :key', array('key' => $forwardOption[1]));

                if (count($modelSip) == 0) {
                    $agi->verbose('GROUP NOT FOUND');
                    $agi->stream_file('prepaid-invalid-digits', '#');

                } else {
                    $group = '';
                    foreach ($modelSip as $key => $value) {
                        $group .= "SIP/" . $value->name . "&";
                    }

                    $dialstr = substr($group, 0, -1);
                    $agi->verbose("DIAL $dialstr", 25);
                    $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->agiconfig['dialcommand_param_sipiax_friend']);
                }

            } elseif ($forwardOptionType == 'custom') {
                $agi->set_variable("CALLERID(num)", $destination);
                if (preg_match('/AGI/', $forwardOption[1])) {
                    $agi = explode("|", $forwardOption[1]);
                    $agi->exec_agi($agi[1] . ",$destination,$idCampaign,$idPhonenumber");
                } else {
                    $MAGNUS->run_dial($agi, $forwardOption[1]);
                }
            }

            $agi->set_variable("CALLERID(num)", $destination);

            if ($MAGNUS->agiconfig['record_call'] == 1 || $MAGNUS->record_call == 1) {
                $myres = $agi->execute("StopMixMonitor");
            }

        }

        //execute poll if exist
        if (count($modelCampaignPoll) > 0) {

            foreach ($modelCampaignPoll as $poll) {

                $repeat = $poll->repeat;

                if ($dtmf_result == -1) {
                    break;
                }
                sleep(1);
                $dtmf_result == '';

                if ($poll->id == 18 && $dtmf_result > 0) {
                    continue;
                }

                if ($poll->id == 20 && $dtmf_result > 0) {
                    continue;
                }

                for ($i = 0; $i < 12; $i++) {

                    $audio = $uploaddir . "idPoll_" . $poll->id;

                    if ($poll->request_authorize == 1) {
                        $agi->verbose('Request authorize', 5);
                        //IF CUSTOMER MARK 1 EXECUTE POLL
                        if ($res_dtmf['result'] == $modelCampaignPoll[0]->digit_authorize) {
                            $agi->verbose('Authorized', 5);
                            $res_dtmf = $agi->get_data($audio, 5000, 1);
                        } else {
                            $dtmf_result = -1;
                            $agi->verbose('NOT authorized', 5);
                            break;
                        }

                    } else {
                        $res_dtmf = $agi->get_data($audio, 5000, 1);
                    }

                    //GET RESULT OF POLL
                    $dtmf_result = $res_dtmf['result'];

                    $agi->verbose("Cliente votou na opcao: $dtmf_result", 5);

                    //Hungaup call if the fisrt poll dtmf is not numeric
                    if ($i == 0 && !is_numeric($dtmf_result)) {
                        $agi->verbose('nao votou nada na 1º enquete', 5);
                        break;
                    }

                    if ($repeat > 0) {
                        for ($i = 0; $i < $repeat; $i++) {

                            if ($i > 0) {
                                $agi->stream_file('prepaid-invalid-digits', ' #');

                                $res_dtmf = $agi->get_data($audio, 5000, 1);

                                //GET RESULT OF POLL
                                $dtmf_result = $res_dtmf['result'];
                            }

                            if ($i == 2) {
                                $agi->verbose('Client press invalid option after two try');
                                $dtmf_result = 'error';
                                break;
                            }

                            if (is_numeric($dtmf_result)) {
                                $agi->verbose("dtmf_result es numerico ", 8);

                                $modelCampaignPoll = CampaignPoll::model()->findByPk($poll->id);

                                $agi->verbose('$i' . $i . " " . $repeat, 25);
                                if ($modelCampaignPoll->resposta_option == '' && $i >= $repeat - 1) {
                                    $agi->verbose("Client press invalid option after try $repeat, hangup call on poll " . $poll->id);
                                    $agi->stream_file('prepaid-invalid-digits', ' #');
                                    $dtmf_result = 'error';
                                    break;
                                } else if ($modelCampaignPoll->resposta_option == '') {
                                    $agi->verbose("Client press invalid option $dtmf_result on poll " . $poll->id, 8);

                                } else {
                                    $agi->verbose("Client press number: $dtmf_result", 8);
                                    break;
                                }
                            }
                        }
                    }

                    if ($modelCampaignPoll[0]->{'option' . $dtmf_result} != 'repeat') {
                        break;
                    }

                }

                if (is_numeric($dtmf_result) && $dtmf_result >= 0) {
                    //si esta hangup en la opcion, corlgar.
                    if (preg_match('/hangup/', $poll->{'option' . $dtmf_result})) {

                        $agi->verbose('desligar chamadas', 25);

                        $newIdPoll = explode('_', $poll->{'option' . $dtmf_result});

                        //si tiene una id en el hangup, executar el audio
                        if (isset($newIdPoll[1])) {
                            $audio    = $uploaddir . "idPoll_" . $newIdPoll[1];
                            $res_dtmf = $agi->get_data($audio, 5000, 1);
                        }

                        $modelCampaignPollInfo                   = new CampaignPollInfo();
                        $modelCampaignPollInfo->id_campaign_poll = $poll->id;
                        $modelCampaignPollInfo->resposta         = $dtmf_result;
                        $modelCampaignPollInfo->number           = $destination;
                        $modelCampaignPollInfo->city             = $phonenumberCity;

                        break;

                    } elseif (preg_match('/create/', $poll->{'option' . $dtmf_result})) {

                        $modelPlan = Plan::model()->find('signup = 1');

                        if (count($modelPlan)) {

                            $id_plan = $modelPlan->id;
                            $credit  = $modelPlan->ini_credit;

                            $modelGroupUser  = GroupUser::model()->find('id_user_type = 3');
                            $id_group        = $modelGroupUser->id;
                            $password        = Util::generatePassword(8, true, true, true, false);
                            $callingcard_pin = Util::getNewLock_pin($agi);

                            $modelUser                  = new User();
                            $modelUser->username        = $destination;
                            $modelUser->password        = $password;
                            $modelUser->id_user         = 1;
                            $modelUser->id_plan         = $id_plan;
                            $modelUser->credit          = $credit;
                            $modelUser->id_group        = $id_group;
                            $modelUser->active          = 1;
                            $modelUser->prefix_local    = $MAGNUS->config['global']['base_language'] == 'pt_BR' ? '0/55,*/5511/8,*/5511/9' : '';
                            $modelUser->callingcard_pin = $callingcard_pin;
                            $modelUser->loginkey        = '';
                            $modelUser->typepaid        = 0;
                            try {
                                $success = $modelUser->save();

                            } catch (Exception $e) {
                                $success = false;
                            }

                            if ($success) {
                                $idUser                = $modelUser->id;
                                $modelSip              = new Sip();
                                $modelSip->id_user     = $idUser;
                                $modelSip->accountcode = $destination;
                                $modelSip->name        = $destination;
                                $modelSip->allow       = 'g729,gsm,g726,alaw,ulaw';
                                $modelSip->host        = 'dynamic';
                                $modelSip->insecure    = 'no';
                                $modelSip->defaultuser = $destination;
                                $modelSip->secret      = $password;
                                $modelSip->save();
                            }

                        } else {
                            $agi->verbose('NOT HAVE PLAN ENABLE ON SIGNUP', 25);
                        }

                    } else {

                        $modelCampaignPollInfo                   = new CampaignPollInfo();
                        $modelCampaignPollInfo->id_campaign_poll = $poll->id;
                        $modelCampaignPollInfo->resposta         = $dtmf_result;
                        $modelCampaignPollInfo->number           = $destination;
                        $modelCampaignPollInfo->city             = $phonenumberCity;
                        $modelCampaignPollInfo->save();

                        if (preg_match('/SIP|sip/', $poll->{'option' . $res_dtmf['result']})) {
                            $MAGNUS->destination = $destination;
                            $MAGNUS->startRecordCall($agi);

                            $dialstr = $poll->{'option' . $res_dtmf['result']};
                            $dialstr = preg_replace("/number/", $destination, $dialstr);
                            $agi->set_variable("CALLERID(num)", $destination);
                            $agi->set_callerid($destination);
                            $agi->verbose('CALL SEND TO SIP IN POLL -> ' . $dialstr, 25);

                            $myres = $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->agiconfig['dialcommand_param_sipiax_friend']);

                            $MAGNUS->stopRecordCall($agi);
                        }
                    }

                } else {
                    $agi->verbose('Cliente no marco nada', 8);
                    break;
                }

            }

            $agi->stream_file('prepaid-final', ' #');
        }

        $modelRate = Rate::model()->findByPk((int) $idRate);

        if (!count($modelRate)) {
            return;
        }

        $id_prefix = $modelRate->id_prefix;
        /*buy rate*/
        $buyrate          = $modelRate->buyrate;
        $buyrateinitblock = $modelRate->buyrateinitblock;
        $buyrateincrement = $modelRate->buyrateincrement;
        /*sell rate*/
        $rateinitial  = $modelRate->rateinitial;
        $initblock    = $modelRate->initblock;
        $billingblock = $modelRate->billingblock;

        $id_trunk = $modelRate->id_trunk;

        $duration = time() - $now;

        /* ####     CALCUL BUYRATE COST     #####*/
        $buyratecost  = $MAGNUS->calculation_price($buyrate, $duration, $buyrateinitblock, $buyrateincrement);
        $sellratecost = $MAGNUS->calculation_price($rateinitial, $duration, $initblock, $billingblock);
        $agi->verbose("[TEMPO DA LIGAÃ‡AO] " . $duration, 8);

        $MAGNUS->id_plan = $modelRate->id_plan;
        if ($duration > 1) {

            if (count($modelCampaign) && $modelCampaign->enable_max_call == 1 && $duration >= $modelCampaign->nb_callmade) {
                //desativa a campanha se o limite de chamadas foi alcançado
                //diminui 1 do total de chamadas permitidas completas , se o tempo da chamada for superior ao tempo do audio

                Campaign::model()->updateByPk($modelCampaign->id,
                    array(
                        'status'         => $modelCampaign->secondusedreal < 1 ? 0 : 1,
                        'secondusedreal' => new CDbExpression('secondusedreal - 1'),
                    )
                );

            }

            $modelCall                   = new Call();
            $modelCall->uniqueid         = $MAGNUS->uniqueid;
            $modelCall->sessionid        = $MAGNUS->channel;
            $modelCall->id_user          = $MAGNUS->id_user;
            $modelCall->starttime        = date("Y-m-d H:i:s", time() - $duration);
            $modelCall->sessiontime      = $duration;
            $modelCall->real_sessiontime = intval($duration);
            $modelCall->calledstation    = $destination;
            $modelCall->terminatecauseid = $terminatecauseid;
            $modelCall->stoptime         = date('Y-m-d H:i:s');
            $modelCall->sessionbill      = $sellratecost;
            $modelCall->id_plan          = $MAGNUS->id_plan;
            $modelCall->id_trunk         = $id_trunk;
            $modelCall->src              = $MAGNUS->username;
            $modelCall->sipiax           = 5;
            $modelCall->buycost          = $buyratecost;
            $modelCall->id_prefix        = $id_prefix;

            $modelCall->id_campaign = $idCampaign;

            $modelCall->save();
            $modelError = $modelCall->getErrors();
            if (count($modelError)) {
                $agi->verbose(print_r($modelError, true), 25);
            }

            if (!is_null($MAGNUS->id_agent) && $MAGNUS->id_agent > 1) {
                $id_call               = $modelCall->id;
                $MAGNUS->id_plan_agent = $agi->get_variable("AGENT_ID_PLAN", true);
                $agi->verbose($MAGNUS->id_plan_agent);
                $agi->verbose('$MAGNUS->id_agent' . $MAGNUS->id_agent . ' $id_call' . $id_call . '$destination' . $destination, 10);
                $calc              = new Calc();
                $calc->sessiontime = $duration;
                $calc->updateSystemAgent($agi, $MAGNUS, $destination, $sellratecost);
            } else {
                $modelUser = User::model()->findByPk((int) $MAGNUS->id_user);
                $modelUser->credit -= $MAGNUS->round_precision(abs($sellratecost));
                $modelUser->lastuse = date('Y-m-d H:i:s');
                $modelUser->save();
            }

            $modelTrunk = Trunk::model()->findByPk((int) $id_trunk);
            $modelTrunk->secondusedreal += $duration;
            $modelTrunk->save();

            $modelProvider = Provider::model()->findByPk((int) $modelTrunk->id_provider);
            $modelProvider->credit -= $buyratecost;
            $modelProvider->save();
        }

        $modelPhoneNumber->status = 3;
        $modelPhoneNumber->save();
        $MAGNUS->hangup($agi);
    }

    private function make_token($line)
    {
        $text  = $line;
        $time  = round(time() / 3600);
        $chars = unpack('C*', $text);
        $stamp = $time;

        foreach ($chars as $key => $char) {
            $stamp = MassiveCall::make_rl($stamp + $char, '+-a^+6');
        }

        $stamp = MassiveCall::make_rl($stamp, '+-3^+b+-f');

        if ($stamp < 0) {
            $stamp = ($stamp & 2147483647) + 2147483648;
        }
        $stamp %= pow(10, 6);
        return ($stamp . '.' . ($stamp ^ $time));

    }

    private function make_rl($num, $str)
    {
        for ($i = 0; $i < strlen($str) - 2; $i += 3) {
            $d = substr($str, $i + 2, 1);
            if (ord($d) >= ord('a')) {
                $d = ord($d) - 87;
            } else {
                $d = round($d);
            }
            if (substr($str, $i + 1, 1) == '+') {
                $d = $num >> $d;
            } else {
                $d = $num << $d;
            }
            if (substr($str, $i, 1) == '+') {
                $num = $num + $d & 4294967295;
            } else {
                $num = $num ^ $d;
            }
        }
        return $num;
    }

}
