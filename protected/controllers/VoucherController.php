<?php
/**
 * Acoes do modulo "Voucher".
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
 * 20/09/2012
 */

class VoucherController extends Controller
{
    public $attributeOrder        = 't.id';
    public $extraValues           = array('idUser' => 'username');
    public $fieldsInvisibleClient = array(
        'tag',
        'creationdate',
        'expirationdate',
        'used',
        'currency',
    );
    public $fieldsInvisibleAgent = array(
        'tag',
    );

    public function init()
    {
        $this->instanceModel = new Voucher;
        $this->abstractModel = Voucher::model();
        $this->titleReport   = Yii::t('yii', 'Voucher');
        parent::init();
    }

    public function actionSample()
    {
        $this->abstractModel->sample();
    }

    public function actionSave()
    {

        if (Yii::app()->session['isClient']) {
            $values = $this->getAttributesRequest();

            if (isset(Yii::app()->session['idClient']) && Yii::app()->session['idClient']) {
                foreach ($this->fieldsInvisibleClient as $field) {
                    unset($values[$field]);
                }
            }

            if (isset(Yii::app()->session['idAgent']) && Yii::app()->session['idAgent']) {
                foreach ($this->fieldsInvisibleAgent as $field) {
                    unset($values[$field]);
                }
            }

            $modelVoucher = $this->abstractModel->find('voucher= :voucher', array(':voucher' => $values['voucher']));

            if (count($modelVoucher)) {
                $modelVoucher->username = Yii::app()->session['login'];
                $modelVoucher->used     = 1;
                $modelVoucher->used     = date('Y-m-d H:i:s');
                $modelVoucher->save();

                $this->success = true;
                $this->msg     = 'Operacao realizada com successo.';

                UserCreditManager::releaseUserCredit(Yii::app()->session['id_user'], $modelVoucher->credit, 'Voucher ' . $values['voucher']);

            } else {
                $this->success = false;
                $this->msg     = 'Voucher inexistente';
            }

            # retorna o resultado da execucao
            echo json_encode(array(
                $this->nameSuccess => $this->success,
                $this->nameMsg     => $this->msg,
            ));
        } else {

            $values = $this->getAttributesRequest();
            if (isset($values['quantity']) && $values['quantity'] > 1) {
                for ($i = 0; $i < $values['quantity']; $i++) {

                    $voucher                    = $this->geraVoucher();
                    $modelVoucher               = new Voucher();
                    $modelVoucher->id_plan      = $values['id_plan'];
                    $modelVoucher->voucher      = $voucher;
                    $modelVoucher->credit       = $values['credit'];
                    $modelVoucher->tag          = $values['tag'];
                    $modelVoucher->language     = $values['language'];
                    $modelVoucher->prefix_local = $values['prefix_local'];
                    try {
                        $modelVoucher->save();
                    } catch (Exception $e) {
                        //print_r($e);
                    }
                }

                $newRecord = $this->abstractModel->findAll(array(
                    'select' => $this->select,
                    'join'   => $this->join,
                ));

                echo json_encode(array(
                    $this->nameSuccess => true,
                    $this->nameRoot    => $this->getAttributesModels($newRecord, $this->extraValues),
                    $this->nameMsg     => $this->msgSuccess,
                ));
                exit;

            } else {
                parent::actionSave();
            }

        }
    }

    public function getAttributesRequest()
    {

        if (isset($_POST[$this->nameRoot])) {
            $values = json_decode($_POST[$this->nameRoot], true);
            if (isset($values['quantity']) && $values['quantity'] == 1) {
                unset($values['quantity']);
                unset($values['idUserusername']);
                $values['voucher'] = $this->geraVoucher();
            }
        } else {
            $values = array_key_exists($this->nameRoot, $_POST) ? json_decode($_POST[$this->nameRoot], true) : $_POST;
        }

        return $values;
    }

    public function extraFilterCustom($filter)
    {
        if (isset($this->defaultFilterAgent)) {
            if (Yii::app()->session['user_type'] == 1) {
                $filter .= ' AND ' . $this->defaultFilterAgent . ' = :dfby0';
                $this->paramsFilter[':dfby0'] = 1;
            } else if (Yii::app()->session['user_type'] == 2) {
                $filter .= ' AND ' . $this->defaultFilterAgent . ' = :dfby';
                $this->paramsFilter[':dfby'] = Yii::app()->session['id_user'];
            }
        }

        if (Yii::app()->session['user_type'] == 3) {
            $filter .= ' AND t.username = :dfby';
        }

        $this->paramsFilter[':dfby'] = Yii::app()->session['login'];

        return $filter;
    }

    public function geraVoucher()
    {
        $existsVoucher = true;
        while ($existsVoucher) {
            $randVoucher = Util::generatePassword(6, false, false, true, false);
            $sql         = "SELECT count(id) FROM pkg_voucher WHERE voucher LIKE :randVoucher
                OR (SELECT count(id) FROM pkg_user WHERE callingcard_pin LIKE :randVoucher) > 0";
            $command = Yii::app()->db->createCommand($sql);
            $command->bindValue(":randVoucher", $randVoucher, PDO::PARAM_STR);
            $countVoucher = $command->queryAll();

            if (count($countVoucher) > 0) {
                $existsVoucher = false;
                break;
            }
        }

        return $randVoucher;
    }
}
