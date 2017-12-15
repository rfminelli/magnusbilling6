<?php
/**
 * Acoes do modulo "Prefix".
 *
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
 * 01/08/2012
 */

class PrefixController extends Controller
{
    public $attributeOrder = 'id';
    public $filterByUser   = false;

    public function init()
    {
        $this->instanceModel = new Prefix;
        $this->abstractModel = Prefix::model();
        $this->titleReport   = Yii::t('yii', 'Prefix');

        parent::init();
    }

    public function extraFilterCustomAgent($filter)
    {
        return $filter;
    }

    public function actionImportFromCsv()
    {

        if (!Yii::app()->session['id_user'] || Yii::app()->session['isAdmin'] != true) {
            exit();
        }

        ini_set("memory_limit", "1500M");
        ini_set("upload_max_filesize", "3M");
        ini_set("max_execution_time", "120");
        $values = $this->getAttributesRequest();

        $handle = fopen($_FILES['file']['tmp_name'], "r");
        $this->importPrefixs($handle, $values);

        fclose($handle);

        echo json_encode(array(
            $this->nameSuccess => true,
            'msg'              => $this->msgSuccess,
        ));
    }

    private function importPrefixs($handle, $values)
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

                $resultPrefix = Prefix::model()->getPrefix($prefix);

                if (count($resultPrefix) > 0) {
                    if ($resultPrefix[0]['destination'] != $destination) {
                        Prefix::model()->updateDestination($prefix, $destination);
                    }

                } else {
                    $sqlPrefix[] = "($prefix, '$destination')";
                }
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

}
