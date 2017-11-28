<?php
/**
 * Acoes do modulo "Sip".
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Todos os direitos reservados.
 * ###################################
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 * 23/06/2012
 */

class SipController extends Controller
{
    public $attributeOrder = 't.id DESC';
    public $extraValues    = array('idUser' => 'username');

    private $sipShowPeers = array();

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => 'username',
        ),
    );

    public function init()
    {
        $this->instanceModel = new Sip;
        $this->abstractModel = Sip::model();
        parent::init();
    }

    public function actionRead($asJson = true, $condition = null)
    {
        $this->sipShowPeers = AsteriskAccess::getSipShowPeers();
        parent::actionRead($asJson = true, $condition = null);
    }

    public function replaceOrder()
    {
        $this->order = preg_replace("/lineStatus/", 'id', $this->order);
        parent::replaceOrder();
    }

    public function removeColumns($columns)
    {
        //remove listatus columns
        for ($i = 0; $i < count($columns); $i++) {
            if ($columns[$i]['dataIndex'] == 'lineStatus') {
                unset($columns[$i]);
            }

        }
        return $columns;
    }

    public function beforeSave($values)
    {

        if ($this->isNewRecord) {

            $modelUser = User::model()->findByPk((int) $values['id_user']);

            $modelSipCount = Sip::model()->count("id_user = :id_user", array(':id_user' => (int) $values['id_user']));

            if (!Yii::app()->session['isAdmin'] && $modelUser->sipaccountlimit > 0
                && $modelSipCount >= $modelUser->sipaccountlimit) {
                echo json_encode(array(
                    'success' => false,
                    'rows'    => array(),
                    'errors'  => 'Limit sip acount exceeded',
                ));
                exit;
            }
            $values['accountcode'] = $modelUser->username;
            $values['regseconds']  = 1;
            $values['context']     = 'billing';
            $values['regexten']    = $values['name'];
            if (!$values['callerid']) {
                $values['callerid'] = $values['name'];
            }

        }

        if (isset($values['id_user'])) {
            $modelUser             = User::model()->findByPk((int) $values['id_user']);
            $values['accountcode'] = $modelUser->username;
        }

        if (isset($values['defaultuser'])) {
            $values['name'] = $values['defaultuser'] == '' ? $values['accountcode'] : $values['defaultuser'];
        }

        if (isset($values['callerid'])) {
            $values['cid_number'] = $values['callerid'];
        }

        if (isset($value['allow'])) {
            $values['allow'] = preg_replace("/,0/", "", $values['allow']);
            $values['allow'] = preg_replace("/0,/", "", $values['allow']);
        }
        return $values;
    }

    public function afterSave($model, $values)
    {

        AsteriskAccess::instance()->generateSipPeers();
        $this->siproxyServer($model, 'save');

        return;
    }

    public function afterDestroy($values)
    {
        AsteriskAccess::instance()->generateSipPeers();
        $this->siproxyServer($values, 'destroy');
        return;
    }

    public function siproxyServer($values, $type)
    {

        $modelServers = Servers::model()->findAll("type = 'sipproxy' AND status = 1");

        foreach ($modelServers as $key => $server) {

            $hostname = $server->host;
            $dbname   = 'opensips';
            $table    = 'subscriber';
            $user     = $server->username;
            $password = $server->password;
            $port     = $server->port;

            $dsn = 'mysql:host=' . $hostname . ';dbname=' . $dbname;

            $con         = new CDbConnection($dsn, $user, $password);
            $con->active = true;

            if ($type == 'destroy') {
                //delete the deletes users on Sipproxy server
                for ($i = 0; $i < count($values); $i++) {
                    $modelSip = Sip::model()->findByPk((int) $values[$i]['id']);
                    $sql      = "DELETE FROM $dbname.$table WHERE username = '" . $modelSip->name . "'";
                    $con->createCommand($sql)->execute();
                }
            } elseif ($type == 'save') {
                if ($this->isNewRecord) {
                    $sql = "INSERT INTO $dbname.$table (username,domain,ha1,accountcode) VALUES
                            ('$values->defaultuser,'$hostname','" . md5($values->defaultuser . ':' . $hostname . ':' . $values->secret) . "','$values->accountcode')";
                    $con->createCommand($sql)->execute();
                } else {
                    $sql = "UPDATE $dbname.$table SET ha1 = '" . md5($values->defaultuser . ':' . $hostname . ':' . $values->secret) . "',
                            username = '$values->defaultuser' WHERE username = '$values->defaultuser'";
                    $con->createCommand($sql)->execute();
                }
            }
        }
    }

    public function setAttributesModels($attributes, $models)
    {

        for ($i = 0; $i < count($attributes) && is_array($attributes); $i++) {
            $attributes[$i]['lineStatus'] = 'unregistered';
            foreach ($this->sipShowPeers as $value) {

                if (strtok($value['Name/username'], '/') == $attributes[$i]['name']) {
                    $attributes[$i]['lineStatus'] = $value['Status'];
                }
            }
        }
        return $attributes;
    }
}
