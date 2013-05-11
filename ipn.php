<?php
/*  Based on:
 *EBS IPN Integration Class Demonstration File
 * 
*/
global $wpdb,$wp_query,$wp_rewrite,$blog_id,$eshopoptions;
$detailstable=$wpdb->prefix.'eshop_orders';
$derror=__('There appears to have been an error, please contact the site admin','eshop');

//sanitise
include_once(WP_PLUGIN_DIR.'/eshop/cart-functions.php');
$_POST=sanitise_array($_POST);


/*
* reqd info for your gateway
*/
include_once (WP_PLUGIN_DIR.'/ebs-gateway-eshop/ebs-gateway-eshop.php');
// Setup class
require_once(WP_PLUGIN_DIR.'/ebs-gateway-eshop/ebs-class.php');  // include the class file
$p = new ebs_class;             // initiate an instance of the class

$p->ebs_url = 'https://secure.ebs.in/pg/ma/sale/pay/';     // EBS url

/*
* reqd info /end
*/

$this_script = site_url();
global $wp_rewrite;
if($eshopoptions['checkout']!=''){
	$p->autoredirect=add_query_arg('eshopaction','redirect',get_permalink($eshopoptions['checkout']));
}else{
	die('<p>'.$derror.'</p>');
}

// if there is no action variable, set the default action of 'process'
if(!isset($wp_query->query_vars['eshopaction']))
	$eshopaction='process';
else
	$eshopaction=$wp_query->query_vars['eshopaction'];

switch ($eshopaction) {
    case 'redirect':
    	//auto-redirect bits
		header('Cache-Control: no-cache, no-store, must-revalidate'); //HTTP/1.1
		header('Expires: Sun, 01 Jul 2005 00:00:00 GMT');
		header('Pragma: no-cache'); //HTTP/1.0

		//enters all the data into the database
		/*
		* this works out eShop's security field
		*/
		$Cost=$_POST['amount'];
		if(isset($_POST['tax']))
			$Cost += $_POST['tax'];
		if(isset($_SESSION['shipping'.$blog_id]['tax'])) $Cost += $_SESSION['shipping'.$blog_id]['tax'];
		$theid=$eshopoptions['ebs']['id'];
		$Cost=number_format($Cost,2);
		$checkid=md5($_POST['ebsoption1'].$theid.'$'.$Cost);
		if(isset($_COOKIE['ap_id'])) $_POST['affiliate'] = $_COOKIE['ap_id'];
		orderhandle($_POST,$checkid);
		if(isset($_COOKIE['ap_id'])) unset($_POST['affiliate']);
		$p = new ebs_class; 
		/*
		* more reqd info
		*/
		$p->ebs_url = 'https://secure.ebs.in/pg/ma/sale/pay/';     // EBS url
		$echoit.=$p->eshop_submit_ebs_post($_POST);
		break;
        
   case 'process':      // Process and order...
		// There should be no output at this point.  To process the POST data,
		// the submit_ebs_post() function will output all the HTML tags which
		// contains a FORM which is submited instantaneously using the BODY onload
		// attribute.  In other words, don't echo or printf anything when you're
		// going to be calling the submit_ebs_post() function.
		
		// This is where you would have your form validation  and all that jazz.
		// You would take your POST vars and load them into the class like below,
		// only using the POST values instead of constant string expressions.

		// For example, after ensureing all the POST variables from your custom
		// order form are valid, you might have:
		//
		// $p->add_field('first_name', $_POST['first_name']);
		// $p->add_field('last_name', $_POST['last_name']);
      
      /****** The order has already gone into the database at this point ******/
      
		//goes direct to this script as nothing needs showing on screen.
		if($eshopoptions['cart_success']!=''){
			$ilink=add_query_arg(array('eshopaction'=>'ebsipn'),get_permalink($eshopoptions['cart_success']));
		}else{
			die('<p>'.$derror.'</p>');
		}
		$p->add_field('ebsURL', $ilink);

		$p->add_field('shipping_1',eshopShipTaxAmt());
		$sttable=$wpdb->prefix.'eshop_states';
		$getstate=$eshopoptions['shipping_state'];
		if($eshopoptions['show_allstates'] != '1'){
			$stateList=$wpdb->get_results("SELECT id,code,stateName FROM $sttable WHERE list='$getstate' ORDER BY stateName",ARRAY_A);
		}else{
			$stateList=$wpdb->get_results("SELECT id,code,stateName,list FROM $sttable ORDER BY list,stateName",ARRAY_A);
		}
		foreach($stateList as $code => $value){
			$eshopstatelist[$value['id']]=$value['code'];
		}		
		foreach($_POST as $name=>$value){
			//have to do a discount code check here - otherwise things just don't work - but fine for free shipping codes
			if(strstr($name,'amount_')){
				if(isset($_SESSION['eshop_discount'.$blog_id]) && eshop_discount_codes_check()){
					$chkcode=valid_eshop_discount_code($_SESSION['eshop_discount'.$blog_id]);
					if($chkcode && apply_eshop_discount_code('discount')>0){
						$discount=apply_eshop_discount_code('discount')/100;
						$value = number_format(round($value-($value * $discount), 2),2);
						$vset='yes';
					}
				}
				if(is_discountable(calculate_total())!=0 && !isset($vset)){
					$discount=is_discountable(calculate_total())/100;
					$value = number_format(round($value-($value * $discount), 2),2);
				}
			}
			if(sizeof($stateList)>0 && ($name=='state' || $name=='ship_state')){
				if($value!='')
					$value=$eshopstatelist[$value];
			}
			$p->add_field($name, $value);
		}
		if($eshopoptions['status']!='live' && is_user_logged_in() &&  current_user_can('eShop_admin')||$eshopoptions['status']=='live'){
			$echoit .= $p->submit_ebs_post(); // submit the fields to ebs
    	}
      	break;
        
        case 'success':      // Order was successful...
		// This is where you would probably want to thank the user for their order
		// or what have you.  The order information at this point is in POST 
		// variables.  However, you don't want to "process" the order until you
		// get validation from the IPN.  That's where you would have the code to
		// email an admin, update the database with payment status, activate a
		// membership, etc.  
		$_SESSION = array();
      	session_destroy();
      	if($eshopoptions['status']=='live'){
			$txn_id = $wpdb->escape($espost['txn_id']);
		}else{
			$txn_id = "TEST-".$wpdb->escape($espost['txn_id']);
		}
		$frow=$wpdb->get_var("select first_name from $detailstable where transid='$txn_id' limit 1");
		$lrow=$wpdb->get_var("select last_name from $detailstable where transid='$txn_id' limit 1");
		if($frow!='' && $lrow!=''){
			$echoit .= "<h3>".__('Thank you for your order','eshop').", ".$frow." ".$lrow."!</h3>";
		}else{
			$echoit .= "<h3>".__('Thank you for your order!','eshop')."</h3>";
		}
		//echo 'name='.$row->first_name.' '.$row->last_name.'<br>';
		// You could also simply re-direct them to another page, or your own 
		// order status page which presents the user with the status of their
		// order based on a database (which can be modified with the IPN code 
		// below).
       	break;
        
        case 'cancel':       // Order was canceled...
	  		/*
	  		The script doesn't get here, so for cancelled orders see the bottom of cart.php
	  		Unfortunate side effect is that the order is left in pending
	  		*/
	  		// The order was canceled before being completed.
	  		/* commented out until i can think of a way to get this to work :(  -Rich
	  		$checked=md5($p->ipn_data['business'].$p->ipn_data['custom'].$p->ipn_data['payer_email'].$p->ipn_data['mc_gross']);
	  		$tstatus=$wpdb->get_var("select status from $detailstable where checkid='$checked' limit 1");
	  		if($eshopoptions['status']=='live'){
	  			$txn_id = 'Cancelled-'.$wpdb->escape($p->ipn_data['txn_id']);
	  		}else{
	  			$txn_id = "TEST-Cancelled-".$wpdb->escape($p->ipn_data['txn_id']);
	  		}
	  		if($tstatus=='Pending'){
	  			$query2=$wpdb->query("UPDATE $detailstable set status='Failed',transid='$txn_id' where checkid='$checked'");
	  		}
	  		*/

		break;
      	
   case 'ebsipn':
   		/*
   		* the routine for when the merchant gateway sontacts your site to validate the order.
   		* may need altering to suit your gateway
   		*/
   		$p->validate_ipn();
		$theid=$eshopoptions['ebs']['id'];
		$checked=md5($p->ipn_data['ebsoption1'].$theid.$p->ipn_data['ebsReturnAmount']);
		if($eshopoptions['status']=='live'){
			$txn_id = $wpdb->escape($p->ipn_data['ebsTrxnReference']);
			$subject = __('EBS Payment IPN -','eshop');
		}else{
			$txn_id = __("TEST-",'eshop').$wpdb->escape($p->ipn_data['ebsTrxnReference']);
			$subject = __('Testing: EBS Payment IPN - ','eshop');
		}
		//check txn_id is unique
		$checktrans=$wpdb->get_results("select transid from $detailstable");
		$astatus=$wpdb->get_var("select status from $detailstable where checkid='$checked' limit 1");
		foreach($checktrans as $trans){
			if(strpos($trans->transid, $p->ipn_data['ebsTrxnReference'])===true){
				$astatus='Failed';
				$txn_id .= __(" - Duplicated",'eshop');
				$ebsdetails .= __("Duplicated Transaction Id.",'eshop');
			}
		}
		//accepted response codes - all other fail.
		$ebsrescodes=array('00','08','10','11','16');
		if(!in_array($p->ipn_data['ebsresponseCode'],$ebsrescodes)){
			$astatus='Failed';
			$txn_id .= __(" - Failed",'eshop');
			$ebsdetails .= ' '.$p->ipn_data['ebsresponseText'];
		}

		//the magic bit  + creating the subject for our email.
		if($astatus=='Pending' && $p->ipn_data['ebsTrxnStatus']=='True'){
			$subject .=__("Completed Payment",'eshop');	
			$ok='yes';
			eshop_mg_process_product($txn_id,$checked);
		}else{
			$query2=$wpdb->query("UPDATE $detailstable set status='Failed',transid='$txn_id' where checkid='$checked'");
			$subject .=__("A Failed Payment",'eshop');
			$ok='no';
			$ebsdetails .= __("The transaction was not completed successfully. eShop could not validate the order.",'eshop');
			$ebsdetails .= ' '.$p->ipn_data['ebsresponseText'];
		}
		$subject .=" Ref:".$txn_id;
		$array=eshop_rtn_order_details($checked);
		// email to business a complete copy of the notification from ebs to keep!!!!!
		 $body =  __("An EBS payment notification was received",'eshop')."\n";
		 $body .= "\n".__("from ",'eshop').$array['eemail'].__(" on ",'eshop').date('m/d/Y');
		 $body .= __(" at ",'eshop').date('g:i A')."\n\n".__('Details','eshop').":\n";
		 if(isset($array['dbid']))
			$body .= get_option( 'siteurl' ).'/wp-admin/admin.php?page=eshop-orders.php&view='.$array['dbid']."\n";

		if($ebsdetails!='') $body .= $ebsdetails."\n\n";
		foreach ($p->ipn_data as $key => $value) { $body .= "\n$key: $value"; }
		//debug
		//	$body .= "\n".'check: '.$p->ipn_data['ebsoption1'].$theid.$p->ipn_data['ebsReturnAmount'];

		$body .= "\n\n".__('Regards, Your friendly automated response.','eshop')."\n\n";
		$headers=eshop_from_address();
		$eshopemailbus=$eshopoptions['ebs']['email'];
		$to = apply_filters('eshop_gatebs_details_email', array($eshopemailbus));
		wp_mail($to, $subject, $body, $headers);

		if($ok=='yes'){
			//only need to send out for the successes!
			//lets make sure this is here and available
			include_once(WP_PLUGIN_DIR.'/eshop/cart-functions.php');
			eshop_send_customer_email($checked, '151');
		}
		$_SESSION = array();
		session_destroy();
		break;
}
?>