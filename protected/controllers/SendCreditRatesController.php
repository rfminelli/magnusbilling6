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

class SendCreditRatesController extends Controller
{
    public $attributeOrder = 't.id';
    public $join           = '   JOIN pkg_send_credit_rates p ON t.operator_id = p.operator_id AND  t.product = p.product ';
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

            $sql     = "SELECT id FROM pkg_send_credit_rates WHERE id_user = " . (int) Yii::app()->session['id_user'];
            $command = Yii::app()->db->createCommand($sql);
            $result  = $command->queryAll();
            //add the user sell_price if his not have any change
            if (!count($result)) {
                $sql = " INSERT INTO pkg_send_credit_rates (id_user,product,operator_id,sell_price)  SELECT " . (int) Yii::app()->session['id_user'] . ",product,operator_id,retail_price FROM pkg_send_credit_products ";
                Yii::app()->db->createCommand($sql)->execute();

            }
        }
        parent::actionRead($asJson = true, $condition = null);
    }

    public function extraFilterCustomClient($filter)
    {

        //se for cliente filtrar pelo pkg_user.id
        $filter .= ' AND p.id_user = :clfby';
        $this->paramsFilter[':clfby'] = Yii::app()->session['id_user'];

        return $filter;
    }

    public function actionSave()
    {
        $values = $this->getAttributesRequest();

        if (isset($_POST['filter'])) {

            if (strlen($_POST['filter']) > 5) {
                echo json_encode(array(
                    $this->nameSuccess => false,
                    $this->nameRoot    => [],
                    $this->nameMsg     => 'You cant use filter to batch update',
                ));
                exit;

            }

            foreach ($values as $fieldName => $value) {
                if (isset($value['isPercent']) && is_bool($value['isPercent'])) {
                    $v            = $value['value'];
                    $percent      = $v / 100;
                    $valuePercent = $value['isPercent'] ? "($fieldName * $percent)" : $v;

                    if ($value['isAdd']) {
                        $valueUpdate = "$fieldName + $valuePercent";
                    } else if ($value['isRemove']) {
                        $valueUpdate = "$fieldName - $valuePercent";
                    } else {
                        $valueUpdate = $valuePercent;
                    }
                } else {

                    $valueUpdate = "'$value'";
                }

                $setters[$fieldName] = "$valueUpdate";
            }

            $sql = "UPDATE pkg_send_credit_rates SET $fieldName = " . $valueUpdate . "  WHERE id_user = " . Yii::app()->session['id_user'] . " ";
            Yii::app()->db->createCommand($sql)->execute();
        } else {

            $modelSendCreditProducts = SendCreditProducts::model()->findByPk((int) $values['id']);

            $sql     = "UPDATE pkg_send_credit_rates SET sell_price = :key WHERE id_user = " . (int) Yii::app()->session['id_user'] . " AND product = " . (int) $modelSendCreditProducts->product . " AND operator_id = " . (int) $modelSendCreditProducts->operator_id . " LIMIT 1";
            $command = Yii::app()->db->createCommand($sql);
            $command->bindValue(":key", $values['sell_price'], PDO::PARAM_STR);
            $command->execute();
        }
        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameRoot    => [],
            $this->nameMsg     => $this->msg,
        ));
    }

    public function actionResetSellPrice()
    {
        $sql = "DELETE FROM pkg_send_credit_rates WHERE id_user = " . (int) Yii::app()->session['id_user'];
        Yii::app()->db->createCommand($sql)->execute();

        $sql = " INSERT INTO pkg_send_credit_rates (id_user,product,operator_id,sell_price)  SELECT " . (int) Yii::app()->session['id_user'] . ",product,operator_id,retail_price FROM pkg_send_credit_products ";
        Yii::app()->db->createCommand($sql)->execute();

        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameMsg     => 'Sell price reseted',
        ));
    }

    public function setAttributesModels($attributes, $models)
    {

        for ($i = 0; $i < count($attributes) && is_array($attributes); $i++) {
            $sql = "SELECT sell_price FROM pkg_send_credit_rates WHERE
                    id_user = " . (int) Yii::app()->session['id_user'] . "  AND
                    operator_id = :key AND
                    product = " . (int) $attributes[$i]['product'] . " LIMIT 1";
            $command = Yii::app()->db->createCommand($sql);
            $command->bindValue(":key", $attributes[$i]['operator_id'], PDO::PARAM_INT);
            $result = $command->queryAll();

            $attributes[$i]['sell_price'] = count($result) ? $result[0]['sell_price'] : $attributes[$i]['retail_price'];

        }

        return $attributes;
    }
}
