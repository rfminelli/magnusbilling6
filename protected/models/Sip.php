<?php
/**
 * Modelo para a tabela "Sip".
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

class Sip extends Model
{
    protected $_module = 'sip';
    private $lineStatus;
    /**
     * Retorna a classe estatica da model.
     *
     * @return Sip classe estatica da model.
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     *
     *
     * @return nome da tabela.
     */
    public function tableName()
    {
        return 'pkg_sip';
    }

    /**
     *
     *
     * @return nome da(s) chave(s) primaria(s).
     */
    public function primaryKey()
    {
        return 'id';
    }

    /**
     *
     *
     * @return array validacao dos campos da model.
     */
    public function rules()
    {
        return array(
            array('id_user', 'required'),
            array('id_user, calllimit, ringfalse, record_call, voicemail', 'numerical', 'integerOnly' => true),
            array('name, callerid, context, fromuser, fromdomain, md5secret, secret, fullcontact', 'length', 'max' => 80),
            array('regexten, insecure, regserver, vmexten, callingpres, mohsuggest, allowtransfer', 'length', 'max' => 20),
            array('amaflags, dtmfmode, qualify', 'length', 'max' => 7),
            array('callgroup, pickupgroup, auth, subscribemwi, usereqphone, autoframing', 'length', 'max' => 10),
            array('DEFAULTip, ipaddr, maxcallbitrate, rtpkeepalive', 'length', 'max' => 15),
            array('nat, host', 'length', 'max' => 31),
            array('language', 'length', 'max' => 2),
            array('mailbox,forward', 'length', 'max' => 50),
            array('accountcode, group', 'length', 'max' => 30),
            array('rtptimeout, rtpholdtimeout,videosupport', 'length', 'max' => 3),
            array('deny, permit', 'length', 'max' => 95),
            array('type', 'length', 'max' => 6),
            array('disallow, allow, setvar, useragent', 'length', 'max' => 100),
            array('lastms, directmedia', 'length', 'max' => 11),
            array('defaultuser, cid_number, outboundproxy, sippasswd', 'length', 'max' => 40),
            array('defaultuser', 'checkusername'),
            array('secret', 'checksecret'),
            array('defaultuser', 'unique', 'caseSensitive' => 'false'),
        );
    }

    public function checkusername($attribute, $params)
    {
        if (preg_match('/ /', $this->defaultuser)) {
            $this->addError($attribute, Yii::t('yii', 'No space allow in defaultuser'));
        }

    }
    public function checksecret($attribute, $params)
    {
        if (preg_match('/ /', $this->secret)) {
            $this->addError($attribute, Yii::t('yii', 'No space allow in password'));
        }

        if ($this->secret == '123456' || $this->secret == '12345678' || $this->secret == '012345') {
            $this->addError($attribute, Yii::t('yii', 'No use sequence in the pasword'));
        }

        if ($this->secret == $this->defaultuser) {
            $this->addError($attribute, Yii::t('yii', 'Password cannot be equal username'));
        }

    }
    /*
     * @return array regras de relacionamento.
     */
    public function relations()
    {
        return array(
            'idUser' => array(self::BELONGS_TO, 'User', 'id_user'),
        );
    }
}
