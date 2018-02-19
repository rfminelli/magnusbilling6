<?php
/**
 * Acoes do modulo "Queue".
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

class QueueMemberDashBoardController extends Controller
{

    public $attributeOrder = 'id';
    public $extraValues    = array('idQueue' => 'name');

    public function init()
    {
        $this->instanceModel = new QueueMemberDashBoard;
        $this->abstractModel = QueueMemberDashBoard::model();
        $this->titleReport   = Yii::t('yii', 'Queue Member DashBoard');

        parent::init();
    }

    public function setAttributesModels($attributes, $models)
    {

        for ($i = 0; $i < count($attributes) && is_array($attributes); $i++) {
            if (preg_match('/IN CALL|IN USE|ON HOLD/', strtoupper($attributes[$i]['agentStatus']))) {
                $result                   = Queue::model()->getQueueStatus($attributes[$i]['id']);
                $attributes[$i]['number'] = $result[0]['callerId'];
            }
        }
        return $attributes;
    }
}
