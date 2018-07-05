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

class QueueController extends Controller
{
    public $attributeOrder = 'id';
    public $extraValues    = array('idUser' => 'username');

    private $host     = 'localhost';
    private $user     = 'magnus';
    private $password = 'magnussolution';

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => 'username',
        ),
    );

    public function init()
    {
        $this->instanceModel = new Queue;
        $this->abstractModel = Queue::model();
        $this->titleReport   = Yii::t('yii', 'Queue');
        parent::init();
    }

    public function afterSave($model, $values)
    {

        if (isset($_FILES["musiconhold"]) && strlen($_FILES["musiconhold"]["name"]) > 1) {

            $uploaddir = '/var/lib/asterisk/moh/' . $model->name;
            shell_exec('mkdir -p ' . $uploaddir);
            Yii::log(print_r($_FILES, true), 'error');

            $typefile   = explode('.', $_FILES["musiconhold"]["name"]);
            $uploadfile = $uploaddir . '/queue-' . time() . '.' . $typefile[1];
            move_uploaded_file($_FILES["musiconhold"]["tmp_name"], $uploadfile);

            $modelQueue = Queue::model()->findAll(array(
                'condition' => 'musiconhold != "default"',
                'group'     => 'musiconhold',
            ));
            $file = '/etc/asterisk/musiconhold_magnus.conf';
            $line = '';
            $fd   = fopen($file, "w");
            foreach ($modelQueue as $key => $queue) {
                if ($fd) {
                    shell_exec('mkdir -p /var/lib/asterisk/moh/' . $queue->name);
                    $line .= "\n\n[" . $queue->name . "]\n";
                    $line .= "mode=files\n";
                    $line .= "directory=/var/lib/asterisk/moh/" . $queue->name . "\n\n";
                }
            }
            if (fwrite($fd, $line) === false) {
                Yii::log("Impossible to write to the file ($file)", 'error');
            }

            $model->musiconhold = $model->name;
            $model->save();
        }

        return;
    }

    public function actionDeleteMusicOnHold()
    {
        $modelQueue = Queue::model()->findByPk((int) $_POST['id_queue']);
        if (count($modelQueue)) {
            shell_exec('rm -rf /var/lib/asterisk/moh/' . $modelQueue->name . '/*');
            echo json_encode(array(
                $this->nameSuccess => true,
                $this->nameMsg     => 'All musiconhold deleted from queue',
            ));
        } else {
            echo json_encode(array(
                $this->nameSuccess => false,
                $this->nameMsg     => 'Queue not found',
            ));
        }
    }

    public function actionResetQueueStats()
    {

        $filter       = isset($_POST['filter']) ? $_POST['filter'] : null;
        $filter       = $this->createCondition(json_decode($filter));
        $this->filter = $filter = $this->extraFilter($filter);

        $id  = json_decode($_POST['ids']);
        $ids = implode(",", $id);

        $uniID = count($ids) == 1 ? true : false;

        $this->abstractModel->truncateQueueStatus();

        $modelQueue = Queue::model()->find("id IN ($ids)");
        foreach ($modelQueue as $key => $queue) {
            try {
                AsteriskAccess::queueReseteStats($queue->name);
                $sussess = true;
            } catch (Exception $e) {
                $sussess          = true;
                $this->msgSuccess = "Error";
            }
        }
        echo json_encode(array(
            $this->nameSuccess => $sussess,
            $this->nameMsg     => $this->msgSuccess,
        ));

    }
}
