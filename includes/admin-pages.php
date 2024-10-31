<?php

defined( 'ABSPATH' ) or die( 'Keep Quit' );

function recommat_setup_menu(){
	// string $page_title, string $menu_title, string $capability, string $menu_slug, callable $function = '', int $position = null 
    add_management_page( 'Recommat Admin', 'Recommat Admin', 'manage_options', 'recommat-plugin', 'recommat_admin_setup' );

	//string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $function = '', int $position = null 
    add_submenu_page( 'recommat-plugin', 'Recommat Reports', 'sub page', 'manage_options', 'recommat-plugin-sub', 'recommat_options_page_sub' );
}

function recommat_admin_setup() {
	$redis = recommat_get_redis();
    
	echo '<br />';
    if(!$redis) {
        echo '❌ redis not connected<br /><br />';
    } else {
        echo '✅ redis connected<br /><br />';

		$total_order_processed = $redis->get('total_order_processed');
        echo 'A.I. learnt from: '.number_format($total_order_processed).' orders<br />';

		echo '<br />';
		
		$last_queued_order_id = $redis->get('last_queued_order_id');
		$order_queue_length = $redis->lLen('order_queue');
        echo 'Last queued order: #'.esc_html($last_queued_order_id).' ('.esc_html($order_queue_length).' items in queue)<br />';

        $last_processed_order_id = $redis->get('last_processed_order_id');
        echo 'Last processed order: #'.esc_html($last_processed_order_id).'<br />';
		
	}
	echo '<br />';

    ?>
    <form action='options.php' method='post'>
    <?php
        settings_fields( 'recommatPluginPage' );
        do_settings_sections( 'recommatPluginPage' );
        submit_button();
    ?>
    </form>

	<a href="admin.php?page=recommat-plugin-sub">Report</a>
<?php
}

function recommat_settings_init(  ) { 

	register_setting( 'recommatPluginPage', 'recommat_settings' );

	add_settings_section( 'recommat_pluginPage_section', 'Settings', 'recommat_admin_section_render', 'recommatPluginPage' );

	add_settings_field( 
		'recommat_redis_url', 
		__( 'Redis URL', 'recommat' ), 
		'recommat_redis_url_render', 
		'recommatPluginPage', 
		'recommat_pluginPage_section' 
	);

	add_settings_field( 
		'recommat_redis_port', 
		__( 'Redis port', 'recommat' ), 
		'recommat_redis_port_render', 
		'recommatPluginPage', 
		'recommat_pluginPage_section' 
	);

    add_settings_field( 
		'recommat_redis_auth', 
		__( 'Redis auth', 'recommat' ), 
		'recommat_redis_auth_render', 
		'recommatPluginPage', 
		'recommat_pluginPage_section' 
	);

	add_settings_field( 
		'recommat_is_replace', 
		__( 'Replace the field value?', 'recommat' ), 
		'recommat_is_replace_render', 
		'recommatPluginPage', 
		'recommat_pluginPage_section' 
	);

	add_settings_field( 
		'recommat_field_to_use', 
		__( 'Fill to which field?', 'recommat' ), 
		'recommat_field_to_use_render', 
		'recommatPluginPage', 
		'recommat_pluginPage_section' 
	);

	add_settings_field( 
		'recommat_number_of_order_processed_per_hour', 
		__( 'Orders send to AI engine', 'recommat' ), 
		'recommat_number_of_order_processed_per_hour_render', 
		'recommatPluginPage', 
		'recommat_pluginPage_section' 
	);

	add_settings_field( 
		'recommat_api_key', 
		__( 'Pro API key', 'recommat' ), 
		'recommat_api_key_render', 
		'recommatPluginPage', 
		'recommat_pluginPage_section' 
	);

	add_settings_field( 
		'recommat_api_passphase', 
		__( 'Pro API passphase', 'recommat' ), 
		'recommat_api_passphase_render', 
		'recommatPluginPage', 
		'recommat_pluginPage_section' 
	);

}


function recommat_api_key_render(  ) { 

	$options = get_option( 'recommat_settings' );
	?>
	<input type='text' name='recommat_settings[recommat_api_key]' value='<?php echo esc_attr($options['recommat_api_key']); ?>' size="60">
	<?php

}


function recommat_api_passphase_render(  ) { 

	$options = get_option( 'recommat_settings' );
	?>
	<input type='text' name='recommat_settings[recommat_api_passphase]' value='<?php echo esc_attr($options['recommat_api_passphase']); ?>' size="60">
	<?php

}


function recommat_redis_url_render(  ) { 

	$options = get_option( 'recommat_settings' );
	?>
	<input type='text' name='recommat_settings[recommat_redis_url]' value='<?php echo esc_attr($options['recommat_redis_url']); ?>' size="80">
    <p>without http(s)</p>
	<?php

}


function recommat_redis_port_render(  ) { 

	$options = get_option( 'recommat_settings' );
	?>
	<input type='text' name='recommat_settings[recommat_redis_port]' value='<?php echo esc_attr($options['recommat_redis_port']); ?>'>
	<?php

}

function recommat_redis_auth_render(  ) { 

	$options = get_option( 'recommat_settings' );
	?>
	<input type='password' name='recommat_settings[recommat_redis_auth]' value='<?php echo esc_attr($options['recommat_redis_auth']); ?>' size="60">
	<?php

}

function recommat_is_replace_render(  ) { 

	$options = get_option( 'recommat_settings' );
	?>
	<input type='checkbox' name='recommat_settings[recommat_is_replace]' <?php checked( isset($options['recommat_is_replace']), 1 ); ?> value='1' disabled> If unchecked, will append the suggestions
	<?php

}

function recommat_field_to_use_render(  ) { 

	$options = get_option( 'recommat_settings' );
	?>
	<select name='recommat_settings[recommat_field_to_use]'>
		<option value='_upsell_ids' <?php selected( $options['recommat_field_to_use'], 1 ); ?>>Upsell</option>
		<option value='_crosssell_ids' <?php selected( $options['recommat_field_to_use'], 2 ); ?>>Crosssell</option>
	</select>
    <p>Change this value may lead to error.</p>
<?php

}

function recommat_number_of_order_processed_per_hour_render(  ) { 

	$options = get_option( 'recommat_settings' );

	if( recommat()->is_pro_active()) {
	?>
	<select name='recommat_settings[recommat_number_of_order_processed_per_hour]'>
		<option value='5' <?php selected( $options['recommat_number_of_order_processed_per_hour'], 5 ); ?>>5</option>
		<option value='10' <?php selected( $options['recommat_number_of_order_processed_per_hour'], 10 ); ?>>10</option>
		<option value='15' <?php selected( $options['recommat_number_of_order_processed_per_hour'], 15 ); ?>>15</option>
		<option value='30' <?php selected( $options['recommat_number_of_order_processed_per_hour'], 30 ); ?>>30</option>
		<option value='50' <?php selected( $options['recommat_number_of_order_processed_per_hour'], 50 ); ?>>50</option>
	</select> orders per hour
	<?php
	} else {
	?>
		<input type="hidden" name="recommat_settings[recommat_number_of_order_processed_per_hour]" value="3" /> 24 orders per day
		<p><a href="<?php echo recommat()->get_pro_link();?>">Get Pro version to unlock!</a></p>
	<?php
	}
}

function recommat_admin_section_render() {
    return;
}

function recommat_options_page(  ) { 
	
	?>
    <form action='options.php' method='post'>
		
		<h2>recommat</h2>
		
        <?php
        settings_fields( 'pluginPage' );
        do_settings_sections( 'pluginPage' );
        submit_button();
        ?>

</form>
<?php

} // end recommat_options_page()


// admin menu
add_action('admin_menu', 'recommat_setup_menu');

add_action( 'admin_init', 'recommat_settings_init' );

add_action('admin_enqueue_scripts', 'recommat_admin_scripts');

function recommat_admin_scripts($hook_suffix) {
	if($hook_suffix == 'admin_page_recommat-plugin-sub') {
		wp_enqueue_style('recommat-admin-bootstrap', plugin_dir_url( __FILE__ ) . '../assets/css/bootstrap.min.css' );
		wp_enqueue_script('recommat-admin-js', plugin_dir_url( __FILE__ ) . '../assets/js/admin.js','','',true );
		wp_enqueue_script('recommat-admin-chartjs', plugin_dir_url( __FILE__ ) . '../assets/js/chart.min.js');
	}
}

function recommat_options_page_sub(  ) { 

	$redis = recommat_get_redis();

	echo '<br />';
	if(!$redis) {
		echo '❌ redis not connected<br /><br />';
		return;
	}

	if( !recommat()->is_pro_active()) {
		$is_pro_active = false;
	} else {
		$is_pro_active = true;
	}

	$results = array();
	$summary = array(
		'order_count' => 0,
		'total_no_of_items' => 0,
		'total_amount' => 0,
		'multiple_item_order_count' => 0,
		'recommended_order_count' => 0,
		'added_amount' => 0,
	);
	if($is_pro_active) {

		if(isset($_GET['end_date']) && !empty($_GET['end_date'])) {
			$end_date_array = date_parse_from_format('Ymd', $_GET['end_date']);
			if($end_date_array['error_count'] > 0){
				$end_date = date('Ymd');
			} else {
				$end_date = $end_date_array['year'] . $end_date_array['month'] . $end_date_array['day'];
			}
		} else {
			$end_date = date('Ymd');
		}
		if(isset($_GET['duration']) && !empty($_GET['duration'])) {
			$duration = filter_var($_GET['duration'], FILTER_VALIDATE_INT, array(
				'options' => array('min_range' => 1, 'max_range' => 60, 'default' => 30)
			));
		} else {
			$duration = 30; //days
		}
	} else {
		$duration = 7; //days
		$end_date = date('Ymd');
	}

	$report_end_date = strtotime($end_date);
	$report_start_date = $report_end_date-86400*($duration-1);
	
	for ($i=$report_start_date; $i<=$report_end_date; $i+=86400) {  
		$d = date("Ymd", $i);  
		
		if(!$redis->exists('daily:order:'.$d)) {
			$results[$d] = array(
				'order_count' => 0,
				'total_no_of_items' => 0,
				'total_amount' => 0,
				'multiple_item_order_count' => 0,
				'recommended_order_count' => 0,
				'added_amount' => 0,
			);
		} else {
			$results[$d] = $redis->hMGet('daily:order:'.$d, array(
				'order_count', 'total_no_of_items', 'total_amount', 'multiple_item_order_count', 'recommended_order_count', 'added_amount'
			));
			$summary['order_count'] += (int)$results[$d]['order_count'];
			$summary['total_no_of_items'] += (int)$results[$d]['total_no_of_items'];
			$summary['total_amount'] += (int)$results[$d]['total_amount'];
			$summary['multiple_item_order_count'] += (int)$results[$d]['multiple_item_order_count'];
			$summary['recommended_order_count'] += (int)$results[$d]['recommended_order_count'];
			$summary['added_amount'] += (int)$results[$d]['added_amount'];
		}
	} 
	
	$order_variance = 0.0;
	$amount_variance = 0.0;
	$order_average = $summary['order_count']/$duration;
	$amount_average = $summary['total_amount']/$duration;
	foreach ($results as $d => $r) {
		$order_variance += pow($r['order_count']-$order_average, 2);
		$amount_variance += pow($r['total_amount']-$amount_average, 2);
	}
	$order_standard_deviation = sqrt($order_variance/$duration);
	$amount_standard_deviation = sqrt($amount_variance/$duration);
	
	// for making custom ajax request
	wp_localize_script( 'wp-api', 'wpApiSettings', array(
		'root' => esc_url_raw( rest_url() ),
		'nonce' => wp_create_nonce( 'wp_rest' )
	) );

	?>

	<script>var admin_url = '<?php echo admin_url();?>';</script>
	<script>var data_table = {dates:[],order_count:[],multiple_item_order_count:[]};</script>
	
	<div class="container px-4 py-4" id="hanging-icons">
		<form action="<?php echo admin_url('admin.php?page=recommat-plugin-sub');?>" method="get">
			<input type="hidden" name="page" value="recommat-plugin-sub"> 
			<h2 class="pb-2 border-bottom">
				<input type="text" name="end_date" id="end_date" value="<?php echo $end_date;?>"> - 
				<select name="duration" id="duration">
					<option value="3" <?php echo ($duration==3)?'selected=selected':''; echo (!$is_pro_active)?' disabled':''; ?>>3<?php echo (!$is_pro_active)?' (Pro only)':'';?></option>
					<option value="7" <?php echo ($duration==7)?'selected=selected':'';?>>7</option>
					<option value="14" <?php echo ($duration==14)?'selected=selected':''; echo (!$is_pro_active)?' disabled':'';?>>14<?php echo (!$is_pro_active)?' (Pro only)':'';?></option>
					<option value="30" <?php echo ($duration==30)?'selected=selected':''; echo (!$is_pro_active)?' disabled':'';?>>30<?php echo (!$is_pro_active)?' (Pro only)':'';?></option>
				</select>days
				<input type="submit" value="Go">
			</h2>
	</form>
	</div>
	<div class="container px-4 py-4" id="">
		<div class="row g-4 row-cols-1 row-cols-lg-3">
		  <div class="col d-flex align-items-start">
			<div class="icon-square bg-light text-dark flex-shrink-0 me-3">
				<svg class="bi" width="1em" height="1em"><use xlink:href="#tools"/></svg>
			</div>
			<div>
				<h2>$<?php echo number_format($summary['added_amount']);?></h2>
				<p>We added to your revenue</p>
			</div>
		  </div>
		  <div class="col d-flex align-items-start">
			<div class="icon-square bg-light text-dark flex-shrink-0 me-3">
				<svg class="bi" width="1em" height="1em"><use xlink:href="#toggles2"/></svg>
			</div>
			<div>
			  <h2>
				<?php echo number_format($summary['order_count']/$duration, 2);?>
				<small>±<?php echo number_format($order_standard_deviation, 2);?></small>
			  </h2>
			  <p>Avg. order/day</p>
			</div>
		  </div>
		  <div class="col d-flex align-items-start">
			<div class="icon-square bg-light text-dark flex-shrink-0 me-3">
			  <svg class="bi" width="1em" height="1em"><use xlink:href="#cpu-fill"/></svg>
			</div>
			<div>
			  <h2>
				$<?php echo number_format($summary['total_amount']/$duration, 2);?>
				<small>±<?php echo number_format($amount_standard_deviation, 0);?></small>
			  </h2>
			  <p>Avg. amount/day</p>
			</div>
		  </div>
		</div>
	</div>
	
	<div class="container">
		<canvas id="curve_chart" width="400" height="225"></canvas>
	</div>
	
	<div class="container py-5">
	<table class="table table-bordered table-hover ">
		<thead>
			<tr>
				<th>Date</th>
				<th class="text-end">Orders</th>
				<th class="text-end">Total items</th>
				<th class="text-end">Total Amount</th>
				<th class="text-end">Orders with <br />>1 items</th>
				<th class="text-end">Orders with <br />recommended items</th>
				<th class="text-end">Added amount</th>
			</tr>
		</thead>

		<?php if($is_pro_active):?>

			<?php recommat_admin_report_table($results);?>
		
		<?php else: 
			$date = array_keys($results);?>
			<tr><td colspan="7"><a href="<?php echo recommat()->get_pro_link();?>">Get Pro to unlock stats.</a></td></tr>
			<?php foreach ($date as $d){ ?>
			<tr>
				<td><?php echo date('Y-m-d', strtotime($d));?></td>
				<?php for ($i=0; $i < 6; $i++) {?>
					<td class="text-end"><a href="<?php echo recommat()->get_pro_link();?>">Unlock</a></td>
				<?php } ?>
			</tr>
			<?php } ?>
			<script>data_table.push(Array('',0,0));</script>
		<?php endif;?>
		<tfoot>
			<tr>
				<td>Total</td>
				<td class="text-end"><?php echo esc_html($summary['order_count']);?></td>
				<td class="text-end"><?php echo esc_html($summary['total_no_of_items']);?></td>
				<td class="text-end">$<?php echo number_format($summary['total_amount']);?></td>
				<td class="text-end"><?php echo esc_html($summary['multiple_item_order_count']);?></td>
				<td class="text-end"><?php echo esc_html($summary['recommended_order_count']);?></td>
				<td class="text-end">$<?php echo number_format($summary['added_amount']);?></td>
			</tr>
		</tfoot>
	</table>
	</div>

<?php } //end recommat_options_page_sub()