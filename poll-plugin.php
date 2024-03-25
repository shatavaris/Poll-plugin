<?php
/*
Plugin Name: Poll Pluglin
Description: Create and manage fun polls in WordPress.
Version: 1.0
Author: Shatavari Shinde
*/

// Register custom post type for polls
function poll_plugin_register_post_type() {
    register_post_type( 'poll', // Post type identifier
        array(
            'labels' => array( // Labels for the post type
                'name' => __( 'Polls' ), // Plural name for the post type
                'singular_name' => __( 'Poll' ) // Singular name for the post type
            ),
            'public' => true, // Whether the post type is intended to be used publicly
            'has_archive' => true, // Whether the post type should have an archive page
            'supports' => array( 'title' ), // Features supported by the post type (in this case, only 'title')
        )
    );
}
add_action( 'init', 'poll_plugin_register_post_type' );


// Add meta box for poll answers
function poll_plugin_add_meta_box() {
    add_meta_box( 'poll_answers', 'Poll Answers', 'poll_plugin_render_meta_box', 'poll' );
}
add_action( 'add_meta_boxes', 'poll_plugin_add_meta_box' );

// Render meta box for poll answers
function poll_plugin_render_meta_box( $post ) {
    $poll_answers = get_post_meta( $post->ID, 'poll_answer' ); // Retrieve poll answers from post meta
    $poll_answers_text = implode( "\n", $poll_answers ); // Convert array of answers to newline separated text

    wp_nonce_field( basename( __FILE__ ), 'poll_answers_nonce' );
    ?>
    <label for="poll_answers"><?php _e( 'Enter Poll Answers (one per line):' ); ?></label>
    <textarea id="poll_answers" name="poll_answers" class="widefat" rows="4"><?php echo esc_textarea( $poll_answers_text ); ?></textarea>
    <?php
}


// Save poll answers when poll is saved
function poll_plugin_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['poll_answers_nonce'] ) || ! wp_verify_nonce( $_POST['poll_answers_nonce'], basename( __FILE__ ) ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( 'poll' !== $_POST['post_type'] ) {
        return;
    }

    if ( isset( $_POST['poll_answers'] ) ) {
        // Split options by semicolon
        $poll_answers = explode( ';', sanitize_text_field( $_POST['poll_answers'] ) );
        
        // Trim whitespace from each option
        $poll_answers = array_map( 'trim', $poll_answers );

        // Save each option separately
        foreach ($poll_answers as $answer) {
            if (!empty($answer)) {
                add_post_meta( $post_id, 'poll_answer', sanitize_text_field( $answer ), false );
            }
        }
    }
}
add_action( 'save_post', 'poll_plugin_save_meta_box_data' );

// Register REST API route to fetch poll data
function poll_plugin_register_routes() {
    register_rest_route( 'poll-plugin/v1', '/polls', array(
        'methods' => 'GET',
        'callback' => 'poll_plugin_get_polls',
    ));
}
add_action( 'rest_api_init', 'poll_plugin_register_routes' );

// Retrieve poll data for API
function poll_plugin_get_polls() {
    $args = array(
        'post_type' => 'poll',
        'posts_per_page' => -1,
    );
    $polls = array();

    $query = new WP_Query( $args );
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $poll_id = get_the_ID();
            $poll_title = get_the_title();
            
            // Fetch all options for the poll
            $poll_answers = get_post_meta( $poll_id, 'poll_answer' );

            // Fetch votes for the poll
            $poll_votes = get_post_meta( $poll_id, 'poll_votes', true );
            if ( ! is_array( $poll_votes ) ) {
                // If no votes yet, initialize with zeros
                $poll_votes = array_fill( 0, count( $poll_answers ), 0 );
            }

            // Combine answers and votes into an associative array
            $answers_with_votes = array();
            foreach ($poll_answers as $index => $answer) {
                $answers_with_votes[$answer] = $poll_votes[$index];
            }

            $polls[] = array(
                'id' => $poll_id,
                'title' => $poll_title,
                'answers' => $answers_with_votes,
            );
        }
        wp_reset_postdata();
    }

    return rest_ensure_response( $polls );
}

// Register REST API route to handle voting
function poll_plugin_register_vote_route() {
    register_rest_route( 'poll-plugin/v1', '/vote', array(
        'methods' => 'POST',
        'callback' => 'poll_plugin_submit_vote',
    ));
}
add_action( 'rest_api_init', 'poll_plugin_register_vote_route' );

// Handle voting functionality
function poll_plugin_submit_vote( $request ) {
    $parameters = $request->get_json_params();

    // Check if all required parameters are provided
    if ( isset( $parameters['pollId'] ) && isset( $parameters['answerIndex'] ) ) {
        $poll_id = sanitize_text_field( $parameters['pollId'] );
        $answer_index = intval( $parameters['answerIndex'] );

        // Update vote count for the selected answer
        $votes = get_post_meta( $poll_id, 'poll_votes', true );
        if ( ! is_array( $votes ) ) {
            $votes = array_fill( 0, count( get_post_meta( $poll_id, 'poll_answer', false ) ), 0 );
        }
        $votes[$answer_index]++;
        update_post_meta( $poll_id, 'poll_votes', $votes );

        return new WP_REST_Response( 'Vote submitted successfully.', 200 );
    } else {
        return new WP_Error( 'missing_parameters', 'Required parameters are missing.', array( 'status' => 400 ) );
    }
}

// Enqueue Vue.js component and styles
function poll_plugin_enqueue_scripts() {
    // Enqueue Vue.js
    wp_enqueue_script('vue', 'https://cdn.jsdelivr.net/npm/vue/dist/vue.js', array(), '2.6.14', true);
    // Enqueue poll component
    wp_enqueue_script('poll-component', plugin_dir_url(__FILE__) . 'assets/poll-component.js', array('vue'), '1.0', true);
    // Enqueue CSS styles
    wp_enqueue_style('poll-styles', plugin_dir_url(__FILE__) . 'assets/styles.css');
}
add_action('wp_enqueue_scripts', 'poll_plugin_enqueue_scripts');

function poll_shortcode($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
      'id' => '',  // default value for the id attribute (remains for backward compatibility)
      'poll_id' => '', // new attribute for specific poll ID
  ), $atts);
  
  // If no ID is provided (use the new poll_id attribute first), return empty
  if (empty($atts['poll_id']) && empty($atts['id'])) {
    return '';
  }
  
  $poll_id = !empty($atts['poll_id']) ? intval($atts['poll_id']) : intval($atts['id']); // Use poll_id if available, otherwise fallback to id
  
  // Output the poll component with specific ID
  ob_start(); ?>
  <div id="app">
    <poll-component :poll-id="<?php echo $poll_id; ?>"></poll-component>
  </div>
  <?php
  return ob_get_clean();
  }
  
  add_shortcode('poll', 'poll_shortcode');
  

  // Add meta box to display poll results
function poll_plugin_results_meta_box() {
    add_meta_box( 'poll_results', 'Poll Results', 'poll_plugin_render_results_meta_box', 'poll', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'poll_plugin_results_meta_box' );

// Render meta box to display poll results
function poll_plugin_render_results_meta_box( $post ) {
    $poll_id = $post->ID;
    $poll_title = get_the_title( $poll_id );

    // Fetch all options for the poll
    $poll_answers = get_post_meta( $poll_id, 'poll_answer' );

    // Fetch votes for the poll
    $poll_votes = get_post_meta( $poll_id, 'poll_votes', true );
    if ( ! is_array( $poll_votes ) ) {
        // If no votes yet, initialize with zeros
        $poll_votes = array_fill( 0, count( $poll_answers ), 0 );
    }

    // Combine answers and votes into an associative array
    $answers_with_votes = array();
    foreach ($poll_answers as $index => $answer) {
        $answers_with_votes[$answer] = $poll_votes[$index];
    }
    ?>
    <div class="poll-results">
        <h3><?php echo esc_html( $poll_title ); ?></h3>
        <ul>
            <?php foreach ( $answers_with_votes as $answer => $votes ) : ?>
                <li><?php echo esc_html( $answer ); ?>: <?php echo esc_html( $votes ); ?> votes</li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

