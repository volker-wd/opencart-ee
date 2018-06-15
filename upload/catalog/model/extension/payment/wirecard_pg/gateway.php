<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

/**
 * Class ModelExtensionPaymentGateway
 *
 * @since 1.0.0
 */
abstract class ModelExtensionPaymentGateway extends Model {

	/**
	 * @var string
	 * @since 1.0.0
	 */
	protected $prefix = 'payment_wirecard_pg_';

	/**
	 * @var string
	 * @since 1.0.0
	 */
	protected $type;

	/**
	 * Get a logger instance
	 *
	 * @return PGLogger
	 * @since 1.0.0
	 */
	protected function getLogger() {
		return new PGLogger($this->config);
	}

	/**
	 * Default payment method getter, method should only be returned if activated
	 *
	 * @param $address
	 * @param $total
	 * @return array
	 * @since 1.0.0
	 */
	public function getMethod($address, $total) {
		$prefix = $this->prefix . $this->type;
		$this->load->language('extension/payment/wirecard_pg_' . $this->type);
		$logo = '<img src="./image/wirecard_pg/'. $this->type .'.png" width="100"/>';
		$title = $logo . ' ' . $this->config->get($prefix . '_title');

		$method_data = array(
			'code'       => 'wirecard_pg_' . $this->type,
			'title'      => $title,
			'terms'      => '',
			'sort_order' => 1
		);

		return $method_data;
	}

	/**
	 * Process transaction request
	 *
	 * @param $config
	 * @param $transaction
	 * @param string $paymetAction
	 * @return \Wirecard\PaymentSdk\Response\Response
	 * @throws Exception
	 * @since 1.0.0
	 */
	public function sendRequest($config, $transaction, $paymetAction) {
		$this->load->language('extension/payment/wirecard_pg');

		$logger = $this->getLogger();
		$transactionService = new \Wirecard\PaymentSdk\TransactionService($config, $logger);

		$redirect = $this->url->link('checkout/checkout', '', true);

		try {
			/* @var \Wirecard\PaymentSdk\Response\Response $response */
			$response = $transactionService->process($transaction, $paymetAction);
		} catch (Exception $exception) {
			$logger->error($exception->getMessage());
			$this->session->data['error'] = $this->language->get('order_error');

			$redirect = $this->url->link('checkout/checkout', '', true);

			return $redirect;
		}

		if ($response instanceof \Wirecard\PaymentSdk\Response\InteractionResponse) {
			$redirect = $response->getRedirectUrl();
		} elseif ($response instanceof \Wirecard\PaymentSdk\Response\FailureResponse) {
			$errors = '';

			foreach ($response->getStatusCollection()->getIterator() as $item) {
				$errors .= $item->getDescription() . "<br>\n";
				$logger->error($item->getDescription());
			}

			$this->session->data['error'] = $errors;
			$redirect = $this->url->link('checkout/checkout', '', true);
		} else {
			$this->session->data['error'] = $this->language->get('order_error');
		}

		return $redirect;
	}

	/**
	 * Create transaction entry
	 *
	 * @param \Wirecard\PaymentSdk\Response\SuccessResponse $response
	 * @param array $order
	 * @param string $transactionState
	 * @param string $paymentMethod
	 * @since 1.0.0
	 */
	public function createTransaction($response, $order, $transactionState, $paymentMethod) {
		$amount = $response->getData()['requested-amount'];
		$orderId = $response->getCustomFields()->get('orderId');
		$currency = $order['currency_code'];

		$this->db->query("
            INSERT INTO `" . DB_PREFIX . "wirecard_ee_transactions` SET 
            `order_id` = '" . (int)$orderId . "', 
            `transaction_id` = '" . $this->db->escape($response->getTransactionId()) . "', 
            `parent_transaction_id` = '" . $this->db->escape($response->getParentTransactionId()) . "', 
            `transaction_type` = '" . $this->db->escape($response->getTransactionType()) . "',
            `payment_method` = '" . $this->db->escape($paymentMethod) . "', 
            `transaction_state` = '" . $this->db->escape($transactionState) . "',
            `amount` = '" . (float)$amount . "',
            `currency` = '" . $this->db->escape($currency) . "',
            `response` = '" . $this->db->escape(json_encode($response->getData())) . "',
            `date_added` = NOW()
            ");
	}

	/**
	 * Update transaction with specific transactionstate
	 *
	 * @param \Wirecard\PaymentSdk\Response\SuccessResponse $response
	 * @param $transactionState
	 * @since 1.0.0
	 */
	public function updateTransactionState($response, $transactionState) {
		$this->db->query("
        UPDATE `" . DB_PREFIX . "wirecard_ee_transactions` SET 
            `transaction_state` = '" . $this->db->escape($transactionState) . "', 
            `response` = '" . $this->db->escape(json_encode($response->getData())) . "', 
            `date_modified` = NOW() WHERE 
            `transaction_id` = '" . $this->db->escape($response->getTransactionId()) . "'
        ");
	}

	/**
	 * Get transaction via transaction id
	 *
	 * @param $transactionId
	 * @return bool|array
	 * @since 1.0.0
	 */
	public function getTransaction($transactionId) {
		$query = $this->db->query("
	        SELECT * FROM `" . DB_PREFIX . "wirecard_ee_transactions` WHERE `transaction_id` = '" . $this->db->escape($transactionId) . "'
	    ");

		if ($query->num_rows) {
			return $query->row;
		}

		return false;
	}
}
