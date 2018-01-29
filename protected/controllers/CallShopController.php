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

    public function init()
    {
        $this->instanceModel = new CallShop;
        $this->abstractModel = CallShop::model();
        $this->titleReport   = Yii::t('yii', 'CallShop');
        parent::init();
    }

    public function actionRead($asJson = true, $condition = null)
    {
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
}
