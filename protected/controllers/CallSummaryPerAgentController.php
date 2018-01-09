<?php
/**
 * Acoes do modulo "Call".
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
 * Magnusbilling.com <info@magnusbilling.com>
 * 17/08/2012
 */

class CallSummaryPerAgentController extends Controller
{
    public $config;
    public $attributeOrder = 't.id_user DESC';
    public $extraValues    = array('idUser' => 'username', 'idTrunk' => 'trunkcode');

    public $limit  = 7;
    public $group  = 't.id_user';
    public $select = 't.id, t.id_user, sum(sessionbill) AS sessionbill, count(*) as nbcall,
            sum(buycost) AS buycost, starttime, sum(sessionbill) - sum(buycost) AS lucro';

    public $fieldsInvisibleClient = array(
        'id',
        'id_user_package_offer',
        'id_did',
        'id_prefix',
        'id_ratecard',
        'id_tariffgroup',
        'id_trunk',
        'real_sessiontime',
        'root_cost',
        'sessionid',
        'sipiax',
        'src',
        'stoptime',
        'markup',
        'calledstation',
        'idTrunktrunkcode',
        'id_user',
        'id_user',
        'lucro',
        'sumlucro',
        'sumbuycost',
        'buycost',
    );

    public $fieldsInvisibleAgent = array(
        'uniqueid',
        'id',
        'id_user_package_offer',
        'id_did',
        'id_prefix',
        'id_ratecard',
        'id_tariffgroup',
        'id_trunk',
        'real_sessiontime',
        'root_cost',
        'sessionid',
        'sipiax',
        'src',
        'stoptime',
        'markup',
        'buycost',
        'calledstation',
        'idTrunktrunkcode',
        'id_user',
        'id_user',
        'sumlucro',
        'sumbuycost',
    );

    public function init()
    {
        if (!Yii::app()->session['isAdmin']) {
            exit();
        }
        $this->instanceModel = new CallSummaryPerUser;
        $this->abstractModel = CallSummaryPerUser::model();
        $this->titleReport   = Yii::t('yii', 'Calls Summary');
        parent::init();
    }

    public function getAttributesModels($models, $itemsExtras = array())
    {
        $attributes = false;
        foreach ($models as $key => $item) {

            //$sql = "SELECT username FROM pkg_user WHERE id = ". $item->idUserusername;
            if (isset($item->idUserusername)) {
                $resultUser = User::model()->findAll(array(
                    'select'    => 'username',
                    'condition' => 'id = ' . $item->idUserusername,
                ));
            }

            $attributes[$key]                   = $item->attributes;
            $attributes[$key]['nbcall']         = $item->nbcall;
            $attributes[$key]['day']            = $item->day;
            $attributes[$key]['lucro']          = $item->lucro;
            $attributes[$key]['aloc_all_calls'] = $item->aloc_all_calls;
            $attributes[$key]['sumsessionbill'] = $item->sumsessionbill;
            $attributes[$key]['sumbuycost']     = $item->sumbuycost;
            $attributes[$key]['sumlucro']       = $item->sumlucro;
            $attributes[$key]['sumnbcall']      = $item->sumnbcall;
            $attributes[$key]['idUserusername'] = isset($resultUser[0]->username)
            ? $resultUser[0]->username : null;
            $attributes[$key]['idTrunktrunkcode'] = $item->idTrunktrunkcode;

            if (isset(Yii::app()->session['idClient']) && Yii::app()->session['idClient']) {
                foreach ($this->fieldsInvisibleClient as $field) {
                    unset($attributes[$key][$field]);
                }
            }

            if (isset(Yii::app()->session['idAgent']) && Yii::app()->session['idAgent']) {
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

    public function extraFilterCustom($filter)
    {

        if (Yii::app()->session['user_type'] == 2) {
            $filter .= ' AND t.id_user =  :dfby';
            $this->paramsFilter[':dfby'] = Yii::app()->session['id_user'];
        } else if (Yii::app()->session['user_type'] == 3) {
            $filter .= ' AND t.id_user =  :dfby';
            $this->paramsFilter[':dfby'] = Yii::app()->session['id_user'];
        }

        if (!Yii::app()->session['isClient']) {
            $summary_per_user_days = $this->config['global']['summary_per_agent_days'] * -1;
            if (!preg_match("/starttime/", $filter)) {
                $filter .= " AND starttime > :dfby ";
                $this->paramsFilter[':dfby'] = date('Y-m-d', strtotime("$summary_per_user_days days"));
            }
        }

        $filter .= " AND t.id_user > :dfby4 ";
        $this->paramsFilter[':dfby4'] = 1;

        return $filter;
    }

}
