<?php
/**
 * Acoes do modulo "CallShop".
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
 * MagnusSolution.com <info@magnussolution.com>
 * 19/09/2012
 */

class CallShopController extends Controller
{
    public $attributeOrder = 't.callerid';
    public $extraValues    = array('idUser' => 'username');
    public $join           = ' INNER JOIN pkg_user c ON t.id_user = c.id';
    public $defaultFilter  = 'c.callshop = 1';
    private $host          = 'localhost';
    private $user          = 'magnus';
    private $l;
    private $password = 'magnussolution';

    public function init()
    {
        $this->instanceModel = new CallShop;
        $this->abstractModel = CallShop::model();
        $this->titleReport   = Yii::t('yii', 'CallShop');
        $this->l             = Yii::app()->session['licence'];
        parent::init();
    }

    public function actionRead($asJson = true, $condition = null)
    {
        $this->asteriskCommand();
        return parent::actionRead($asJson = true, $condition = null);
    }

    public function actionLiberar()
    {
        if (isset($_GET['name'])) {
            $filter[0]['value'] = $_GET['name'];
        } else {
            $filter = json_decode($_POST['filter'], true);
        }

        $modelSip         = Sip::model()->find("name = :name ", array(':name' => $filter[0]['value']));
        $modelSip->status = 2;
        $modelSip->save();

        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameMsg     => $this->msgSuccess,
        ));

    }

    public function actionCobrar()
    {
        if (isset($_GET['name'])) {
            $filter[0]['value'] = $_GET['name'];
        } else {
            $filter = json_decode($_POST['filter'], true);
        }

        $modelSip                 = Sip::model()->find("name = :name ", array(':name' => $filter[0]['value']));
        $modelSip->status         = 0;
        $modelSip->callshopnumber = 'NULL';
        $modelSip->callshoptime   = 0;
        $modelSip->save();

        CallShopCdr::model()->updateAll(array('status' => '1'), 'cabina = :key', array(':key' => $filter[0]['value']));

        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameMsg     => $this->msgSuccess,
        ));
    }

    public function asteriskCommand()
    {
        $sql    = "DELETE FROM pkg_callshop WHERE status = 3 ";
        $result = Yii::app()->db->createCommand($sql)->execute();

        $sql = "UPDATE pkg_sip SET status = 2 WHERE status = 3 ";
        Yii::app()->db->createCommand($sql)->execute();

        $calls = AsteriskAccess::getCoreShowChannels();

        if (count($calls)) {
            $sql = array();
            foreach ($calls as $key => $call) {

                if (!isset($call[1])) {
                    continue;
                }

                $canal = $call[0];

                $username = isset($call[8]) ? $call[8] : 0;

                if (!$canal) {
                    continue;
                }
                $ndiscado = $call[2];

                $status = $call[4];

                $ramal = explode('-', $canal);
                $ramal = explode('/', $ramal[0]);
                $ramal = isset($ramal[1]) ? $ramal[1] : null;

                $seconds = $call[11];

                $ramal = isset($ramal) ? $ramal : null;

                $resultUser = User::model()->find(array(
                    'select'    => 'id, callshop',
                    'condition' => "username = '" . $username . "'",
                ));

                $id_user = isset($resultUser->id) ? $resultUser->id : false;

                $status = explode(" ", (string) $status);

                if (preg_match("/billing/", $call[1]) && isset($ndiscado) && $ndiscado != '(N/A)' && $status != 'Down' && is_numeric($ndiscado) && !is_null($ramal)) {

                    if (isset($resultUser->callshop) && $resultUser->callshop == 1) {

                        $sql = "UPDATE pkg_sip SET status = 3, callshopnumber = :ndiscado, callshoptime = :seconds
                                            WHERE name = :name ";
                        $command = Yii::app()->db->createCommand($sql);
                        $command->bindValue(":ndiscado", $ndiscado, PDO::PARAM_STR);
                        $command->bindValue(":seconds", $seconds, PDO::PARAM_STR);
                        $command->bindValue(":name", $ramal, PDO::PARAM_STR);
                        $command->execute();

                        $sql = "SELECT id FROM pkg_prefix WHERE prefix = SUBSTRING('$ndiscado',1,length(prefix))
                                ORDER BY LENGTH(prefix) DESC LIMIT 1";

                        $command = Yii::app()->db->createCommand($sql);
                        $command->bindValue(":ndiscado", $ndiscado, PDO::PARAM_STR);
                        $resultPrefix = $command->queryAll();

                        $columm  = "sessionid, id_user, id_prefix, status, price, calledstation, cabina, sessiontime";
                        $values  = ":sessionid, :id_user, :id_prefix, '3', 0, :ndiscado, :ramal, :seconds";
                        $sql     = "INSERT INTO pkg_callshop ($columm) VALUES ( $values )";
                        $command = Yii::app()->db->createCommand($sql);
                        $command->bindValue(":sessionid", $canal, PDO::PARAM_STR);
                        $command->bindValue(":id_user", $id_user, PDO::PARAM_INT);
                        $command->bindValue(":id_prefix", $resultPrefix[0]['id'], PDO::PARAM_INT);
                        $command->bindValue(":ndiscado", $ndiscado, PDO::PARAM_STR);
                        $command->bindValue(":ramal", $ramal, PDO::PARAM_STR);
                        $command->bindValue(":seconds", $seconds, PDO::PARAM_STR);
                        $command->execute();

                    }
                }

            }

        }
    }
}
