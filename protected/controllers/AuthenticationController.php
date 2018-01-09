<?php
/**
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
 *
 */

/*
<?php
if (isset($_GET['user']) && isset($_GET['password']))
header('Location: http://186.225.143.142/mbilling/index.php/authentication/login?remote=1&user='.$_GET['user'].'&password='.strtoupper(MD5($_GET['password'])));
?>

<form action="" method="GET">
<input type="text" name="user" size="18" placeholder="Username">
<input type="password" name="password" size="18" placeholder="Password">
<button type="submit">Login</button>
</form>
 */

class AuthenticationController extends Controller
{
    private $menu = array();

    public function actionLogin()
    {
        $user     = $_REQUEST['user'];
        $password = $_REQUEST['password'];

        $modelUser = User::model()->find("username = :user", array(':user' => $user));
        if (isset($modelUser->idGroup->idUserType->id) && $modelUser->idGroup->idUserType->id == 1) {
            $password = sha1($password);
        }

        if (isset($_REQUEST['remote'])) {
            $modelSip = $this->remoteLogin($user, $password);
            $user     = $modelSip->idUser->username;
            $password = $modelSip->idUser->password;
        }

        $condition = "((username COLLATE utf8_bin = :user OR email LIKE :user) AND password COLLATE utf8_bin = :pass) OR ";
        $condition .= " (id = (SELECT id_user FROM pkg_sip WHERE name COLLATE utf8_bin = :user AND secret COLLATE utf8_bin = :pass) )";

        $modelUser = User::model()->find(
            array(
                'condition' => $condition,
                'params'    => array(':user' => $user, ':pass' => $password),
            ));

        $loginkey = isset($_POST['loginkey']) ? $_POST['loginkey'] : false;

        if (strlen($loginkey) > 5 && $loginkey == $modelUser->loginkey) {
            $modelUser->active   = 1;
            $modelUser->loginkey = '';
            $modelUser->save();

            $mail = new Mail(Mail::$TYPE_SIGNUPCONFIRM, $modelUser->id);
            $mail->send();
        }

        if (!count($modelUser)) {
            Yii::app()->session['logged'] = false;
            echo json_encode(array(
                'success' => false,
                'msg'     => 'Username or password is wrong',
            ));
            $nameMsg = $this->nameMsg;

            $info = 'Username or password is wrong - User ' . $user . ' from IP - ' . $_SERVER['REMOTE_ADDR'];
            Yii::log($info, 'error');
            MagnusLog::insertLOG(1, $info);

            return;
        }

        if ($modelUser->active != 1) {
            Yii::app()->session['logged'] = false;
            echo json_encode(array(
                'success' => false,
                'msg'     => 'Username is disabled',
            ));

            $info = 'Username ' . $user . ' is disabled';
            MagnusLog::insertLOG(1, $info);

            return;
        }

        $idUserType = $modelUser->idGroup->idUserType->id;

        Yii::app()->session['isAdmin']       = $idUserType == 1 ? true : false;
        Yii::app()->session['isAgent']       = $idUserType == 2 ? true : false;
        Yii::app()->session['isClient']      = $idUserType == 3 ? true : false;
        Yii::app()->session['isClientAgent'] = isset($modelUser->id_user) && $modelUser->id_user > 1 ? true : false;
        Yii::app()->session['id_plan']       = $modelUser->id_plan;
        Yii::app()->session['credit']        = isset($modelUser->credit) ? $modelUser->credit : 0;
        Yii::app()->session['username']      = $modelUser->username;
        Yii::app()->session['logged']        = true;
        Yii::app()->session['id_user']       = $modelUser->id;
        Yii::app()->session['id_agent']      = is_null($modelUser->id_user) ? 1 : $modelUser->id_user;
        Yii::app()->session['name_user']     = $modelUser->firstname . ' ' . $modelUser->lastname;
        Yii::app()->session['id_group']      = $modelUser->id_group;
        Yii::app()->session['user_type']     = $idUserType;
        Yii::app()->session['systemName']    = $_SERVER['SCRIPT_FILENAME'];
        Yii::app()->session['session_start'] = time();
        Yii::app()->session['userCount']     = User::model()->count("credit != 0");

        if ($modelUser->googleAuthenticator_enable > 0) {

            require_once 'lib/GoogleAuthenticator/GoogleAuthenticator.php';
            $ga = new PHPGangsta_GoogleAuthenticator();

            if ($modelUser->google_authenticator_key != '') {
                $secret                                       = $modelUser->google_authenticator_key;
                Yii::app()->session['newGoogleAuthenticator'] = false;
                if ($modelUser->googleAuthenticator_enable == 2) {
                    Yii::app()->session['showGoogleCode'] = true;
                } else {
                    Yii::app()->session['showGoogleCode'] = false;
                }
            } else {
                $secret                              = $ga->createSecret();
                $modelUser->google_authenticator_key = $secret;
                $modelUser->save();
                Yii::app()->session['newGoogleAuthenticator'] = true;

            }

            Yii::app()->session['googleAuthenticatorKey'] = $ga->getQRCodeGoogleUrl('MagnusBilling-' . $modelUser->username . '-' . $modelUser->id, $secret);

            Yii::app()->session['checkGoogleAuthenticator'] = true;
        } else {
            Yii::app()->session['showGoogleCode']           = false;
            Yii::app()->session['newGoogleAuthenticator']   = false;
            Yii::app()->session['checkGoogleAuthenticator'] = false;
        }

        MagnusLog::insertLOG(1, 'Username Login on the panel - User ' . Yii::app()->session['username']);

        if (isset($_REQUEST['remote'])) {
            header("Location: ../..");
        }
        echo json_encode(array(
            'success' => Yii::app()->session['username'],
            'msg'     => Yii::app()->session['name_user'],
        ));

    }

    private function mountMenu()
    {
        $modelGroupModule = GroupModule::model()->getGroupModule(Yii::app()->session['id_group'], Yii::app()->session['isClient'], Yii::app()->session['id_user']);

        Yii::app()->session['action'] = $this->getActions($modelGroupModule);
        Yii::app()->session['menu']   = $this->getMenu($modelGroupModule);
    }
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

    public function actionLogoff()
    {
        Yii::app()->session['logged']        = false;
        Yii::app()->session['id_user']       = false;
        Yii::app()->session['id_agent']      = false;
        Yii::app()->session['name_user']     = false;
        Yii::app()->session['menu']          = array();
        Yii::app()->session['action']        = array();
        Yii::app()->session['currency']      = false;
        Yii::app()->session['language']      = false;
        Yii::app()->session['isAdmin']       = true;
        Yii::app()->session['isClient']      = false;
        Yii::app()->session['isAgent']       = false;
        Yii::app()->session['isClientAgent'] = false;
        Yii::app()->session['id_plan']       = false;
        Yii::app()->session['credit']        = false;
        Yii::app()->session['username']      = false;
        Yii::app()->session['id_group']      = false;
        Yii::app()->session['user_type']     = false;
        Yii::app()->session['decimal']       = false;
        Yii::app()->session['licence']       = false;
        Yii::app()->session['email']         = false;
        Yii::app()->session['userCount']     = false;
        Yii::app()->session['systemName']    = false;
        Yii::app()->session['base_country']  = false;
        Yii::app()->session['version']       = false;

        Yii::app()->session->clear();
        Yii::app()->session->destroy();

        echo json_encode(array(
            'success' => true,
        ));
    }

    public function actionCheck()
    {
        if (Yii::app()->session['logged']) {

            $this->mountMenu();
            $modelGroupUserGroup = GroupUserGroup::model()->find('id_group_user = :key',
                array(':key' => Yii::app()->session['id_group']));
            Yii::app()->session['adminLimitUsers']                = count($modelGroupUserGroup);
            Yii::app()->session['licence']                        = $this->config['global']['licence'];
            Yii::app()->session['email']                          = $this->config['global']['admin_email'];
            Yii::app()->session['currency']                       = $this->config['global']['base_currency'];
            Yii::app()->session['language']                       = $this->config['global']['base_language'];
            Yii::app()->session['decimal']                        = $this->config['global']['decimal_precision'];
            Yii::app()->session['base_country']                   = $this->config['global']['base_country'];
            Yii::app()->session['version']                        = $this->config['global']['version'];
            Yii::app()->session['asterisk_version']               = $this->config['global']['asterisk_version'];
            Yii::app()->session['social_media_network']           = $this->config['global']['social_media_network'];
            Yii::app()->session['fm_transfer_show_selling_price'] = preg_replace("/%/", "", $this->config['global']['fm_transfer_show_selling_price']);

            $id_user                        = Yii::app()->session['id_user'];
            $id_agent                       = Yii::app()->session['id_agent'];
            $nameUser                       = Yii::app()->session['name_user'];
            $logged                         = Yii::app()->session['logged'];
            $menu                           = Yii::app()->session['menu'];
            $currency                       = Yii::app()->session['currency'];
            $language                       = Yii::app()->session['language'];
            $isAdmin                        = Yii::app()->session['isAdmin'];
            $isClient                       = Yii::app()->session['isClient'];
            $isAgent                        = Yii::app()->session['isAgent'];
            $isClientAgent                  = Yii::app()->session['isClientAgent'];
            $id_plan                        = Yii::app()->session['id_plan'];
            $credit                         = Yii::app()->session['credit'];
            $username                       = Yii::app()->session['username'];
            $id_group                       = Yii::app()->session['id_group'];
            $user_type                      = Yii::app()->session['user_type'];
            $decimal                        = Yii::app()->session['decimal'];
            $licence                        = Yii::app()->session['licence'];
            $email                          = Yii::app()->session['email'];
            $userCount                      = Yii::app()->session['userCount'];
            $base_country                   = Yii::app()->session['base_country'];
            $version                        = Yii::app()->session['version'];
            $social_media_network           = Yii::app()->session['social_media_network'];
            $fm_transfer_show_selling_price = Yii::app()->session['fm_transfer_show_selling_price'];
            $checkGoogleAuthenticator       = Yii::app()->session['checkGoogleAuthenticator'];
            $googleAuthenticatorKey         = Yii::app()->session['googleAuthenticatorKey'];
            $newGoogleAuthenticator         = Yii::app()->session['newGoogleAuthenticator'];
            $showGoogleCode                 = Yii::app()->session['showGoogleCode'];
        } else {
            $id_user                        = false;
            $id_agent                       = false;
            $nameUser                       = false;
            $logged                         = false;
            $menu                           = array();
            $currency                       = false;
            $language                       = false;
            $isAdmin                        = false;
            $isClient                       = false;
            $isAgent                        = false;
            $isClientAgent                  = false;
            $id_plan                        = false;
            $credit                         = false;
            $username                       = false;
            $id_group                       = false;
            $user_type                      = false;
            $decimal                        = false;
            $licence                        = false;
            $email                          = false;
            $userCount                      = false;
            $base_country                   = false;
            $version                        = false;
            $fm_transfer_show_selling_price = false;
            $checkGoogleAuthenticator       = false;
            $googleAuthenticatorKey         = false;
            $newGoogleAuthenticator         = false;
            $showGoogleCode                 = false;
            $social_media_network           = false;
        }
        $language = isset(Yii::app()->session['language']) ? Yii::app()->session['language'] : Yii::app()->sourceLanguage;
        $theme    = isset(Yii::app()->session['theme']) ? Yii::app()->session['theme'] : 'blue-neptune';

        if (file_exists('resources/images/logo_custom.png')) {
            Yii::log('file existe', 'info');
        }

        echo json_encode(array(
            'id'                             => $id_user,
            'id_agent'                       => $id_agent,
            'name'                           => $nameUser,
            'success'                        => $logged,
            'menu'                           => $menu,
            'language'                       => $language,
            'theme'                          => $theme,
            'currency'                       => $currency,
            'language'                       => $language,
            'isAdmin'                        => $isAdmin,
            'isClient'                       => $isClient,
            'isAgent'                        => $isAgent,
            'isClientAgent'                  => $isClientAgent,
            'id_plan'                        => $id_plan,
            'credit'                         => $credit,
            'username'                       => $username,
            'id_group'                       => $id_group,
            'user_type'                      => $user_type,
            'decimal'                        => $decimal,
            'licence'                        => $licence,
            'email'                          => $email,
            'userCount'                      => $userCount,
            'base_country'                   => $base_country,
            'version'                        => $version,
            'social_media_network'           => $social_media_network,
            'fm_transfer_show_selling_price' => $fm_transfer_show_selling_price,
            'asterisk_version'               => Yii::app()->session['asterisk_version'],
            'checkGoogleAuthenticator'       => $checkGoogleAuthenticator,
            'googleAuthenticatorKey'         => $googleAuthenticatorKey,
            'newGoogleAuthenticator'         => $newGoogleAuthenticator,
            'showGoogleCode'                 => $showGoogleCode,
            'logo'                           => file_exists('resources/images/logo_custom.png') ? 'resources/images/logo_custom.png' : 'resources/images/logo.png',
        ));
    }

    public function actionGoogleAuthenticator()
    {
        require_once 'lib/GoogleAuthenticator/GoogleAuthenticator.php';

        $ga = new PHPGangsta_GoogleAuthenticator();

        $modelUser = User::model()->findByPk((int) Yii::app()->session['id_user']);

        //Yii::log(print_r($sql,true),'info');
        $secret      = $modelUser->google_authenticator_key;
        $oneCodePost = $_POST['oneCode'];

        $checkResult = $ga->verifyCode($secret, $oneCodePost, 2);

        if ($checkResult) {
            $sussess                                        = true;
            Yii::app()->session['checkGoogleAuthenticator'] = false;
            $modelUser->googleAuthenticator_enable          = 1;
            $modelUser->save();
        } else {
            $sussess = false;
        }
        //$sussess = true;

        echo json_encode(array(
            'success' => $sussess,
            'msg'     => Yii::app()->session['name_user'],
        ));

    }

    public function actionChangePassword()
    {
        $passwordChanged = false;
        $id_user         = Yii::app()->session['id_user'];
        $currentPassword = $_POST['current_password'];
        $newPassword     = $_POST['password'];
        $isClient        = Yii::app()->session['isClient'];
        $errors          = '';

        $moduleUser = User::model()->find("id LIKE :id_user AND password LIKE :currentPassword",
            array(
                ":id_user"         => $id_user,
                ":currentPassword" => $currentPassword,
            ));

        if (count($moduleUser)) {
            try
            {
                $moduleUser->password = $newPassword;
                $passwordChanged      = $moduleUser->save();

            } catch (Exception $e) {
                $errors = $this->getErrorMySql($e);
            }

            $msg = $passwordChanged ? yii::t('yii', 'Password change success!') : $errors;
        } else {
            $msg = yii::t('yii', 'Current Password incorrect.');
        }

        echo json_encode(array(
            'success' => $passwordChanged,
            'msg'     => $msg,
        ));
    }

    public function actionImportLogo()
    {
        if (isset($_FILES['logo']['tmp_name']) && strlen($_FILES['logo']['tmp_name']) > 3) {

            $uploaddir  = "resources/images/";
            $typefile   = explode('.', $_FILES["logo"]["name"]);
            $uploadfile = $uploaddir . 'logo_custom.png';
            move_uploaded_file($_FILES["logo"]["tmp_name"], $uploadfile);
        }

        echo json_encode(array(
            'success' => true,
            'msg'     => 'Refresh the system to see the new logo',
        ));
    }

    public function actionImportWallpapers()
    {
        if (isset($_FILES['logo']['tmp_name']) && strlen($_FILES['logo']['tmp_name']) > 3) {

            $uploaddir  = "resources/images/wallpapers/";
            $typefile   = explode('.', $_FILES["logo"]["name"]);
            $uploadfile = $uploaddir . 'Customization.jpg';
            move_uploaded_file($_FILES["logo"]["tmp_name"], $uploadfile);
        }

        $modelConfiguration               = Configuration::model()->find("config_key LIKE 'wallpaper'");
        $modelConfiguration->config_value = 'Customization';
        try {
            $success = $modelConfiguration->save();
            $msg     = Yii::t('yii', 'Refresh the system to see the new logo');
        } catch (Exception $e) {
            $success = false;
            $msg     = $this->getErrorMySql($e);
        }
        echo json_encode(array(
            'success' => $success,
            'msg'     => $msg,
        ));

        echo json_encode(array(
            'success' => true,
            'msg'     => $msg,
        ));
    }

    public function actionForgetPassword()
    {
        if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {

            $modelUser = User::model()->findAll('email =:key', array(':key' => $_POST['email']));
            if (count($modelUser) > 1) {
                $success = false;
                $msg     = "Email in use more than 1 account, contact administrator";
            } else if (count($modelUser)) {

                if ($modelUser[0]->idGroup->idUserType->id == 1) {

                    $success = false;
                    $msg     = "You can't request admin password";
                } else {

                    $mail = new Mail(Mail::$TYPE_FORGETPASSWORD, $modelUser[0]->id);
                    try {
                        $mail->send();
                    } catch (Exception $e) {
                        //
                    }
                    $success = true;
                    $msg     = "Your password was sent to your email";
                }
            } else {
                $success = false;
                $msg     = "Email not found";
            }
        } else {
            $success = false;
            $msg     = "Email not found";
        }

        echo json_encode(array(
            'success' => $success,
            'msg'     => $msg,
        ));

    }
}
