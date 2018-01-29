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
class Calc
{
    public $lastcost            = 0;
    public $lastbuycost         = 0;
    public $answeredtime        = 0;
    public $real_answeredtime   = 0;
    public $dialstatus          = 0;
    public $usedratecard        = 0;
    public $usedtrunk           = 0;
    public $freetimetocall_used = 0;
    public $dialstatus_rev_list;
    public $tariffObj                 = array();
    public $freetimetocall_left       = array();
    public $freecall                  = array();
    public $offerToApply              = array();
    public $number_trunk              = 0;
    public $idCallCallBack            = 0;
    public $agent_bill                = 0;
    public $did_charge_of_id_user     = 0;
    public $did_charge_of_answer_time = 0;
    public $didAgi                    = array();

    public function __construct()
    {
        $this->dialstatus_rev_list = Magnus::getDialStatus_Revert_List();
    }

    public function init()
    {
        $this->number_trunk      = 0;
        $this->answeredtime      = 0;
        $this->real_answeredtime = 0;
        $this->dialstatus        = '';
        $this->usedratecard      = '';
        $this->usedtrunk         = '';
        $this->lastcost          = '';
        $this->lastbuycost       = '';
    }

    public function calculateAllTimeout(&$MAGNUS, $credit, $agi)
    {
        if (!is_array($this->tariffObj) || count($this->tariffObj) == 0) {
            return false;
        }

        for ($k = 0; $k < count($this->tariffObj); $k++) {
            $res_calcultimeout = $this->calculateTimeout($MAGNUS, $credit, $k, $agi);

            if (substr($res_calcultimeout, 0, 5) == 'ERROR' || $res_calcultimeout < 1) {
                return false;
            } else {
                return true;
            }
        }

        return true;
    }

    public function calculateTimeout(&$MAGNUS, $credit, $K = 0, $agi)
    {
        $rateinitial                   = $MAGNUS->round_precision(abs($this->tariffObj[$K]['rateinitial']));
        $initblock                     = $this->tariffObj[$K]['initblock'];
        $billingblock                  = $this->tariffObj[$K]['billingblock'];
        $connectcharge                 = $MAGNUS->round_precision(abs($this->tariffObj[$K]['connectcharge']));
        $id_offer                      = $package_offer                      = $this->tariffObj[$K]['package_offer'];
        $id_rate                       = $this->tariffObj[$K]['id_rate'];
        $initial_credit                = $credit;
        $this->freetimetocall_left[$K] = 0;
        $this->freecall[$K]            = false;
        $this->offerToApply[$K]        = null;

        if ($id_offer == 1 && $MAGNUS->id_offer > 0 && $K == 0) {
            $modelOfferUse = OfferUse::model()->find(array(
                'condition' => 'pkg_offer = :key AND id_user = :key1',
                'params'    => array(
                    ':key'  => $MAGNUS->id_offer,
                    ':key1' => $MAGNUS->id_user,
                ),
                'order'     => 'packagetype ASC',
            ));

            if (count($modelOfferUse)) {
                $package_selected = false;
                $freetimetocall   = $modelOfferUse->idOffer->freetimetocall;
                $packagetype      = $modelOfferUse->idOffer->packagetype;
                $billingtype      = $modelOfferUse->idOffer->billingtype;
                $startday         = date('d', strtotime($modelOfferUse->reservationdate));
                $id_offer         = $modelOfferUse->id_offer;
                switch ($packagetype) {
                    case 0:
                        $agi->verbose("offer Unlimited calls");
                        $this->freecall[0]      = true;
                        $package_selected       = true;
                        $this->offerToApply[$K] = array("id" => $id_offer, "label" => "Unlimited calls", "type" => $packagetype);
                        break;
                    case 1:

                        if ($freetimetocall > 0) {
                            $agi->verbose('FREE CALLS');
                            $number_calls_used = $MAGNUS->freeCallUsed($agi, $MAGNUS->id_user, $id_offer, $billingtype, $startday);

                            if ($number_calls_used < $freetimetocall) {
                                $this->freecall[$K]     = true;
                                $package_selected       = true;
                                $this->offerToApply[$K] = array("id" => $id_offer, "label" => "Number of Free calls", "type" => $packagetype);
                                $agi->verbose(print_r($this->offerToApply[$K], true), 6);
                            }
                        }
                        break;
                    case 2:
                        if ($freetimetocall > 0) {
                            $this->freetimetocall_used     = $MAGNUS->packageUsedSeconds($agi, $MAGNUS->id_user, $id_offer, $billingtype, $startday);
                            $this->freetimetocall_left[$K] = $freetimetocall - $this->freetimetocall_used;

                            if ($this->freetimetocall_left[$K] < 0) {
                                $this->freetimetocall_left[$K] = 0;
                            }

                            if ($this->freetimetocall_left[$K] > 0) {
                                $package_selected       = true;
                                $this->offerToApply[$K] = array("id" => $id_offer, "label" => "Free minutes", "type" => $packagetype);
                                $agi->verbose(print_r($this->offerToApply[$K], true), 6);
                            }
                        }
                        break;
                }
            }
        }

        $credit -= $connectcharge;
        $this->tariffObj[$K]['timeout']                     = 0;
        $this->tariffObj[$K]['timeout_without_rules']       = 0;
        $this->tariffObj[$K]['freetime_include_in_timeout'] = $this->freetimetocall_left[$K];
        $agi->verbose("Credit $credit", 20);
        if ($credit < 0 && !$this->freecall[$K] && $this->freetimetocall_left[$K] <= 0) {
            return "ERROR CT1";
            /*NO  CREDIT TO CALL */
        }

        $TIMEOUT              = 0;
        $answeredtime_1st_leg = 0;
        if ($rateinitial <= 0) /*Se o preÃ§o for 0, entao retornar o timeout em 3600 s*/ {
            $this->tariffObj[$K]['timeout']               = 3600;
            $this->tariffObj[$K]['timeout_without_rules'] = 3600;
            $TIMEOUT                                      = 3600;
            return $TIMEOUT;
        }

        if ($this->freecall[$K]) /*usado para planos gratis*/ {
            $this->tariffObj[$K]['timeout']                     = 3600;
            $TIMEOUT                                            = 3600;
            $this->tariffObj[$K]['timeout_without_rules']       = 3600;
            $this->tariffObj[$K]['freetime_include_in_timeout'] = 3600;
            return $TIMEOUT;
        }
        if ($credit < 0 && $this->freetimetocall_left[$K] > 0) {
            $this->tariffObj[$K]['timeout']               = $this->freetimetocall_left[$K];
            $TIMEOUT                                      = $this->freetimetocall_left[$K];
            $this->tariffObj[$K]['timeout_without_rules'] = $this->freetimetocall_left[$K];
            return $TIMEOUT;
        }

        if ($MAGNUS->mode == 'callback') {
            $credit -= $calling_party_connectcharge;
            $credit -= $calling_party_disconnectcharge;
            $num_min              = $credit / ($rateinitial + $calling_party_rateinitial);
            $answeredtime_1st_leg = intval($agi->get_variable('ANSWEREDTIME', true));
        } else {
            $num_min = $credit / $rateinitial; /*numero de minutos*/
        }

        $num_sec = intval($num_min * 60) - $answeredtime_1st_leg; /*numero de segundos - o tempo que gastou para completar*/
        if ($billingblock > 0) {
            $mod_sec = $num_sec % $billingblock;
            $num_sec = $num_sec - $mod_sec;
        }
        $TIMEOUT = $num_sec;

        /*Call time to speak without rate rules... idiot rules*/
        $num_min_WR                                   = $initial_credit / $rateinitial;
        $num_sec_WR                                   = intval($num_min_WR * 60);
        $this->tariffObj[$K]['timeout_without_rules'] = $num_sec_WR + $this->freetimetocall_left[$K];
        $this->tariffObj[$K]['timeout']               = $TIMEOUT + $this->freetimetocall_left[$K];

        return $TIMEOUT + $this->freetimetocall_left[$K];
    }

    public function calculateCost(&$MAGNUS, $callduration, $K = 0, $agi)
    {
        $this->usedratecard    = $this->usedratecard < 0 ? 0 : $this->usedratecard;
        $K                     = $this->usedratecard;
        $buyrate               = $MAGNUS->round_precision(abs($this->tariffObj[$K]['buyrate']));
        $buyrateinitblock      = $this->tariffObj[$K]['buyrateinitblock'];
        $buyrateincrement      = $this->tariffObj[$K]['buyrateincrement'];
        $rateinitial           = $MAGNUS->round_precision(abs($this->tariffObj[$K]['rateinitial']));
        $initblock             = $this->tariffObj[$K]['initblock'];
        $billingblock          = $this->tariffObj[$K]['billingblock'];
        $connectcharge         = $MAGNUS->round_precision(abs($this->tariffObj[$K]['connectcharge']));
        $disconnectcharge      = $MAGNUS->round_precision(abs($this->tariffObj[$K]['disconnectcharge']));
        $additional_grace_time = $this->tariffObj[$K]['additional_grace'];

        $this->freetimetocall_used = 0;

        $agi->verbose("CALCULCOST: K=$K - CALLDURATION:$callduration - freetimetocall_used=$this->freetimetocall_used", 10);
        $cost = 0;
        $cost += $connectcharge;

        $buyratecallduration = $callduration;

        $buyratecost = 0;
        if ($buyratecallduration < $buyrateinitblock) {
            $buyratecallduration = $buyrateinitblock;
        }

        if (($buyrateincrement > 0) && ($buyratecallduration > $buyrateinitblock)) {
            $mod_sec = $buyratecallduration % $buyrateincrement;
            /* 12 = 30 % 18*/
            if ($mod_sec > 0) {
                $buyratecallduration += ($buyrateincrement - $mod_sec);
            }

            /* 30 += 18 - 12*/
        }
        $buyratecost += ($buyratecallduration / 60) * $buyrate;
        if ($this->freecall[$K]) {
            $this->lastcost    = 0;
            $this->lastbuycost = $buyratecost;
            $agi->verbose("CALCUL COST: K=$K - BUYCOST: $buyratecost - SELLING COST: $cost", 10);
            return;
        }
        if ($callduration < $initblock) {
            $callduration = $initblock;
        }

        if (($billingblock > 0) && ($callduration > $initblock)) {
            $mod_sec = $callduration % $billingblock;
            if ($mod_sec > 0) {
                $callduration += ($billingblock - $mod_sec);
            }
        }
        if ($this->freetimetocall_left[$K] >= $callduration) {
            $this->freetimetocall_used = $callduration;
            $callduration              = 0;
        }
        $cost += ($callduration / 60) * $rateinitial;

        $agi->verbose("CALCULCOST: 1. COST: \$cost = ($callduration/60) * $rateinitial ", 10);

        $this->lastcost    = $MAGNUS->round_precision($cost);
        $this->lastbuycost = $buyratecost;
        $agi->verbose("CALCULCOST: K=$K - BUYCOST:$buyratecost - SELLING COST:$this->lastcost", 10);

        $agi->verbose('$this->lastbuycost = ' . $this->lastbuycost, 10);
        $agi->verbose('$this->lastcost = ' . $this->lastcost, 10);
    }

    public function array_csort()
    {
        $args      = func_get_args();
        $marray    = array_shift($args);
        $i         = 0;
        $msortline = "return(array_multisort(";
        foreach ($args as $arg) {
            $i++;
            if (is_string($arg)) {
                foreach ($marray as $row) {
                    $sortarr[$i][] = $row[$arg];
                }
            } else {
                $sortarr[$i] = $arg;
            }
            $msortline .= "\$sortarr[" . $i . "],";
        }
        $msortline .= "\$marray));";
        eval($msortline);
        return $marray;
    }

    public function updateSystem(&$MAGNUS, &$agi, $doibill = 1, $didcall = 0, $callback = 0)
    {
        $agi->verbose('Update System', 6);
        $this->usedratecard    = $this->usedratecard < 0 ? 0 : $this->usedratecard;
        $K                     = $this->usedratecard;
        $id_offer              = $MAGNUS->id_offer;
        $additional_grace_time = $this->tariffObj[$K]['additional_grace'];
        $sessiontime           = $this->answeredtime;
        $dialstatus            = $this->dialstatus;

        if ($sessiontime > 0) {
            $this->freetimetocall_used = 0;
            //adiciona o tempo adicional
            if (substr($additional_grace_time, -1) == "%") {
                $additional_grace_time = str_replace("%", "", $additional_grace_time);
                $additional_grace_time = $additional_grace_time / 100;
                $additional_grace_time = str_replace("0.", "1.", $additional_grace_time);
                $sessiontime           = $sessiontime * $additional_grace_time;
            } else {
                if ($sessiontime > 0) {
                    $sessiontime = $sessiontime + $additional_grace_time;
                }
            }

            if (($id_offer != -1) && ($this->offerToApply[$K] != null)) {
                $id_offer = $this->offerToApply[$K]["id"];

                $this->calculateCost($MAGNUS, $sessiontime, 0, $agi);

                switch ($this->offerToApply[$K]["type"]) {
                    /*Unlimited*/
                    case 0:
                        $this->freetimetocall_used = $sessiontime;
                        break;
                    /*free calls*/
                    case 1:
                        $this->freetimetocall_used = $sessiontime;
                        break;
                    /*free minutes*/
                    case 2:
                        if ($sessiontime > '60') {
                            $restominutos   = $sessiontime % 60;
                            $calculaminutos = ($sessiontime - $restominutos) / 60;
                            if ($restominutos > '0') {
                                $calculaminutos++;
                            }

                            $sessiontime = $calculaminutos * 60;
                        } elseif ($sessiontime < '1') {
                            $sessiontime = 0;
                        } else {
                            $sessiontime = 60;
                        }

                        $this->freetimetocall_used = $sessiontime;

                        break;
                }

                /* calculcost could have change the duration of the call*/
                $sessiontime = $this->answeredtime;
                /* add grace time*/

                $modelOfferCdr                = new OfferCdr();
                $modelOfferCdr->id_user       = $MAGNUS->id_user;
                $modelOfferCdr->id_offer      = $id_offer;
                $modelOfferCdr->used_secondes = $this->freetimetocall_used;
                $modelOfferCdr->save();
                $modelError = $modelOfferCdr->getErrors();
                if (count($modelError)) {
                    $agi->verbose($modelError, 25);
                }
            } else {
                $this->calculateCost($MAGNUS, $sessiontime, 0, $agi);
            }
        } else {
            $sessiontime = 0;
        }
        $agi->verbose('Sessiontime' . $sessiontime, 10);

        if (($id_offer != -1) && ($this->offerToApply[$K] != null) && $sessiontime > 0) {
            $sessiontime = $this->freetimetocall_used;
        }

        $id_prefix = $this->tariffObj[0]['id_prefix'];
        $id_plan   = $this->tariffObj[$K]['id_plan'];
        $buycost   = 0;

        if ($doibill == 0 || $sessiontime < $this->tariffObj[$K]['minimal_time_charge']) {
            $cost = 0;
        } else {
            $cost = $this->lastcost;
        }

        if ($doibill == 0 || $sessiontime < $this->tariffObj[$K]['minimal_time_buy']) {
            $buycost = 0;
        } else {
            $buycost = abs($this->lastbuycost);
        }

        $buyrateapply = $this->tariffObj[$K]['buyrate'];
        $rateapply    = $this->tariffObj[$K]['rateinitial'];
        $agi->verbose("CALL: used tariff K=$K - (sessiontime=$sessiontime :: dialstatus=$dialstatus)", 10);

        if ($didcall) {
            $calltype = 2;
        } elseif ($callback == 2) {
            $calltype = 7;
        } elseif ($callback) {
            $calltype = 4;
        } else {
            $calltype = 0;
        }

        if (strlen($this->dialstatus_rev_list[$dialstatus]) > 0) {
            $terminatecauseid = $this->dialstatus_rev_list[$dialstatus];
        } else {
            $terminatecauseid = 0;
        }

        if ($callback == 2) //muda o termino para transferencia
        {
            $terminatecauseid = 1;
        }

        if ($calltype == '4' && $sessiontime > '0') {
            $terminatecauseid = '1';
        }

        /*recondeo call*/
        if ($MAGNUS->config["global"]['bloc_time_call'] == 1 && $cost != 0) {
            $initblock    = ($this->tariffObj[$K]['initblock'] < 1) ? 1 : $this->tariffObj[$K]['initblock'];
            $billingblock = ($this->tariffObj[$K]['billingblock'] < 1) ? 1 : $this->tariffObj[$K]['billingblock'];

            if ($sessiontime > $initblock) {
                $restominutos   = $sessiontime % $billingblock;
                $calculaminutos = ($sessiontime - $restominutos) / $billingblock;
                if ($restominutos > '0') {
                    $calculaminutos++;
                }

                $sessiontime = $calculaminutos * $billingblock;

            } elseif ($sessiontime < '1') {
                $sessiontime = 0;
            } else {
                $sessiontime = $initblock;
            }

        }
        $calldestinationPortabilidade = $MAGNUS->destination;
        if ($MAGNUS->portabilidade == 1) {
            if (substr($MAGNUS->destination, 0, 4) == '1111') {
                $MAGNUS->destination = str_replace(substr($MAGNUS->destination, 0, 7), "", $MAGNUS->destination);
            }

        }
        $cost += $MAGNUS->callingcardConnection;

        $agi->verbose($terminatecauseid . ' ' . $cost . '+' . $MAGNUS->round_precision(abs($MAGNUS->callingcardConnection)) . ' = ' . $cost, 25);
        $costCdr = $cost;
        if ($sessiontime > 0) {

            CallbackAgi::chargeFistCall($agi, $MAGNUS, $this, $sessiontime);
            /*Update the global credit */
            $MAGNUS->credit = $MAGNUS->credit + $cost;
            /*CALULATION CUSTO AND SELL RESELLER */

            if (!is_null($MAGNUS->id_agent) && $MAGNUS->id_agent > 1) {
                $agi->verbose('$MAGNUS->id_agent' . $MAGNUS->id_agent . ' ' . $MAGNUS->destination . ' - ' .
                    $calldestinationPortabilidade . ' - ' . $this->real_answeredtime . ' - ' . $cost, 1);
                $cost = $this->agent_bill = $this->updateSystemAgent($agi, $MAGNUS, $calldestinationPortabilidade, $MAGNUS->round_precision(abs($cost)), $sessiontime);
            } else {
                User::model()->updateByPk($MAGNUS->modelUser->id,
                    array(
                        'lastuse' => date('Y-m-d H:i:s'),
                        'credit'  => new CDbExpression('credit - ' . $MAGNUS->round_precision(abs($costCdr))),
                    )
                );

                $agi->verbose("Update credit username $MAGNUS->username, " . $MAGNUS->round_precision(abs($cost)), 6);
            }

            Trunk::model()->updateByPk($this->usedtrunk,
                array(
                    'call_answered'  => new CDbExpression('call_answered + 1'),
                    'secondusedreal' => new CDbExpression('secondusedreal + ' . $sessiontime),
                )
            );

            Provider::model()->updateByPk($this->tariffObj[$K]['id_provider'],
                array(
                    'credit' => new CDbExpression('credit - ' . $buycost),
                )
            );

        }
        $this->callShop($agi, $MAGNUS, $sessiontime, $id_prefix);

        if ($this->did_charge_of_id_user > 0) {

            $agi->verbose('Did_charge_of_id_user = ' . $this->did_charge_of_id_user, 15);

            $didDuration = time() - $this->did_charge_of_answer_time;

            $agi->verbose('DidDuration = ' . $didDuration);
            $this->didAgi->sell_price = $MAGNUS->roudRatePrice($didDuration, $this->didAgi->sell_price,
                $this->didAgi->initblock,
                $this->didAgi->increment);

            User::model()->updateByPk($this->did_charge_of_id_user,
                array(
                    'credit' => new CDbExpression('credit - ' . $MAGNUS->round_precision(abs($MAGNUS->did_charge_of_cost))),
                )
            );

            $agi->verbose('Add CDR the DID cost to CallerID. Cost =' . $MAGNUS->did_charge_of_cost . ', duration' . $didDuration);
            $modelCall                   = new Call();
            $modelCall->uniqueid         = $MAGNUS->uniqueid;
            $modelCall->sessionid        = $MAGNUS->channel;
            $modelCall->id_user          = $this->did_charge_of_id_user;
            $modelCall->starttime        = date("Y-m-d H:i:s", $this->did_charge_of_answer_time);
            $modelCall->sessiontime      = $didDuration;
            $modelCall->real_sessiontime = $didDuration;
            $modelCall->calledstation    = $this->didAgi->did;
            $modelCall->terminatecauseid = $terminatecauseid;
            $modelCall->stoptime         = date('Y-m-d H:i:s');
            $modelCall->sessionbill      = $this->didAgi->sell_price;
            $modelCall->id_plan          = $MAGNUS->id_plan;
            $modelCall->id_trunk         = null;
            $modelCall->src              = $MAGNUS->CallerID;
            $modelCall->sipiax           = 3;
            $modelCall->buycost          = 0;
            $modelCall->id_prefix        = $id_prefix;
            $modelCall->save();

            $modelError = $modelCall->getErrors();
            if (count($modelError)) {
                $agi->verbose(print_r($modelError, true), 25);
            }

        }

        if ($terminatecauseid == 1) {

            if ($agi->get_variable("IDCALLBACK", true)) {
                $modelCallBack                    = CallBack::model()->findByPk((int) $agi->get_variable("IDCALLBACK", true));
                $modelCallBack->last_attempt_time = date('Y-m-d H:i:s');
                $modelCallBack->status            = 3;
                $modelCallBack->save();
            }
            $agi->verbose('Insert call on CDR', 10);
            $modelCall                   = new Call();
            $modelCall->uniqueid         = $MAGNUS->uniqueid;
            $modelCall->sessionid        = $MAGNUS->channel;
            $modelCall->id_user          = $MAGNUS->id_user;
            $modelCall->starttime        = date("Y-m-d H:i:s", time() - $this->real_answeredtime);
            $modelCall->sessiontime      = $sessiontime;
            $modelCall->real_sessiontime = intval($this->real_answeredtime);
            $modelCall->calledstation    = $MAGNUS->destination;
            $modelCall->terminatecauseid = $terminatecauseid;
            $modelCall->stoptime         = date('Y-m-d H:i:s');
            $modelCall->sessionbill      = $costCdr;
            $modelCall->id_plan          = $MAGNUS->id_plan;
            $modelCall->id_trunk         = $this->usedtrunk;
            $modelCall->src              = $MAGNUS->CallerID;
            $modelCall->sipiax           = $calltype;
            $modelCall->buycost          = $buycost;
            $modelCall->id_prefix        = $id_prefix;
            $modelCall->agent_bill       = $this->agent_bill;
            $modelCall->save();
            $modelError = $modelCall->getErrors();
            if (count($modelError)) {
                $agi->verbose(print_r($modelError, true), 25);
            }

        } else {
            if (file_exists(dirname(__FILE__) . '/CallCache.php')) {
                include dirname(__FILE__) . '/CallCache.php';
            } else {
                $agi->verbose('Insert failed call', 1);
                $modelCallFailed                   = new CallFailed();
                $modelCallFailed->uniqueid         = $MAGNUS->uniqueid;
                $modelCallFailed->sessionid        = $MAGNUS->channel;
                $modelCallFailed->id_user          = $MAGNUS->id_user;
                $modelCallFailed->starttime        = date('Y-m-d H:i:s');
                $modelCallFailed->calledstation    = $MAGNUS->destination;
                $modelCallFailed->terminatecauseid = $terminatecauseid;
                $modelCallFailed->id_plan          = $MAGNUS->id_plan;
                $modelCallFailed->id_trunk         = $this->usedtrunk;
                $modelCallFailed->src              = $MAGNUS->CallerID;
                $modelCallFailed->sipiax           = $calltype;
                $modelCallFailed->id_prefix        = $id_prefix;
                $modelCallFailed->save();
                $modelError = $modelCallFailed->getErrors();
                if (count($modelError)) {
                    $agi->verbose($modelError, 25);
                }
            }
        }
    }

    public function updateSystemAgent($agi, $MAGNUS, $calledstation, $cost, $sessiontime)
    {

        $modelRateAgent = Rate::model()->searchAgentRate($calledstation, $MAGNUS->id_plan_agent);

        if (!count($modelRateAgent)) {
            $agi->verbose('NOT FOUND AGENT TARRIF, USE AGENT COST PRICE');
            $cost_customer = $cost;
        } else {
            $agi->verbose('Found agent sell price ' . print_r($modelRateAgent[0], true) . '-  ' . $sessiontime, 25);
            $cost_customer = $MAGNUS->roudRatePrice($sessiontime, $modelRateAgent[0]['rateinitial'],
                $modelRateAgent[0]['initblock'], $modelRateAgent[0]['billingblock']);
            $agi->verbose('$cost_customer=' . $cost_customer);
        }

        if ($sessiontime < $modelRateAgent[0]['minimal_time_charge']) {
            $agi->verbose("Tempo meno que o tempo minimo para", 15);
            $cost_customer = 0;
        }

        $agi->verbose("Sellratecost_customer: $cost_customer", 15);

        $modelUser = User::model()->findByPk((int) $MAGNUS->id_agent);
        $modelUser->credit -= $cost;
        $modelUser->save();

        $agi->verbose("Update credit Agent $MAGNUS->agentUsername, " . $cost, 15);

        $modelUser = User::model()->findByPk((int) $MAGNUS->id_user);
        $modelUser->credit -= $MAGNUS->round_precision(abs($cost_customer));
        $modelUser->lastuse = date('Y-m-d H:i:s');
        $modelUser->save();

        $agi->verbose("Update credit customer Agent $MAGNUS->username, " . $MAGNUS->round_precision(abs($cost_customer)), 6);

        return $cost_customer;
    }

    public function sendCall($agi, $destination, &$MAGNUS, $typecall = 0)
    {
        $max_long = 2147483647;

        if (substr("$destination", 0, 4) == 1111) /*Retira o techprefix de numeros portados*/ {
            $destination = str_replace(substr($destination, 0, 7), "", $destination);
        }
        $old_destination = $destination;

        CallOnlineChart::model()->updateCall();

        for ($k = 0; $k < count($this->tariffObj); $k++) {
            $destination = $old_destination;
            if ($this->tariffObj[$k]['id_trunk'] != '-1') {
                $this->usedtrunk   = $this->tariffObj[$k]['id_trunk'];
                $usetrunk_failover = 1;
            } else {
                return false;
            }
            //se nao tem tronco retornar erro*/

            $prefix         = $this->tariffObj[$k]['rc_trunkprefix'];
            $tech           = $this->tariffObj[$k]['rc_providertech'];
            $ipaddress      = $this->tariffObj[$k]['rc_providerip'];
            $removeprefix   = $this->tariffObj[$k]['rc_removeprefix'];
            $timeout        = $this->tariffObj[0]['timeout'];
            $failover_trunk = $this->tariffObj[$k]['rt_failover_trunk'];
            $addparameter   = $this->tariffObj[$k]['rt_addparameter_trunk'];
            $inuse          = $this->tariffObj[$k]['inuse'];
            $maxuse         = $this->tariffObj[$k]['maxuse'];
            $allow_error    = $this->tariffObj[$k]['allow_error'];

            if ($typecall == 1) {
                $timeout = 3600;
            }

            $outcid = 0;

            $this->sendCalltoTrunk($MAGNUS, $agi, $k, $destination, $prefix, $tech, $ipaddress, $removeprefix, $timeout
                , $addparameter, $inuse, $maxuse, $allow_error);

            $loop_failover = 0;

            while ($loop_failover <= $MAGNUS->agiconfig['failover_recursive_limit']
                && is_numeric($failover_trunk) && $failover_trunk >= 0
                && ($this->dialstatus == "CHANUNAVAIL" || $this->dialstatus == "CONGESTION")) {
                $loop_failover++;
                $this->real_answeredtime = $this->answeredtime = 0;
                $this->usedtrunk         = $failover_trunk;
                $agi->verbose("K=$k -> ANSWEREDTIME=" . $this->answeredtime . "-DIALSTATUS=" . $this->dialstatus, 10);
                $destination = $old_destination;

                $modelTrunk = Trunk::model()->findByPk($failover_trunk);

                if (count($modelTrunk)) {
                    $prefix              = $modelTrunk->trunkprefix;
                    $tech                = $modelTrunk->providertech;
                    $ipaddress           = $modelTrunk->providerip;
                    $removeprefix        = $modelTrunk->removeprefix;
                    $next_failover_trunk = $modelTrunk->failover_trunk;
                    $timeout             = $this->tariffObj[0]['timeout'];
                    $status              = $this->tariffObj[$k]['status']              = $modelTrunk->status;
                    $inuse               = $this->tariffObj[$k]['inuse']               = $modelTrunk->inuse;
                    $maxuse              = $this->tariffObj[$k]['maxuse']              = $modelTrunk->maxuse;
                    $allow_error         = $this->tariffObj[$k]['allow_error']         = $modelTrunk->allow_error;
                    $addparameter        = $this->tariffObj[$k]['rt_addparameter_trunk']        = $modelTrunk->addparameter;

                    $this->tariffObj[$k]['credit_control'] = $modelTrunk->idProvider->credit_control;
                    $this->tariffObj[$k]['credit']         = $modelTrunk->idProvider->credit;

                    if ($status == 0) {
                        $agi->verbose("Failover trunk cannot be used because it is disabled", 3);
                        break;
                    }

                    $this->sendCalltoTrunk($MAGNUS, $agi, $k, $destination, $prefix, $tech, $ipaddress, $removeprefix, $timeout
                        , $addparameter, $inuse, $maxuse, $allow_error);
                    $agi->verbose("FAILOVER app_callingcard: Dialing '$dialstr' with timeout of '$timeout'.", 15);

                    $agi->verbose("[FAILOVER K=$k]:[ANSTIME=" . $this->answeredtime . "-DIALSTATUS=" . $this->dialstatus, 15);
                }
                /* IF THE FAILOVER TRUNK IS SAME AS THE ACTUAL TRUNK WE BREAK */
                if ($next_failover_trunk == $failover_trunk) {
                    break;
                } else {
                    $failover_trunk = $next_failover_trunk;
                }

            }

            if (($this->dialstatus == "CANCEL")) {
                return true;
            }

            if ($this->tariffObj[$k]['status'] != 1) /*Change dialstatus of the trunk for send for LCR/LCD prefix*/ {
                if ($MAGNUS->agiconfig['failover_lc_prefix']) {
                    continue;
                }

            }

            /* END FOR LOOP FAILOVER */
            /*# Ooh, something actually happened! */
            if ($this->dialstatus == "BUSY") {
                $this->real_answeredtime = $this->answeredtime = 0;
                if ($MAGNUS->play_audio == 1) {
                    $agi->stream_file('prepaid-isbusy', '#');
                } else {
                    $agi->execute((busy), busy);
                }

            } elseif ($this->dialstatus == "NOANSWER") {
                $this->real_answeredtime = $this->answeredtime = 0;
                if ($MAGNUS->play_audio == 1) {
                    $agi->stream_file('prepaid-noanswer', '#');
                } else {
                    $agi->execute((congestion), Congestion);
                }

            } elseif ($this->dialstatus == "CANCEL") {
                $this->real_answeredtime = $this->answeredtime = 0;
            } elseif (($this->dialstatus == "CHANUNAVAIL") || ($this->dialstatus == "CONGESTION")) {
                $this->real_answeredtime = $this->answeredtime = 0;
                /* Check if we will failover for LCR/LCD prefix - better false for an exact billing on resell */
                if ($MAGNUS->agiconfig['failover_lc_prefix']) {
                    $agi->verbose("Call send for backup trunk -> ERROR => $this->dialstatus", 6);
                    continue;
                }
                return false;
            }

            $this->usedratecard = $k;
            $agi->verbose("USED TARIFF=" . $this->usedratecard, 10);
            return true;
        }
        /* End for */
        $this->usedratecard = $k - $loop_failover;
        $agi->verbose("USEDRATECARD - FAIL =" . $this->usedratecard, 10);
        return false;
    }

    public function sendCalltoTrunk($MAGNUS, $agi, $k, $destination, $prefix, $tech, $ipaddress, $removeprefix, $timeout
        , $addparameter, $inuse, $maxuse, $allow_error) {
        if (strncmp($destination, $removeprefix, strlen($removeprefix)) == 0) {
            $destination = substr($destination, strlen($removeprefix));
        }

        if ($MAGNUS->agiconfig['switchdialcommand'] == 1) {
            $dialstr = "$tech/$prefix$destination@$ipaddress";
        } else {
            $dialstr = "$tech/$ipaddress/$prefix$destination";
        }

        if ($this->tariffObj[$k]['credit_control'] == 1 && $this->tariffObj[$k]['credit'] <= 0) {
            $agi->verbose("Provider not have credit", 3);
            $this->tariffObj[$k]['status'] = 0;
        }

        if ($this->tariffObj[$k]['status'] == 1) {
            if (AsteriskAccess::groupTrunk($agi, $ipaddress, $maxuse)) {
                $dialedpeername       = $agi->get_variable("SIPTRANSFER");
                $this->dialedpeername = $dialedpeername['data'];

                if ($this->dialedpeername == 'yes') {
                    $agi->execute("hangup request $this->channel");
                    $MAGNUS->hangup($agi);
                }

                Trunk::model()->updateTotalCall($this->usedtrunk);

                $MAGNUS->startRecordCall($agi);
                try {
                    $MAGNUS->run_dial($agi, $dialstr, $MAGNUS->agiconfig['dialcommand_param']
                        , $this->tariffObj[$k]['rc_directmedia'], $timeout);
                } catch (Exception $e) {
                    //
                }
                if ($MAGNUS->demo == true) {
                    $answeredtime            = date('s');
                    $this->real_answeredtime = $answeredtime;
                    $dialstatus              = 'ANSWER';
                    $this->dialstatus        = 'ANSWER';
                } else {

                    $answeredtime            = $agi->get_variable("ANSWEREDTIME");
                    $this->real_answeredtime = $this->answeredtime = $answeredtime['data'];
                    $dialstatus              = $agi->get_variable("DIALSTATUS");
                    $this->dialstatus        = $dialstatus['data'];
                }
            } else {
                $agi->verbose('THE TRUNK ' . $ipaddress . ' CANNOT BE USED BECOUSE MAXIMUM NUMBER IS REACHED, SEND TO NEXT TRUNK');
                $this->answeredtime = $answeredtime['data'] = 0;
                $this->dialstatus   = 'CONGESTION';
            }

        }
        //if the trunk is inactive
        else {
            $agi->verbose('THE TRUNK ' . $ipaddress . ' IS INACTIVE, SEND TO NEXT TRUNK');
            $this->answeredtime = $answeredtime['data'] = 0;
            $this->dialstatus   = 'CONGESTION';
        }

        $MAGNUS->stopRecordCall($agi);

        if ($allow_error == 1 && $this->dialstatus == "BUSY") {
            $this->dialstatus = "CONGESTION";
        }

    }

    public function callShop($agi, $MAGNUS, $sessiontime, $id_prefix)
    {

        if ($MAGNUS->callshop == 1) {

            Sip::model()->updateAll(array('status' => 2), 'name = :key', array(':key' => $MAGNUS->sip_account));

            if ($sessiontime > 0) {

                $modelReteCallshop = RateCallshop::model()->findCallShopRate($MAGNUS->destination, $MAGNUS->id_user);

                if (!count($modelReteCallshop)) {
                    $agi->verbose('Not found CallShop rate => ' . $MAGNUS->destination . ' ' . $MAGNUS->id_user);
                    return;
                }
                $buyrate   = $modelReteCallshop[0]['buyrate'] > 0 ? $modelReteCallshop[0]['buyrate'] : $cost;
                $initblock = $modelReteCallshop[0]['minimo'];
                $increment = $modelReteCallshop[0]['block'];

                $sellratecost_callshop = $MAGNUS->calculation_price($buyrate, $sessiontime, $initblock, $increment);

                if ($sessiontime < $modelReteCallshop[0]['minimal_time_charge']) {
                    $agi->verbose("Minimal time to charge. Cost 0.0000", 15);
                    $sellratecost_callshop = 0;
                }
                //save in CDRCALLSHOP the
                $modelCallShop                = new CallShopCdr();
                $modelCallShop->sessionid     = $MAGNUS->channel;
                $modelCallShop->id_user       = $MAGNUS->id_user;
                $modelCallShop->status        = 0;
                $modelCallShop->price         = $sellratecost_callshop;
                $modelCallShop->buycost       = $MAGNUS->round_precision(abs($buyrate));
                $modelCallShop->calledstation = $MAGNUS->destination;
                $modelCallShop->destination   = $modelReteCallshop[0]['destination'];
                $modelCallShop->price_min     = $buyrate;
                $modelCallShop->cabina        = $MAGNUS->sip_account;
                $modelCallShop->sessiontime   = $sessiontime;
                $modelCallShop->save();
                $modelError = $modelCallShop->getErrors();
                if (count($modelError)) {
                    $agi->verbose(print_r($modelError, true), 25);
                }
            }
        }
        return;
    }
}
