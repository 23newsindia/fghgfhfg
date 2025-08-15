<?php
if (!defined('ABSPATH')) {
    exit;
}

function wns_render_email_queue_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Email Queue', 'wp-newsletter-subscription'); ?></h1>

        <form method="post">
            <?php
            $list_table = new WNS_Email_Queue_List_Table();
            $list_table->prepare_items();
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}