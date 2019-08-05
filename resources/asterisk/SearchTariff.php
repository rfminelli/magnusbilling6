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
class SearchTariff
{

    public function find(&$MAGNUS, &$agi)
    {

        if (file_exists(dirname(__FILE__) . '/beforeSearchTariff.php')) {
            include dirname(__FILE__) . '/beforeSearchTariff.php';
        }

        //return the maximun length of prefix
        $sql               = "SELECT length FROM pkg_prefix_length WHERE code = '" . substr($MAGNUS->destination, 0, 2) . "' LIMIT 1";
        $modelPrefixLength = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

        if (isset($modelPrefixLength->length)) {
            $max_len_prefix = $modelPrefixLength->length;
            $prefixclause   = '(';
            while ($max_len_prefix >= 1) {
                $prefixclause .= "prefix='" . substr($MAGNUS->destination, 0, $max_len_prefix) . "' OR ";
                $max_len_prefix--;
            }

            $prefixclause = substr($prefixclause, 0, -3) . ")";

        } else {
            $max_len_prefix = 6;
            $prefixclause   = "  (prefix LIKE '&_%' ESCAPE '&' AND '" . $MAGNUS->destination . "'
                        REGEXP REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT('^', prefix, '$'),
                        'X', '[0-9]'), 'Z', '[1-9]'), 'N', '[2-9]'), '.', '.+'), '_', ''))";
        }

        $sql = "SELECT lcrtype, pkg_plan.id AS id_plan, pkg_prefix.prefix AS dialprefix,
                pkg_plan.name, pkg_rate.id_prefix, pkg_rate.id AS id_rate, buyrate,  buyrateinitblock buyrateinitblock,
                buyrateincrement, rateinitial, initblock, billingblock, connectcharge, disconnectcharge disconnectcharge,
                pkg_rate.id_trunk AS id_trunk, pkg_trunk.trunkprefix AS rc_trunkprefix, pkg_trunk.directmedia AS rc_directmedia,
                pkg_trunk.providertech AS rc_providertech ,pkg_trunk.providerip AS rc_providerip,
                pkg_trunk.removeprefix AS rc_removeprefix, pkg_trunk.failover_trunk AS rt_failover_trunk,
                pkg_trunk.addparameter AS rt_addparameter_trunk, pkg_trunk.status, pkg_trunk.inuse, pkg_trunk.maxuse,
                pkg_trunk.allow_error,pkg_trunk.if_max_use, pkg_rate.additional_grace AS additional_grace, minimal_time_charge,
                minimal_time_buy, pkg_trunk.link_sms, pkg_trunk.user user, pkg_trunk.secret, package_offer ,
                pkg_trunk.id_provider, pkg_provider.credit_control, pkg_provider.credit
                FROM pkg_plan
                LEFT JOIN pkg_rate ON pkg_plan.id = pkg_rate.id_plan
                LEFT JOIN pkg_trunk AS pkg_trunk ON pkg_trunk.id = pkg_rate.id_trunk
                LEFT JOIN pkg_prefix ON pkg_rate.id_prefix = pkg_prefix.id
                LEFT JOIN pkg_provider ON pkg_trunk.id_provider = pkg_provider.id
                WHERE pkg_plan.id=$MAGNUS->id_plan AND pkg_rate.status = 1 AND $prefixclause
                ORDER BY LENGTH( prefix ) DESC LIMIT $MAGNUS->tariff_limit";
        $result = $agi->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        // $agi->verbose($result, 25);

        if (!is_array($result) || count($result) == 0) {
            return 0;
        }

        //1) REMOVE THOSE THAT HAVE A SMALLER DIALPREFIX
        $max_len_prefix = strlen($result[0]['dialprefix']);
        for ($i = 1; $i < count($result); $i++) {
            if (strlen($result[$i]['dialprefix']) < $max_len_prefix) {
                break;
            }

        }

        $result = array_slice($result, 0, $i);

        if (count($result) > 1) {
            if ($result[0]['lcrtype'] == 2) {
                $result = $this->load_balancer($agi, $result);
            } else if ($result[0]['lcrtype'] == 1) {
                $result = $this->array_csort($result, 'buyrate', SORT_ASC);
            } else {
                $result = $this->array_csort($result, 'rateinitial', SORT_ASC);
            }

        }

        // 3) REMOVE THOSE THAT USE THE SAME TRUNK - MAKE A DISTINCT AND THOSE THAT ARE DISABLED.
        $mylistoftrunk = array();

        //Select custom rate to user
        $sql           = "SELECT * FROM pkg_user_rate WHERE id_user = $MAGNUS->id_user AND id_prefix = '" . $result[0]['id_prefix'] . "' LIMIT 1";
        $modelUserRate = $agi->query($sql)->fetch(PDO::FETCH_OBJ);
        for ($i = 0; $i < count($result); $i++) {
            //change custom rate to user
            if (isset($modelUserRate->id)) {
                $result[$i]['rateinitial']  = $modelUserRate->rateinitial;
                $result[$i]['initblock']    = $modelUserRate->initblock;
                $result[$i]['billingblock'] = $modelUserRate->billingblock;
            }

            $status               = $result[$i]['status']; //status trunk
            $mylistoftrunk_next[] = $mycurrenttrunk = $result[$i]['id_trunk'];

            //Check if we already have the same trunk in the ratecard
            if (($i == 0 || !in_array($mycurrenttrunk, $mylistoftrunk))) {
                $distinct_result[] = $result[$i];
            }
            if ($status == 1) {
                $mylistoftrunk[] = $mycurrenttrunk;
            }

        }
        $number_trunk = count($distinct_result); //total de troncos

        $agi->verbose("NUMBER TRUNK FOUND " . $number_trunk, 10);

        if (file_exists(dirname(__FILE__) . '/AfterSearchTariff.php')) {
            include dirname(__FILE__) . '/AfterSearchTariff.php';
        }

        return $distinct_result;

    }

    public function load_balancer(&$agi, $result)
    {
        $agi->verbose('Load Balancer', 15);
        $total        = count($result);
        $sql          = "SELECT * FROM pkg_balance WHERE id_prefix = " . $result[0]['dialprefix'] . " LIMIT 1";
        $modelBalance = $agi->query($sql)->fetch(PDO::FETCH_OBJ);

        if (isset($modelBalance->last_use)) {
            $ultimo = $modelBalance->last_use;
            if ($modelBalance->last_use >= $total - 1) {
                $sql = "UPDATE pkg_balance SET last_use = 0 WHERE id_prefix = " . $result[0]['dialprefix'];
            } else {
                $sql = "UPDATE pkg_balance SET last_use = last_use + 1 WHERE id_prefix = " . $result[0]['dialprefix'];
            }
            $agi->exec($sql);
        } else {
            $sql = "INSERT INTO pkg_balance (last_use, id_prefix) VALUES (0, '" . $result[0]['dialprefix'] . ")";
            $agi->exec($sql);
            $ultimo = 0;
        }

        //coloca o id ultimo em primeiro
        $result = array_filter(array_merge(array($result[$ultimo]), $result));

        //retira o id dublicado
        for ($i = 0; $i <= $total; $i++) {
            if ($i > 0) {
                if ($result[$i]['id_rate'] == $result[0]['id_rate']) {
                    unset($result[$i]);
                }
            }
        }
        $result = array_values($result);
        foreach ($result as $key => $value) {
            $agi->verbose($key . ' => ' . print_r($value['id_rate'], true), 15);
        }

        return $result;
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

}
