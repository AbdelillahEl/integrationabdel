<?php
/**
 * Plugin Name: Customer Sync Plugin
 * Description: Syncs customer data with microservice via RabbitMQ
 */

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class CustomerSyncPlugin {
    private $connection;
    private $channel;

    public function __construct() {
        $this->setup_rabbitmq_connection();
        add_action('wp_insert_post', array($this, 'on_customer_created'), 10, 3);
        add_action('post_updated', array($this, 'on_customer_updated'), 10, 3);
        add_action('before_delete_post', array($this, 'on_customer_deleted'));
    }

    private function setup_rabbitmq_connection() {
        $this->connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest', '/');
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare('customer_sync', false, true, false, false);
    }

    public function on_customer_created($post_id, $post, $update) {
        if ($post->post_type !== 'customer' || $update) return;
        $this->send_message('created', $post_id);
    }

    public function on_customer_updated($post_id, $post_after, $post_before) {
        if ($post_after->post_type !== 'customer') return;
        $this->send_message('updated', $post_id);
    }

    public function on_customer_deleted($post_id) {
        $post = get_post($post_id);
        if ($post->post_type !== 'customer') return;
        $this->send_message('deleted', $post_id);
    }

    private function send_message($action, $post_id) {
        $message = new AMQPMessage(json_encode([
            'action' => $action,
            'customer_id' => $post_id,
            'data' => get_post_meta($post_id)
        ]));
        $this->channel->basic_publish($message, '', 'customer_sync');
    }

    public function __destruct() {
        $this->channel->close();
        $this->connection->close();
    }
}

new CustomerSyncPlugin();