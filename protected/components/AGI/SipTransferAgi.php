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

class SipTransferAgi
{
    public function billing(&$MAGNUS, $agi, &$Calc)
    {
        $siptransfer = $agi->get_variable("SIPTRANSFER");
        if ($siptransfer['data'] == 'yes') {
            // transferencia
            $agi->verbose("SIPTRANSFER", 15);

            $MAGNUS->agiconfig['cid_enable']     = 0;
            $MAGNUS->agiconfig['say_timetocall'] = 0;
            if (strlen($MAGNUS->CallerID) < 6) {
                $modelCall = Call::model()->find('uniqueid = :key1 AND starttime = :key2',
                    array(
                        ':key1' => $MAGNUS->uniqueid,
                        ';Key2' => date("Y-m-d"),
                    ));
                if (count($modelCall)) {}
                $MAGNUS->CallerID = $modelCall->calledstation;

            }
            $cia_res = AuthenticateAgi::authenticateUser($agi, $MAGNUS);

            if ($cia_res == 1) {

                $MAGNUS->CallerID = $MAGNUS->number_translation($agi, $MAGNUS->CallerID);
                $MAGNUS->dnid     = $MAGNUS->destination     = $MAGNUS->CallerID;

                $MAGNUS->agiconfig['use_dnid'] = 1;

                $SearchTariff       = new SearchTariff();
                $resfindrate        = $SearchTariff->find($MAGNUS->destination, $MAGNUS->id_plan, $MAGNUS->id_user, $agi);
                $Calc->tariffObj    = $resfindrate;
                $Calc->number_trunk = count($resfindrate);

                $agi->verbose(print_r($Calc->tariffObj, true), 10);
                $Calc->usedratecard = 0;
                // IF FIND RATE
                if ($resfindrate != 0) {
                    $agi->verbose("CREDIT $MAGNUS->credit", 15);
                    $res_all_calcultimeout = $Calc->calculateAllTimeout($MAGNUS, $MAGNUS->credit);
                    $agi->verbose(print_r($res_all_calcultimeout, true), 10);

                    if ($res_all_calcultimeout) {
                        $dialtime = $agi->get_variable("DIALEDTIME");
                        $dialtime = $dialtime['data'];

                        $answeredtime = $agi->get_variable("ANSWEREDTIME");
                        $answeredtime = $answeredtime['data'];

                        $Calc->answeredtime = $dialtime + $answeredtime + 30;
                        $Calc->dialstatus   = 'ANSWERED';
                        $Calc->usedtrunk    = $Calc->tariffObj[0]['rc_id_trunk'];
                        $agi->verbose("Calc -> answeredtime=" . $Calc->answeredtime . " $Calc->usedtrunk", 15);

                        $Calc->updateSystem($MAGNUS, $agi, 1, 0, 2);

                    }
                }
            }
        }
    }
}
