<?php
/**
 * بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم
 *
 * Created by Jim Yaghi
 * Date: 2021-02-28
 * Time: 15:51
 *
 */


namespace JY\BounceHandlerPlugin {


	use Aws\Sns\Message;
	use Aws\Sns\MessageValidator;
	use Exception;

	use MailPoet\Models\Subscriber;

	/**
	 * Class SNSHandler
	 * @package JY\PostfixMailsterPlugin
	 *
	 */
	class SNSMessageHandler {

		/**
		 * @var SNSMessageHandler
		 */
		private static SNSMessageHandler $instance;

		/**
		 * @var string
		 */
		public $sns_query_key;

		private function __construct( ) {
			$this->sns_query_key = get_option( 'jy_sns_key', uniqid() );
		}

		/**
		 * @return SNSMessageHandler
		 */
		static function instance() {
			if ( static::$instance === null ) {
				static::$instance = new static();
				static::$instance->run();
			}

			return static::$instance;
		}


		/**
		 * Handle the Amazon SNS notifications
		 *
		 * @param array $notification
		 * @param string $notification_id
		 *
		 * @return bool
		 */
		public function handle( $notification, $notification_id = '' ): bool {
			wp_ob_end_flush_all();

			if ( $notification_id ) {
				$defaults['notification_id'] = $notification_id;
				echo( "\nGot Notification ID: {$notification_id}" );
			}

			switch ( $notification['Type'] ) {

				case 'SubscriptionConfirmation':
					$this->confirm( $notification );
					break;

				case 'Notification':
					$message = $this->parse_message( $notification );
					$mail    = $message['mail'];

					$defaults['aws_message_id'] = $mail['messageId'];


					switch ( $message['notificationType'] ) {
						case 'Received':
							break;

						default:
							switch ( $message['eventType'] ) {
								// "Send", "Delivery", "Reject", "Bounce", "Complaint", "Open", "Click", "Rendering Failure", ""
								case 'Send':
								case 'Delivery':
								case 'Reject':
								case "Open":
								case "Click":
								case 'Rendering Failure':
									break;

								case 'Bounce':
									$this->bounced( $message['bounce'] );
									break;

								case 'Complaint':
									$this->complained( $message['complaint'] );
									break;

							}
					}
			}


			return true;
		}

		public function validate(): array {
			if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
				http_response_code( 405 );
				die;
			}

			if ( get_option( 'jy_sns_key' ) !== $_GET[ $this->sns_query_key ] ) {
				http_response_code( 404 );
				die;
			}

			try {
				// Create a message from the post data and validate its signature
				$sns_notification = Message::fromRawPostData();
				$validator        = new MessageValidator();
				$validator->validate( $sns_notification );


				return $sns_notification->toArray();
			} catch ( Exception $e ) {
				// Pretend we're not here if the message is invalid
				http_response_code( 404 );
				die;
			}
		}

		public function confirm( $notification ) {
			$response = wp_remote_get( $notification['SubscribeURL'] );
			$code     = wp_remote_retrieve_response_code( $response );

			if ( 200 == $code ) {
				update_option( 'jy_notices', "Confirmed the Amazon SNS subscription to topic '<code>{$notification['TopicArn']}</code>." );
			} else {
				update_option( 'jy_notices', "Unable to confirm the Amazon SNS subscription to topic '<code>{$notification['TopicArn']}</code>." );
			}
		}

		public function parse_message( $notification ) {
			$message = json_decode( $notification['Message'] ?? "{}", true );

			// make sure we have keys for all the expected keys so we don't have to keep doing checks for empty
			return array_replace_recursive(
				[
					// "Bounce", "Complaint", "Delivery", "Send", "Reject", "Open", "Click", "Rendering Failure", ""
					'eventType'        => '',
					// "Received" for reply notifications
					'notificationType' => '',

					// present when eventType is one of "Bounce", "Complaint", "Delivery", "Send", "Reject", "Open", "Click", "Rendering Failure"
					// or when notificationType is "Received"
					'mail'             => [
						'messageId' => '',
						'headers'   => [],
						// the time in ISO format at which the original email message was sent, except in the case
						// of Received event when it would be the time the received event was, you know, received.
						'timestamp' => ''
					],
					// present when event is Bounce
					'bounce'           => [],
					// present when event is Complaint
					'complaint'        => [],
					// present when event is Delivery
					'delivery'         => [],
					// present when event is Send
					'send'             => [],
					// present when event is Reject
					'reject'           => [],
					// present when event is Open
					'open'             => [],
					// present when event is Click
					'click'            => [],
					// present when event is Rendering Failure
					'failure'          => [],
					// present when event is Received
					'receipt'          => [],
					'content'          => ''
				], $message );
		}

		public function bounced( $bounce = [] ) {
			switch ( $bounce['bounceType'] ) {
				case 'Permanent':
					foreach ( $bounce['bouncedRecipients'] ?? [] as $bouncedRecipient ) {
						$email = $bouncedRecipient['emailAddress'] ?? '';
						if ( ! $email ) {
							continue;
						}

						$subs = Subscriber::findOne( $email );
						if ( ! $subs ) {
							continue;
						}
						print "\nGot BOUNCE <{$email}>";
						$subs->status = Subscriber::STATUS_BOUNCED;
						$subs->save();
					}
					break;

				case 'Transient':
				default:
			}
		}

		public function complained( $complaint ) {
			foreach ( $complaint['complainedRecipients'] ?? [] as $complainedRecipients ) {
				$email = $complainedRecipients['emailAddress'] ?? '';
				if ( ! $email ) {
					continue;
				}

				$subs = Subscriber::findOne( $email );
				if ( ! $subs ) {
					continue;
				}
				print "\nGot COMPLAINT <{$email}>";
				$subs->status = Subscriber::STATUS_UNSUBSCRIBED;
				$subs->save();
			}
		}

		public function route_query_call() {
			if ( isset( $_GET[ $this->sns_query_key ] ) ) {
				$notification = $this->validate();

				return $this->handle( $notification );
			}

			return false;
		}

		function show_notice() {
			if ( $notice = get_option( 'jy_notices' ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . $notice . '</p></div>';
				update_option( 'jy_notices', '' );
			}
		}

		private function run() {
			add_action( 'wp_loaded', [ $this, 'route_query_call' ] );
			add_action( 'admin_notices', [$this, 'show_notice'] );
		}
	}
}