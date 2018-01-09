<?php
/**
 * Acoes do modulo "Call".
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
 * 19/09/2012
 */

class CallOnlineChartController extends Controller
{

    public function init()
    {
        if (!Yii::app()->session['id_user']) {
            die("Access denied to save in module: $module");
            exit;
        }

        $this->instanceModel = new CallOnlineChart;
        $this->abstractModel = CallOnlineChart::model();
        $this->titleReport   = Yii::t('yii', 'CallOnlineChart');
        parent::init();
    }

    public function actionRead($asJson = true, $condition = null)
    {
        $filter = isset($_GET['filter']) ? json_decode($_GET['filter']) : null;

        if (isset($filter) && $filter[0]->value == 'hour') {

            $modelCallOnlineChart = CallOnlineChart::model()->findAll(array(
                'select' => 'id,  DATE_FORMAT( date, \'%H\' ) date , SUM(total) AS total, SUM(answer) AS answer',
                'group'  => "DATE_FORMAT( date, '%Y-%m-%d %H' )",
                'order'  => 'id DESC',
                'limit'  => 24,
            ));
        } else {
            $modelCallOnlineChart = CallOnlineChart::model()->findAll(array(
                'select' => 'id, DATE_FORMAT( date, \'%H:%i\' ) date, total, answer',
                'order'  => 'id DESC',
                'limit'  => 20,
            ));
        }
        # envia o json requisitado
        echo json_encode(array(
            $this->nameRoot  => $this->getAttributesModels($modelCallOnlineChart, $this->extraValues),
            $this->nameCount => 0,
        ));

    }

}
