<?php
/**
 * Modelo para a tabela "Boleto".
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v3
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 19/09/2012
 */

class User extends Model
{
    protected $_module     = 'user';
    protected $newPassword = null;
    /**
     * Return the static class of model.
     *
     * @return User classe estatica da model.
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     *
     *
     * @return name of table.
     */
    public function tableName()
    {
        return 'pkg_user';
    }

    /**
     *
     *
     * @return name of primary key(s).
     */
    public function primaryKey()
    {
        return 'id';
    }

    /**
     *
     *
     * @return array validation of fields of model.
     */
    public function rules()
    {
        return array(
            array('username, password', 'required'),
            array('id_user, id_group, id_plan, id_offer, active, enableexpire, expiredays,
                    typepaid, creditlimit, credit_notification,sipaccountlimit, restriction,
                    callingcard_pin, callshop, plan_day, record_call, active_paypal, boleto,
                    boleto_day, calllimit, disk_space,id_group_agent,transfer_dbbl_rocke_profit,
                    transfer_bkash_profit,transfer_flexiload_profit,transfer_international_profit,
                    transfer_dbbl_rocke,transfer_bkash,transfer_flexiload,transfer_international,
                    transfer_bdservice_rate,transfer_show_selling_price
                        ', 'numerical', 'integerOnly' => true),
            array('language,mix_monitor_format', 'length', 'max' => 5),
            array('username, zipcode, phone, mobile, vat', 'length', 'max' => 20),
            array('city, state, country, loginkey', 'length', 'max' => 40),
            array('lastname, firstname, company_name, redial, prefix_local', 'length', 'max' => 50),
            array('company_website', 'length', 'max' => 60),
            array('address, email, description, doc', 'length', 'max' => 100),
            array('credit', 'type', 'type' => 'double'),
            array('expirationdate, password, lastuse', 'length', 'max' => 100),
            array('username', 'checkusername'),
            array('password', 'checksecret'),
            array('username', 'unique', 'caseSensitive' => 'false'),

        );
    }

    public function checkusername($attribute, $params)
    {
        if (preg_match('/ /', $this->username)) {
            $this->addError($attribute, Yii::t('yii', 'No space allow in username'));
        }

        if (!preg_match('/^[0-9]|^[A-Z]|^[a-z]/', $this->username)) {
            $this->addError($attribute, Yii::t('yii', 'Username need start with numbers or letters'));
        }

    }

    public function checksecret($attribute, $params)
    {
        if (preg_match('/ /', $this->password)) {
            $this->addError($attribute, Yii::t('yii', 'No space allow in password'));
        }

        if ($this->password == '123456' || $this->password == '12345678' || $this->password == '012345') {
            $this->addError($attribute, Yii::t('yii', 'No use sequence in the pasword'));
        }

        if ($this->password == $this->username) {
            $this->addError($attribute, Yii::t('yii', 'Password cannot be equal username'));
        }

    }

    public function relations()
    {
        return array(
            'idGroup' => array(self::BELONGS_TO, 'GroupUser', 'id_group'),
            'idPlan'  => array(self::BELONGS_TO, 'Plan', 'id_plan'),
            'idUser'  => array(self::BELONGS_TO, 'User', 'id_user'),
        );
    }
}
