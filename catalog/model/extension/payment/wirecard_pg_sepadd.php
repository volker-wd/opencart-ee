<?php
/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/opencart-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/opencart-ee/blob/master/LICENSE
 */

require_once(dirname( __FILE__ ) . '/wirecard_pg/gateway.php');

/**
 * Class ModelExtensionPaymentWirecardPGSepaDD
 *
 * SepaDirectDebit Transaction model
 *
 * @since 1.1.0
 */
class ModelExtensionPaymentWirecardPGSepaDD extends ModelExtensionPaymentGateway {

	/**
	 * @var string
	 * @since 1.1.0
	 */
	protected $type = 'sepadd';

	/**
	 * @var int
	 * @since 1.1.0
	 */
	protected $scale = 2;
}
