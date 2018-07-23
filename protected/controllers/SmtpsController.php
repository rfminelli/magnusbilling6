<?php
/**
 * Acoes do modulo "Did".
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
 * 24/09/2012
 */

class SmtpsController extends Controller
{
    public $attributeOrder = 't.id';
    public $extraValues    = array('idUser' => 'username');

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => 'username',
        ),
    );

    public $fieldsInvisibleClient = array(
        'id_user',
        'password',
        'username',
        'host',
        'port',
        'encryption',
    );

    public function init()
    {
        $this->instanceModel = new Smtps;
        $this->abstractModel = Smtps::model();
        $this->titleReport   = Yii::t('yii', 'Smtp');

        parent::init();
    }

    public function actionRead($asJson = true, $condition = null)
    {
        if (Yii::app()->session['isAdmin']) {
            $this->filter = ' AND id_user = 1';
        }

        parent::actionRead($asJson = true, $condition = null);
    }

    public function beforeSave($values)
    {
        if ($this->isNewRecord) {

            $modelUser = Smtps::model()->find("id_user = " . Yii::app()->session['id_user']);
            if (count($modelUser)) {
                echo json_encode(array(
                    'success' => false,
                    'rows'    => array(),
                    'errors'  => Yii::t('yii', 'Do you already have a SMTP'),
                ));
                exit;
            }

            if (Yii::app()->session['isAgent'] == 1) {

                $filter            = "id_user = 1 AND ( mailtype = 'signup'  OR mailtype = 'signupconfirmed' OR mailtype = 'reminder' OR mailtype = 'refill')";
                $modelTemplateMail = TemplateMail::model()->findAll($filter);

                foreach ($modelTemplateMail as $key => $mail) {
                    //add new template to user
                    $modelTemplateMailNew              = new TemplateMail();
                    $modelTemplateMailNew->id_user     = Yii::app()->session['id_user'];
                    $modelTemplateMailNew->mailtype    = $mail->mailtype;
                    $modelTemplateMailNew->fromemail   = $values['username'];
                    $modelTemplateMailNew->fromname    = $mail->fromname;
                    $modelTemplateMailNew->subject     = $mail->subject;
                    $modelTemplateMailNew->messagehtml = $mail->messagehtml;
                    $modelTemplateMailNew->language    = $mail->language;
                    $modelTemplateMailNew->save();
                }
            }
        }
        if (Yii::app()->session['isAgent'] == 1) {
            $values['id_user'] = Yii::app()->session['id_user'];
        } else {
            $values['id_user'] = 1;
        }
        return $values;
    }

    public function actionTestMail()
    {

        $modelUser = User::model()->findByPk((int) Yii::app()->session['id_user']);

        if (!preg_match("/@/", $modelUser->email)) {

            echo json_encode(array(
                $this->nameSuccess => false,
                $this->nameMsg     => 'PLEASE CONFIGURE A VALID EMAIL TO USER ' . $modelUser->username,
            ));
            exit;
        }
        $modelSmtp = $this->abstractModel->findByPk((int) $_POST['id']);

        Yii::import('application.extensions.phpmailer.JPhpMailer');
        $language = Yii::app()->language == 'pt_BR' ? 'br' : Yii::app()->language;
        $mail     = new JPhpMailer;
        $mail->IsSMTP();
        $mail->SMTPAuth = true;

        $mail->Host       = $modelSmtp->host;
        $mail->SMTPSecure = $modelSmtp->encryption;
        $mail->Username   = $modelSmtp->username;
        $mail->Password   = $modelSmtp->password;
        $mail->Port       = $modelSmtp->port;
        $mail->SetFrom($modelSmtp->username);
        $mail->SetLanguage($language);
        $mail->Subject = 'MagnusBilling email test';
        $mail->AltBody = 'To view the message, please use an HTML compatible email viewer!';
        $mail->MsgHTML('<br>Hi, this is a email from MagnusBilling.');
        $mail->AddAddress($modelUser->email);
        $mail->CharSet = 'utf-8';

        ob_start();
        $mail->Send();
        $output = ob_get_contents();
        ob_end_clean();

        if (preg_match("/Erro/", $output)) {
            $sussess = false;
        } else {
            $output  = $this->msgSuccess;
            $sussess = true;
        }

        echo json_encode(array(
            $this->nameSuccess => $sussess,
            $this->nameMsg     => $output,
        ));
    }

}
