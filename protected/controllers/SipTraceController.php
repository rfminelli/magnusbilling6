<?php
/**
 * Acoes do modulo "SipTrace".
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
 * 19/02/2018
 */

class SipTraceController extends Controller
{
    public $attributeOrder = 't.id';

    public function init()
    {
        $this->instanceModel = new SipTrace;
        $this->abstractModel = SipTrace::model();
        $this->titleReport   = Yii::t('yii', 'SipTrace');
        parent::init();
    }

    public function actionStart()
    {

        $modelTrace = Trace::model()->find('in_use = 1 OR status = 1');

        if (count($modelTrace)) {
            echo json_encode(array(
                $this->nameSuccess => false,
                $this->nameMsg     => Yii::t('yii', 'Exist a filter active or in use. Wait.....'),
            ));
            exit;
        }
        $modelTrace          = new Trace();
        $modelTrace->filter  = $_POST['filter'];
        $modelTrace->timeout = $_POST['timeout'];
        $modelTrace->status  = 1;
        $modelTrace->in_use  = 0;
        $modelTrace->save();

        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameMsg     => Yii::t('yii', 'Wait ' . $_POST['timeout'] . ', and refresh the module to see the packets'),
        ));
    }

}
