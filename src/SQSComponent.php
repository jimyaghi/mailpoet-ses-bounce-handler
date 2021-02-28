<?php
/**
 * بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم
 *
 * Created by Jim Yaghi
 * Date: 2021-02-28
 * Time: 15:32
 *
 */


namespace JY\BounceHandlerPlugin {


	use Aws\Retry\Configuration;
	use Aws\Sqs\SqsClient;

	/**
	 * Class SQSComponent
	 * @package JY\PostfixMailsterPlugin
	 */
	class SQSComponent {

		/**
		 * @var string
		 */
		private string $queueUrl = '';

		/**
		 * @var SqsClient|null
		 */
		private ?SqsClient $SqsClient = null;


		/**
		 * @var SQSComponent|null
		 */
		static ?SQSComponent $instance = null;

		private function __construct() {

		}

		/**
		 * @return SQSComponent
		 */
		static function instance(): SQSComponent {
			if ( static::$instance === null ) {
				static::$instance = new static();
			}

			return static::$instance;
		}

		/**
		 * @return SqsClient
		 */
		public function getClient(): SqsClient {
			if ( ! $this->SqsClient ) {
				//$profile  = 'default';
				$config = new Configuration( 'adaptive', 3 );
				//$path     = AWS_CREDENTIALS_PATH;
				//$provider = CredentialProvider::ini( $profile, $path );
				//$provider = CredentialProvider::memoize( $provider );
				$client = new SqsClient(
					[
						//	'credentials' => $provider,
						'region'  => 'us-east-1',
						'version' => 'latest',
						'retries' => $config
					] );

				$this->SqsClient = $client;
			}

			return $this->SqsClient;
		}





	}

}
