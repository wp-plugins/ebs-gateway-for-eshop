<?php

/*Plugin Name: EBS Gateway For eShop.
* Plugin URI: 
* Description: EBS Merchant Gateway for eShop WordPress Plugin. New versions will be coming soon & you can purchase them from my company store once I upload them.
* Version: 0.1
* Author: L.Ch.Rajkumar 
* Author URI: http://www.twitter.com/lchrajkumar/
* Copyright 2013 L.Ch.Rajkumar  (email : hr.techtransformerz@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
register_activation_hook(__FILE__,'eshopebs_activate');
function eshopebs_activate(){
	/*
	* Activation routines
	*/
	global $wpdb;
	$opts=get_option('active_plugins');
	$eshopthere=false;
	foreach($opts as $opt){
		if($opt=='eshop/eshop.php')
			$eshopthere=true;
	}
	if($eshopthere==false){
		deactivate_plugins('ebs-gateway-eshop.php'); //Deactivate ourself
		wp_die(__('ERROR! eShop is not active.','eshop')); 
	}
	/*
	* insert email template for use with this merchant gateway, if 151 is changed, then ipn.php needs amending as well 
	*/
	$table = $wpdb->prefix ."eshop_emails";
	$esubject=__('Your order from ','eshop').get_bloginfo('name');
	$wpdb->query("INSERT INTO ".$table." (id,emailType,emailSubject) VALUES ('151','".__('Automatic EBS email','eshop')."','$esubject')"); 
        
	
}
add_action('eshop_setting_merchant_load','eshopmgpage2');
function eshopmgpage2($thist){
	/*
	* adding the meta box for this gateway
	*/
	add_meta_box('eshop-m-ebs', __('EBS','eshop'), 'ebs_box', $thist->pagehook, 'normal', 'core');
}
function ebs_box($eshopoptions){
    /*
     * the meta box content, obviously you have to set up the required fields for your gateway here
     */
    if(isset($eshopoptions['ebs'])){
		$eshopebs = $eshopoptions['ebs']; 
	}else{
		$eshopebs['frname']='';
		$eshopebs['acid']='';
                $eshopebs['seckey']='';
                //$eshopebs['mode']='';
               
	}
        //add the image
        $eshopmerchantimgpath=WP_PLUGIN_DIR.'/ebs-gateway-eshop/ebs-payment.png';
	$eshopmerchantimgurl=WP_PLUGIN_URL.'/ebs-gateway-eshop/ebs-payment.png';
	$dims[3]='';
	if(file_exists($eshopmerchantimgpath))
	$dims=getimagesize($eshopmerchantimgpath);
        echo '<fieldset>';
	echo '<p class="eshopgatebs"><img src="'.$eshopmerchantimgurl.'" '.$dims[3].' alt="ebs" title="ebs" /></p>'."\n";
?>
	<p class="cbox"><input id="eshop_methodebs" name="eshop_method[]" type="checkbox" value="ebs"<?php if(in_array('ebs',(array)$eshopoptions['method'])) echo ' checked="checked"'; ?> /><label for="eshop_methodebs" class="eshopmethod"><?php _e('Accept payment by EBS(INDIA)','eshop'); ?></label></p>
	<label for="eshop_ebsfrname"><?php _e('Friendly Name','eshop'); ?></label><input id="eshop_ebsfrname" name="ebs[frname]" type="text" value="<?php echo $eshopebs['frname']; ?>" size="45" maxlength="50" /><br />
	<label for="eshop_ebsacid"><?php _e('Your Account ID','eshop'); ?></label><input id="eshop_ebsacid" name="ebs[acid]" type="text" value="<?php echo $eshopebs['acid']; ?>" size="60" maxlength="35" /><br />
        <label for="eshop_ebsseckey"><?php _e('Secret Key', 'eshop');?></label><input id="eshop_ebsseckey" name="ebs[seckey]" type="text" value="<?php echo $eshopebs['seckey'];?>" size="100" maxlength="30"/><br/>
        </fieldset>
<?php
}
add_filter('eshop_setting_merchant_save','ebssave',10,2);
function ebssave($eshopoptions,$posted){
	/*
	* save routine for the fields you added above
	*/
	global $wpdb;
	$ebspost['frname']=$wpdb->escape($posted['ebs']['frname']);
	//$moneybookerspost['id']=$wpdb->escape($posted['moneybookers']['id']);
	$ebspost['acid']=$wpdb->escape($posted['ebs']['acid']);
        $ebspost['seckey']=$wpdb->escape($posted['ebs']['seckey']);
	$eshopoptions['ebs']=$ebspost;
	return $eshopoptions;
}
add_action('eshop_include_mg_ipn','eshopebs');
function eshopebs($eshopaction){
	/*
	* adding the necessary link for the instant payment notification of your gateway
	*/
	if($eshopaction=='ebsipn'){
		include_once WP_PLUGIN_DIR.'/ebs-gateway-eshop/ipn.php';
	}
}
add_filter('eshop_mg_inc_path','ebspath',10,2);
function ebspath($path,$paymentmethod){
	/*
	* adding another necessary link for the instant payment notification of your gateway
	*/
	if($paymentmethod=='ebs')
		return WP_PLUGIN_DIR.'/ebs-gateway-eshop/ipn.php';
	return $path;
}
add_filter('eshop_mg_inc_idx_path','ebsidxpath',10,2);
function ebsidxpath($path,$paymentmethod){
	/*
	* adding the necessary link to the class for this gateway
	*/
	if($paymentmethod=='ebs')
		return WP_PLUGIN_DIR.'/ebs-gateway-eshop/ebs-class.php';
	return $path;
}
//message on fail.
add_filter('eshop_show_success', 'eshop_ebs_return_fail',10,3);
function eshop_ebs_return_fail($echo, $eshopaction, $postit){
	/*
	* failed payment, you can add in details for this, will need tweaking for your gateway
	*/
	//these are the successful codes, all others fail
	$ebsrescodes=array('00','08','10','11','16');
	if($eshopaction=='ebsipn'){
		if($postit['ebsTrxnStatus']=='False' && !in_array($postit['ebsresponseCode'],$ebsrescodes))
			$echo .= '<p>There was a problem with your order, please contact admin@ ... quoting Error Code '.$postit['ebsresponseCode']."</p>\n";
	}
	return $echo;
}
?>
