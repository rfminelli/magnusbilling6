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
    public $extraValues    = array(
        'idUser' => 'username',
    );

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => "username ",
        ),
    );

    public function init()
    {
        $this->instanceModel = new SendCreditSummary;
        $this->abstractModel = SendCreditSummary::model();
        $this->titleReport   = Yii::t('yii', 'SendCreditSummary');

        parent::init();
    }

    public function unique_multidim_array($array, $key)
    {
        $temp_array = array();
        $i          = 0;
        $key_array  = array();

        foreach ($array as $val) {
            if (!in_array($val->{$key}, $key_array)) {
                $key_array[$i]  = $val->{$key};
                $temp_array[$i] = $val;
            }
            $i++;
        }
        return $temp_array;
    }

    public function setfilter($value)
    {
        # recebe os parametros para o filtro
        $filter   = isset($_GET['filter']) ? json_decode($_GET['filter']) : null;
        $filterIn = isset($_GET['filterIn']) ? json_decode($_GET['filterIn']) : null;

        if ($filter && $filterIn) {
            $filter = array_merge($filter, $filterIn);
        } else if ($filterIn) {
            $filter = $filterIn;
        }
        if (count($filter)) {
            $filter = $this->unique_multidim_array(array_reverse($filter), 'comparison');
        }
        $filter       = $filter ? $this->createCondition($filter) : $this->defaultFilter;
        $this->filter = $this->fixedWhere ? $filter . ' ' . $this->fixedWhere : $filter;
        $this->filter = $this->extraFilter($filter);
    }

    public function actionRead($asJson = true, $condition = null)
    {
        $this->select = '*, count(*) count, sum(sell) total_sale, sum(cost) total_cost, sum(earned) earned, DATE(date) AS day ';
        $this->group  = 'day, service';
        parent::actionRead();
    }

    public function getAttributesModels($models, $itemsExtras = array())
    {
        $attributes = false;
        foreach ($models as $key => $item) {
            $attributes[$key]        = $item->attributes;
            $attributes[$key]['day'] = $item->day;

            if (isset(Yii::app()->session['isClient']) && Yii::app()->session['isClient']) {
                foreach ($this->fieldsInvisibleClient as $field) {
                    unset($attributes[$key][$field]);
                }
            }

            if (isset(Yii::app()->session['isAgent']) && Yii::app()->session['isAgent']) {
                foreach ($this->fieldsInvisibleAgent as $field) {
                    unset($attributes[$key][$field]);
                }
            }

            foreach ($itemsExtras as $relation => $fields) {
                $arrFields = explode(',', $fields);
                foreach ($arrFields as $field) {
                    $attributes[$key][$relation . $field] = $item->$relation->$field;
                    if (Yii::app()->session['idClient']) {
                        foreach ($this->fieldsInvisibleClient as $field) {
                            unset($attributes[$key][$field]);
                        }
                    }

                    if (Yii::app()->session['idAgent']) {
                        foreach ($this->fieldsInvisibleAgent as $field) {
                            unset($attributes[$key][$field]);
                        }
                    }
                }
            }
        }

        return $attributes;
    }
}
