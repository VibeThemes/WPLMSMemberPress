<?php
/**
 * Plugin Name: WPLMS MemberPress
 * Plugin URI: https://wplms.io
 * Description: Integrate WPLMS with MemberPress
 * Version: 1.0
 * Author: VibeThemes
 * Author URI: https://vibethemes.com/
 * Text Domain: wplms-memberpress
 * Domain Path: languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;


class WPLMSMemberPress_Init{

    public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new WPLMSMemberPress_Init();

        return self::$instance;
    }

    private function __construct(){

    	add_action( 'mepr-product-options-tabs', array( $this, 'wplms_option' ) );
		add_action( 'mepr-product-options-pages', array( $this, 'wplms_option_page' ) );
		add_action( 'mepr-membership-save-meta', array( $this, 'save_post_meta' ) );


		add_action( 'mepr-txn-transition-status', array( $this, 'transaction_transition_status' ), 10, 3 );
		add_action( 'mepr-transaction-expired', array( $this, 'transaction_expired' ), 10, 2 );
		add_action( 'mepr_pre_delete_transaction', array( $this, 'delete_transaction' ), 10, 1 );
		add_action( 'mepr_subscription_transition_status', array( $this, 'subscription_transition_status' ), 10, 3 );
    }

    function wplms_option(){
    	echo '<a class="nav-tab main-nav-tab" href="#" id="wplms">'.__( 'WPLMS', 'wplms-memberpress' ).'</a>';
		
    }

    function wplms_option_page($product){

    	
    	$courses = new WP_Query(array('post_type'=>'course','posts_per_page'=>-1));

    	if($courses->have_posts()){

		$saved_courses = maybe_unserialize( get_post_meta( $product->rec->ID, '_wplms_courses', true ) );
		?>
		
		<div class="product_options_page wplms">
			<div class="product-options-panel">
				<div class="memberpress-options">
					<p><strong><?php _e( 'Courses', 'wplms-memberpress' ); ?></strong></p>
					<ul class="wplms-course-option" style="display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); grid-gap: 15px; align-items: center;">
					<?php 

					while($courses->have_posts()){
						$courses->the_post();
						?>
						<li><label for="<?php echo get_the_ID(); ?>">
							<input type="checkbox" name="_wplms_courses[]" id="<?php echo get_the_ID(); ?>" value="<?php echo get_the_ID(); ?>" <?php echo $this->is_selected( get_the_ID(), $saved_courses ); ?>>
							<?php echo  get_the_title(); ?>
						</label></li>
						<?php
					}
					?>
					</div>
				</div>
			</div>
		</div>

		<?php
		}
    }

    function is_selected($course_id,$course_ids){
    	
    	if(!empty($course_ids) && in_array($course_id,$course_ids)){
    		return 'checked="checked"';
    	}


    	return '';
    }

    function save_post_meta(){

    	$associated_courses = get_post_meta( $_POST['post_ID'], '_wplms_courses', true );
		$courses = $_POST['_wplms_courses'];

		do_action( 'wplms_memberpress_update_courses', $associated_courses,$courses );

		update_post_meta( $_POST['post_ID'], '_wplms_courses', $courses );
    }


    function transaction_transition_status( $old_status, $new_status, $txn )
	{

		print_r($old_status.' --- '.$new_status);
		
		
		// if ( $txn->subscription() !== false ) {
		// 	return;
		// }

		$courses = maybe_unserialize( get_post_meta( $txn->rec->product_id, '_wplms_courses', true ) );

		if ( empty( $courses ) ) {
			return;
		}

		if ( ( $txn->txn_type == 'sub_account' || $old_status != 'complete' ) && $new_status == 'complete' ) {
			foreach ( $courses as $course_id ) {
				bp_course_add_user_to_course($txn->rec->user_id,$course_id);
			}
		} elseif ( $old_status == 'complete' && $new_status != 'complete' ) {
			foreach ( $courses as $course_id ) {


				bp_course_remove_user_from_course( $txn->rec->user_id,$course_id);
			}
		}
	}

	public function transaction_expired( $txn, $sub_status )
	{
		$courses = maybe_unserialize( get_post_meta( $txn->rec->product_id, '_wplms_courses', true ) );

		if ( empty( $courses ) ) { 
			return; 
		}

		$user = new MeprUser( $txn->user_id );
		$subs = $user->active_product_subscriptions( 'ids' );

		if ( ! empty( $subs ) && in_array( $txn->product_id, $subs ) ) { 
			return; 
		}

		foreach ( $courses as $course_id ) { 
			bp_course_remove_user_from_course( $txn->rec->user_id,$course_id);
		}
	}

	public function delete_transaction( $txn )
	{
		if ( $txn->subscription() ) {
			return;
		}

		if ( $txn->rec->status != 'complete' ) {
			return;
		}

		$courses = maybe_unserialize( get_post_meta( $txn->product_id, '_wplms_courses', true ) );

		if ( empty( $courses ) ) {
			return;
		}
		
		foreach ( $courses as $course_id ) {
			bp_course_remove_user_from_course( $txn->rec->user_id,$course_id);
		}
	}


	public function subscription_transition_status( $old_status, $new_status, $subscription )
	{
		$courses = maybe_unserialize( get_post_meta( $subscription->product_id, '_wplms_courses', true ) );

		
		if ( empty( $courses ) ) {
			return;
		}

		if ( $new_status == 'active' ) {

			foreach ( $courses as $course_id ) {
				bp_course_add_user_to_course($subscription->user_id,$course_id);
			}

		} elseif ( $new_status != 'active' ) {
			if ( ! $subscription->is_expired() ) {
				return;
			}

			foreach ( $courses as $course_id ) {
				bp_course_remove_user_from_course( $subscription->user_id,$course_id);
			}
		}
	}
}

WPLMSMemberPress_Init::init();