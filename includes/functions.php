<?php

defined( 'ABSPATH' ) or die( 'Keep Quit' );

function recommat_hourly_cron_exec() {
    $order_to_process = 3;

    $options = get_option( 'recommat_settings' );
    if(isset($options['recommat_number_of_order_processed_per_hour']) && is_numeric($options['recommat_number_of_order_processed_per_hour'])) {
        $order_to_process = $options['recommat_number_of_order_processed_per_hour'];
    }

    $redis = recommat_get_redis();

    if(!$redis) return;
    
    // find recent orders, send order data to engine
    $last_queued_order_id = $redis->get('last_queued_order_id');
    if($last_queued_order_id === FALSE) {
        $last_queued_order_id = 0;
    }

    // we need 2 queues for cron
    // queue#1. send orders from wordpress to redis (init setup can have 100k+ orders to start, cannot send them to redis all at once)
    // queue#2. process order from redis (each cron should only process limited orders as AI is involved)

    // queue#1 from wordpress to redis
    // get order id > $last_queued_order_id
    // TODO: exclude cancelled orders
    // TODO: oldest order process first? or newest order process first?
    $query = new WC_Order_Query( array(
        'limit' => $order_to_process*2,
        'return' => 'ids',
        'post__not_in' => range(0, $last_queued_order_id),
        'order' => 'ASC'
    ) );
    $orders = $query->get_orders();
    foreach ($orders as $order_id) {
        //RPUSH order_id
        $redis->rPush('order_queue', $order_id);
        $redis->set('last_queued_order_id', $order_id);
    }

    // queue#2, from redis to AI engine, LPOP
    // there is a possible that, some orders are updated and queued for reprocess
    $orders = $redis->lRange('order_queue', 0, $order_to_process);
    foreach ($orders as $order_id) {
    
        // send order data to engine
        // reuse the new incoming order code
        recommat_order_status_update($order_id, 'pending', 'processing');

        // update the suggestion data from engine to WP
        $order = new WC_Order($order_id);
        $order_items = $order->get_items();
        foreach( $order_items as $product ) {
            // in wordpress case, the engine need variation parent ID, and use simple product linkage
            if( $product->is_type('variation') ){
                $product_id = $product->get_parent_id();
            } else {
                $product_id = $product->get_product_id();
            }
            recommat_update($product_id);
        }
        
    }
}


/**
 * in combination with other config, business related logics,
 * return a list of product id
 * 
 * in wordpress case, the engine need variation parent ID, and use simple product linkage
 * 
 * @param Array|Int $product_ids product IDs, in variation case, need its parent ID
 * @param Bool $cached 
 * @return Array return a list of product id. E.g. array(1,2,3)
 * 
 * TODO: handle if engine returns less than needed item count
 **/
function recommat_get($product_ids = array(), $cached = true) {
    
    $number_of_items = 4;
    
    //float $percentage_of_new: in the return list of products, percentage of item comes from new products
    $percentage_of_new = 0.2;
    
    if($percentage_of_new > 0) {
        // TODO: get new products from config
        $new_products = array();
    }
    $random_ordering = false;

    // side note: variation ID will be passed in, but due to we reuse the wordpress default upsell field, variation ID have no use. In wordpress case, the engine will get variation parent ID, and use simple product linkage

    /*
    This need elabouration.
    if incoming a product variation, we store the data linkage using variation instead of simple product
    */
    // a business related decision is needed for:
    // if product have variations, the suggestion should be combined set of all variations?
    // or each variation have its own suggestion

    $recommend_products = recommat_get_model($product_ids, $cached);
    $score_total = array_sum($recommend_products);

    if($random_ordering) {
        // TODO: random pick products from list
    } else {
        $recommend_products = array_keys(array_slice($recommend_products, 0, $number_of_items, true));
        // recommend_products actually is variation IDs, need to convert to product IDs
        $list = array();
        foreach ($recommend_products as $k => $product_id ) {
            $product = new WC_Product($product_id);
            if(!$product) {
                // if ID not found, we might be testing this in a test env with live engine?
                $list[] = $product_id;
            } else {
                // as varation do not have a PDP, if suggestion itself if a variation, we need to pass back its parent
                // this is a wordpress limitation as we are re-using the default product upsell field
                if( $product->is_type( 'simple' ) ){
                    $list[] = $product_id;
                } elseif( $product->is_type( 'variable' ) ){
                    $list[] = $product->get_parent_id();
                }
            }
        }

        return $list;
    }
}

// define the woocommerce_order_status_changed callback 
// we will :
// 1. store order related info to the engine
// 2. update cached info to the engine to cache
// 3. add info for reporting
function recommat_order_status_update( $order_id, $this_status_transition_from, $this_status_transition_to ) { 

	// if from pending to processing, it is by card
	// if from on-hold to processing, it is by manual
  if(
		($this_status_transition_from == 'pending' && $this_status_transition_to == 'processing')
		||
		($this_status_transition_from == 'on-hold' && $this_status_transition_to == 'processing')
	) {
        
        $redis = recommat_get_redis();
        if(!$redis) return false;
        
		$order = new WC_Order($order_id);
        $order_items = $order->get_items();
        
        //items
        $ids = array();
        $item_ids = array();
        foreach( $order_items as $product ) {
            // in wordpress case, the engine need variation parent ID, and use simple product linkage
            if( $product->is_type('variation') ){
                $id = $product->get_parent_id();
            } else {
                $id = $product->get_product_id();
            }
            $item_ids[] = $id;
            $ids[$id] = $order->get_line_total( $product);
        }

        // need to find if there is a recomendation one by one
        // Do not pass in array to recommat_get_model(),
        // as it will always ignore passed in products
        $overlaps = array();
        /**
         * A recomends C, C recomends A
         * NOT recommat_get_model(A,B,C), as this will not return A,C
         * 
         * A $100
         * B $200
         * C $300
         * Total $600
         * is_recommended = true
         * added_amount = $400
         *
         **/
        foreach ($ids as $product_id => $amount) {
            $recommend_products = array_keys(recommat_get_model($product_id));
            // find if order have recommended items, merge to $overlaps
            $t = array_intersect(array_keys($ids), $recommend_products);
            $overlaps = array_merge($overlaps, array_values($t));
        }

        // statistics for success rate
        // hset order:$order_id $json{$no_of_items, $amount, $is_recommended}
        $date = date('Ymd', strtotime($order->get_date_paid()));
        $data = array(
            'no_of_items' => count($order_items),
            'amount' => $order->get_total(),
            'is_recommended' => empty($overlaps)? 0:1,
            'date' => $date,
            'item_ids' => implode(',', $item_ids),
        );
        $redis->hMSet('order:'. $order_id, $data);

        // hset order:daily:$order_date $json{$order_count, $total_no_of_items, $total_amount, $multiple_item_order, $recommended_order_count, $added_amount}
        $response = $redis->hMGet('daily:order:'.$date, array(
            'order_count', 'total_no_of_items', 'total_amount', 'multiple_item_order_count', 'recommended_order_count', 'added_amount', 'order_ids'
        ));

        // need to check if we had processed this order before
        $processed_orders = array();
        if($response['order_ids']) {
            $processed_orders = explode(',', $response['order_ids']);
        }
        if(!in_array($order_id, $processed_orders)) {
            $processed_orders[] = $order_id;
        }
        
        //TODO: if order_id exists in processed_orders, the order itself might jumped the line
        // what is the difference between new and old order handling here?

        $response['order_count'] += 1;
        $response['total_no_of_items'] += $data['no_of_items'];
        $response['total_amount'] += $data['amount'];
        if($data['no_of_items'] > 1) {
            $response['multiple_item_order_count'] += 1;
        }
        if($data['is_recommended'] > 0) {
            $response['recommended_order_count'] += 1;
            foreach ($overlaps as $product_id) {
                $response['added_amount'] += $ids[$product_id];
            }
        }
        $response['order_ids'] = implode(',', $processed_orders);

        $response = $redis->hMSet('daily:order:'.$date, $response);

        $redis->set('last_processed_order_id', $order_id);

        $redis->incr('total_order_processed');

        // check order, if product count > 1, then send to redis
        if(count($order_items) > 1) {
            foreach( $order_items as $product ) {
                // in wordpress case, the engine need variation parent ID, and use simple product linkage
                if( $product->is_type('variation') ){
                    $id = $product->get_parent_id();
                } else {
                    $id = $product->get_product_id();
                }
                //sadd item:$product_id1:orders
                $redis->sAdd('item:'.$id.':orders', $order_id);
                //sadd order:$order_id1:items
                $redis->sAdd('order:'.$order_id.':items', $id);
                // update cached info to the engine to cache
                recommat_get_model($id, false);
            }
        }
	}
	
};

/** 
 * update product info to ec site
 * 
 * @param INT $product_id product ID for simple product, or variation id
*/
function recommat_update($product_id) {
    $items = recommat_get($product_id);
    if(!empty($items)) {
        // var_dump($items);
        $product = new WC_Product($product_id);

        $options = get_option( 'recommat_settings' );
        $field_to_update = isset($options['recommat_field_to_use'])? $options['recommat_field_to_use'] : '_upsell_ids';

        if($product) {
            // merge with existing items instead of replace
            // TODO: provide an option in admin form to choose to replace or merge
            $upsell_ids = get_post_meta( $product_id, $field_to_update, true );
            $items = array_unique(array_merge($items, $upsell_ids));

            if( $product->is_type( 'simple' ) ){
                update_post_meta( $product_id, $field_to_update, $items );
            } elseif( $product->is_type( 'variable' ) ){
                update_post_meta( $product->get_parent_id(), $field_to_update, $items );
            }
        }
    }
}
