<?php

/**
 * Url for moip ruturn http://ip/billing/index.php/pagSeguro .
 * https://pagseguro.uol.com.br/preferences/automaticReturn.jhtml
 */
class PagSeguroController extends Controller
{
    public function actionIndex()
    {

        /*$_POST = array(
        'ProdValor_1'     => '50.00',
        'TransacaoID'     => 'WVJ3YK6545HDVC',
        'tax'             => '0.00',
        'StatusTransacao' => 'Aprovado',
        'payment_status'  => 'Completed',
        'charset'         => 'windows-1252',
        'first_name'      => 'Anibal',
        'mc_fee'          => '4.00',
        'notify_version'  => '3.7',
        'custom'          => '',
        'payer_status'    => 'verified',
        'txn_id'          => '4Y190387AG109562T',
        'receiver_email'  => 'magnusadilsom@gmail.com',
        'payment_fee'     => '4.00',
        'receiver_id'     => 'HVUEC4FXXDVDB',
        'Referencia'      => '1458545545-user-110',
        );*/

        if (!isset($_POST) || count($_POST) < 5) {
            exit;
        }

        $filter = "payment_method = 'Pagseguro'";
        $params = array();

        if (isset($_GET['agent'])) {
            $filter .= " AND u.username = :username";
            $params = array(':username' => addslashes(strip_tags(trim($_GET['agent']))));
        } else {
            $filter .= " AND u.id = 1";
        }

        $modelMethodpay = Methodpay::model()->find(array(
            'condition' => $filter,
            'join'      => 'INNER JOIN pkg_user u ON t.id_user = u.id',
            'params'    => $params,
        ));

        if (!count($modelMethodpay)) {
            exit;
        }

        $identification = Util::getDataFromMethodPay($_POST['Referencia']);
        if (!is_array($identification)) {
            exit;
        }

        $username = $identification['username'];
        $id_user  = $identification['id_user'];

        $TOKEN = $modelMethodpay->pagseguro_TOKEN;
        define('TOKEN', $TOKEN);

        if (count($_POST) > 0) {
            // POST recebido, indica que é a requisição do NPI.
            $npi         = new PagSeguroNpi();
            $result      = $npi->notificationPost();
            $transacaoID = isset($_POST['TransacaoID']) ? $_POST['TransacaoID'] : '';

            if ($result == "VERIFICADO") {
                $StatusTransacao = $_POST['StatusTransacao'];
                $monto           = str_replace(",", ".", $_POST['ProdValor_1']);
                $description     = "Pagamento confirmado, PAGSEGURO:" . $transacaoID;

                if ($StatusTransacao == 'Aprovado') {

                    $modelUser = User::model()->findByPk((int) $id_user);

                    if (count($modelUser) && Refill::model()->countRefill($transacaoID, $modelUser->id) == 0) {
                        Yii::log($modelUser->id . ' ' . $monto . ' ' . $description . ' ' . $transacaoID, 'error');
                        UserCreditManager::releaseUserCredit($modelUser->id, $monto, $description, 1, $transacaoID);
                    }
                }
            } else {
                echo 'error';
            }
        } else {
            echo '<h3>Obrigado por efetuar a compra.</h3>';
        }
    }
}

class PagSeguroNpi
{
    private $timeout = 20; // Timeout em segundos
    public function notificationPost()
    {
        $postdata = 'Comando=validar&Token=' . TOKEN;
        foreach ($_POST as $key => $value) {
            $valued = $this->clearStr($value);
            $postdata .= "&$key=$valued";
        }
        return $this->verify($postdata);
    }
    private function clearStr($str)
    {
        if (!get_magic_quotes_gpc()) {
            $str = addslashes($str);
        }
        return $str;
    }

    private function verify($data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://pagseguro.uol.com.br/pagseguro-ws/checkout/NPI.jhtml");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $result = trim(curl_exec($curl));
        curl_close($curl);
        return $result;
    }

}
