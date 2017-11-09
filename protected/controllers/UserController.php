<?php
/**
 * Actions of module "User".
 *
 * MagnusBilling <info@magnusbilling.com>
 * 15/04/2013
 */

class UserController extends Controller
{

    public $attributeOrder = 't.credit DESC';
    public $titleReport    = 'User';
    public $subTitleReport = 'User';

    public $extraValues   = array('idGroup' => 'name,id_user_type', 'idPlan' => 'name', 'idUser' => 'username');
    public $nameFkRelated = 'idUser';

    public $fieldsFkReport = array(
        'id_group' => array(
            'table'       => 'pkg_group_user',
            'pk'          => 'id',
            'fieldReport' => 'name',
        ),
        'id_plan'  => array(
            'table'       => 'pkg_plan',
            'pk'          => 'id',
            'fieldReport' => 'name',
        ),
        'id_user'  => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => 'username',
        ),
    );

    public $fieldsInvisibleClient = array(
        'active_paypal',
        'boleto',
        'boleto_day',
        'callshop',
        'creditlimit',
        'currency',
        'description',
        'enableexpire',
        'expirationdate',
        'expiredays',
        'firstusedate',
        'id_group',
        'idGroupname',
        'id_offer',
        'id_user',
        'id_plan',
        'idAgentlogin',
        'creationdate',
        'lastuse',
        'typepaid',
        'loginkey',
        'last_notification',
        'restriction',
        'plan_day',
        'record_call',
        'idGroupid_user_type',
        'idPlanname',
    );
    public $fieldsInvisibleAgent = array(
        'id_group',
        'idGroupname',
        'enableexpire',
        'expirationdate',
        'record_call',
        'id_offer',
        'loginkey',
    );

    public function init()
    {
        $this->instanceModel = new User;
        $this->abstractModel = User::model();
        parent::init();
    }

    public function applyFilterToLimitedAdmin()
    {
        if (Yii::app()->session['user_type'] == 1 && Yii::app()->session['adminLimitUsers'] == true) {
            $this->filter .= " AND t.id_group IN (SELECT gug.id_group
                                FROM pkg_group_user_group gug
                                WHERE gug.id_group_user = :idgA0)";
            $this->paramsFilter['idgA0'] = Yii::app()->session['id_group'];
        }
    }

    public function checkAgentEdit($values)
    {
        //not allow agent edit his account.
        if (Yii::app()->session['isAgent'] && !$this->isNewRecord && Yii::app()->session['id_user'] == $values['id']) {
            echo json_encode(array(
                'success' => false,
                'rows'    => array(),
                'errors'  => Yii::t('yii', 'You cannot EDIT your account.'),
            ));
            exit();

        }
    }

    public function beforeDestroy($values)
    {
        $this->checkAgentEdit($values);
        return $values;
    }

    public function beforeSave($values)
    {

        if (isset($values['id_group_agent'])) {
            if (Yii::app()->session['user_type'] == 1 && $values['id_group_agent'] > 0) {

                $modelGroupUser = GroupUser::model()->find('id_user_type = 3 AND id = :key',
                    array(':key' => $values['id_group_agent'])
                );

                if (count($modelGroupUser) == 0) {
                    echo json_encode(array(
                        'success' => false,
                        'rows'    => array(),
                        'errors'  => Yii::t('yii', 'Group no allow for Agent users'),
                    ));
                    exit();
                }
            }
        }

        $methodModel = Methodpay::Model()->findAll(
            "payment_method=:field1 AND active=:field12",
            array(
                "field1"  => 'SuperLogica',
                "field12" => '1',
            )
        );

        $values = $this->superLogica($methodModel, $values);

        if (Yii::app()->session['isAgent']) {
            //get the group id_group_agent
            $modelUser          = User::model()->findByPk((int) Yii::app()->session['id_user']);
            $values['id_group'] = $modelUser->id_group_agent;

            $this->checkAgentEdit($values);
        }

        if ($this->isNewRecord) {

            $groupType = GroupUser::model()->find(
                "id=:field1",
                array(
                    'field1' => $values['id_group'],
                )
            );
            $idUserType = $groupType->id_user_type;

            if (Yii::app()->session['isAdmin'] == true && $idUserType == 1) {
                $values['password'] = sha1($values['password']);
            }

            if (count($methodModel) > 0 && $idUserType == 3) {

                if (strlen($values['lastname']) < 5) {
                    $error = Yii::t('yii', 'lastname');
                } else if (strlen($values['firstname']) < 5) {
                    $error = Yii::t('yii', 'firstname');
                } else if (strlen($values['doc']) < 11) {
                    $error = Yii::t('yii', 'CPF');
                } else if (!preg_match('/^[^0-9][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[@][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[.][a-zA-Z]{2,4}$/', $values['email'])) {
                    $error = Yii::t('yii', 'email');
                }
                if (isset($error)) {
                    echo json_encode(array(
                        'success' => false,
                        'rows'    => array(),
                        'errors'  => Yii::t('yii', $error) . ' ' . Yii::t('yii', 'is required'),
                    ));
                    exit();
                }

            }

            if (Yii::app()->session['user_type'] == 2) {
                $values['id_user'] = Yii::app()->session['id_user'];

                $modelAgent = User::model()->findByPk((int) $values['id_user']);

                $values['id_group'] = $modelAgent->id_group_agent;

            } else {
                $values['id_user'] = 1;
            }

        } else {

            $modelUser = User::model()->findByPk((int) $values['id']);

            $idUserTypeOld = $modelUser->idGroup->idUserType->id;

            if (isset($idUserTypeOld) && $idUserTypeOld == 1 && isset($values['password'])) {
                MagnusLog::insertLOG(2, 'User try change the password');

                echo json_encode(array(
                    'success' => false,
                    'rows'    => array(),
                    'errors'  => Yii::t('yii', 'You are not allowed to edit this field'),
                ));
                exit;
            }

            if (isset($idUserType) && $idUserType != $idUserTypeOld) {
                MagnusLog::insertLOG(2, 'User try change the group');
                echo json_encode(array(
                    'success' => false,
                    'rows'    => array(),
                    'errors'  => Yii::t('yii', 'You cannot change user type group'),
                ));
                exit;
            }
        }
        if (isset($values['id_plan'])) {
            $values['id_plan'] = $values['id_plan'] < 1 ? null : $values['id_plan'];
        }

        if (isset($values['id_group_agent'])) {
            $values['id_group_agent'] = $values['id_group_agent'] == 0 || !is_numeric($values['id_group_agent'])
            ? null :
            $values['id_group_agent'];
        }

        if (isset($values['id_offer'])) {
            $values['id_offer'] = $values['id_offer'] === 0 ? null : $values['id_offer'];
        }

        if (isset($values['enableexpire']) && $values['enableexpire'] == 0) {
            $values['expirationdate'] = '0000-00-00 00:00:00';
        }

        return $values;
    }

    public function afterSave($model, $values)
    {
        if ($model->idGroup->idUserType->id == 3) {
            $modelSip = $this->isNewRecord ?
            new Sip() :
            Sip::model()->findByAttributes(array('id_user' => $model->id));

            if ($this->isNewRecord || isset($modelSip->id_user)) {
                $modelSip->id_user     = $model->id;
                $modelSip->accountcode = $model->username;
                if ($this->isNewRecord) {
                    $modelSip->name         = $model->username;
                    $modelSip->allow        = 'gsm,alaw,ulaw';
                    $modelSip->host         = 'dynamic';
                    $modelSip->insecure     = 'no';
                    $modelSip->defaultuser  = $model->username;
                    $modelSip->secret       = $model->password;
                    $modelSip->videosupport = 'no';
                    $modelSip->context      = 'billing';
                }

                $modelSip->save();
            }

            AsteriskAccess::instance()->generateSipPeers();
        }

        if (!$this->isNewRecord && isset($model->id_group_agent) && $model->id_group_agent > 1) {
            $modelUser = User::model()->find("id_user = :key", array(':key' => $model->id));
            if (count($modelUser)) {
                $modelUser->id_group = $model->id_group_agent;
                $modelUser->save();
            }
        }

        $modelOfferUse = OfferUse::model()->findAll(
            "id_user = :id_user AND releasedate = '0000-00-00 00:00:00' AND status = 1 ",
            array(
                ':id_user' => $model->id,
            )
        );

        if ($model->id_offer > 0) {
            //if exists a offer to this user, disable that to add the new.
            if (count($modelOfferUse) > 0) {
                $modelOfferUse->releasedate = date('Y-m-d H:i:s');
                $modelOfferUse->status      = 0;
                $modelOfferUse->save();
            }

            $modelOfferUse              = new OfferUse();
            $modelOfferUse->id_user     = $model->id;
            $modelOfferUse->id_offer    = $model->id_offer;
            $modelOfferUse->status      = 1;
            $modelOfferUse->month_payed = 1;
            $modelOfferUse->save();
        } else if ($model->id_offer == 0 and count($modelOfferUse)) {

            $modelOfferUse->releasedate = date('Y-m-d H:i:s');
            $modelOfferUse->status      = 0;
            $modelOfferUse->save();
        }

        if ($model->idGroup->idUserType->id == 3) {
            $this->createCallshopRates($model, $values);
        }

        return;
    }

    public function createCallshopRates($model, $values)
    {

        if ($model->callshop == 0) {

            $modelRateCallshop = RateCallshop::model()->deleteAll('id_user = :id_user',
                array(':id_user' => $model->id)
            );
        } elseif ($model->callshop == 1) {

            $modelRateCallshop = RateCallshop::model()->findAll('id_user = :id_user',
                array(':id_user' => $model->id)
            );
            if (count($modelRateCallshop) == 0) {
                RateCallshop::model()->createCallShopRates($model);
            }

        }
    }

    public function superLogica($methodModel, $values)
    {
        if ($this->isNewRecord) {
            if (isset($methodModel[0]->SLAppToken)) {
                $response = SLUserSave::saveUserSLCurl($this, $methodModel[0]->SLAppToken
                    , $methodModel[0]->SLAccessToken);
                $values['id_sacado_sac'] = $response[0]->data->id_sacado_sac;
            }
        } else {
            if (isset($methodModel[0]->SLAppToken)) {
                $response = SLUserSave::saveUserSLCurl($this, $methodModel[0]->SLAppToken
                    , $methodModel[0]->SLAccessToken, false);
            }
        }

        return $values;
    }

    public function actionCredit()
    {
        if (!Yii::app()->session['id_user']) {
            exit();
        }

        $modelUser = $this->abstractModel->findByPk((int) $_POST['id']);
        $credit    = array('rows' => array('credit' => $modelUser->credit));

        echo json_encode($credit);
    }

    public function extraFilterCustomClient($filter)
    {
        //se for cliente filtrar pelo pkg_user.id
        $filter .= ' AND t.id = :dfby';
        $this->paramsFilter[':dfby'] = Yii::app()->session['id_user'];

        return $filter;
    }

    public function extraFilterCustomAgent($filter)
    {
        //se é agente filtrar pelo t.id_user e t.id
        $this->join .= ' JOIN pkg_user user ON t.id_user = user.id ';
        $filter .= ' AND ( t.id_user = :agfby OR t.id = :agfby)';
        $this->paramsFilter[':agfby'] = Yii::app()->session['id_user'];

        return $filter;
    }

    public function actionGetNewUsername()
    {
        echo json_encode(array(
            $this->nameSuccess => true,
            'newUsername'      => Util::getNewUsername(),
        ));
    }

    public function actionGetNewPassword()
    {
        echo json_encode(array(
            $this->nameSuccess => true,
            'newPassword'      => Util::generatePassword(8, true, true, true, false),
        ));
    }

    public function actionGetNewPinCallingcard()
    {
        echo json_encode(array(
            $this->nameSuccess  => true,
            'newCallingcardPin' => Util::generatePinCallingcard(),
        ));
    }

    public function actionBulk()
    {
        $values = $this->getAttributesRequest();

        if (Yii::app()->session['user_type'] == 2) {
            $id_user = Yii::app()->getSession()->get('id_user');

            $sql     = "SELECT id_group_agent FROM pkg_user WHERE id = :id";
            $command = Yii::app()->db->createCommand($sql);
            $command->bindValue(":id", $id_user, PDO::PARAM_INT);
            $result = $command->queryAll();

            $values['id_group'] = $result[0]['id_group_agent'];
        } else {
            $id_user = 1;
        }

        $modelGroupUser = GroupUser::model()->findByPk((int) $values['id_group']);

        if ($modelGroupUser->id_user_type != 3) {
            echo json_encode(array(
                $this->nameSuccess => false,
                $this->nameMsg     => 'Only allowed create user. you try create admin or agent',
            ));
            exit;
        }
        for ($i = 0; $i < $values['totalToCreate']; $i++) {

            $modelUser                  = new User();
            $modelUser->username        = Util::getNewUsername();
            $modelUser->password        = Util::generatePassword(8, true, true, true, false);
            $modelUser->callingcard_pin = Util::generatePinCallingcard();
            $modelUser->id_group        = $values['id_group'];
            $modelUser->language        = $values['language'];
            $modelUser->id_plan         = $values['id_plan'];
            $modelUser->active          = $values['active'];
            $modelUser->id_user         = $id_user;
            $modelUser->credit          = $values['credit'] > 0 ? $values['credit'] : 0;
            $modelUser->save();

            if ($values['credit'] > 0) {
                $modelRefill              = new Refill();
                $modelRefill->id_user     = $modelUser->id;
                $modelRefill->payment     = 1;
                $modelRefill->credit      = $values['credit'];
                $modelRefill->description = Yii::t('yii', 'Automatic credit');
                $modelRefill->save();
            }

        }

        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameMsg     => $this->msgSuccess,
        ));
    }
}
