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
 * @copyright Copyright (C) 2005 - 2018 MagnusSolution. All rights reserved.
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

        $hours = isset($filter[0]) ? $filter[0]->value : 1;

        $filter = 'date >= date_sub(NOW(), interval ' . $hours . ' hour)';

        if ($hours == 1222) {
            $dateFormat = 'DATE_FORMAT( date, \'%d %H\' ) date';
            $group      = "DATE_FORMAT( date, '%Y-%m-%d %H' )";
        } else if ($hours > 12) {
            $dateFormat = 'DATE_FORMAT( date, \'%D %l%p\' ) date';
            $group      = "DATE_FORMAT( date, '%Y-%m-%d %H' )";
        } else {
            $dateFormat = 'DATE_FORMAT( date, \'%H:%i\' ) date';
            $group      = 1;
        }

        $modelCallOnlineChart = CallOnlineChart::model()->findAll(array(
            'select'    => 'id, ' . $dateFormat . ', total, answer',
            'order'     => 'id DESC',
            'group'     => $group,
            'condition' => $filter,
        ));

        # envia o json requisitado
        echo json_encode(array(
            $this->nameRoot  => $this->getAttributesModels($modelCallOnlineChart, $this->extraValues),
            $this->nameCount => 0,
        ));

    }

}
