<?php
/**
 * Actions of module "Pedido".
 *
 * MagnusBilling <info@magnusbilling.com>
 * 05/06/2013
 */

class SiteController extends Controller
{

    public function actionIndex()
    {

        if (isset($_GET['paypal'])) {
            exit(isset($this->config['global']['paypal-softphone']) ? $this->config['global']['paypal-softphone'] : 0);
        }

        if (isset($_GET['callback'])) {
            exit(isset($this->config['global']['callback-softphone']) ? $this->config['global']['callback-softphone'] : 0);
        }

        $base_language = $this->config['global']['base_language'];
        echo 'window.lang = ' . json_encode($base_language) . ';';
        Yii::app()->session['language'] = $base_language;
        Yii::app()->setLanguage(Yii::app()->session['language']);

        $template = $this->config['global']['template'];
        echo 'window.theme = ' . json_encode($template) . ';';
        Yii::app()->session['theme'] = $template;

        $layout = $this->config['global']['layout'];
        echo 'window.layout = ' . json_encode($layout) . ';';
        Yii::app()->session['layout'] = $layout;

        $wallpaper = $this->config['global']['wallpaper'];
        echo 'window.wallpaper = ' . json_encode($wallpaper) . ';';
        Yii::app()->session['wallpaper'] = $wallpaper;
        echo 'window.colorMenu = ' . json_encode($this->config['global']['color_menu']) . ';';
        echo 'window.moduleExtra = ' . json_encode($this->config['global']['module_extra']) . ';';
        $reCaptchaKey = isset($this->config['global']['reCaptchaKey'])
        ? $this->config['global']['reCaptchaKey']
        : "";
        echo 'window.reCaptchaKey = ' . json_encode($reCaptchaKey) . ';';
    }
}
