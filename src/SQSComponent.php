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
	use Aws\Sqs\Exception\SqsException;
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


		/**
		 * @return string
		 */
		public function notificationQ(): string {
			return get_option( 'jy_sqs_queue', 'BounceNotifications' );
		}


		/**
		 *
		 * @return string
		 * @throws SqsException
		 */
		public function getNotificationQueue(): string {
			if ( ! $this->queueUrl ) {
				$QueueName = $this->notificationQ();

				try {
					$response       = $this->getClient()->getQueueUrl( compact( 'QueueName' ) );
					$this->queueUrl = (string) $response->get( 'QueueUrl' );
				} catch ( SqsException $exception ) {
					$msg = "Could not find the Queue `{$this->notificationQ()}`. Got {$exception->getAwsErrorCode()} {$exception->getAwsErrorMessage()}.";
					error_log( $msg );
				}
			}

			return $this->queueUrl;
		}


	}

}
