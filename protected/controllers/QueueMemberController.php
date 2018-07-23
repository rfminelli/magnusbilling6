<?php
/**
 * Acoes do modulo "QueueMember".
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
 * 17/08/2012
 */

class QueueMemberController extends Controller
{
    public $attributeOrder = 'uniqueid';
    public $extraValues    = array('idUser' => 'username');

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => 'username',
        ),
    );

    public function init()
    {
        $this->instanceModel = new QueueMember;
        $this->abstractModel = QueueMember::model();
        $this->titleReport   = Yii::t('yii', 'Queue Member');
        parent::init();
    }

    public function beforeSave($values)
    {
        if (isset($values['interface'])) {
            $modelSip = Sip::model()->find("id = :id OR name = :id",
                array('id' => preg_replace("/SIP\//", '', $values['interface']))
            );

            $values['id_user']   = $modelSip->id_user;
            $values['interface'] = 'SIP/' . $modelSip->name;
        }
        if (isset($values['queue_name'])) {
            $modelQueue = Queue::model()->find("id = :id OR name = :id",
                array('id' => $values['queue_name'])
            );
            $values['queue_name'] = $modelQueue->name;
        }

        return $values;
    }
}
