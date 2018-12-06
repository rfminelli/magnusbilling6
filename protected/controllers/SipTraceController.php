<?php
/**
 * Acoes do modulo "SipTrace".
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
 * 19/02/2018
 */

class SipTraceController extends Controller
{
    public $attributeOrder = 't.id ASC';
    private $log_name      = 'resources/reports/siptrace.log';
    public function init()
    {
        $this->instanceModel = new SipTrace;
        $this->abstractModel = SipTrace::model();
        $this->titleReport   = Yii::t('yii', 'SipTrace');
        parent::init();
    }

    public function actionDetails()
    {

        try {
            $myfile = @fopen($this->log_name, "r")
            or die(
                json_encode(array(
                    'rows'  => [],
                    'count' => 0,
                    'sum'   => [],
                )));
        } catch (Exception $e) {
            exit;
        }

        $result = fread($myfile, filesize($this->log_name));
        fclose($myfile);
        $result = explode('U ', $result);
        $packet = [];
        $id     = 1;
        foreach ($result as $key => $value) {

            $callid = '';
            $lines  = preg_split('/\r\n|\r|\n/', $value);

            if (count($lines) < 7) {
                continue;
            }
            if (preg_match('/Trying/', $lines[1])) {
                $method = '100 Trying';
            } else if (preg_match('/SIP\/2\.0 /', $lines[1])) {
                $method = explode('SIP/2.0 ', $lines[1]);
                $method = $method[1];
            } else {
                $method = explode(' ', $lines[1]);
                $method = $method[0];
            }

            $fromTo = explode(' ', $lines[0]);

            $date = $fromTo[0] . ' ' . $fromTo[1];

            $fromIp = strtok($fromTo[2], ':');
            $toIp   = strtok($fromTo[4], ':');

            foreach ($lines as $key => $line) {
                if (preg_match('/Call-ID:/', $line)) {
                    $callid = trim(substr($line, 8));
                }if (preg_match('/To:/', $line)) {
                    $sipto = trim(substr($line, 9, -2));
                }
            }
            foreach ($lines as $key => $line) {
                if (preg_match('/Call-ID:/', $line)) {
                    $callid = trim(substr($line, 8));
                }if (preg_match('/To:/', $line)) {
                    $sipto = trim(substr($line, 9, -2));
                }
            }

            if ($_GET['callid'] != $callid) {
                continue;
            }
            if ($id > 50) {
                break;
            }

            if ($id == 1) {
                $firstPacket = $fromIp;
            }
            array_push($packet, array(
                'id'        => $id,
                'method'    => $method,
                'fromip'    => $fromIp,
                'toip'      => $toIp,
                'sipto'     => $sipto,
                'callid'    => $callid,
                'head'      => $value,
                'date'      => $date,
                'direction' => $firstPacket == $fromIp ? 'red' : 'green',
            ));

            $id++;

        }

        $this->render('index', array('packet' => $packet));
    }

    public function actionDestroy()
    {
        exec("rm -rf " . $this->log_name);
    }

    public function actionRead($asJson = true, $condition = null)
    {
        $start = $_GET['start'];

        $filter = isset($_GET['filter']) ? json_decode($_GET['filter']) : null;

        if (count($filter)) {

            foreach ($filter as $key => $value) {
                if ($value->field == 'method') {
                    $filterMethod            = $value->value;
                    $filterMethodComparation = $value->comparison;
                }
                if ($value->field == 'callid') {
                    $filterCallid            = $value->value;
                    $filterCallidComparation = $value->comparison;
                }
                if ($value->field == 'fromip') {
                    $filterFromIp            = $value->value;
                    $filterFromIpComparation = $value->comparison;
                }
                if ($value->field == 'toip') {
                    $filterToIp            = $value->value;
                    $filterToIpComparation = $value->comparison;
                }
                if ($value->field == 'sipto') {
                    $filterSipTo            = $value->value;
                    $filterSipToComparation = $value->comparison;
                }
            }
        }
        try {
            $myfile = @fopen($this->log_name, "r")
            or die(
                json_encode(array(
                    'rows'  => [],
                    'count' => 0,
                    'sum'   => [],
                )));
        } catch (Exception $e) {
            exit;
        }

        $result = fread($myfile, filesize($this->log_name));
        fclose($myfile);
        $result = explode('U ', $result);
        $packet = [];
        $id     = 1;
        foreach ($result as $key => $value) {

            $callid = '';
            $lines  = preg_split('/\r\n|\r|\n/', $value);

            if (count($lines) < 7) {
                continue;
            }
            if (preg_match('/Trying/', $lines[1])) {
                $method = '100 Trying';
            } else if (preg_match('/SIP\/2\.0 /', $lines[1])) {
                $method = explode('SIP/2.0 ', $lines[1]);
                $method = $method[1];
            } else {
                $method = explode(' ', $lines[1]);
                $method = $method[0];
            }

            $fromTo = explode(' ', $lines[0]);
            $fromIp = strtok($fromTo[2], ':');
            $toIp   = strtok($fromTo[4], ':');

            foreach ($lines as $key => $line) {
                if (preg_match('/Call-ID:/', $line)) {
                    $callid = trim(substr($line, 8));
                }if (preg_match('/To:/', $line)) {
                    $sipto = trim(substr($line, 9, -2));
                }
            }

            if (isset($filterMethod)) {
                //filter method
                if ($filterMethodComparation == 'st') {
                    if (!preg_match('/^' . $filterMethod . '/', $method)) {
                        continue;
                    }
                }
                if ($filterMethodComparation == 'ed') {
                    if (!preg_match('/' . $filterMethod . '$/', $method)) {
                        continue;
                    }
                }
                if ($filterMethodComparation == 'ct') {
                    if (!preg_match('/' . $filterMethod . '/', $method)) {
                        continue;
                    }
                }
                if ($filterMethodComparation == 'eq') {
                    if ($filterMethod != $method) {
                        continue;
                    }
                }

            }

            if (isset($filterFromIp)) {
                //filter callerid
                if ($filterFromIpComparation == 'st') {
                    if (!preg_match('/^' . $filterFromIp . '/', $fromIp)) {
                        continue;
                    }
                }
                if ($filterFromIpComparation == 'ed') {
                    if (!preg_match('/' . $filterFromIp . '$/', $fromIp)) {
                        continue;
                    }
                }
                if ($filterFromIpComparation == 'ct') {
                    if (!preg_match('/' . $filterFromIp . '/', $fromIp)) {
                        continue;
                    }
                }
                if ($filterFromIpComparation == 'eq') {
                    if ($filterFromIp != $fromIp) {
                        continue;
                    }
                }
            }

            if (isset($filterToIp)) {
                //filter callerid
                if ($filterToIpComparation == 'st') {
                    if (!preg_match('/^' . $filterToIp . '/', $toIp)) {
                        continue;
                    }
                }
                if ($filterToIpComparation == 'ed') {
                    if (!preg_match('/' . $filterToIp . '$/', $toIp)) {
                        continue;
                    }
                }
                if ($filterToIpComparation == 'ct') {
                    if (!preg_match('/' . $filterToIp . '/', $toIp)) {
                        continue;
                    }
                }
                if ($filterToIpComparation == 'eq') {
                    if ($filterToIp != $toIp) {
                        continue;
                    }
                }
            }

            if (isset($filterCallid)) {
                //filter callerid
                if ($filterCallidComparation == 'st') {
                    if (!preg_match('/^' . $filterCallid . '/', $callid)) {
                        continue;
                    }
                }
                if ($filterCallidComparation == 'ed') {
                    if (!preg_match('/' . $filterCallid . '$/', $callid)) {
                        continue;
                    }
                }
                if ($filterCallidComparation == 'ct') {
                    if (!preg_match('/' . $filterCallid . '/', $callid)) {
                        continue;
                    }
                }
                if ($filterCallidComparation == 'eq') {
                    if ($filterCallid != $callid) {
                        continue;
                    }
                }
            }

            if (isset($filterSipTo)) {
                //filter callerid
                if ($filterSipToComparation == 'st') {
                    if (!preg_match('/^' . $filterSipTo . '/', $sipto)) {
                        continue;
                    }
                }
                if ($filterSipToComparation == 'ed') {
                    if (!preg_match('/' . $filterSipTo . '$/', $sipto)) {
                        continue;
                    }
                }
                if ($filterSipToComparation == 'ct') {
                    if (!preg_match('/' . $filterSipTo . '/', $sipto)) {
                        continue;
                    }
                }
                if ($filterSipToComparation == 'eq') {
                    if ($filterSipTo != $sipto) {
                        continue;
                    }
                }
            }

            if ($id < $start) {
                $id++;
                continue;
            }
            if ($id > $start + 25) {
                break;
            }

            array_push($packet, array(
                'id'     => $id,
                'method' => $method,
                'fromip' => $fromIp,
                'toip'   => $toIp,
                'sipto'  => $sipto,
                'callid' => $callid,
                'head'   => $value,
            ));

            $id++;

        }

        echo json_encode(array(
            'rows'  => $packet,
            'count' => count($packet) < 25 ? count($packet) : count($result),
            'sum'   => [],
        ), JSON_UNESCAPED_SLASHES);
    }

    public function actionExport()
    {
        header('Content-type: application/csv; charset=utf-8');
        header('Content-Disposition: inline; filename="MagnusBilling_siptrace_' . time() . '.log"');
        header('Content-Transfer-Encoding: binary');

        header('Accept-Ranges: bytes');
        ob_clean();
        flush();
        readfile($this->log_name);

    }

    public function actionStart()
    {

        $modelTrace = Trace::model()->find('in_use = 1 OR status = 1');

        if (count($modelTrace)) {
            echo json_encode(array(
                $this->nameSuccess => false,
                $this->nameMsg     => Yii::t('yii', 'Exist a filter active or in use. Wait or click in Stop Capture button.'),
            ));
            exit;
        }
        $modelTrace          = new Trace();
        $modelTrace->filter  = $_POST['filter'];
        $modelTrace->timeout = $_POST['timeout'];
        $modelTrace->port    = $_POST['port'];
        $modelTrace->status  = 1;
        $modelTrace->in_use  = 0;
        $modelTrace->save();

        echo json_encode(array(
            $this->nameSuccess => true,
            $this->nameMsg     => Yii::t('yii', 'Reload this module to see the packets'),
        ));
    }

    public function actionClearAll()
    {
        SipTrace::model()->deleteAll();

        Trace::model()->deleteAll();
        $modelTrace         = new Trace();
        $modelTrace->filter = 'stop';
        $modelTrace->status = 0;
        $modelTrace->save();
    }

}
