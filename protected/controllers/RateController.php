<?php
/**
 * Acoes do modulo "Rate".
 *
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
 * 30/07/2012
 */

class RateController extends Controller
{
    public $attributeOrder = 't.id';
    public $extraValues    = array(
        'idTrunk'  => 'trunkcode',
        'idPlan'   => 'name',
        'idPrefix' => 'destination,prefix',
    );

    public $fieldsFkReport = array(
        'id_plan'   => array(
            'table'       => 'pkg_plan',
            'pk'          => 'id',
            'fieldReport' => 'name',
        ),
        'id_trunk'  => array(
            'table'       => 'pkg_trunk',
            'pk'          => 'id',
            'fieldReport' => 'trunkcode',
        ),
        'id_prefix' => array(
            'table'       => 'pkg_prefix',
            'pk'          => 'id',
            'fieldReport' => 'prefix',
        ),
        'id'        => array(
            'table'       => 'pkg_prefix',
            'pk'          => 'id',
            'fieldReport' => 'destination',
        ),
    );

    public $fieldsInvisibleClient = array(
        'additional_grace',
        'id_trunk',
        'idTrunktrunkcode',
        'buyrate',
        'buyrateinitblock',
        'buyrateincrement',
        'connectcharge',
        'disconnectcharge',
        'startdate',
        'stopdate',
        'starttime',
        'endtime',
        'musiconhold',
        'rounding_calltime',
        'rounding_threshold',
        'additional_block_charge',
        'additional_block_charge_time',
        'disconnectcharge_after',
        'minimal_cost',
        'minimal_time_charge',
        'package_offer',
    );

    public $fieldsInvisibleAgent = array(
        'additional_grace',
        'id_trunk',
        'idTrunktrunkcode',
        'buyrate',
        'buyrateinitblock',
        'buyrateincrement',
        'connectcharge',
        'disconnectcharge',
        'startdate',
        'stopdate',
        'starttime',
        'endtime',
        'musiconhold',
        'rounding_calltime',
        'rounding_threshold',
        'additional_block_charge',
        'additional_block_charge_time',
        'disconnectcharge_after',
        'minimal_cost',
        'package_offer',
    );

    public $FilterByUser;

    public function init()
    {

        if (Yii::app()->session['isAgent'] || Yii::app()->session['id_agent'] > 1) {
            $this->instanceModel = new RateAgent;
            $this->abstractModel = RateAgent::model();
        } else {
            $this->instanceModel = new Rate;
            $this->abstractModel = Rate::model();
        }

        $this->titleReport = Yii::t('yii', 'Tarrifs');

        parent::init();
        if (!Yii::app()->session['isAdmin']) {
            $this->extraValues = array(
                'idPlan'   => 'name',
                'idPrefix' => 'destination,prefix',
            );
        }
    }

    public function extraFilterCustomClient($filter)
    {
        //se for cliente filtrar pelo plano do cliente
        $filter .= ' AND t.id_plan = :dfby0';
        $this->paramsFilter[':dfby0'] = Yii::app()->session['id_plan'];

        return $filter;
    }

    public function extraFilterCustomAgent($filter)
    {
        //se é agente filtrar pelo user.id_user

        if (array_key_exists('idPlan', $this->relationFilter)) {
            $this->relationFilter['idPlan']['condition'] .= " AND idPlan.id_user LIKE :agfby";
        } else {
            $this->relationFilter['idPlan'] = array(
                'condition' => "idPlan.id_user LIKE :agfby",
            );
        }
        $this->paramsFilter[':agfby'] = Yii::app()->session['id_user'];

        return $filter;
    }

    public function actionReport()
    {
        $this->replaceToExport();
        parent::actionReport();
    }

    public function actionCsv()
    {

        $this->replaceToExport();
        parent::actionCsv();
    }

    public function replaceToExport()
    {

        //altera as colunas para poder pegar o destino das tarifas
        $destino    = '{"header":"Prefixo","dataIndex":"idPrefixprefix"},';
        $destinoNew = '{"header":"Prefixo","dataIndex":"id_prefix"},';
        if (preg_match("/$destino/", $_GET['columns'])) {
            $_GET['columns'] = preg_replace("/$destino/", $destinoNew, $_GET['columns']);
        }

        $destino    = '{"header":"Destino","dataIndex":"idPrefixdestination"},';
        $destinoNew = '{"header":"Destino","dataIndex":"id"},';
        if (preg_match("/$destino/", $_GET['columns'])) {
            $_GET['columns'] = preg_replace("/$destino/", $destinoNew, $_GET['columns']);
        }
    }

    public function actionImportFromCsv()
    {

        if (!Yii::app()->session['id_user'] || Yii::app()->session['isClient'] == true) {
            exit();
        }
        ini_set("upload_max_filesize", "3M");
        ini_set("max_execution_time", "120");
        $values = $this->getAttributesRequest();

        $handle = fopen($_FILES['file']['tmp_name'], "r");
        $this->importPrefixs($handle, $values);

        $handle = fopen($_FILES['file']['tmp_name'], "r");
        $this->importRates($handle, $values);

        fclose($handle);
        Prefix::model()->prefixLength();
        echo json_encode(array(
            $this->nameSuccess => true,
            'msg'              => $this->msgSuccess,
        ));
    }

    public function importPrefixs($handle, $values)
    {
        $sqlPrefix = array();
        while (($row = fgetcsv($handle, 32768, $values['delimiter'])) !== false) {

            $checkDelimiter = $values['delimiter'] == ',' ? ';' : ',';
            //erro do separador
            if (preg_match("/$checkDelimiter/", $row[0])) {
                echo json_encode(array(
                    $this->nameSuccess => false,
                    'errors'           => Yii::t('yii', 'ERROR: CSV delimiter, please select ( ' . $checkDelimiter . ' ) on the import form'),
                ));
                exit;
            }

            if (isset($row[1])) {
                if (!isset($row[0]) || $row[0] == '') {
                    echo json_encode(array(
                        $this->nameSuccess => false,
                        'errors'           => 'Prefix not exit in the CSV file . Line: ' . print_r($row, true),
                    ));
                    exit;
                }
                $prefix      = $row[0];
                $destination = ($row[1] == '') ? 'ROC' : trim($row[1]);
                $destination = utf8_encode($destination);
                $destination = preg_replace("/'/", "''", $destination);
                $sqlPrefix[] = "('$prefix', '$destination')";

            }
        }
        if (count($sqlPrefix) > 0) {
            SqlInject::sanitize($sqlPrefix);
            if (count($sqlPrefix) > 0) {
                $result = Prefix::model()->insertPrefixs($sqlPrefix);
                Yii::log(print_r($result, true), 'info');

                if (isset($result->errorInfo)) {
                    echo json_encode(array(
                        $this->nameSuccess => false,
                        'errors'           => $this->getErrorMySql($result),
                    ));
                    exit;
                }
            }
        }
    }

    public function importRates($handle, $values)
    {
        $sqlRate = array();
        $idPlan  = $values['id_plan'];
        $idTrunk = $values['id_trunk'];
        while (($row = fgetcsv($handle, 32768, $values['delimiter'])) !== false) {
            Yii::log(print_r($row, true), 'info');
            if (isset($row[1])) {
                if (!isset($row[0]) || $row[0] == '') {
                    echo json_encode(array(
                        $this->nameSuccess => false,
                        'msg'              => 'Prefix not exit in the CSV file . Line: ' . print_r($row, true),
                    ));
                    exit;
                }
                $prefix           = $row[0];
                $destination      = ($row[1] == '') ? 'ROC' : trim($row[1]);
                $destination      = utf8_encode($destination);
                $price            = $row[2] == '' ? '0.0000' : $row[2];
                $buyprice         = isset($row[3]) ? $row[3] : $row[2];
                $initblock        = isset($row[4]) ? $row[4] : 1;
                $billingblock     = isset($row[5]) ? $row[5] : 1;
                $buyrateinitblock = isset($row[6]) ? $row[6] : 1;
                $buyrateincrement = isset($row[7]) ? $row[7] : 1;

                $resultPrefix = Prefix::model()->getPrefix($prefix);
                if (isset($resultPrefix->errorInfo)) {
                    echo json_encode(array(
                        $this->nameSuccess => false,
                        'errors'           => $this->getErrorMySql($result),
                    ));
                    exit;
                }

                $idPrefix = $resultPrefix[0]['id'];

                if (Yii::app()->session['isAdmin']) {
                    $sqlRate[] = "($idPrefix, $idPlan, $price, $buyprice, $idTrunk, $initblock, $billingblock, $buyrateinitblock, $buyrateincrement, 1)";
                } else {
                    $sqlRate[] = "($idPrefix, $idPlan, $price, $initblock, $billingblock)";
                }

            }
        }

        if (count($sqlRate) > 0) {
            Yii::log('ewewewew', 'info');
            SqlInject::sanitize($sqlRate);
            $result = Rate::model()->insertRates(Yii::app()->session['isAdmin'], $sqlRate);
            if (isset($result->errorInfo)) {
                echo json_encode(array(
                    $this->nameSuccess => false,
                    'errors'           => $this->getErrorMySql($result),
                ));
                exit;
            }
        }
    }

    public function afterSave($model, $values)
    {
        $this->tablesChanges();
    }

    public function afterUpdateAll($strIds)
    {
        $this->tablesChanges();
    }
    public function afterDestroy($values)
    {
        $this->tablesChanges();
    }

    public function tablesChanges()
    {
        TablesChanges::model()->updateAll(array('last_time' => time()), 'module = :key', array(':key' => 'pkg_rate'));
    }
}
