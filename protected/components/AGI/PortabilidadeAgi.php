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

class PortabilidadeAgi
{

    public static function getDestination($agi, $MAGNUS, $number)
    {
        $agi->verbose("consulta portabilidade numero " . $number, 25);

        //celular SP
        $mobile = false;
        $fixed  = false;

        if (strlen($number) >= 10 && substr($number, 0, 2) == 55) {

            if (substr($number, 4, 1) >= 7) {
                $mobile = true;
            } else {
                $fixed = true;
            }

            if (($mobile == true && $MAGNUS->modelUser->idPlan->portabilidadeMobile == 1) ||
                ($fixed == true && $MAGNUS->modelUser->idPlan->portabilidadeFixed == 1)) {

                $MAGNUS->portabilidade = true;
                if (strlen($MAGNUS->config['global']['portabilidadeUsername']) > 3 && strlen($MAGNUS->config['global']['portabilidadePassword']) > 3) {
                    $user = $MAGNUS->config['global']['portabilidadeUsername'];
                    $pass = $MAGNUS->config['global']['portabilidadePassword'];
                    $url  = "http://consultas.portabilidadecelular.com/painel/consulta_numero.php?user=" . $user . "&pass=" . $pass . "&seache_number=" . $number . "";
                    $agi->verbose($url, 25);

                    if (!$operadora = @file_get_contents($url, false)) {
                        $operadora = '55999';
                    }
                    $company = str_replace("55", "", $operadora);
                    $number  = "1111" . $company . $number;
                } else {
                    $ddd = substr($number, 2);
                    //verifico se é radio
                    if (strlen($ddd) == 10 && $mobile == true) {
                        $resultNextel = Portability::model()->findPrefix(substr($ddd, 0, 6));
                        if (count($resultNextel) && ($resultNextel[0]['company'] == '55377' || $resultNextel[0]['company'] == '55390' || $resultNextel[0]['company'] == '55391')) {
                            $agi->verbose("é Nextel", 15);
                        } else {

                            $agi->verbose("Numero sem o nono digito, MBilling adicionou", 8);
                            $ddd    = substr($ddd, 0, 2) . 9 . substr($ddd, 2);
                            $number = "55" . $ddd;

                        }
                    }
                    $modelPortabilidade = Portability::model()->find('number = :key', array(':key' => $ddd));

                    if (count($modelPortabilidade)) {
                        $company = str_replace("55", "", $modelPortabilidade->company);
                        $number  = "1111" . $company . $number;
                        $agi->verbose("CONSULTA DA PORTABILIDADE ->" . $modelPortabilidade->company, 25);
                    } else {
                        if (strlen($ddd) == 11) {
                            $modelPortabilidade = Portability::model()->findPrefix(substr($ddd, 0, 7));
                        } else {
                            $modelPortabilidade = $resultNextel;
                        }

                        if (count($modelPortabilidade)) {
                            $company = str_replace("55", "", $modelPortabilidade[0]['company']);
                            $number  = "1111" . $company . $number;
                            $agi->verbose("CONSULTA DA PORTABILIDADE ->NUMERO NAO FOI PORTADO->" . $modelPortabilidade[0]['company'], 25);
                        } else {
                            $company = 399;
                            $number  = "1111" . $company . $number;
                            $agi->verbose("CONSULTA DA PORTABILIDADE ->Numero sem operadora->" . $number, 3);
                        }
                    }
                }
                $agi->verbose("CONSULTA DA PORTABILIDADE ->" . $number, 25);
            }
        }
        return $number;
    }
}
