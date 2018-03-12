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
 * Please submit bug reports, patches, etc to https://github.com/magnussolution/callcenter/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 *
 */
class MagnusCommand extends CConsoleCommand
{

    public function run($args)
    {
        define('LOGFILE', 'protected/runtime/magnus.log');
        define('$this->debug', 0);

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGHUP, SIG_IGN);
        }

        error_reporting(E_ALL ^ (E_NOTICE | E_WARNING));

        $agi    = new AGI();
        $MAGNUS = new Magnus();
        $Calc   = new Calc();

        $agi->verbose("", 5);
        $agi->verbose("Start MBilling AGI", 5);

        $MAGNUS->load_conf($agi, null, 0, 1);
        $MAGNUS->get_agi_request_parameter($agi);

        $MAGNUS->init();
        $Calc->init();
        $MAGNUS->mode = 'standard';

        if ($MAGNUS->dnid == 'failed') {
            $agi->verbose("Hangup becouse dnid is OutgoingSpoolFailed", 25);
            $MAGNUS->hangup($agi);
            exit;
        }

        if (substr($MAGNUS->dnid, 0, 2) == '*7') {
            PickupAgi::execute($agi, $MAGNUS);
        }

        //Hangup call that start with 1111, avoid fake call to Brasilian portability
        if (substr($MAGNUS->dnid, 0, 4) == 1111) {
            $agi->execute((congestion), Congestion);
            $MAGNUS->hangup($agi);
        }

        if ($agi->get_variable("IDCALLBACK", true)) {
            $modelSip = Sip::model()->find('id_user = :key',
                array(':key' => $agi->get_variable("IDUSER", true)));
            $agi->set_callerid($modelSip->callerid);
        }

        if ($agi->get_variable("CIDCALLBACK", true)) {

            CallbackAgi::chargeFistCall($agi, $MAGNUS, $Calc, 0);
            $MAGNUS->agiconfig['answer']     = 1;
            $MAGNUS->agiconfig['cid_enable'] = 1;
            $MAGNUS->agiconfig['use_dnid']   = 0;
            $MAGNUS->agiconfig['number_try'] = 3;
        }

        if ($agi->get_variable("MEMBERNAME", true) || $agi->get_variable("QUEUEPOSITION", true)) {
            $agi->answer();
            $Calc->init();
            QueueAgi::recIvrQueue($agi, $MAGNUS, $Calc);
        }

        if ($agi->get_variable("PHONENUMBER_ID", true) > 0 && $agi->get_variable("CAMPAIGN_ID", true) > 0) {
            $MAGNUS->mode = 'massive-call';
            MassiveCall::send($agi, $MAGNUS, $Calc);
        }

        if ($agi->get_variable("SPY", true) == 1) {

            $channel = $agi->get_variable("CHANNELSPY", true);
            $agi->verbose('SPY CALL ' . $channel);
            $agi->execute("ChanSpy", $channel, "bqE");
            $agi->stream_file('prepaid-final', '#');
            $MAGNUS->hangup($agi);
            exit;
        }

        $didAgi = new DidAgi();
        $didAgi->checkIfIsDidCall($agi, $MAGNUS, $Calc);

        if ($MAGNUS->mode == 'standard') {

            //get if the call have the second number
            if ($agi->get_variable("SECCALL", true)) {

                $agi->stream_file('prepaid-secondCall', '#');
                $MAGNUS->agiconfig['use_dnid'] = 1;
                $MAGNUS->destination           = $MAGNUS->extension           = $MAGNUS->dnid           = $agi->get_variable("SECCALL", true);
                $MAGNUS->modelUser             = User::model()->findByPk((int) $agi->get_variable("IDUSER", true));
                $MAGNUS->accountcode           = isset($MAGNUS->modelUser->username) ? $MAGNUS->modelUser->username : null;
                $agi->verbose("CALL TO PSTN FROM CLIC TO CALL", 15);
                $standardCall = new StandardCallAgi();
                $standardCall->processCall($MAGNUS, $agi, $Calc);
            } else {

                $MAGNUS->modelSip = Sip::model()->find('name = :key', array(':key' => $MAGNUS->dnid));

                if (count($MAGNUS->modelSip) && strlen($MAGNUS->modelSip->name) > 3) {
                    $MAGNUS->mode      = 'call-sip';
                    $MAGNUS->voicemail = $MAGNUS->modelSip->voicemail;
                    $agi->verbose("CALL TO SIP", 15);
                    $sipCallAgi = new SipCallAgi();
                    $sipCallAgi->processCall($MAGNUS, $agi, $Calc);

                } else {
                    $agi->verbose("CALL TO PSTN", 15);
                    $standardCall = new StandardCallAgi();
                    $standardCall->processCall($MAGNUS, $agi, $Calc);
                }
            }
        }
        SipTransferAgi::billing($MAGNUS, $agi, $Calc);
        $MAGNUS->hangup($agi);
    }
}
