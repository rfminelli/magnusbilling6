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

class SendCreditRatesController extends Controller
{
    public $attributeOrder = 't.id';
    public $extraValues    = array('idUser' => 'username', 'idProduct' => 'operator_name,country,currency_dest,product,currency_orig,wholesale_price');

    public function init()
    {
        $this->instanceModel = new SendCreditRates;
        $this->abstractModel = SendCreditRates::model();
        $this->titleReport   = Yii::t('yii', 'SendCreditProducts');
        parent::init();
    }

    public function actionRead($asJson = true, $condition = null)
    {
        if (Yii::app()->session['isClient']) {
            $modelSendCreditRates = SendCreditRates::model()->find('id_user = :key', array(':key' => Yii::app()->session['id_user']));
            //add the user sell_price if his not have any change
            if (!count($modelSendCreditRates)) {
                $sql = " INSERT INTO pkg_send_credit_rates (id_user,id_product,sell_price)  SELECT " . (int) Yii::app()->session['id_user'] . ",id,wholesale_price FROM pkg_send_credit_products ";
                Yii::app()->db->createCommand($sql)->execute();

            }
        }
        parent::actionRead($asJson = true, $condition = null);
    }

    public function actionResetSellPrice()
    {
        SendCreditRates::model()->deleteAll('id_user = :key',
            array(':key' => Yii::app()->session['id_user'])
        );

        $sql = " INSERT INTO pkg_send_credit_rates (id_user,id_product,sell_price)  SELECT " . (int) Yii::app()->session['id_user'] . ",id,wholesale_price FROM pkg_send_credit_products ";
        Yii::app()->db->createCommand($sql)->execute();

        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameMsg     => 'Sell price reseted',
        ));
    }

}
