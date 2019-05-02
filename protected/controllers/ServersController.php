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

class ServersController extends Controller
{
    public $attributeOrder = 't.id';

    public function init()
    {
        $this->instanceModel = new Servers;
        $this->abstractModel = Servers::model();
        $this->titleReport   = Yii::t('yii', 'Callerid');
        parent::init();
    }

    public function afterSave($model, $values)
    {

        $modelServer = Servers::model()->findAll("type = 'sipproxy' AND status = 1");
        foreach ($modelServer as $key => $server) {

            $hostname = $server->host;
            $dbname   = 'opensips';
            $table    = 'dispatcher';
            $user     = $server->username;
            $password = $server->password;
            $port     = $server->port;

            $dsn = 'mysql:host=' . $hostname . ';dbname=' . $dbname;

            try {
                $con = new CDbConnection($dsn, $user, $password);
            } catch (Exception $e) {
                return;
            }

            $con->active = true;

            $sql = "TRUNCATE $dbname.$table";
            $con->createCommand($sql)->execute();

            $modelServerAS = Servers::model()->findAll("(type = 'asterisk' OR type = 'mbilling')
                        AND status = 1 AND weight > 0");

            foreach ($modelServerAS as $key => $server) {
                $sql = "INSERT INTO $dbname.$table (setid,destination,weight,description) VALUES ('1','sip:" . $server->host . ":" . $server->sip_port . "','" . $server->weight . "','" . $server->description . "')";
                $con->createCommand($sql)->execute();

            }
        }
    }

}
