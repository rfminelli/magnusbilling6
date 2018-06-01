<?php
/**
 * Acoes do modulo "CampaignPollInfo".
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
 * 28/10/2012
 */

class CampaignPollInfoController extends Controller
{
    public $attributeOrder = 't.id';
    public $extraValues    = array('idCampaignPoll' => 'name');

    public $nameFileReport = 'exported';

    public $fieldsFkReport = array(
        'id_campaign_poll' => array(
            'table'       => 'pkg_campaign_poll',
            'pk'          => 'id',
            'fieldReport' => 'name',
        ),
    );

    public function init()
    {
        $this->instanceModel = new CampaignPollInfo;
        $this->abstractModel = CampaignPollInfo::model();
        $this->titleReport   = Yii::t('yii', 'Poll Info');
        parent::init();
    }

    public function applyFilterToLimitedAdmin()
    {
        if (Yii::app()->session['user_type'] == 1 && Yii::app()->session['adminLimitUsers'] == true) {
            $this->join .= ' JOIN pkg_campaign_poll cp ON cp.id_user = t.id';
            $this->join .= ' JOIN pkg_user ub ON cp.id_user = ub.id';
            $this->filter .= " AND ub.id_group IN (SELECT gug.id_group
                                FROM pkg_group_user_group gug
                                WHERE gug.id_group_user = :idgA0)";

            $this->paramsFilter['idgA0'] = Yii::app()->session['id_group'];
        }
    }

    public function actionCsv()
    {

        $_GET['columns'] = preg_replace('/idUserusername/', 'id_user', $_GET['columns']);
        $_GET['columns'] = preg_replace('/idPrefixdestination/', 'id', $_GET['columns']);
        $_GET['columns'] = preg_replace('/idPrefixprefix/', 'id_prefix', $_GET['columns']);
        $_GET['columns'] = preg_replace('/idPhonebookt.name/', 'id_phonebook', $_GET['columns']);
        $_GET['columns'] = preg_replace('/idDiddid/', 'id_did', $_GET['columns']);

        $columns    = json_decode($_GET['columns'], true);
        $filter     = isset($_GET['filter']) ? $this->createCondition(json_decode($_GET['filter'])) : null;
        $fieldGroup = json_decode($_GET['group']);
        $sort       = json_decode($_GET['sort']);

        $arraySort = ($sort && $fieldGroup) ? explode(' ', implode(' ', $sort)) : null;
        $dirGroup  = $arraySort ? $arraySort[array_search($fieldGroup, $arraySort) + 1] : null;
        $firstSort = $fieldGroup ? $fieldGroup . ' ' . $dirGroup . ',' : null;
        $sort      = $sort ? $firstSort . implode(',', $sort) : null;

        $sort = $this->replaceOrder($sort);

        $this->filter = $filter = $this->extraFilter($filter);

        if (!preg_match('/id_campaign_poll/', $filter)) {
            exit('Please filter one or more poll');
        }
        $records = $this->abstractModel->findAll(array(
            'select'    => $this->getColumnsFromReport($columns),
            'join'      => $this->join,
            'condition' => $this->filter,
            'params'    => $this->paramsFilter,
            'order'     => $sort,
            'group'     => 'number',
        ));

        $pathCsv = $this->magnusFilesDirectory . $this->nameFileReport . time() . '.csv';
        if (!is_dir($this->magnusFilesDirectory)) {
            mkdir($this->magnusFilesDirectory, 777, true);
        }

        $fileOpen  = fopen($pathCsv, 'w');
        $separador = Yii::app()->session['language'] == 'pt_BR' ? ';' : ',';

        $fieldsCsv = array();
        $t         = 0;

        foreach ($records as $numero) {

            $paramsFilter2 = $this->paramsFilter;

            $paramsFilter2['number'] = $numero->number;

            $modelCampaignPollInfo = $this->abstractModel->findAll(array(
                'condition' => "number = :number AND $filter",
                'order'     => 'id_campaign_poll ASC',
                'join'      => $this->join,
                'params'    => $paramsFilter2,
            ));

            $respostas = array();
            $s         = 2;

            if (count($modelCampaignPollInfo) == 1 && $modelCampaignPollInfo[0]->resposta < 0) {
                continue;
            }

            $ids = $ids = $this->paramsFilter['pIn00'];
            $ids = explode(',', $ids);

            for ($i = 0; $i < count($ids); $i++) {

                if (isset($modelCampaignPollInfo[$i]->id_campaign_poll) && in_array($modelCampaignPollInfo[$i]->id_campaign_poll, $ids)) {
                    $respostas[] = $modelCampaignPollInfo[$i]['resposta'];
                } else {
                    $respostas[] = '';
                }

            }
            $result = implode(',', $respostas);

            if ($t == 0) {
                $colunas = 'Fecha,Numero,city';

                $filter2 = preg_replace('/id_campaign_poll/', 'id', $filter);
                $filter2 = explode(" AND", $filter2);

                foreach ($filter2 as $key => $value) {
                    if (!preg_match('/id IN/', $value)) {
                        unset($filter2[$key]);
                    }
                }

                foreach ($this->paramsFilter as $key => $value) {
                    if ($key != 'pIn00') {
                        continue;
                    }
                    $paramsIDPoll[$key] = $value;
                }

                $modelCampaignPoll = CampaignPoll::model()->findAll(array(
                    'condition' => $filter2[1],
                    'order'     => 'id ASC',
                    'params'    => $paramsIDPoll,
                ));
                foreach ($modelCampaignPoll as $key => $coluna) {

                    $colunas .= "," . $coluna->name;
                }

                fwrite($fileOpen, $colunas . "\n");
                $t++;
            }

            $fieldsCsv2 = $modelCampaignPollInfo[0]->date . ',' . $numero->number . ',' . $numero->city . ",$result\n";

            fwrite($fileOpen, $fieldsCsv2);

        }

        fclose($fileOpen);

        header('Content-type: application/csv');
        header('Content-Disposition: inline; filename="' . $pathCsv . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        ob_clean();
        flush();
        if (readfile($pathCsv)) {
            unlink($pathCsv);
        }
    }

    public function extraFilterCustomClient($filter)
    {
        $this->join .= 'JOIN pkg_campaign_poll cp ON cp.id = id_campaign_poll';
        $filter .= ' AND cp.id_user = :clfby';
        $this->paramsFilter[':clfby'] = Yii::app()->session['id_user'];
        return $filter;
    }

    public function extraFilterCustomAgent($filter)
    {
        $this->join .= 'JOIN pkg_campaign_poll cp ON cp.id = id_campaign_poll';
        $this->join .= ' JOIN pkg_user user ON cp.id_user = user.id ';

        $filter .= ' AND user.id_user = :agfby';
        $this->paramsFilter[':agfby'] = Yii::app()->session['id_user'];

        return $filter;
    }
}
