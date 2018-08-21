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

class CallSummaryPerTrunkController extends Controller
{
    public $config;
    public $attributeOrder = 't.id_trunk DESC';
    public $group          = 't.id_trunk';
    public $select         = 'SQL_CACHE t.id, t.id_trunk, starttime, c.trunkcode AS idTrunktrunkcode,
            sum(sessionbill) AS sessionbill,
            count(*) as nbcall,
            sum(buycost) AS buycost
            ';
    public $join = 'JOIN pkg_trunk c ON t.id_trunk = c.id';

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
        'sipiax',
        'src',
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
        'sipiax',
        'src',
        'markup',
        'calledstation',
        'idTrunktrunkcode',
        'id_user',
        'id_user',
        'sumlucro',
        'sumbuycost',
    );

    public function init()
    {

        $this->instanceModel = new CallSummaryPerTrunk;
        $this->abstractModel = CallSummaryPerTrunk::model();
        $this->titleReport   = Yii::t('yii', 'Calls Summary per trunk');
        parent::init();

        if (Yii::app()->session['isAgent'] == 2) {
            $this->select = 'SQL_CACHE t.id, t.id_user, starttime, c.username AS idUserusername,
            sum(agent_bill) AS sessionbill,
            count(*) as nbcall,
            sum(sessionbill) AS buycost';
        } else if (Yii::app()->session['isClientAgent'] == true) {
            $this->select = 'SQL_CACHE t.id, t.id_user, starttime, c.username AS idUserusername,
            sum(agent_bill) AS sessionbill,
            count(*) as nbcall';
        }

    }

    public function getAttributesModels($models, $itemsExtras = array())
    {
        $attributes = false;
        foreach ($models as $key => $item) {
            $attributes[$key]                     = $item->attributes;
            $attributes[$key]['nbcall']           = $item->nbcall;
            $attributes[$key]['day']              = $item->day;
            $attributes[$key]['lucro']            = $item->sessionbill - $item->buycost;
            $attributes[$key]['sessiontime']      = $item->sessiontime / 60;
            $attributes[$key]['aloc_all_calls']   = $item->aloc_all_calls;
            $attributes[$key]['sumsessionbill']   = $item->sumsessionbill;
            $attributes[$key]['sumbuycost']       = $item->sumbuycost;
            $attributes[$key]['sumlucro']         = $item->sumlucro;
            $attributes[$key]['sumnbcall']        = $item->sumnbcall;
            $attributes[$key]['idUserusername']   = $item->idUserusername;
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

    public function filterReplace($filter)
    {

        if (preg_match('/c.username/', $filter)) {
            if (!preg_match("/JOIN pkg_user/", $this->join)) {
                $this->join .= ' LEFT JOIN pkg_user c ON t.id_user = c.id';
            }

            $filter = preg_replace('/c.username/', "c.username", $filter);
        }

        if (preg_match('/pkg_trunk.trunkcode/', $filter)) {
            if (!preg_match("/JOIN pkg_trunk/", $this->join)) {
                $this->join .= ' LEFT JOIN pkg_trunk ON t.id_trunk = pkg_trunk.id';
            }

        }

        return $filter;
    }

    public function extraFilterCustom($filter)
    {

        //is agent, get only agent customers
        if (Yii::app()->session['user_type'] == 2) {
            $filter .= ' AND t.id_user IN (SELECT id FROM pkg_user WHERE id_user = :dfby)  ';
            $this->paramsFilter[':dfby'] = Yii::app()->session['id_user'];
        } else if (Yii::app()->session['user_type'] == 3) {
            $filter .= ' AND t.id_user =  :dfby';
            $this->paramsFilter[':dfby'] = Yii::app()->session['id_user'];
        }

        if (!Yii::app()->session['isClient']) {
            $summary_per_user_days = $this->config['global']['summary_per_user_days'] * -1;
            if (!preg_match("/starttime/", $filter)) {
                $filter .= " AND starttime > :dfby ";
                $this->paramsFilter[':dfby'] = date('Y-m-d', strtotime("$summary_per_user_days days"));
            }
        } else {
            $filter .= " AND c.id_user < :dfby4";
            $this->paramsFilter[':dfby4'] = 2;
        }

        return $filter;
    }

    public function actionCsv()
    {

        if (!AccessManager::getInstance($this->instanceModel->getModule())->canRead()) {
            header('HTTP/1.0 401 Unauthorized');
            die("Access denied to read in module:" . $this->instanceModel->getModule());
        }

        if (!isset(Yii::app()->session['id_user'])) {
            $info = 'User try export CSV without login';
            MagnusLog::insertLOG(7, $info);
            exit;
        } else {
            $info = 'User try export CSV ' . $this->abstractModel->tableName();
            MagnusLog::insertLOG(7, $info);
        }

        $columns = json_decode($_GET['columns'], true);

        $columns = $this->repaceColumns($columns);

        $columns = $this->removeColumns($columns);

        $this->setLimit($_GET);

        $this->setStart($_GET);

        $this->setSort();

        $this->order = 't.id ASC';

        $this->setfilter($_GET);

        $this->applyFilterToLimitedAdmin();

        $this->magnusFilesDirectory = '/var/www/tmpmagnus/';
        $nameFileCsv                = $this->nameFileReport . time();
        $pathCsv                    = $this->magnusFilesDirectory . $nameFileCsv . '.csv';

        $this->convertRelationFilter();

        $sql = "SELECT SQL_CACHE c.trunkcode AS idTrunktrunkcode,  count(*) as nbcall,
            sum(buycost) AS buycost, sum(sessionbill) AS sessionbill INTO OUTFILE '" . $this->magnusFilesDirectory . $nameFileCsv . ".csv' FIELDS TERMINATED BY '\;' LINES TERMINATED BY '\n'
                FROM " . $this->abstractModel->tableName() . " t $this->join WHERE $this->filter GROUP BY id_trunk";
        $command = Yii::app()->db->createCommand($sql);
        if (count($this->paramsFilter)) {
            foreach ($this->paramsFilter as $key => $value) {
                $command->bindValue($key, $value, PDO::PARAM_STR);
            }

        }

        $command->execute();
        header('Content-type: application/csv');
        header('Content-Disposition: inline; filename="' . $this->modelName . '_' . time() . '.csv"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        ob_clean();
        flush();
        if (readfile($pathCsv)) {
            unlink($pathCsv);
        }

    }
     public function actionExportCsvCalls()
    {

        if (!Yii::app()->session['isAdmin']) {
            exit;
        }

        $this->setfilter($_GET);

        $trunkcode = explode(' - ', $_GET['id'])[0];
        $this->filter .= ' AND trunkcode = :keytrunkcode';

        $this->paramsFilter[':keytrunkcode'] = $trunkcode;

        $this->magnusFilesDirectory = '/var/www/tmpmagnus/';
        $nameFileCsv                = $this->nameFileReport . time();
        $pathCsv                    = $this->magnusFilesDirectory . $nameFileCsv . '.csv';

        $this->convertRelationFilter();

        $columns    = 'u.username,CONCAT(firstname, " ",lastname),starttime,calledstation,sessiontime,real_sessiontime,buycost,sessionbill,trunkcode ';
        $this->join = 'JOIN pkg_user u ON t.id_user = u.id ';
        $this->join .= 'JOIN pkg_trunk r ON t.id_trunk = r.id ';
        $sql = "SELECT " . $columns . "  INTO OUTFILE '" . $this->magnusFilesDirectory . $nameFileCsv . ".csv' FIELDS TERMINATED BY '\;' LINES TERMINATED BY '\n'
                FROM " . $this->abstractModel->tableName() . " t $this->join WHERE $this->filter";

        $command = Yii::app()->db->createCommand($sql);
        if (count($this->paramsFilter)) {
            foreach ($this->paramsFilter as $key => $value) {
                $command->bindValue($key, $value, PDO::PARAM_STR);
            }

        }

        $command->execute();
        header('Content-type: application/csv');
        header('Content-Disposition: inline; filename="' . $this->modelName . '_' . time() . '.csv"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        ob_clean();
        flush();
        if (readfile($pathCsv)) {
            unlink($pathCsv);
        }

    }

}
