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
class SearchTariff
{

    public function find($destination, $id_plan, $id_user, $agi = null)
    {

        if (is_null($agi)) {
            $agi = $this;
        }

        if (file_exists(dirname(__FILE__) . '/beforeSearchTariff.php')) {
            include dirname(__FILE__) . '/beforeSearchTariff.php';
        }

        $result = Plan::model()->searchTariff($id_plan, $destination);
        $agi->verbose($result[0], 25);
        $result = $result[1];

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
        $modelUserRate = UserRate::model()->find('id_prefix = :key AND id_user = :key1', array(
            ':key'  => $result[0]['id_prefix'],
            ':key1' => $id_user,
        ));

        for ($i = 0; $i < count($result); $i++) {
            //change custom rate to user
            if (count($modelUserRate)) {
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

        $agi->verbose("NUMBER TRUNK FOUND" . $number_trunk, 10);

        if (file_exists(dirname(__FILE__) . '/AfterSearchTariff.php')) {
            include dirname(__FILE__) . '/AfterSearchTariff.php';
        }

        return $distinct_result;

    }

    public function load_balancer(&$agi, $result)
    {
        $agi->verbose('Load Balancer', 15);
        $total = count($result);

        $modelBalance = Balance::model()->find('id_prefix = :key', array(':key' => $result[0]['dialprefix']));

        if (count($modelBalance)) {
            $ultimo = $modelBalance->last_use;

            if ($ultimo >= $total - 1) {
                $modelBalance->last_use = 0;
            } else {
                $modelBalance->last_use += 1;
            }

        } else {
            $modelBalance            = new Balance();
            $modelBalance->last_use  = 0;
            $modelBalance->id_prefix = $result[0]['dialprefix'];
            $ultimo                  = 0;
        }

        $modelBalance->save();

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

    public function verbose($message, $level = 0)
    {
        if ($level >= 30) {
            echo $message . "<br>";
        }

    }

}
