<?php header('Content-type: text/html; charset=ISO-8859-1');?>
<?php
/**
 * View to modulo "PlacetoPay".
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package    MagnusBilling
 * @author    Adilson Leffa Magnus.
 * @copyright    Todos os direitos reservados.
 * ###################################
 * =======================================
 * MagnusSolution.com <info@magnussolution.com>
 * 2016-03-18
 */
require_once 'lib/PlacetoPay/classes/EGM/PlacetoPay.php';

define('GNUPG_PROGRAM_PATH', '/usr/bin/gpg');
define('GNUPG_HOME_DIRECTORY', '/var/www/PlacetoPay/llaves');

// define los datos propios del comercio
define('P2P_CustomerSiteID', $modelMethodPay->P2P_CustomerSiteID);
define('P2P_KeyID', $modelMethodPay->P2P_KeyID);
define('P2P_Passphrase', $modelMethodPay->P2P_Passphrase);
define('P2P_RecipientKeyID', $modelMethodPay->P2P_RecipientKeyID);

$TotalAmount = $_GET['amount'];
$TotalAmount = $selectdAmount = preg_replace("/,/", '', $TotalAmount);

if ((isset($_GET['iva']) && $_GET['iva'] == 1) || strlen($modelUser->vat) > 1) {

    if (preg_match("/\+/", $modelUser->vat)) {
        $TotalAmount = $TotalAmount * ((intval($modelUser->vat) / 100) + 1);
    } else {
        $TotalAmount = $TotalAmount / ((intval($modelUser->vat) / 100) + 1);
    }
}

$TaxAmount            = is_numeric($modelMethodPay->P2P_tax_amount) ? $modelMethodPay->P2P_tax_amount : 0;
$DevolutionBaseAmount = (isset($_POST['DevolutionBaseAmount']) ? floatval($_POST['DevolutionBaseAmount']) : 0);

// estos parametros de entrada son opcionales
$ShopperID     = (!empty($_POST['ShopperID']) ? $_POST['ShopperID'] : false);
$ShopperIDType = (!empty($_POST['ShopperIDType']) ? $_POST['ShopperIDType'] : false);

$name                = (!empty($modelUser->firstname) ? $modelUser->firstname : false);
$modelUser->lastname = convertEncoding($name . ' ' . (!empty($modelUser->lastname) ? $modelUser->lastname : false));

$ShopperName = convertEncoding((!empty($modelUser->lastname) ? $modelUser->lastname : false));

$ShopperName    = preg_replace("/[0-9]|-|\/|\*|\.|\,/", "", $ShopperName);
$ShopperName    = preg_replace("/\+|\*/", "", $ShopperName);
$ShopperEmail   = convertEncoding((!empty($modelUser->email) ? $modelUser->email : false));
$ShopperAddress = convertEncoding((!empty($modelUser->address) ? $modelUser->address : false));
$ShopperCity    = convertEncoding((!empty($modelUser->city) ? $modelUser->city : false));
$ShopperState   = convertEncoding((!empty($modelUser->state) ? $modelUser->state : false));
$ShopperCountry = convertEncoding((!empty($modelUser->Country) ? $modelUser->Country : false));
$ShopperPhone   = convertEncoding((!empty($modelUser->phone) ? $modelUser->phone : false));
$ShopperMobile  = convertEncoding((!empty($modelUser->mobile) ? $modelUser->mobile : false));

$p2p = new PlacetoPay();

$p2p->setGPGProgramPath(GNUPG_PROGRAM_PATH);
$p2p->setGPGHomeDirectory(GNUPG_HOME_DIRECTORY);

// establece los datos del pagador, los datos de identificación son requeridos así como el nombre
// y el correo electrónico
$p2p->setPayerInfo($ShopperIDType, $ShopperID, $ShopperName, $ShopperEmail, $ShopperAddress, $ShopperCity, $ShopperState, $ShopperCountry, $ShopperPhone, $ShopperMobile);

// opcionalmente se pueden dar los datos del comprador
//$p2p->setBuyerInfo($BuyerIDType, $BuyerID, $BuyerName, $BuyerEmail, $BuyerAddress, $BuyerCity, $BuyerState, $BuyerCountry, $BuyerPhone, $BuyerMobile);

// por defecto la moneda de la plataforma es Pesos Colombianos (COP), si
// el pago es en dolares use USD, para euros EUR.
$p2p->setCurrency('COP');

// por defecto el idioma en que se muestran las plantillas en la plataforma es ES, si
// se desea que sea en ingles use EN
$p2p->setLanguage('ES');

// el campo de datos extra solo se requiere si usted desea enviar algun dato
// para su posterior procesamiento, esta informacion no es usada por
// PlacetoPay y la retorna de la misma forma en que le fue enviada
// $p2p->setExtraData($ExtraData);

// otros campos que adicionalmente se deseen enviar, estos solo se remiten a la plataforma
// y no son retrasmitidos por ella, puede hacer tantas llamadas de este metodo como
// informacion que desee agregar a la transaccion
// $p2p->addAdditionalData($keyword, $value);

// en general no se requiere establecer el codigo de compensacion, este solo
// aplica para pagos con VerifiedByVISA en el caso en que recaude a nombre de
// un tercero
// $p2p->setCompensation($Compensation);

// este método solo debe ser usado por las agencias de viaje cuando aplica el
// cobro de la tasa administrativa, hay una tabla especial para los códigos de
// servicio y de aerolinea.
// $p2p->setServiceFee($ServiceFee, $ServiceFeeTax, $ServiceFeeDevolution, $ServiceFeeCode);
// $p2p->setAirlineCode($AirlineCode);
// $p2p->setAirportTax($AirportTax);

// no sobre escriba la direccion de retorno a no ser que sea absolutamente
// necesario, recuerde que la base debe ser como minimo la establecida en
// la plataforma
$p2p->setOverrideReturn('http://' . $_SERVER['HTTP_HOST'] . '/mbilling/index.php/placetoPay');

// para las transacciones recurrentes, deberá indicarse la intención así como
// la periodicidad (Y anual, M menusual, D diaria), el intervalo aplicado a la
// periodicidad, por ejemplo si el intervalo es 3 y la periodicidad es M, entonces
// se estará haciendo el pago trimestralmente. El control de numero de veces que
// se realiza el pago se hace especificando una fecha limite o un numero de veces
// que ocurra un numero máximo de períodos en -1 es ilimitado así como un valor
// unlimited para la fecha, use uno de los dos parámetros
// $p2p->setRecurrent('Y', 1, -1, 'unlimited');

// obtiene la trama y la URL a la cual debe ser redirigido el flujo para que llegue
// a PlacetoPay, si vine en blanco entonces use el metodo getErrorMessage() para determinar
// el motivo
$modelRefill = Refill::model()->find('description LIKE "%pendiente%" AND payment = 0 AND id_user = :key',
    array(
        ':key' => $modelUser->id,
    ));

$modelAdmin = User::model()->find('id_group = 1');

if (count($modelRefill) > 0): ?>

     	<?php $rc = $p2p->queryPayment(P2P_CustomerSiteID, $modelRefill->id, 'COP', $modelRefill->credit);?>

    		<center>
	  		<table width="200" border="0" cellspacing="0" cellpadding="0">
	    			<tr>
	      			<td><img src="../../../resources/images/logo_placetopay.png" border=0/></td>
	    			</tr>
		    		<tr>
		      		<td>
		        			<table class="placetopay">
							<tr>
							</tr>
							<tr>
								<td class="placetopaytitulo" align="right">Company:&nbsp;</td>
								<td class="placetopayvalor"><?php echo $modelAdmin->company_name ?></td>
							</tr>
							<tr>
								<td class="placetopaytitulo" align="right">COD:&nbsp;</td>
								<td class="placetopayvalor"><?php echo $modelAdmin->doc ?></td>
							</tr>
							<tr>
								<td colspan="2"></td>
							</tr>

					          <tr>
					            	<td colspan="2"><font color=red><b><br><br><br>En este momento su orden #<?php echo $modelRefill->id ?> presenta un proceso de pago cuya transacci&oacute;n se encuentra PENDIENTE de
								recibir confirmaci&oacute;n por parte de su entidad financiera. Por favor espere unos
								minutos y vuelva a consultar mas tarde para verificar que su pago fue
								confirmado de forma exitosa. Si desea mayor informaci&oacute;n sobre el estado actual
								de su operaci&oacute;n puede comunicarse a nuestras l&iacuteneas de atenci&oacute;n al cliente al
								tel&eacute;fono si tiene alguna inquietud cont&aacute;ctenos al tel&eacute;fono <?php echo $modelAdmin->phone ?>.  V&iacute;a email <?php echo $modelAdmin->email ?> y pregunte por el estado de la transacci&oacute;n # <?php echo $p2p->getAuthorization() ?> .</b></font><br><br>
								</td>
					          </tr>
					          <tr>
					          </tr>
		        			</table>

		        			<p>&nbsp;</p>
		     		</td>
		    		</tr>
		    		<tr>
		      		<td>
		      			<div id="placetopay-footer"> <img src="https://www.placetopay.com/images/customers/PLACETOPAY.png" border="0" alt=""/> </div>
		      		</td>
		    		</tr>
	  		</table>
		</center>

		<div id="placetopay-header"></div>


     <?php
exit;
endif;

if (isset($_GET['id_refill'])) {
    $descr = 'Recarga PlaceToPay <font color=blue>pendiente</font>, referencia: ' . $_GET['id_refill'] . ' ';

    $modeRefill              = Refill::model()->findByPk((int) $_GET['id_refill']);
    $modeRefill->description = $descr;
    $modeRefill->save();

    $Reference = $_GET['id_refill'];
} else {
    $modeRefill          = new Refill();
    $modeRefill->id_user = $modelUser->id;
    $modeRefill->payment = 0;
    $modeRefill->credit  = $selectdAmount;
    $modeRefill->save();

    $Reference = $modeRefill->id;

    $descr = 'Recarga PlaceToPay <font color=blue>pendiente</font>, referencia: ' . $Reference . ' ';

    Refill::model()->updateByPk($Reference, array('description' => $descr));
}

$paymentRequest = $p2p->getPaymentRedirect(
    P2P_KeyID, P2P_Passphrase, P2P_RecipientKeyID,
    P2P_CustomerSiteID, $Reference, $TotalAmount, $TaxAmount, $DevolutionBaseAmount);

if (empty($paymentRequest)) {
    // TODO: genere algun feedback al cliente informando que no se pudo asegurar
    echo 'Error contact us';
} else {
    // TODO: haga el cambio en la BD asentando la operacion como pendiente

    // envie la trama a PlacetoPay
    header('Location: ' . $paymentRequest);
}

function convertEncoding($value)
{
    return mb_convert_encoding($value, 'ISO-8859-1', mb_detect_encoding($value, "UTF-8, ISO-8859-1, ASCII"));
}

?>

<style type="text/css">
	body {margin: 0px; font-family: Verdana, Arial, sans-serif; font-size: 10pt;}
	#placetopay-header { width: 550px; margin-left: auto; margin-right: auto; text-align: left; }
	#placetopay-content {
	width: 550px;
	margin-left: auto;
	margin-right: auto;
	border-radius: 10px;
	-moz-border-radius: 10px;
	-webkit-border-radius: 10px;
	-khtml-border-radius: 10px;
	-webkit-box-shadow: 1px 1px 5px 0px #ccc;
	box-shadow: 1px 1px 5px 0px #ccc;
	padding: 30px;
	text-align: center;
}
	#placetopay-footer { width: 550px; margin-left: auto; margin-right: auto; text-align: right; }
	h3 { padding: 5px; font-size: 16pt; }
	table.placetopay {width: 450px;margin-left: auto; margin-right: auto;}
	th.placetopay  {font-weight: bold; background-color: #000; color: #ffffff; padding: 5px; border-radius: 5px; -moz-border-radius: 5px; -webkit-border-radius: 5px; -khtml-border-radius: 5px; font-size: 11pt;}
	td.placetopayheader {font-size: 14pt; color: #000; font-weight: bold;}
	td.placetopaytitulo {font-size: 8pt; font-weight: bold; vertical-align: top}
	td.placetopayvalor {font-size: 10pt; text-align: justify;}
	th.placetopay1 {
	font-weight: bold;
	background-color: #00F;
	color: #ffffff;
	padding: 5px;
	border-radius: 5px;
	-moz-border-radius: 5px;
	-webkit-border-radius: 5px;
	-khtml-border-radius: 5px;
	font-size: 11pt;
}
</style>
