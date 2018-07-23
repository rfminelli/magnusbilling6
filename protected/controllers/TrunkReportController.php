<?php
/**
 * Acoes do modulo "Trunk".
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
 * 23/06/2012
 */

class TrunkReportController extends Controller
{

    public $attributeOrder = 'id';
    public $select         = 'id, trunkcode, call_answered, call_total, secondusedreal,
    ( call_answered / call_total) * 100 AS asr,
    secondusedreal / call_answered AS acd';
    public function init()
    {
        $this->instanceModel = new TrunkReport;
        $this->abstractModel = TrunkReport::model();
        $this->titleReport   = Yii::t('yii', 'Trunk Report');

        parent::init();
    }

    public function actionClear()
    {
        # recebe os parametros para o filtro
        if (isset($_POST['filter']) && strlen($_POST['filter']) > 5) {
            $filter = $_POST['filter'];
        } else {
            echo json_encode(array(
                $this->nameSuccess => false,
                $this->nameMsg     => 'Por favor realizar um filtro para reprocesar',
            ));
            exit;
        }
        $filter = $filter ? $this->createCondition(json_decode($filter)) : '';

        $filter = preg_replace("/t\./", '', $filter);

        Trunk::model()->updateAll(array(
            'call_answered'  => 0,
            'call_total'     => 0,
            'secondusedreal' => 0,

        ), $filter, $this->paramsFilter);

        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameMsg     => $this->msgSuccess,
        ));

    }

    public function setAttributesModels($attributes, $models)
    {
        for ($i = 0; $i < count($attributes) && is_array($attributes); $i++) {

            $attributes[$i]['asr'] = isset($models[$i]->asr) ? $models[$i]->asr : 0;
            $attributes[$i]['acd'] = isset($models[$i]->acd) ? $models[$i]->acd : 0;
        }

        return $attributes;
    }
}
