<?php
// Add custom post type for a odoo client
require_once(__DIR__ . '/vendor/autoload.php');
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class CustomerSyncPlugin {
    private $connection;
    private $channel;

    public function __construct() {
        $this->setup_rabbitmq_connection();
    }

    private function setup_rabbitmq_connection() {
        try {
            $this->connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest', '/');
            $this->channel = $this->connection->channel();
            $this->channel->queue_declare('wordpress_updates', false, true, false, false);
            $this->channel->queue_declare('odoo_updates', false, true, false, false);
            error_log('Connected to RabbitMQ');
            // Consume messages from the 'odoo_updates' queue
            while (true) {
                $msg = $this->channel->basic_get('odoo_updates');
                if ($msg) {
                    $this->process_message($msg);
                } else {
                    break;
                }
            }
            $this->rabbitmq_available = true;
        } catch (\Exception $e) {
            error_log('Failed to connect to RabbitMQ: ' . $e->getMessage());
            $this->rabbitmq_available = false;
        }
    }

    public function send_message($action, $client, $id) {
        $values = array();
        // Convert client array to values dictionary
        foreach ($client as $key => $value) {
            if (!is_array($value)) {  // Ensure we aren't mistakenly using a nested array
                $values[str_replace('_', '', $key)] = $value;
            }
        }
        
        // Ensure 'values' is an associative array (dictionary)
        if (!is_array($values) || array_values($values) === $values) {
            error_log('Error: Values is not an associative array');
            return;
        }
    
        $json = json_encode([
            'action' => $action,
            'id'     => $id,
            'values' => $values
        ]);
        error_log('Sending message to RabbitMQ: ' . $json);
        $message = new AMQPMessage($json, ['delivery_mode' => 2]); // Persistent message
        $this->channel->basic_publish($message, '', 'wordpress_updates');
    }
    
    public function __destruct() {
        $this->channel->close();
        $this->connection->close();
    }

    // Handle RabbitMQ messages
    private function process_message($message) {
        $data = json_decode($message->body, true);
        $action = $data['action'];
        $id = $data['id'];
        $values = $data['values'];

        error_log('Processing message from Odoo: ' . $action);

        switch ($action) {
            case 'create':
                $this->new_client($values);
                break;
            case 'update':
                $this->edit_client($id, $values);
                break;
            case 'delete':
                $this->delete_client($values);
                break;
            default:
                error_log('Unknown action: ' . $action);
                break;
        }
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    private function new_client($client) {
        
        // Check if email already exists
        $client_email = $client['email'];
        $args = array(
            'post_type' => 'client',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'client_email',
                    'value' => $client_email,
                    'compare' => '='
                )
            )
        );
        $clients = get_posts($args);
        if (count($clients) > 0) {
            return null; // Client already exists
        }

        // Insert a new post of type 'client'
        $client_id = wp_insert_post(array(
            'post_title' => $client_email,
            'post_type' => 'client',
            'post_status' => 'publish',
            'meta_input' => array(
                'client_first_name' => $client['name'],
                'client_phone' => $client['phone'],
                'client_email' => $client['email'])
        ));

        return $client_id;
    }

    private function edit_client($id, $client) {
        $client_post = get_post($id);

       
        // Update the existing client post
        wp_update_post(array(
            'ID' => $id,
            'meta_input' =>array(
                'client_first_name' => $client['first_name'],
                'client_phone' => $client['phone'],
                'client_email' => $client['email'])
            
        ),
        );
    }

    private function delete_client($values) {
        if (isset($values['email'])) {
            $client_email = $values['email'];
    
            // Check if a client with this email exists
            $args = array(
                'post_type' => 'client',
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => 'client_email',
                        'value' => $client_email,
                        'compare' => '='
                    )
                )
            );
            $clients = get_posts($args);
    
            if (count($clients) > 0) {
                $client_id = $clients[0]->ID;
                wp_delete_post($client_id, true);
                error_log("Client with email $client_email deleted from WordPress.");
            } else {
                error_log("Attempted to delete non-existing client with email $client_email.");
            }
        } else {
            error_log("Email not provided for delete action.");
        }
    }
    
}

function initiate_rabbitmq_connection() {
    global $customer_sync_plugin;
    $customer_sync_plugin = new CustomerSyncPlugin();
}

add_action('init', 'initiate_rabbitmq_connection');

function add_client_post_type() {
    $labels = array(
        'name' => 'Clients',
        'singular_name' => 'Client',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Client',
        'edit_item' => 'Edit Client',
        'new_item' => 'New Client',
        'all_items' => 'All Clients',
        'view_item' => 'View Client',
        'search_items' => 'Search Clients',
        'not_found' =>  'No Clients found',
        'not_found_in_trash' => 'No Clients found in Trash',
        'parent_item_colon' => '',
        'menu_name' => 'Clients'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'client'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments')
    );

    register_post_type('client', $args);
}

add_action('init', 'add_client_post_type');

function handle_form_submit_new_client() {
    if (!isset($_POST['submit_client_form'])) {
        
        
        return;
    }
   
   

    global $customer_sync_plugin;

    $client = array(
        'first_name' => sanitize_text_field($_POST['client_first_name']),
        'phone' => sanitize_text_field($_POST['client_phone']),
        'email' => sanitize_text_field($_POST['client_email']),

    );

    $client_id = new_client($client);
    if ($client_id) {
        $customer_sync_plugin->send_message('create', $client, $client_id);
        echo '<p>Client added successfully!</p>';
    } else {
        echo '<p>Client already exists.</p>';
    }
}

add_action('wp', 'handle_form_submit_new_client');

function handle_form_submit_update_client() {
    if (!isset($_POST['edit_client_form'])) {
        return;
    }
    global $customer_sync_plugin;

    $client_id = sanitize_text_field($_POST['client_id']);
    $client = array(
        'first_name' => sanitize_text_field($_POST['client_first_name']),
        'phone' => sanitize_text_field($_POST['client_phone']),
        'email' => sanitize_text_field($_POST['client_email']),
    );
    edit_client($client);


    $customer_sync_plugin->send_message('update', $client, $client_id);
    echo '<p>Client updated successfully!</p>';
}

add_action('wp', 'handle_form_submit_update_client');

function handle_form_submit_delete_client() {
    if (!isset($_POST['delete_client_form'])) {
        return;
    }
    global $customer_sync_plugin;

    $client_id = sanitize_text_field($_POST['client_id']);
    delete_client($client_id);
    $customer_sync_plugin->send_message('delete', 0, $client_id);
    echo '<p>Client deleted successfully!</p>';
}



add_action('wp', 'handle_form_submit_delete_client');
function delete_client( $id ) {
    wp_delete_post( $id, true );
}
function new_client($client) {
    $client_first_name = $client['first_name'];
    $client_phone = $client['phone'];
    $client_email = $client['email'];

    // Check if email already exists
    $args = array(
        'post_type' => 'client',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'client_email',
                'value' => $client_email,
                'compare' => '='
            )
        )
    );

    $clients = get_posts($args);
    if (count($clients) > 0) {
        return null;
    }

    // Insert a new post of type 'odoo_client'
    $client_id = wp_insert_post(array(
        'post_title' => $client_email,
        'post_type' => 'client',
        'post_status' => 'publish',
        'meta_input' => array(
            'client_first_name' => $client_first_name,
            'client_phone' => $client_phone,
            'client_email' => $client_email,
        ),
    ));
    return $client_id;
}
function edit_client($client) {
    $client_email = $client['email'];
    $args = array(
        'post_type' => 'client',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'client_email',
                'value' => $client_email,
                'compare' => '='
            )
        )
    );

    $clients = get_posts($args);
    if (count($clients) == 0) {
        echo '<p>Client does not exist.</p>';
        return;
    }

    $old_client = $clients[0];

    $client_first_name = $client['first_name'];
    $client_phone = $client['phone'];
    

    // Update the post of type 'odoo_client'
    $client_id = wp_update_post(array(
        'ID' => $old_client->ID,
        'post_title' => $client_email,
        'post_type' => 'client',
        'post_status' => 'publish',
        'meta_input' => array(
            'client_first_name' => $client_first_name,
            'client_phone' => $client_phone,
            'client_email' => $client_email,
        ),
    ));
    return $client_id;
}



?>
