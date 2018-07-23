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
class PortalDeVozAgi
{

    public function send(&$agi, &$MAGNUS, &$Calc, $modelDestination)
    {
        $agi->answer();
        $agi->verbose('PortalDeVozAgi');
        for ($i = 0; $i < 3; $i++) {
            $res_dtmf     = $agi->get_data('prepaid-enter-dest', 5000, 10);
            $MAGNUS->dnid = $res_dtmf["result"];

            $MAGNUS->modelSip = Sip::model()->find('name = :key', array(':key' => $MAGNUS->dnid));

            if (!count($MAGNUS->modelSip)) {
                $agi->verbose('User no found', 15);
                $agi->stream_file('prepaid-invalid-digits', '#');
                continue;
            } else {
                $agi->verbose('Call to user ' . $MAGNUS->modelSip->name, 15);
                $MAGNUS->extension = $MAGNUS->destination = $MAGNUS->dnid = $MAGNUS->modelSip->name;
                SipCallAgi::processCall($MAGNUS, $agi, $Calc);
                break;
            }
        }

    }
}
