<?php
/**
 *
 */
class SipCallAgi
{

    public function processCall(&$MAGNUS, &$agi, &$CalcAgi)
    {
        if (($MAGNUS->agiconfig['use_dnid'] == 1) && (strlen($MAGNUS->dnid) > 2)) {
            $MAGNUS->destination = $MAGNUS->dnid;
        }

        $MAGNUS->destination = $MAGNUS->dnid;
        $dialparams          = $MAGNUS->agiconfig['dialcommand_param_sipiax_friend'];
        //add the sipaccount dial timeout in dialcommand_param.
        $dialparams    = explode(',', $dialparams);
        $dialparamsArg = isset($dialparams[2]) ? $dialparams[2] : '';
        $dialparams    = ',' . $MAGNUS->modelSip->dial_timeout . ',' . $dialparamsArg;

        $sql               = "SELECT * FROM pkg_user WHERE id = " . $MAGNUS->modelSip->id_user . " LIMIT 1";
        $MAGNUS->modelUser = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
        AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $agi, $MAGNUS->modelUser);

        $MAGNUS->startRecordCall($agi);

        $dialstr = "SIP/" . $MAGNUS->destination;
        //check if user are registered in a asterisk slave
        $sql          = "SELECT id FROM pkg_servers WHERE status = 1 AND type = 'asterisk' LIMIT 1";
        $modelServers = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
        if (isset($modelServers->id)) {
            if (strlen($MAGNUS->modelSip->register_server_ip) > 1 && $MAGNUS->modelSip->regseconds < (time() - 7200)) {
                $dialstr .= '@' . $MAGNUS->modelSip->register_server_ip;
            }

        }
        $startCall = time();
        $MAGNUS->run_dial($agi, $dialstr, $dialparams);

        $answeredtime = $agi->get_variable("ANSWEREDTIME");
        $answeredtime = $answeredtime['data'];
        $dialstatus   = $agi->get_variable("DIALSTATUS");
        $dialstatus   = $dialstatus['data'];

        $MAGNUS->stopRecordCall($agi);

        $agi->verbose("[" . $MAGNUS->username . " Friend]:[ANSWEREDTIME=" . $answeredtime . "-DIALSTATUS=" . $dialstatus . "]", 6);

        $sql             = "SELECT * FROM pkg_sip WHERE name = '$MAGNUS->destination' LIMIT 1 ";
        $modelSipForward = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
        if (isset($modelSipForward->id) && strlen($modelSipForward->forward) > 3 && $dialstatus != 'CANCEL' && $dialstatus != 'ANSWER') {

            $sql              = "SELECT * FROM pkg_user WHERE id = $modelSipForward->id_user  LIMIT 1";
            $modelUserForward = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
            $credit           = $modelUserForward->typepaid == 1
            ? $modelUserForward->credit + $modelUserForward->creditlimit
            : $modelUserForward->credit;

            $sql = "SELECT id FROM pkg_sip WHERE name = '$modelSipForward->forward' LIMIT 1";
            if ($agi->query($sql)->fetch(PDO::FETCH_OBJ)) {
                $agi->verbose('Forward to sipaccount ' . $modelSipForward->forward, 5);
                $MAGNUS->dnid = $MAGNUS->destination = $modelSipForward->forward;
                $this->processCall($MAGNUS, $agi, $CalcAgi);
                return;
            } elseif ($credit > 1) {
                $agi->verbose('Forward to PSTN network. Number ' . $modelSipForward->forward, 5);
                $MAGNUS->dnid        = $MAGNUS->destination        = $MAGNUS->extension        = $modelSipForward->forward;
                $MAGNUS->accountcode = $modelSipForward->accountcode;

                if (AuthenticateAgi::authenticateUser($agi, $MAGNUS)) {
                    if ($MAGNUS->checkNumber($agi, $CalcAgi, 0, true) == 1) {
                        $standardCall = new StandardCallAgi();
                        $standardCall->processCall($MAGNUS, $agi, $CalcAgi);

                        $dialstatus   = $CalcAgi->dialstatus;
                        $answeredtime = $CalcAgi->answeredtime;
                        /* INSERT CDR  & UPDATE SYSTEM*/
                        $CalcAgi->updateSystem($this, $agi, $this->destination, 1, 1);
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
                $sql  = "UPDATE pkg_user SET credit = credit - " . $MAGNUS->round_precision(abs($cost)) . "
                            WHERE id = $MAGNUS->modelUser->id LIMIT 1  ";
                $agi->exec($sql);
                $agi->verbose("Update credit username after transfer $MAGNUS->username, " . $cost, 15);
            } else {
                $cost = 0;
            }
        }

        $MAGNUS->id_trunk          = null;
        $CalcAgi->starttime        = date("Y-m-d H:i:s", $startCall);
        $CalcAgi->sessiontime      = $answeredtime;
        $CalcAgi->terminatecauseid = $terminatecauseid;
        $CalcAgi->sessionbill      = $cost;
        $CalcAgi->sipiax           = 1;
        $CalcAgi->buycost          = 0;
        $CalcAgi->id_prefix        = null;
        $CalcAgi->saveCDR($agi, $MAGNUS);

        $MAGNUS->hangup($agi);
    }
}
