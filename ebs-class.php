<?php
if ('ebs-class.php' == basename($_SERVER['SCRIPT_FILENAME']))
     die ('<h2>Direct File Access Prohibited</h2>');
/*******************************************************************************
 *                      PHP ebs IPN Integration Class
 *******************************************************************************
 *      Author:     L.Ch.Rajkumar
 *      Based on: EBS
 *      
 *      This class uses the EBS payment system
 *      into ESHOP WordPress Plugin's Merchant Gateway
 * 
 *******************************************************************************
*/
class ebs_class{
    var $last_error;
    var $ipn_response;
    var $ipn_data = array();
    var $fields = array();
    function ebs_class() {
       
      // initialization constructor.  Called when class is created.
      $this->last_error = '';
      $this->ipn_response = '';
    
   }
   function add_field($field, $value) {
      
      // adds a key=>value pair to the fields array, which is what will be 
      // sent to ebs as POST variables.  If the value is already in the 
      // array, it will be overwritten.
      
      $this->fields["$field"] = $value;
   }
    function submit_ebs_post() {
      // The user will briefly see a message on the screen that reads:
      // "Please wait, your order is being processed..." and then immediately
      // is redirected to ebs.
      $echo= "<form method=\"post\" class=\"eshop eshop-confirm\" action=\"".$this->autoredirect."\"><div>\n";
	/*
	*
	* Grab the standard data
	*
	*/
      foreach ($this->fields as $name => $value) {
			$pos = strpos($name, 'amount');
			if ($pos === false) {
			   $echo.= "<input type=\"hidden\" name=\"$name\" value=\"$value\" />\n";
			}else{
				$echo .= eshopTaxCartFields($name,$value);
      	    }
      }
      	/*
	  	* Changes the standard text of the redirect page.
		*/
      $refid=uniqid(rand());
      $echo .= "<input type=\"hidden\" name=\"ebsoption1\" value=\"$refid\" />\n";
      $echo.='<label for="ppsubmit" class="finalize"><small>'.__('<strong>Note:</strong> Submit to finalize order at EBS Payment System.','eshop').'</small><br />
      <input class="button submit2" type="submit" id="ppsubmit" name="ppsubmit" value="'.__('Proceed to Checkout &raquo;','eshop').'" /></label>';
	  $echo.="</div></form>\n";
      
      return $echo;
   }
   function eshop_submit_ebs_post(){
       $ebs = $eshopoptions['ebs'];
       	$echortn='<div id="process">
         <p><strong>'.__('Please wait, your order is being processed&#8230;','eshop').'</strong></p>
	     <p>'. __('If you are not automatically redirected to EBS, please use the <em>Proceed to ebs</em> button.','eshop').'</p>
         <form method="post" id="eshopgatebs" class="eshop" action="'.$this->ebs_url.'">
          <p>';
        $replace = array("&#039;","'", "\"","&quot;","&amp;","&");
        $ebs = $eshopoptions['ebs'];
        /* your changes would replace this section: start*/
        $Cost=$_POST['amount'];
        if(isset($_POST['tax']))
			$Cost += $_POST['tax'];
        if(isset($_SESSION['shipping'.$blog_id]['tax'])) $Cost += $_SESSION['shipping'.$blog_id]['tax'];
        $Cost = $Cost * 100;
        $State = $_POST['state'].''.$_POST['altstate'];
        if($eshopoptions['status']!='live'){
			$ebs['id']='87654321';
		}
                $echortn.='
                    <input type="hidden" name="account_id" value="'.$ebs[acid].'" />
                    <input type="hidden" name="name" value="'.$_POST['first_name'].'" />
                    <input type="hidden" name="address" value="'.$_POST['address1'].'" />
                    <input type="hidden" name="city" value="'.$_POST['city'].'"/>
                    <input type="hidden" name="state" value="'.$State.'"/>
                    <input type="hidden" name="country" value="'.$_POST['country'].'"/>
                    <input type="hidden" name="postal_code" value="'.$_POST['zip'].'"/>
                    <input type="hidden" name="phone" value="'.$_POST['phone'].'"/>
                    <input type="hidden" name="email" value="'.$_POST['email'].'"/>
                    <input type="hidden" name="amount" value="'.$Cost.'"/>
                    <input class="button" type="submit" id="ppsubmit" name="ppsubmit" value="'. __('Proceed to EBS Payment System &raquo;','eshop').'" /></p>    
                 </form>
	  </div>';
                return $echortn;
   }
   function validate_ipn() {
      // generate the post string from the _POST vars aswell as load the
      // _POST vars into an arry so we can play with them from the calling
      // script.
      foreach ($_REQUEST as $field=>$value) { 
         $this->ipn_data["$field"] = $value;
      }
     
   }
}
?>
