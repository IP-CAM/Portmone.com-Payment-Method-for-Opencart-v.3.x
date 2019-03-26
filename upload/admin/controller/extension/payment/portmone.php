<?php 
class ControllerPaymentPortmone extends Controller {
	private $error = array(); 

	public function index() {
		$this->language->load('payment/portmone');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
			
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('portmone', $this->request->post);
			
			$this->session->data['success'] = $this->language->get('text_success');

			$this->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
		}

		$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['text_enabled'] = $this->language->get('text_enabled');
		$this->data['text_disabled'] = $this->language->get('text_disabled');
		$this->data['text_payment'] = $this->language->get('text_payment');
		$this->data['text_authenticate'] = $this->language->get('text_authenticate');
		$this->data['text_all_zones'] = $this->language->get('text_all_zones');
		
		$this->data['entry_payee_id'] = $this->language->get('entry_payee_id');
		$this->data['entry_password'] = JText::_('JGLOBAL_PASSWORD');
		$this->data['entry_transaction'] = $this->language->get('entry_transaction');
		$this->data['entry_total'] = $this->language->get('entry_total');
		$this->data['entry_order_status'] = $this->language->get('entry_order_status');		
		$this->data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$this->data['entry_status'] = $this->language->get('entry_status');
		$this->data['entry_sort_order'] = $this->language->get('entry_sort_order');
		
		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		if (isset($this->error['error_payee_id'])) {
			$this->data['error_payee_id'] = $this->error['error_payee_id'];
		} else {
			$this->data['error_payee_id'] = '';
		}

		$this->load->model('localisation/language');

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_payment'),
			'href'      => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('payment/portmone', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);
				
		$this->data['action'] = $this->url->link('payment/portmone', 'token=' . $this->session->data['token'], 'SSL');
		
		$this->data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

		if (isset($this->request->post['portmone_payee_id'])) {
			$this->data['portmone_payee_id'] = $this->request->post['portmone_payee_id'];
		} else {
			$this->data['portmone_payee_id'] = $this->config->get('portmone_payee_id');
		} 
				
		if (isset($this->request->post['portmone_password'])) {
			$this->data['portmone_password'] = $this->request->post['portmone_password'];
		} else {
			$this->data['portmone_password'] = $this->config->get('portmone_password');
		}

		if (isset($this->request->post['portmone_preauthorise'])) {
			$this->data['portmone_preauthorise'] = $this->request->post['portmone_preauthorise'];
		} elseif($this->config->get('portmone_preauthorise')) {
			$this->data['portmone_preauthorise'] = $this->config->get('portmone_preauthorise');
		} else {
			$this->data['portmone_preauthorise'] = 'Y';
		}

		if (isset($this->request->post['portmone_total'])) {
			$this->data['portmone_total'] = $this->request->post['portmone_total'];
		} else {
			$this->data['portmone_total'] = $this->config->get('portmone_total');
		}

		if (isset($this->request->post['portmone_order_status_id'])) {
			$this->data['portmone_order_status_id'] = $this->request->post['portmone_order_status_id'];
		} elseif($this->config->get('portmone_order_status_id')) {
			$this->data['portmone_order_status_id'] = $this->config->get('portmone_order_status_id');
		} else {
			$this->data['portmone_order_status_id'] = 1; //pending
		}

		$this->load->model('localisation/order_status');
		
		$this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		if (isset($this->request->post['portmone_geo_zone_id'])) {
			$this->data['portmone_geo_zone_id'] = $this->request->post['portmone_geo_zone_id'];
		} else {
			$this->data['portmone_geo_zone_id'] = $this->config->get('portmone_geo_zone_id');
		} 
		
		$this->load->model('localisation/geo_zone');
										
		$this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		
		if (isset($this->request->post['portmone_status'])) {
			$this->data['portmone_status'] = $this->request->post['portmone_status'];
		} else {
			$this->data['portmone_status'] = $this->config->get('portmone_status');
		}
		
		if (isset($this->request->post['portmone_sort_order'])) {
			$this->data['portmone_sort_order'] = $this->request->post['portmone_sort_order'];
		} else {
			$this->data['portmone_sort_order'] = $this->config->get('portmone_sort_order');
		}
		

		$this->template = 'payment/portmone.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);
				
		$this->response->setOutput($this->render());
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'payment/portmone')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['portmone_payee_id']) {
				$this->error['error_payee_id'] = $this->language->get('error_payee_id');
		}

		if (!$this->error) {
			return true;
		} else {
			return false;
		}	
	}
}
?>