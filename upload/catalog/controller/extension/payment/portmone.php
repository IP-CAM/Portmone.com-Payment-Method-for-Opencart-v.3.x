<?php
class ControllerExtensionPaymentPortmone extends Controller {
	private $transaction_currency = 'UAH';
	private $gateway = 'https://www.portmone.com.ua/gateway/';
	private $login = 'login';
	private $portmone_xml;

	public function index() {
		$this->language->load('extension/payment/portmone');

		$data['button_confirm'] = $this->language->get('button_confirm');

		$data['action'] = $this->gateway;
		$data['payee_id'] = $this->config->get('payment_portmone_payee_id');

		$this->load->model('checkout/order');

		$order_id = $this->session->data['order_id'];
		$data['order_id'] = $order_id;

		$order_info = $this->model_checkout_order->getOrder($order_id);

		$data['description'] = sprintf($this->language->get('text_description'), $order_id, $order_info['firstname'].' '.$order_info['lastname']);

		$data['amount'] = $this->currency->format(
			$order_info['total'],
			$this->transaction_currency,
			$this->currency->getValue($this->transaction_currency),
			false
		);

		$data['language']      = $this->language->get('code');
		$data['preauthorize'] = $this->config->get('payment_portmone_preauthorise');
		$data['url_confirm']   = $this->url->link('extension/payment/portmone/confirm');

		if($order_info['currency_code'] != $this->transaction_currency) {
			$data['payment_note'] = sprintf($this->language->get('text_payment_note'),
				$this->transaction_currency,
				$this->currency->format(
				$order_info['total'],
				$this->transaction_currency,
				$this->currency->getValue($this->transaction_currency)
			));
		}
		return $this->load->view('extension/payment/portmone', $data);
	}

	public function confirm()
	{
		if(!isset($this->session->data['order_id']))
			return;
		$order_id = $this->session->data['order_id'];
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);

		$this->language->load('checkout/success');
		$this->language->load('extension/payment/portmone');

		$this->document->setTitle($this->language->get('text_card_error'));

		$data['breadcrumbs'] = array();

		/*$data['breadcrumbs'][] = array(
          'href'      => $this->url->link('common/home'),
          'text'      => $this->language->get('text_home'),
          'separator' => false
        ); */

		$data['breadcrumbs'][] = array(
			'href'      => $this->url->link('checkout/cart'),
			'text'      => $this->language->get('text_basket'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'href'      => $this->url->link('checkout/checkout', '', 'SSL'),
			'text'      => $this->language->get('text_checkout'),
			'separator' => $this->language->get('text_separator')
		);

		if((!isset($this->request->post['SHOPORDERNUMBER'])
			|| !isset($this->request->post['BILL_AMOUNT'])
			|| $this->request->post['SHOPORDERNUMBER'] != $order_id
			|| $this->request->post['BILL_AMOUNT'] != $this->currency->format(
				$order_info['total'],
				$this->transaction_currency,
				$order_info['uah_value'] ? $order_info['uah_value'] : $this->currency->getValue($this->transaction_currency),
				false
				))) {
			$data['text_try_again'] = $this->language->get('text_try_again');
			$data['try_again_link'] = $this->url->link('checkout/checkout');
			$data['text_cancel'] = $this->language->get('text_cancel');
			$data['cancel_link'] = $this->url->link('payment/portmone/cancel');
			$data['text_error_note'] = $this->language->get('text_card_error');
			$data['server_response'] = isset($this->request->post['RESULT']) ? $this->request->post['RESULT'] : '... Unable to process the card.';

			$data['breadcrumbs'][] = array(
				'href'      => '#',
				'text'      => JText::_('ERROR'),
				'separator' => $this->language->get('text_separator')
			);
            return $this->load->view('extension/payment/portmone_failure', $data);
		}
		$data['breadcrumbs'][] = array(
			'href'      => $this->url->link('checkout/success'),
			'text'      => $this->language->get('text_success'),
			'separator' => $this->language->get('text_separator')
		);
		if(isset($this->request->post['APPROVALCODE']) && isset($this->request->post['RECEIPT_URL'])) {
			$comment = $this->session->data['transaction_note'] = PapertablesHelper::getString('orders', 'card_success', '', '', array(
				'auth_code' => $this->request->post['APPROVALCODE'],
				'receipt_url' => $this->request->post['RECEIPT_URL']
			));
		} else {
			$comment = '';
		}
		$this->model_checkout_order->update($order_id, PENDING, $comment);
		$data['continue'] = $this->url->link('checkout/success');
		return $this->load->view('extension/payment/portmone_success', $data);
	}

	private function getXmlValue($path)
	{
		$a = $this->portmone_xml->xpath(strtoupper($path));
		$value = (string)array_pop($a);
		return trim($value);
	}

	public function confirmPayment() {
		$order_id = $this->session->data['order_id'];
		$this->load->model('checkout/order');
		$comment = "";
		$pass = $this->config->get('payment_portmone_password');
		if($pass) {
			$status = $this->model_checkout_order->getOrderStatusId($order_id);
			$data = array(
				'method' => 'result',
				'payee_id' => $this->config->get('payment_portmone_payee_id'),
				'login' => $this->login,
				'password' => $pass,
				'shop_order_number' => $this->session->data['order_id'],
				'status' => '',
				);

			// use key 'http' even if you send the request to https://...
			$options = array(
				'http' => array(
					'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
					'method'  => 'POST',
					'content' => http_build_query($data)
				)
			);
			$context  = stream_context_create($options);
			$result = file_get_contents($this->gateway, false, $context);
			$error = "";
			if ($result === FALSE) {
				$error = "No data";
			} else {
				@$this->portmone_xml = simplexml_load_string($result);
				if (!$this->portmone_xml instanceof SimpleXMLElement) {
					$error = 'Wrong type of data';
				} else {
					$error_code = $this->getXmlValue('/portmoneresult/orders/order/error_code');
					$status = $this->getXmlValue('/portmoneresult/orders/order/status');
					if ((int)$error_code != 0 || $status != 'PAYED') {
						$error = $this->getXmlValue('/portmoneresult/orders/order/error_message');
					} else {
						$amount = $this->getXmlValue('/portmoneresult/orders/order/bill_amount');
						$order_info = $this->model_checkout_order->getOrder($order_id);
						if($amount != $this->currency->format(
                                $order_info['total'],
                                $this->transaction_currency,
                                $order_info['uah_value'] ? $order_info['uah_value'] : $this->currency->getValue($this->transaction_currency),
                                false
                            )) {
                            $error = "Wrong ammount of transaction.<br/>";
							$this->model_checkout_order->update($order_id, PENDING, $this->portmone_xml->asXML());
                        }
					}
				}
			}
		}
		if(empty($error)) {
			$this->model_checkout_order->confirm($order_id, $this->config->get('payment_portmone_order_status_id '), $comment);
			$return = '[]';
		} else {
			$return = json_encode(array('err'=>$error));
		}
		$this->response->setOutput($return);
	}

	public function payed_info() {
		if(!isset($this->request->post['data'])) {
			return;
		}
		$data = html_entity_decode($this->request->post['data']);
		@$this->portmone_xml = simplexml_load_string($data);
		if (!$this->portmone_xml instanceof SimpleXMLElement) {
			return;
		}
		$payee_id =$this->config->get('payment_portmone_payee_id');
		$code = $this->getXmlValue('/bills/bill/payee/code');
		if(!$code) {
			$code = $this->getXmlValue('/pay_orders/pay_order/payee/code');
		}
		if(!$code) {
			return;
		}
		if($code!=$payee_id) {
			$error_code = 1;
			$description = "Payee code doesn't match";
		} else {
			$error_code = 0;
			$description = "OK";
		}
		$reply = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<RESULT>
	<ERROR_CODE>$error_code</ERROR_CODE>
	<REASON>$description</REASON>
</RESULT>
XML;
		$this->response->setOutput($reply);
	}

	public function cancel() {
		if (!isset($this->session->data['order_id']))
			return;
		$order_id = $this->session->data['order_id'];
		$this->load->model('checkout/order');
		$this->model_checkout_order->update($order_id, CANCELED, '', true);

		$this->cart->clear();

		unset($this->session->data['shipping_method']);
		unset($this->session->data['shipping_methods']);
		unset($this->session->data['payment_method']);
		unset($this->session->data['payment_methods']);
		unset($this->session->data['guest']);
		unset($this->session->data['comment']);
		unset($this->session->data['order_id']);
		unset($this->session->data['coupon']);
		unset($this->session->data['reward']);
		unset($this->session->data['voucher']);
		unset($this->session->data['vouchers']);
		unset($this->session->data['totals']);
		unset($this->session->data['transaction_note']);
		$this->language->load('checkout/success');

		$this->document->setTitle($this->language->get('heading_cancel_title'));

		$data['breadcrumbs'] = array();

		/*$data['breadcrumbs'][] = array(
          'href'      => $this->url->link('common/home'),
          'text'      => $this->language->get('text_home'),
          'separator' => false
        ); */

		$data['breadcrumbs'][] = array(
			'href'      => $this->url->link('checkout/cart'),
			'text'      => $this->language->get('text_basket'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'href'      => '#',
			'text'      => $this->language->get('text_checkout'),
			'separator' => $this->language->get('text_separator')
		);

		$data['heading_title'] = $this->language->get('heading_cancel_title');

		$data['text_message'] = $this->language->get('text_cancel_order');

		$data['button_continue'] = $this->language->get('button_continue');

		$data['continue'] = $this->url->link('common/home');

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/cancel.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/checkout/cancel.tpl';
		} else {
			$this->template = 'default/template/checkout/cancel.tpl';
		}

		$this->response->setOutput($this->render());
	}


}
