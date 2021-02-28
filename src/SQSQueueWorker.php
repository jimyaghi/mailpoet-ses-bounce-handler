<?php
/**
 * بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم
 *
 * Created by Jim Yaghi
 * Date: 2021-02-28
 * Time: 15:31
 *
 */

namespace JY\BounceHandlerPlugin {

	use \Aws\Sqs\SqsClient;
	use \Aws\Retry\Configuration;
	use \Aws\Sqs\Exception\SqsException;

	class SQSQueueWorker {
		/**
		 * @var string
		 */
		private string $queueUrl = '';


		/**
		 * @var SqsClient|null
		 */
		public $sqsClient = null;

		/**
		 * @var string
		 */
		private $queueId = '';

		/**
		 * @var int
		 */
		public int $fails = 0;

		/**
		 * @var int
		 */
		public int $check_count = 0;


		public function __construct( $queue_name ='' ) {
			$this->queueId  = $queue_name;
			$this->sqsClient = $this->getClient();
		}

		public function queueId(): string {
			return $this->queueId;
		}

		/**
		 * @return SqsClient
		 */
		public function getClient(): SqsClient {
			if ( ! $this->sqsClient ) {
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

				$this->sqsClient = $client;
			}

			return $this->sqsClient;
		}

		/**
		 *
		 * @return string
		 * @throws SqsException
		 */
		public function queueUrl(): string {
			if ( ! $this->queueUrl ) {
				$QueueName = $this->queueId();

				try {
					$response       = $this->sqsClient->getQueueUrl( compact( 'QueueName' ) );
					$this->queueUrl = (string) $response->get( 'QueueUrl' );
				} catch ( SqsException $exception ) {
					$msg = "Could not find the Queue `{$this->queueId()}`. Got {$exception->getAwsErrorCode()} {$exception->getAwsErrorMessage()}.";
					$this->out( $msg );
				}
			}

			return $this->queueUrl;
		}


		private function printHeader() {
			echo "\n\n";
			echo "\n*****************************************************************";
			echo "\n**** Worker for Queue {$this->queueId()} started at " . date( "Y-m-d H:i:s" );
			echo "\n*****************************************************************";
		}

		private function printFooter() {
			echo "\n\n";
			echo "\n*****************************************************************";
			echo "\n**** Worker for Queue {$this->queueId()} finished at " . date( "Y-m-d H:i:s" );
			echo "\n*****************************************************************";
			echo "\n\n";
		}

		/**
		 * @param string $message
		 */
		public function out( string $message ) {
			echo "\n" . $message;
		}

		public function work() {
			$this->start();
		}

		public function start() {
			$this->printHeader();
		}

		public function shutdown() {
			$this->printFooter();
			die;
		}
	}
}