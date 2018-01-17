<?php
/**
 *
 */
class SipCallAgi
{

    public function processCall(&$MAGNUS, &$agi, &$Calc)
    {

        if (($MAGNUS->agiconfig['use_dnid'] == 1) && (strlen($MAGNUS->dnid) > 2)) {
            $MAGNUS->destination = $MAGNUS->dnid;
        }

        $MAGNUS->destination = $MAGNUS->dnid;

        $dialparams        = $MAGNUS->agiconfig['dialcommand_param_sipiax_friend'];
        $MAGNUS->modelUser = User::model()->findByPk((int) $MAGNUS->modelSip->id_user);
        AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $MAGNUS->modelUser);

        $MAGNUS->startRecordCall($agi);

        $dialstr = "SIP/" . $MAGNUS->destination;
        //check if user are registered in a asterisk slave
        $modelServers = Servers::model()->find("status = 1 AND type = 'asterisk'");
        if (count($modelServers) && count($modelServers)) {
            if (strlen($MAGNUS->modelSip->register_server_ip) > 1 && $MAGNUS->modelSip->regseconds < (time() - 7200)) {
                $dialstr .= '@' . $MAGNUS->modelSip->register_server_ip;
            }

        }
        $startCall = time();
        $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->config["agi-conf1"]['dialcommand_param_sipiax_friend']);

        $answeredtime = $agi->get_variable("ANSWEREDTIME");
        $answeredtime = $answeredtime['data'];
        $dialstatus   = $agi->get_variable("DIALSTATUS");
        $dialstatus   = $dialstatus['data'];

        $MAGNUS->stopRecordCall($agi);

        $agi->verbose("[" . $MAGNUS->username . " Friend]:[ANSWEREDTIME=" . $answeredtime . "-DIALSTATUS=" . $dialstatus . "]", 6);

        $modelSipForward = Sip::model()->find('name = :key', array(':key' => $MAGNUS->destination));
        if (strlen($modelSipForward->forward) > 3) {
            $credit = $modelSipForward->idUser->typepaid == 1
            ? $modelSipForward->idUser->credit + $modelSipForward->idUser->creditlimit
            : $modelSipForward->idUser->credit;

            if (Sip::model()->find('name = :key', array(':key' => $modelSipForward->forward))) {
                $agi->verbose('Forward to sipaccount ' . $modelSipForward->forward, 5);
                $MAGNUS->dnid = $MAGNUS->destination = $modelSipForward->forward;
                $this->processCall($MAGNUS, $agi, $Calc);
                return;
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
                return;
            }
        }

        $answeredtime = $MAGNUS->executeVoiceMail($agi, $dialstatus, $answeredtime);

        if (strlen($MAGNUS->dialstatus_rev_list[$dialstatus]) > 0) {
            $terminatecauseid = $MAGNUS->dialstatus_rev_list[$dialstatus];
        } else {
            $terminatecauseid = 0;
        }

        $siptransfer = $agi->get_variable("SIPTRANSFER");
        if ($answeredtime > 0 && $siptransfer['data'] != 'yes' && $terminatecauseid == 1) {
            if ($MAGNUS->config['global']['charge_sip_call'] > 0) {

                $cost = ($MAGNUS->config['global']['charge_sip_call'] / 60) * $answeredtime;

                User::model()->updateByPk($MAGNUS->modelUser->id,
                    array(
                        'lastuse' => date('Y-m-d H:i:s'),
                        'credit'  => new CDbExpression('credit - ' . $MAGNUS->round_precision(abs($cost))),
                    )
                );
                $agi->verbose("Update credit username after transfer $MAGNUS->username, " . $cost, 15);
            } else {
                $cost = 0;
            }

            $modelCall                   = new Call();
            $modelCall->uniqueid         = $MAGNUS->uniqueid;
            $modelCall->sessionid        = $MAGNUS->channel;
            $modelCall->id_user          = $MAGNUS->id_user;
            $modelCall->starttime        = date("Y-m-d H:i:s", $startCall);
            $modelCall->sessiontime      = $answeredtime;
            $modelCall->calledstation    = $MAGNUS->destination;
            $modelCall->terminatecauseid = $terminatecauseid;
            $modelCall->stoptime         = date('Y-m-d H:i:s');
            $modelCall->id_plan          = $MAGNUS->id_plan;
            $modelCall->id_trunk         = null;
            $modelCall->src              = $MAGNUS->username;
            $modelCall->sipiax           = 1;
            $modelCall->id_prefix        = null;
            $modelCall->buycost          = 0;
            $modelCall->sessionbill      = $cost;
            $modelCall->save();
            //$agi->verbose(print_r($modelCall->getErrors(),true));

        } else {

            $modelCallFailed                   = new CallFailed();
            $modelCallFailed->uniqueid         = $MAGNUS->uniqueid;
            $modelCallFailed->sessionid        = $MAGNUS->channel;
            $modelCallFailed->id_user          = $MAGNUS->id_user;
            $modelCallFailed->starttime        = date('Y-m-d H:i:s');
            $modelCallFailed->calledstation    = $MAGNUS->destination;
            $modelCallFailed->terminatecauseid = $terminatecauseid;
            $modelCallFailed->id_plan          = null;
            $modelCallFailed->id_trunk         = null;
            $modelCallFailed->src              = $MAGNUS->CallerID;
            $modelCallFailed->sipiax           = 0;
            $modelCallFailed->id_prefix        = null;
            $modelCallFailed->save();
            //$agi->verbose(print_r($modelCallFailed->getErrors(),true),25);
        }

        $MAGNUS->hangup($agi);
    }
}
