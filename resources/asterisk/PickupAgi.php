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

class PickupAgi
{
    public function execute(&$agi, &$MAGNUS)
    {
        $sql      = "SELECT accountcode FROM pkg_sip WHERE name = '" . substr($MAGNUS->dnid, 2) . "' LIMIT 1";
        $modelSip = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

        if (isset($modelSip->accountcode) && $modelSip->accountcode == $MAGNUS->accountcode) {

            $agi->verbose('Pickup module - SipAccount ' . $MAGNUS->accountcode . ' try pickup extension ' . substr($MAGNUS->dnid, 2), 1);

            $agi->execute('Pickup', substr($MAGNUS->dnid, 2));
        } else {
            $agi->verbose('Pickup module - SipAccount ' . $MAGNUS->accountcode . ' try pickup from another user extension ' . substr($MAGNUS->dnid, 2), 1);
        }

        $MAGNUS->hangup($agi);

    }
}
