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
 * 17/08/2012
 */

class CallFailedController extends Controller
{
    public $attributeOrder = 'id DESC';
    public $extraValues    = array(
        'idUser'   => 'username',
        'idPlan'   => 'name',
        'idTrunk'  => 'trunkcode',
        'idPrefix' => 'destination',
    );

    public $fieldsInvisibleClient = array(
        'username',
        'trunk',
        'id_user',
        'provider_name',
    );

    public $fieldsInvisibleAgent = array(
        'trunk',
        'id_user',
        'provider_name',
    );

    public $fieldsFkReport = array(
        'id_user'   => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => "username ",
        ),
        'id_trunk'  => array(
            'table'       => 'pkg_trunk',
            'pk'          => 'id',
            'fieldReport' => 'trunkcode',
        ),
        'id_prefix' => array(
            'table'       => 'pkg_prefix',
            'pk'          => 'id',
            'fieldReport' => 'destination',
        ),
        'id'        => array(
            'table'       => 'pkg_prefix',
            'pk'          => 'id',
            'fieldReport' => 'destination',
        ),
    );

    public function init()
    {
        $this->instanceModel = new CallFailed;
        $this->abstractModel = CallFailed::model();
        $this->titleReport   = Yii::t('yii', 'Call Failed');

        parent::init();

        if (!Yii::app()->session['isAdmin']) {
            $this->extraValues = array(
                'idUser'   => 'username',
                'idPlan'   => 'name',
                'idPrefix' => 'destination',
            );
        }
    }

    /**
     * Cria/Atualiza um registro da model
     */
    public function actionSave()
    {
        $values = $this->getAttributesRequest();

        if (isset($values['id']) && !$values['id']) {
            echo json_encode(array(
                $this->nameSuccess => false,
                $this->nameRoot    => 'error',
                $this->nameMsg     => 'Operation no allow',
            ));
            exit;
        }
        parent::actionSave();
    }

    public function getAttributesRequest()
    {
        $arrPost = array_key_exists($this->nameRoot, $_POST) ? json_decode($_POST[$this->nameRoot], true) : $_POST;
        //retira capos antes de salvar
        unset($arrPost['starttime']);
        unset($arrPost['callerid']);
        unset($arrPost['id_prefix']);
        unset($arrPost['username']);
        unset($arrPost['trunk']);
        unset($arrPost['terminatecauseid']);
        unset($arrPost['calltype']);
        unset($arrPost['idPrefixdestination']);

        return $arrPost;
    }
}
