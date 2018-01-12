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

class AuthenticateAgi
{
    public static function authenticateUser($agi, $MAGNUS)
    {
        $agi->verbose('AuthenticateUser ' . $MAGNUS->accountcode, 15);
        $authentication = false;

        /* TRY WITH THE CALLERID AUTHENTICATION*/
        $authentication = AuthenticateAgi::callerIdAuthenticate($MAGNUS, $agi, $authentication);

        //AUTHENTICATION VIA TECHPREFIX
        $authentication = AuthenticateAgi::techPrefixAuthenticate($MAGNUS, $agi, $authentication);

        /*TRY WITH THE ACCOUNTCODE AUTHENTICATION*/
        $authentication = AuthenticateAgi::accountcodeAuthenticate($MAGNUS, $agi, $authentication);

        /*TRY WITH THE SIPPROXY AUTHENTICATION*/
        $authentication = AuthenticateAgi::sipPorxyAuthenticate($MAGNUS, $agi, $authentication);

        /* AUTHENTICATE BY PIN */
        $authentication = AuthenticateAgi::callingCardAuthenticate($MAGNUS, $agi, $authentication);

        $authentication = AuthenticateAgi::checkIfCallShopCall($MAGNUS, $agi, $authentication);

        if ($authentication == false || $MAGNUS->active != 1) {
            $prompt = "prepaid-auth-fail";
        } else {
            $prompt = $MAGNUS->check_expirationdate_customer($agi);
        }

        if (strlen($prompt) > 0) {
            $MAGNUS->executePlayAudio($prompt, $agi);
            $MAGNUS->hangup($agi);
        }

        AuthenticateAgi::checkUserCallLimit($MAGNUS, $agi);

        AuthenticateAgi::checkPlanTechPrefix($MAGNUS, $agi);

        AuthenticateAgi::checkPlanIntraInter($MAGNUS, $agi);

        AuthenticateAgi::checkIfIsAgent($MAGNUS, $agi);

        if (isset($agi->username)) {
            $agi->username = $MAGNUS->username;
        }

        $agi->set_variable('CHANNEL(language)', $MAGNUS->language);

        return $authentication;
    }

    public static function callerIdAuthenticate(&$MAGNUS, &$agi, $authentication)
    {
        if ($authentication == false && $MAGNUS->agiconfig['cid_enable'] == 1 && is_numeric($MAGNUS->CallerID) && $MAGNUS->CallerID > 0) {
            $agi->verbose('Try callerID authentication ' . $MAGNUS->CallerID, 15);
            $modelCallerid = Callerid::model()->find('cid = :key', array(':key' => $MAGNUS->CallerID));

            if (count($modelCallerid)) {
                AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $modelCallerid->idUser);
                $agi->verbose("AUTHENTICATION BY CALLERID:" . $MAGNUS->CallerID, 6);
                $authentication = true;

            }
        }
        return $authentication;
    }

    public static function techPrefixAuthenticate(&$MAGNUS, &$agi, $authentication)
    {
        if ($authentication != true && strlen($MAGNUS->dnid) > 16) {
            $tech_prefix = substr($MAGNUS->dnid, 0, 6);

            $agi->verbose('Try accountcode + techprefix authentication ' . $tech_prefix, 15);

            $from = $agi->get_variable("SIP_HEADER(Contact)", true);

            $from = explode('@', $from);
            $from = explode('>', $from[1]);
            $from = explode(':', $from[0]);
            $from = $from[0];

            $modelSip = Sip::model()->find(
                array(
                    'condition' => 'host = :key2',
                    'with'      => array('idUser' => array('condition' => "idUser.username LIKE :key AND idUser.callingcard_pin = :key1"),
                    ),
                    'params'    => array(
                        ':key'  => $MAGNUS->accountcode,
                        ':key1' => $tech_prefix,
                        ':key2' => $from,
                    ),
                )
            );

            if (count($modelSip)) {
                AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $modelSip->idUser);
                $MAGNUS->dnid = substr($MAGNUS->dnid, 6);
                $agi->verbose("AUTHENTICATION BY TECHPREFIX $tech_prefix - Username: " . $MAGNUS->username . '  ' . $MAGNUS->dnid, 6);
                $authentication = true;
            }
        }

        return $authentication;
    }

    public static function sipPorxyAuthenticate(&$MAGNUS, &$agi, $authentication)
    {
        if ($authentication != true && !filter_var($agi->get_variable("SIP_HEADER(X-AUTH-IP)", true), FILTER_VALIDATE_IP) === false) {
            $agi->verbose("TRY Authentication via Proxy ");
            $agi->verbose($agi->get_variable("SIP_HEADER(P-Accountcode)", true), 1);
            $proxyServer = explode("@", $agi->get_variable("SIP_HEADER(to)", true));
            $proxyServer = isset($proxyServer[1]) ? substr($proxyServer[1], 0, -1) : '';

            $modelServers = Servers::model()->find('host = :key', array(':key' => $proxyServer));

            if (count($modelServers)) {
                if ($agi->get_variable("SIP_HEADER(P-Accountcode)", true) == '<null>') {

                    $modelSip = Sip::model()->find('host =:key',
                        array(
                            ':key' => $agi->get_variable("SIP_HEADER(X-AUTH-IP)", true),
                        )
                    );

                    if (count($modelSip)) {
                        AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $modelSip->idUser);
                        $authentication = true;
                        $agi->verbose("AUTHENTICATION BY X-AUTH-IP header (" . $agi->get_variable("SIP_HEADER(X-AUTH-IP)", true) . "), accountcode" . $MAGNUS->accountcode);
                    }
                } else {
                    $MAGNUS->accountcode = $agi->get_variable("SIP_HEADER(P-Accountcode)", true);
                    $modelUser           = User::model()->find('username = :key', array(':key' => $MAGNUS->accountcode));

                    if (count($modelUser)) {
                        AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $modelUser);
                        $authentication = true;
                        $agi->verbose("AUTHENTICATION BY P-Accountcode header " . $MAGNUS->accountcode);

                    }
                }
            } else {
                $agi->verbose("Try send call with X-AUTH-IP, BUT IS INVALID ", $proxyServer, 1);
            }
        }
        return $authentication;
    }

    public static function accountcodeAuthenticate(&$MAGNUS, &$agi, $authentication)
    {
        if (strlen($MAGNUS->accountcode) >= 1 && $authentication != true) {
            $agi->verbose('Try accountcode authentication ' . $MAGNUS->accountcode, 15);

            $modelUser = User::model()->find('username = :key', array(':key' => $MAGNUS->accountcode));

            if (count($modelUser)) {
                AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $modelUser);
                $agi->verbose("AUTHENTICATION BY ACCOUNTCODE:" . $MAGNUS->username, 6);
                $authentication = true;
            }
        }
        return $authentication;
    }

    public static function pinAuthenticate(&$MAGNUS, &$agi, $authentication, $pin)
    {

        $agi->verbose('Try pin authentication ' . $pin, 15);

        $modelUser = User::model()->find('callingcard_pin = :key', array(':key' => $pin));

        if (count($modelUser)) {
            AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $modelUser);
            $agi->verbose("AUTHENTICATION BY PIN:" . $pin, 6);
            $authentication = true;
        }

        return $authentication;
    }

    public static function voucherAuthenticate(&$MAGNUS, &$agi, $authentication, $pin)
    {
        $agi->verbose('Check voucher Number $pin');
        //check if the PIN is a valid voucher
        $modelVoucher = Voucher::model()->find('voucher = :key', array(':key' => $pin));
        if (count($modelVoucher)) {
            $agi->verbose('Found valid voucher Number $pin');
            $modelUser = new User();

            //Cria conta para usuario com voucher
            $modelUser->id_user         = 1;
            $modelUser->username        = Util::getNewUsername();
            $modelUser->id_group        = 3;
            $modelUser->id_plan         = $modelVoucher->id_plan;
            $modelUser->password        = Util::generatePassword(8, true, true, true, false);
            $modelUser->credit          = $modelVoucher->credit;
            $modelUser->active          = 1;
            $modelUser->callingcard_pin = $modelVoucher->voucher;
            $modelUser->callshop        = 0;
            $modelUser->plan_day        = 0;

            $modelUser->boleto_day   = 0;
            $modelUser->language     = $modelVoucher->language;
            $modelUser->prefix_local = $modelVoucher->prefix_local;
            try {
                $modelUser->save();
                $id_user = $modelUser->id;
            } catch (Exception $e) {
                $agi->verbose($modelUser->getErrors(), 25);
            }

            //Marca o voucher como usado
            $modelVoucher->id_user = $id_user;
            $modelVoucher->usedate = date('Y-m-d H:i:s');
            $modelVoucher->used    = 1;
            $modelVoucher->save();

            AuthenticateAgi::setMagnusAttrubutes($MAGNUS, $modelUser);
            $authentication = true;
        }
        return $authentication;
    }

    public static function callingCardAuthenticate(&$MAGNUS, &$agi, $authentication)
    {
        if ($authentication != true) {
            $agi->verbose('try callingcard', 6);

            for ($retries = 0; $retries < 3; $retries++) {
                $agi->answer();

                if (($retries > 0) && (strlen($prompt) > 0)) {
                    $agi->verbose($prompt, 3);
                    $agi->stream_file($prompt, '#');
                }
                if ($res < 0) {
                    $res = -1;
                    break;
                }

                $res      = 0;
                $res_dtmf = $agi->get_data('prepaid-enter-pin-number', 6000, 6);

                $agi->verbose('PIN callingcard ' . $res_dtmf["result"], 20);

                $pin = $res_dtmf["result"];

                if (!isset($pin) || strlen($pin) == 0) {
                    $prompt = "prepaid-no-card-entered";
                    $agi->verbose('No user entered', 6);
                    continue;
                }

                if (strlen($pin) > 6 || strlen($pin) < 6) {
                    $agi->verbose($prompt, 6);
                    $prompt = "prepaid-invalid-digits";
                    continue;
                }

                $authentication = AuthenticateAgi::pinAuthenticate($MAGNUS, $agi, $authentication, $pin);

                if ($authentication == true) {
                    break;
                } else {

                    $authentication = AuthenticateAgi::voucherAuthenticate($MAGNUS, $agi, $authentication, $pin);

                    if ($authentication == true) {
                        break;
                    } else {
                        $prompt = "prepaid-auth-fail";
                        continue;
                    }
                }
            }
        }
        return $authentication;
    }

    public static function checkPlanIntraInter(&$MAGNUS, $agi)
    {
        if ($MAGNUS->config['global']['intra-inter'] == '1') {
            $agi->verbose(substr($MAGNUS->modelSip->callerid, 0, 4) . "  " . substr($MAGNUS->dnid, 0, 4), 30);

            //if user callerId is equal the fist 4 digits of dialnumber, it is a intra call
            if (substr($MAGNUS->modelSip->callerid, 0, 4) == substr($MAGNUS->dnid, 0, 4)) {

                $modelPlan = Plan::model()->find('name = :key', array('key' => $MAGNUS->modelUser->idPlan->name . ' Intra'));
                if (count($modelPlan)) {
                    //if found plan with Intra in the name, change the plan
                    $agi->verbose("INTRA PLAN FOUND AND CHANGED \$MAGNUS->id_plan to " . $modelPlan->name, 5);
                    $MAGNUS->id_plan = $modelPlan->id;
                }
            }
        }
    }

    public static function checkIfIsAgent(&$MAGNUS, $agi)
    {
        /*check if user is a agent user*/
        if (!is_null($MAGNUS->id_agent) && $MAGNUS->id_agent > 1) {
            $MAGNUS->id_plan_agent  = $MAGNUS->id_plan;
            $MAGNUS->modelUserAgent = User::model()->findByPk((int) $MAGNUS->id_agent);

            $MAGNUS->id_plan       = $MAGNUS->modelUserAgent->id_plan;
            $MAGNUS->agentUsername = $MAGNUS->modelUserAgent->username;

            $agi->verbose("Reseller id_Plan $MAGNUS->id_plan_agent, user id_plan $MAGNUS->id_plan", 6);
        }
    }

    public static function checkPlanTechPrefix(&$MAGNUS, &$agi)
    {
        if (strlen($MAGNUS->dnid) > 13) {
            //tech prefix to route
            $modelPlan = Plan::model()->find('techprefix = :key', array(':key' => substr($MAGNUS->dnid, 0, 5)));

            if (count($modelPlan)) {
                $MAGNUS->id_plan = $modelPlan->id;
                $MAGNUS->dnid    = substr($MAGNUS->dnid, 5);
                $agi->verbose("Changed plan via TechPrefix: Plan used $MAGNUS->id_plan - Number: $MAGNUS->dnid ", 15);
            }
        }
    }

    public static function checkUserCallLimit(&$MAGNUS, &$agi)
    {
        if ($MAGNUS->mode == 'standard' && $MAGNUS->user_calllimit >= 0) {
            //check user call limit
            $agi->verbose('check user call limit', 15);
            $calls = AsteriskAccess::getCallsPerUser($MAGNUS->accountcode);
            $agi->verbose(print_r($calls, true));
            if ($calls > $MAGNUS->user_calllimit) {
                $agi->verbose("Send Congestion user call limit", 3);
                $agi->execute((congestion), Congestion);
                $MAGNUS->hangup($agi);
            }
        }
    }

    public static function checkIfCallShopCall(&$MAGNUS, &$agi, $authentication)
    {

        //verfica se é cliente de callshop, e se a cabina esta ativa
        if ($MAGNUS->callshop == 1) {
            $modelSip = Sip::model()->find('name = :key', array(':key' => $MAGNUS->sip_account));

            if ($modelSip->status == 0) {
                $agi->verbose("CABINA DISABLED " . $MAGNUS->sip_account, 3);
                $authentication = false;
            }
        }

        return $authentication;
    }

    public static function setMagnusAttrubutes(&$MAGNUS, $model)
    {
        $MAGNUS->modelUser          = $model;
        $MAGNUS->credit             = $model->credit;
        $MAGNUS->id_plan            = $model->id_plan;
        $MAGNUS->active             = $model->active;
        $MAGNUS->typepaid           = $model->typepaid;
        $MAGNUS->creditlimit        = $model->creditlimit;
        $MAGNUS->language           = $model->language;
        $MAGNUS->accountcode        = $model->username;
        $MAGNUS->username           = $model->username;
        $MAGNUS->removeinterprefix  = $model->idPlan->removeinterprefix;
        $MAGNUS->redial             = $model->redial;
        $MAGNUS->enableexpire       = $model->enableexpire;
        $MAGNUS->expirationdate     = $model->expirationdate;
        $MAGNUS->expiredays         = $model->expiredays;
        $MAGNUS->creationdate       = $model->creationdate;
        $MAGNUS->id_user            = $model->id;
        $MAGNUS->id_agent           = $model->id_user;
        $MAGNUS->restriction        = $model->restriction;
        $MAGNUS->callshop           = $model->callshop;
        $MAGNUS->id_offer           = $model->id_offer;
        $MAGNUS->prefix_local       = $model->prefix_local;
        $MAGNUS->countryCode        = $model->country;
        $MAGNUS->user_calllimit     = $model->calllimit;
        $MAGNUS->play_audio         = $model->idPlan->play_audio;
        $MAGNUS->mix_monitor_format = $model->mix_monitor_format;
        $MAGNUS->credit             = $MAGNUS->typepaid == 1
        ? $MAGNUS->credit + $MAGNUS->creditlimit
        : $MAGNUS->credit;

        $MAGNUS->modelSip = Sip::model()->find('name = :key', array('key' => $MAGNUS->sip_account));
        if ($MAGNUS->voicemail != 1) {
            $MAGNUS->voicemail = count($MAGNUS->modelSip) ? $MAGNUS->modelSip->voicemail : false;
        }
        $MAGNUS->record_call = (count($MAGNUS->modelSip) && $MAGNUS->modelSip->record_call) || $MAGNUS->agiconfig['record_call'] ? true : false;

    }

};
