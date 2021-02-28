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

	class SQSQueueWorker {

		/**
		 * @var SQSComponent|null
		 */
		public ?SQSComponent $sqs = null;
		/**
		 * @var string
		 */
		public $queue_id = '';

		/**
		 * @var int
		 */
		public int $fails = 0;
		/**
		 * @var int
		 */
		public int $check_count = 0;


		public function __construct( $queue_name ='' ) {
			$this->queue_id = $queue_name;
		}


		private function printHeader() {
			echo "\n\n";
			echo "\n*****************************************************************";
			echo "\n**** Worker for Queue {$this->queue_id} started at " . date( "Y-m-d H:i:s" );
			echo "\n*****************************************************************";
		}

		private function printFooter() {
			echo "\n\n";
			echo "\n*****************************************************************";
			echo "\n**** Worker for Queue {$this->queue_id} finished at " . date( "Y-m-d H:i:s" );
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