<?php
/**
 * Actions of module "Authentication".
 *
 * MagnusBilling <info@magnusbilling.com>
 * 15/04/2013
 */

class JoomlaController extends Controller
{
    private $menu = array();

    private function getActions($modules)
    {
        $actions = array();

        foreach ($modules as $key => $value) {
            if (!empty($value['action'])) {
                $actions[$value['module']] = $value['action'];
            }
        }

        return $actions;
    }

    private function getMenu($modules)
    {
        $menu = array();

        foreach ($modules as $value) {
            if ($value['module'] != 'buycredit') {
                if (!$value['show_menu']) {
                    continue;
                }
            }

            if (empty($value['id_module'])) {
                array_push($menu, array(
                    'text'    => preg_replace("/ Module/", "", $value['text']),
                    'iconCls' => $value['icon_cls'],
                    'rows'    => $this->getSubMenu($modules, $value['id']),
                ));
            }
        }

        return $menu;
    }

    private function getSubMenu($modules, $idOwner)
    {
        $subModulesOwner = Util::arrayFindByProperty($modules, 'id_module', $idOwner);
        $subMenu         = array();

        foreach ($subModulesOwner as $value) {

            if ($value['module'] != 'buycredit') {
                if (!$value['show_menu']) {
                    continue;
                }
            }

            if (!empty($value['module'])) {
                array_push($subMenu, array(
                    'text'             => $value['text'],
                    'iconCls'          => $value['icon_cls'],
                    'module'           => $value['module'],
                    'action'           => $value['action'],
                    'leaf'             => true,
                    'createShortCut'   => $value['createShortCut'],
                    'createQuickStart' => $value['createQuickStart'],
                ));
            } else {
                array_push($subMenu, array(
                    'text'    => $value['text'],
                    'iconCls' => $value['icon_cls'],
                    'rows'    => $this->getSubMenu($modules, $value['id']),
                ));
            }
        }

        return $subMenu;
    }

    public function actionEdit()
    {

        if (isset($_POST['key'])) {

            $modelUser = User::model()->find('username = :key', array('key' => $_POST['user']));
            if (count($modelUser)) {
                $key = sha1($modelUser->username . $modelUser->password);

                if ($key == $_POST['key']) {
                    if (strlen($_POST['password'])) {
                        $modelUser->password = $_POST['password'];
                    }
                    $modelUser->email = $_POST['email'];
                    $modelUser->save();
                } else {
                    print_r($_POST);
                    echo "<br>" . $key;
                }
            }
        }

        exit;
    }

    public function actionSignup()
    {

        if (isset($_GET['user']) && $_GET['user'] == 'roud') {
            $is_android           = true;
            $_REQUEST['user']     = Util::getNewUsername();
            $_REQUEST['password'] = trim(Util::generatePassword(20, true, true, true, false));
            $_REQUEST['active']   = 0;
        }
        $modelUser = User::model()->find('email = :key', array(':key' => $_REQUEST['email']));

        if (count($modelUser)) {
            if (isset($is_android)) {
                exit(Yii::t('yii', 'Email already in use'));
            } else {
                exit('COM_USERS_PROFILE_EMAIL1_MESSAGE');
            }

        }
        $modelUser = User::model()->find('username = :key', array(':key' => $_REQUEST['user']));

        if (count($modelUser)) {
            exit('COM_USERS_PROFILE_USERNAME_MESSAGE');
        }

        $modelPlan = Plan::model()->find('signup = 1');

        if (count($modelPlan)) {
            $id_plan = $modelPlan->id;
        } else {
            exit('No plan active');
        }

        $credit = $modelPlan->ini_credit;

        if (isset($_REQUEST['id_group'])) {
            $id_group = $_REQUEST['id_group'];

        } else {

            $modelGroupUser = GroupUser::model()->findAllByAttributes(array("id_user_type" => 3));
            if (count($modelGroupUser)) {
                $id_group = $modelGroupUser[0]['id'];
            } else {
                exit('No plan group for user');
            }
        }

        $callingcard_pin = Util::getNewLock_pin();

        $modelUser                  = new User();
        $modelUser->username        = $_REQUEST['user'];
        $modelUser->password        = $_REQUEST['password'];
        $modelUser->email           = $_REQUEST['email'];
        $modelUser->firstname       = $_REQUEST['firstname'];
        $modelUser->id_group        = $id_group;
        $modelUser->id_plan         = $id_plan;
        $modelUser->active          = $_REQUEST['active'];
        $modelUser->callingcard_pin = $callingcard_pin;
        $modelUser->id_user         = 1;
        $modelUser->credit          = $credit;
        if (isset($_REQUEST['phone']) && strlen($_REQUEST['phone']) > 5) {
            $modelUser->phone = $_REQUEST['phone'];
        }
        $success = $modelUser->save();

        if ($success) {

            $modelSip              = new Sip();
            $modelSip->id_user     = $modelUser->id;
            $modelSip->accountcode = $modelUser->username;
            $modelSip->name        = $modelUser->username;
            $modelSip->allow       = 'g729,gsm,alaw,ulaw';
            $modelSip->host        = 'dynamic';
            $modelSip->insecure    = 'no';
            $modelSip->defaultuser = $modelUser->username;
            $modelSip->secret      = $modelUser->password;
            if (strlen($modelUser->phone) > 5) {
                $modelSip->callerid   = $modelUser->phone;
                $modelSip->cid_number = $modelUser->phone;
            }
            $modelSip->save();

        }

        if (isset($is_android)) {

            AsteriskAccess::instance()->generateSipPeers();

            $mail = new Mail(Mail::$TYPE_SIGNUP, $modelUser->id);
            $mail->send();

            exit('Success:' . $modelUser->username . ":" . $modelUser->password);
        }
    }

    public function actionJoomlaMenu()
    {

        $user      = trim($_POST['user']);
        $password  = trim($_POST['password']);
        $condition = "(username LIKE :user OR email LIKE :user)";

        $sql     = "SELECT * FROM pkg_user WHERE $condition";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":user", $user, PDO::PARAM_STR);
        $result = $command->queryAll();

        if (!isset($result[0]['username']) || sha1($result[0]['password']) != $password) {
            exit;
        }

        $sql = "SELECT m.id, action, show_menu, text, module, icon_cls, m.id_module, gm.createShortCut, gm.createQuickStart FROM
                            pkg_group_module gm INNER JOIN pkg_module m ON gm.id_module = m.id WHERE
                            id_group = :id_group";
        $command = Yii::app()->db->createCommand($sql);
        $command->bindValue(":id_group", $result[0]['id_group'], PDO::PARAM_STR);
        $result = $command->queryAll();

        Yii::app()->session['action']   = $this->getActions($result);
        Yii::app()->session['menu']     = $this->getMenu($result);
        Yii::app()->session['currency'] = $this->config['global']['base_currency'];
        Yii::app()->session['language'] = $this->config['global']['base_language'];
        Yii::app()->session['decimal']  = $this->config['global']['decimal_precision'];

        echo json_encode(array(
            'id'       => Yii::app()->session['menu'],
            'currency' => Yii::app()->session['currency'],
            'language' => Yii::app()->session['language'],
            'decimal'  => Yii::app()->session['decimal'],
        ));
    }

}
