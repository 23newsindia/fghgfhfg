<?php
if (!defined('ABSPATH')) {
    exit;
}

function wns_render_subscriber_list_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Newsletter Subscribers', 'wp-newsletter-subscription'); ?></h1>

        <form method="post">
            <?php
            $list_table = new WNS_Subscriber_List_Table();
            $list_table->prepare_items();
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}