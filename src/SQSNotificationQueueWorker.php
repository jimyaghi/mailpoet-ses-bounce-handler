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


	use Aws\Result;
	use Aws\Sqs\Exception\SqsException;

	class SQSNotificationQueueWorker extends SQSQueueWorker {


		/**
		 * @var string
		 */
		public string $queue_url = '';


		/**
		 * @param $message
		 *
		 * @return Result
		 */
		public function ackMessage( $message ): Result {
			// we will return true so as not to affect things in the test stage
			return SQSComponent::instance()->getClient()->deleteMessage(
				[
					'QueueUrl'      => $this->queue_url, // REQUIRED
					'ReceiptHandle' => $message['ReceiptHandle'], // REQUIRED
				] );
		}

		public function nackMessage( $message, $visibilityTimeout = 0 ): Result {

			return SQSComponent::instance()->getClient()->changeMessageVisibility(
				[
					'VisibilityTimeout' => $visibilityTimeout,
					'QueueUrl'          => $this->queue_url,
					'ReceiptHandle'     => $message['ReceiptHandle']
				] );
		}


		public function process( $message ): bool {
			$body = json_decode( $message['Body'] ?? '{}', true );
			if ( ! is_array( $body ) ) {
				$body = [];
			}

			return SNSMessageHandler::instance()->handle( $body, $message['MessageId'] ?? '' );
		}


		/**
		 * @param $messages
		 */
		public function processBatch( $messages ) {

			for ( $i = 0; $i < count( $messages ); $i ++ ) {
				$completed = $this->process( $messages[ $i ] );

				if ( $completed ) {
					$this->ackMessage( $messages[ $i ] );
				} else {
					$this->nackMessage( $messages[ $i ] );
				}
			}
		}

		public function work() {
			parent::work();
			$this->queue_url = SQSComponent::instance()->getNotificationQueue();

			$noMessagesCount = 0;
			while ( ++ $this->check_count ) {
				$this->out( "Check #{$this->check_count} for messages on Queue {$this->queue_id}" );
				try {
					$result = SQSComponent::instance()->getClient()->receiveMessage(
						[
							'AttributeNames'        => [ 'SentTimestamp' ],
							'MaxNumberOfMessages'   => 10,
							'MessageAttributeNames' => [ 'All' ],
							'QueueUrl'              => $this->queue_url,
							'WaitTimeSeconds'       => 20,
							'VisibilityTimeout'     => 120
						] );

					$messages = $result->get( 'Messages' );

					if ( $messages ) {
						$noMessagesCount = 0;
						$this->out( count( $messages ) . " Messages found in Queue `{$this->queue_id}`" );
						$this->processBatch( $messages );
					} elseif ( $noMessagesCount ++ < 3 ) {
						$this->out( "[{$noMessagesCount}] No messages found in Queue `{$this->queue_id}`. Sleeping for 5 mins" );
						sleep( 300 );
					} else {
						$this->out( "Reached max empty messages for Queue `{$this->queue_id}`. Shutting down." );
						$this->shutdown();
					}
					// reset fails
					$this->fails = 0;
				} catch ( SqsException $reason ) {
					// output error message if fails
					$this->out( "Getting messages from Queue {$this->queue_id} failed. Got {$reason->getAwsErrorMessage()}" );
					// count the fail
					if ( $this->fails ++ >= 3 ) {
						// we failed 3 times, let's quit this queue
						$this->out( "Too many errors on Queue {$this->queue_id}. Shutting down worker." );
						$this->shutdown();
					}
				}
			}
		}
	}

}