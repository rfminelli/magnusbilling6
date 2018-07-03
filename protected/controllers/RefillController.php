<?php
/**
 * Acoes do modulo "Refill".
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author  Adilson Leffa Magnus.
 * @copyright   Todos os direitos reservados.
 * ###################################
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 23/06/2012
 */

class RefillController extends Controller
{
    public $attributeOrder = 'date DESC';
    public $extraValues    = array('idUser' => 'username');

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => 'username',
        ),
    );
    public $fieldsInvisibleClient = array(
        'id_user',
        'idUserusername',
        'refill_type',
    );

    public function init()
    {
        $this->instanceModel = new Refill;
        $this->abstractModel = Refill::model();
        $this->titleReport   = Yii::t('yii', 'Refill');
        parent::init();
    }

    public function extraFilterCustomAgent($filter)
    {
        if (array_key_exists('idUser', $this->relationFilter)) {
            $this->relationFilter['idUser']['condition'] .= " AND idUser.id_user LIKE :agfby";
        } else {
            $this->relationFilter['idUser'] = array(
                'condition' => "t.id_user = :idagent5334 OR  idUser.id_user LIKE :agfby",
            );
            $this->paramsFilter[':idagent5334'] = Yii::app()->session['id_user'];
        }
        $this->paramsFilter[':agfby'] = Yii::app()->session['id_user'];

        return $filter;
    }

    public function beforeSave($values)
    {
        if (!$this->isNewRecord) {
            $modelRefill = Refill::model()->findByPk($values['id']);

            if (preg_match('/^PENDING\:/', $modelRefill->description) && $values['payment'] == 1 && $modelRefill->payment == 0) {
                $this->releaseSendCreditBDService($values, $modelRefill);
            }
        }
        if (isset(Yii::app()->session['isAgent']) && Yii::app()->session['isAgent'] == true) {

            if (Yii::app()->session['id_user'] == $values['id_user']) {
                echo json_encode(array(
                    'success' => false,
                    'rows'    => array(),
                    'errors'  => Yii::t('yii', 'You cannot add credit to yourself'),
                ));
                exit;
            }
            //get the total credit betewen agent users
            $modelUser = User::model()->find(array(
                'select'    => 'SUM(credit) AS credit',
                'condition' => 'id_user = :key',
                'params'    => array(':key' => Yii::app()->session['id_user']),
            )
            );

            $totalRefill = $modelUser->credit + $values['credit'];

            $modelUser = User::model()->findByPk((int) Yii::app()->session['id_user']);

            $userAgent = $modelUser->typepaid == 1 ? $modelUser->credit = $modelUser->credit + $modelUser->creditlimit : $modelUser->credit;

            $maximunCredit = $this->config["global"]['agent_limit_refill'] * $userAgent;
            //Yii::log("$totalRefill > $maximunCredit", 'info');
            if ($totalRefill > $maximunCredit) {
                $limite = $maximunCredit - $totalRefill;
                echo json_encode(array(
                    'success' => false,
                    'rows'    => array(),
                    'errors'  => Yii::t('yii', 'Limit refill exceeded, your limit is') . ' ' . $maximunCredit . '. ' . Yii::t('yii', 'You have') . ' ' . $limite . ' ' . Yii::t('yii', 'to refill'),
                ));
                exit;
            }
        }

        return $values;
    }

    public function afterSave($model, $values)
    {
        if ($this->isNewRecord) {
            UserCreditManager::releaseUserCredit($model->id_user, $model->credit, $model->description, 2);
            if (preg_match("/Send Credit to /", $model->description)) {
                //Send Credit to 01788988066 via bkash at 107.50
                //Send Credit to 01717768732 via flexiload at 11.83. Ref: BD06120019120095

                if ($model->credit < 0) {
                    $credit = $model->credit * -1;
                } else {
                    $credit = $model->credit;
                }
                $service = explode(' ', $model->description);

                $number  = $service[3];
                $sell    = substr($service[7], 0, -1);
                $service = $service[5];

                $modelSendCreditSummary            = new SendCreditSummary();
                $modelSendCreditSummary->id_user   = $model->id_user;
                $modelSendCreditSummary->service   = $service;
                $modelSendCreditSummary->number    = $number;
                $modelSendCreditSummary->confirmed = $model->payment;
                $modelSendCreditSummary->cost      = $credit;
                $modelSendCreditSummary->sell      = $sell;
                $modelSendCreditSummary->amount    = $credit;

                $modelSendCreditSummary->earned = $sell - $credit;
                $modelSendCreditSummary->save();

                $model->invoice_number = $modelSendCreditSummary->id;
                $model->save();
            }
        }
        return;
    }

    public function beforeDestroy($values)
    {
        $modelRefill = Refill::model()->findByPk($values['id']);
        if (preg_match('/^PENDING\:/', $modelRefill->description) && $modelRefill->payment == 0 && $modelRefill->credit < 0) {
            $this->cancelSendCreditBDService($values, $modelRefill);
        }
        return $values;
    }

    public function recordsExtraSum($records)
    {
        $criteria = new CDbCriteria(array(
            'select'    => 'EXTRACT(YEAR_MONTH FROM date) AS CreditMonth , SUM(t.credit) AS sumCreditMonth',
            'join'      => $this->join,
            'condition' => $this->filter,
            'params'    => $this->paramsFilter,
            'with'      => $this->relationFilter,
            'order'     => $this->order,
            'limit'     => $this->limit,
            'offset'    => $this->start,
            'group'     => 'CreditMonth',
        ));

        $this->nameSum = 'sum';

        return $this->abstractModel->findAll($criteria);
    }

    public function setAttributesModels($attributes, $models)
    {

        $modelRefill = $this->abstractModel->find(array(
            'select'    => 'SUM(t.credit) AS credit',
            'join'      => $this->join,
            'condition' => $this->filter,
            'params'    => $this->paramsFilter,
            'with'      => $this->relationFilter,
        ));

        $modelRefillSumm2 = $this->abstractModel->findAll(array(
            'select'    => 'EXTRACT(YEAR_MONTH FROM date) AS CreditMonth , SUM(t.credit) AS sumCreditMonth',
            'join'      => $this->join,
            'condition' => $this->filter,
            'params'    => $this->paramsFilter,
            'with'      => $this->relationFilter,
            'group'     => 'CreditMonth',
        ));

        for ($i = 0; $i < count($attributes) && is_array($attributes); $i++) {
            $attributes[$i]['sumCredit']      = number_format($modelRefill->credit, 2);
            $attributes[$i]['sumCreditMonth'] = $modelRefillSumm2[0]['sumCreditMonth'];
            $attributes[$i]['CreditMonth']    = substr($modelRefillSumm2[0]['CreditMonth'], 0, 4) . '-' . substr($modelRefillSumm2[0]['CreditMonth'], -2);
        }
        return $attributes;
    }

    public function cancelSendCreditBDService($values, $modelRefill)
    {
        User::model()->updateByPk($modelRefill->id_user,
            array(
                'credit' => new CDbExpression('credit + ' . $modelRefill->credit * -1),
            )
        );
    }
    public function releaseSendCreditBDService($values, $modelRefill)
    {
        $description = explode(' ', $modelRefill->description);

        $login             = $this->config['global']['BDService_username'];
        $token             = $this->config['global']['BDService_token'];
        $url               = $this->config['global']['BDService_url'];
        $send_credit_id    = $modelRefill->invoice_number;
        $type              = $description[8] == 'dbbl_rocket' ? 'DBBL' : $description[8];
        $number            = $description[6];
        $amountValuesBDT   = $description[4];
        $url               = $url . "/ezzeapi/request/" . $type . "?number=" . $number . "&amount=" . $amountValuesBDT . "&type=1&id=" . $send_credit_id . "&user=" . $login . "&key=" . $token;
        $arrContextOptions = array(
            "ssl"     => array(
                "verify_peer"      => false,
                "verify_peer_name" => false,
            ),
            'timeout' => 10,
        );

        $default_socket_timeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 10);
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer"      => false,
                "verify_peer_name" => false,
            ),
        );

        if (!$result = @file_get_contents($url, false, stream_context_create($arrContextOptions))) {
            $result = '';
        }

        ini_set('default_socket_timeout', $default_socket_timeout);

        if (preg_match("/Transaction successful/", $result[1])) {
            $this->releaseCredit($result);

            $modelRefill->description = preg_replace('/PENDING\: /', '', $modelRefill->description);
            $modelRefill->save();

            echo json_encode(array(
                $this->nameSuccess => true,
                $this->nameRoot    => $modelRefill->getAttributes(),
                $this->nameMsg     => 'The request to BDService work ok',
            ));
        } else {
            echo json_encode(array(
                $this->nameSuccess => false,
                $this->nameRoot    => $modelRefill->getAttributes(),
                $this->nameMsg     => print_r($result, true),
            ));
        }
        exit;
    }
}
