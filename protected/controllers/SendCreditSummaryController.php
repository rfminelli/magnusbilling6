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

class SendCreditSummaryController extends Controller
{
    public $attributeOrder = 't.id DESC';

    public function actionRead($asJson = true, $condition = null)
    {

        $model = new SendCreditSummary();

        $model->date = isset($_POST['SendCreditSummary']['date'])
        ? $_POST['SendCreditSummary']['date']
        : date('Y-m-d ', strtotime("-1 month", time()));
        $model->stopdate = isset($_POST['SendCreditSummary']['stopdate'])
        ? $_POST['SendCreditSummary']['stopdate']
        : date('Y-m-d');

        $_POST['SendCreditSummary']['id'] = isset($_POST['SendCreditSummary']['id'])
        ? $_POST['SendCreditSummary']['id']
        : 1;

        $this->filter = 'confirmed = 1 ';
        if (isset($_POST['SendCreditSummary']['id']) && $_POST['SendCreditSummary']['id'] > 1) {
            $this->filter .= ' AND id_user = :id_user';
            $this->paramsFilter['id_user'] = $_POST['SendCreditSummary']['id'];
        }

        $this->filter .= ' AND date > :date AND date < :stopdate';
        $this->paramsFilter['date']     = $model->date;
        $this->paramsFilter['stopdate'] = $model->stopdate . ' 23:59:59';

        $this->select = '*, count(*) count, sum(sell) total_sale, sum(cost) total_cost, sum(earned) earned, DATE(date) AS day, date';
        $this->group  = 'day, service';

        $modelSendCreditSummary = SendCreditSummary::model()->findAll(array(
            'select'    => $this->select,
            'join'      => $this->join,
            'condition' => $this->filter,
            'params'    => $this->paramsFilter,
            'order'     => $this->order,
            'group'     => $this->group,
        ));

        $this->render('index', array(
            'model'                  => $model,
            'modelSendCreditSummary' => $modelSendCreditSummary,
        ));
        exit;

        parent::actionRead();
    }
}
