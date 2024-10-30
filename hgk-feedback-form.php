<?php
/*
Plugin Name: HGK Feedback Form
Plugin URI: http://www.ihagaki.com/wordpress/hgk-feedback-form-plugin/
Description: Drop-in feedback / contact form for your WordPress blog. Works for pages and posts.
Author: ihagaki.com
Author URI: http://www.ihagaki.com
Version: 1.2
*/

if (!class_exists("Hgk_Feedback_Form")) {
	class Hgk_Feedback_Form {
		private $hgk_ff_mail;
		private $hgk_ff_subj;
		private $hgk_ff_msg_ok;
		private $hgk_ff_msg_err;
		private $hgk_ff_token = '<!-- hgk feedback form -->';

		function init() {
			//  set defaults
			add_option('hgk_ff_mail',   __('johndoe@gmail.com', 'hgkff'));
			add_option('hgk_ff_subj',   __('New Feedback', 'hgkff'));
			add_option('hgk_ff_msg_ok', __('Thanks for your feedback!', 'hgkff'));
			add_option('hgk_ff_msg_err',__('Please fill in the required fields.', 'hgkff'));

			$this->updateState();

			add_action('wp_head',	 array(&$this, 'addHeaderHtml'), 1);
			add_action('admin_menu',  array(&$this, 'adminMenu'), 1);
			add_filter('the_content', array(&$this, 'insertFeedbackForm'), 7);
		}

		function addHeaderHtml() {
			$plugin_home_url = WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__), "", plugin_basename(__FILE__));
			echo "	<link rel='stylesheet' href='". $plugin_home_url . "style.css' type='text/css' media='screen' />\n";
		}

		function adminMenu() {
			add_options_page(__('HGK Feedback Form Options', 'hgkff'), __('HGK Feedback Form', 'hgkff'), 8, __FILE__, array(&$this, 'displayOptions'));
		}

		function displayOptions() {
			$hgk_action_url = admin_url('options-general.php?page=' . plugin_basename(__FILE__));

			//  update options
			if (isset($_POST['hgkaction']) && $_POST['hgkaction'] == 'update')
			{
				update_option('hgk_ff_mail',	trim($_POST['hgk_ff_mail']));
				update_option('hgk_ff_subj',	trim($_POST['hgk_ff_subj']));
				update_option('hgk_ff_msg_ok',  trim($_POST['hgk_ff_msg_ok']));
				update_option('hgk_ff_msg_err', trim($_POST['hgk_ff_msg_err']));
			}

			$this->updateState();
	?>

			<div class="wrap">
				<?php screen_icon(); ?>
				<h2><?php _e('HGK Feedback Form Options', 'hgkff') ?></h2>
				<form method="post" action="<?php echo $hgk_action_url ?>&amp;updated=true">
				<input type="hidden" name="hgkaction" value="update" />

				<table>
					<tr valign="top"><td colspan="3"><br/>
						<font size="-2">&nbsp;<strong><?php echo htmlspecialchars($this->hgk_ff_token) ?></strong> <i><?php _e('in posts and pages will be replaced with the feedback form', 'hgkff') ?></i></font><br/>&nbsp;</td>
					</tr>
					<tr valign="top">
						<th scope="row" align="right"><?php _e('Recipient e-mail:', 'hgkff') ?></th>
						<td>&nbsp;</td>
						<td><input name="hgk_ff_mail" type="text" id="hgk_ff_mail" value="<?php echo $this->hgk_ff_mail; ?>" size="30" />
						<br />
						<font size="-2">&nbsp;<i><?php _e('E-mail address where feedback form content will be sent to. If left blank, the blog\'s admin email is used', 'hgkff') ?></i></font><br/>&nbsp;</td>
					</tr>
					<tr valign="top">
						<th scope="row" align="right"><?php _e('E-mail subject:', 'hgkff') ?></th>
						<td>&nbsp;</td>
						<td><input name="hgk_ff_subj" type="text" id="hgk_ff_subj" value="<?php echo $this->hgk_ff_subj; ?>" size="30" /><br/>&nbsp;</td>
					</tr>
					<tr valign="top">
						<th scope="row" align="right"><?php _e('Success message:', 'hgkff') ?></th>
						<td>&nbsp;</td>
						<td><textarea name="hgk_ff_msg_ok" id="hgk_ff_msg_ok" rows="3" cols="50"><?php echo $this->hgk_ff_msg_ok; ?></textarea>
						<br />
						<font size="-2">&nbsp;<i><?php _e('Message to display when form is successfully submitted', 'hgkff') ?></i></font><br/>&nbsp;</td>
					</tr>
					<tr valign="top">
						<th scope="row" align="right"><?php _e('Error message:', 'hgkff') ?></th>
						<td>&nbsp;</td>
						<td><textarea name="hgk_ff_msg_err" id="hgk_ff_msg_err" rows="3" cols="50"><?php echo $this->hgk_ff_msg_err; ?></textarea>
						<br />
						<font size="-2">&nbsp;<i><?php _e('Message to display when user input errors are detected (for instance, empty required fields)', 'hgkff') ?></i></font><br/>&nbsp;</td>
					</tr>
				</table>

				<p class="submit">
				<input class="button-primary" type="submit" name="submit" value="<?php _e('Save Changes', 'hgkff') ?>" />
				</p>
				</form>
			</div>
	<?php
		}

		function insertFeedbackForm($content) {
			if (false === strpos($content, $this->hgk_ff_token)) {
				return $content;
			}

			//  normalize input
			$hgk_your_name = isset($_POST['hgk_your_name']) ? trim(strip_tags($_POST['hgk_your_name'])) : '';
			$hgk_your_mail = isset($_POST['hgk_your_mail']) ? trim($_POST['hgk_your_mail']) : '';
			$hgk_your_site = isset($_POST['hgk_your_site']) ? trim($_POST['hgk_your_site']) : '';
			$hgk_your_msg  = isset($_POST['hgk_your_msg']) ? trim($_POST['hgk_your_msg']) : '';
			if (!empty($hgk_your_msg)) {
				$hgk_your_msg = str_replace(array("\r\n", "\r"), "\n", $hgk_your_msg); // x-platform newlines
				$hgk_your_msg = preg_replace("/\n\n+/", "\n\n", $hgk_your_msg); // remove newline duplicates
			}

            $action_submit = isset($_POST['hgkaction']) && $_POST['hgkaction'] == 'submit';
			$error_msg = "";

			if ($action_submit === TRUE) {
				if (empty($hgk_your_name) || !is_email($hgk_your_mail) || empty($hgk_your_msg)) {
					$error_msg = '<div class="hgk_fferror">' . $this->hgk_ff_msg_err . '</div>';
				}
			}

			if ($action_submit === TRUE && empty($error_msg))
			{
				if (empty($hgk_your_site)) {
					$hgk_your_site = __('not specified', 'hgkff');
				}
				$msg = "$hgk_your_name <$hgk_your_mail> wrote:\n\n";
				$msg .= wordwrap($hgk_your_msg, 80, "\n") . "\n\n";
				$msg .= "website: $hgk_your_site \n";
				$msg .= "ip: " . $this->getIP();

				$to = $this->hgk_ff_mail;
				if (empty($to)) {
					$to = get_option('admin_email');
				}

				if (wp_mail($to, $this->hgk_ff_subj, $msg, "from: $hgk_your_name <$hgk_your_mail>")) {
					echo "\n<!-- Message has been sent -->\n";
				} else {
					echo "\n<!-- Error sending message -->\n";
				}
				echo '<div class="hgk_ffok">' . $this->hgk_ff_msg_ok . '</div><br/>';
				echo '<form action="' . get_permalink() . '" method="post">';
				echo '<input type="submit" name="submit" value="' . __('Return', 'hgkff') . '" id="submit" /></form>';
			}
			else
			{
				if (has_filter('the_content', 'wpautop') !== false) {
					//	wpautop fails to recognize that newlines within 
					//	textarea must not be replaced with <br />
					$hgk_your_msg = str_replace("\n", '<WPPreserveNewline />', $hgk_your_msg);
					echo '<!-- ' . $hgk_your_msg . ' -->';
				}
				$form = '<div class="hgk_ffcontainer">' . $error_msg . '
				<form name="hgk_ffform" id="hgk_ffform" action="' . get_permalink() . '" method="post">
				<input type="hidden" name="hgkaction" id="hgkaction" value="hgksubmit" />
				<p><input type="text" name="hgk_your_name" id="hgk_your_name" size="25" maxlength="50" tabindex="1" aria-required="true" value="' . $hgk_your_name . '" />
				<label for="hgk_your_name"><small>' . __('Name', 'hgkff') . ' (' . __('required', 'hgkff') . ')</small></label></p>
				<p><input type="text" name="hgk_your_mail" id="hgk_your_mail" size="25" maxlength="50" tabindex="2" aria-required="true" value="' . $hgk_your_mail . '" />
				<label for="hgk_your_mail"><small>' . __('Mail', 'hgkff') . ' (' . __('required', 'hgkff') . ')</small></label></p>
				<p><input type="text" name="hgk_your_site" id="hgk_your_site" size="25" maxlength="100" tabindex="3" value="' . $hgk_your_site . '" />
				<label for="hgk_your_site"><small>' . __('Website', 'hgkff') . '</small></label></p>
				<p><textarea name="hgk_your_msg" id="hgk_your_msg" cols="67" rows="7" tabindex="4">' . $hgk_your_msg . '</textarea></p>
				<p><button tabindex="5" onclick="document.hgk_ffform.hgkaction.value='."'submit'".';document.hgk_ffform.submit();">' . __('Submit Comment', 'hgkff') . '</button></p></form></div><br clear="all" />';
				return str_replace($this->hgk_ff_token, $form, $content);
			}
		}

		function updateState() {
			$this->hgk_ff_mail	= get_option('hgk_ff_mail');
			$this->hgk_ff_subj	= get_option('hgk_ff_subj');
			$this->hgk_ff_msg_ok  = get_option('hgk_ff_msg_ok');
			$this->hgk_ff_msg_err = get_option('hgk_ff_msg_err');
		}

		function getIP() {
			if (isset($_SERVER)) {
				if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
				} elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
					$ip = $_SERVER['HTTP_CLIENT_IP'];
				} else {
					$ip = $_SERVER['REMOTE_ADDR'];
				}
			} else {
				if (getenv('HTTP_X_FORWARDED_FOR')) {
					$ip = getenv('HTTP_X_FORWARDED_FOR');
				} elseif (getenv('HTTP_CLIENT_IP')) {
					$ip = getenv('HTTP_CLIENT_IP');
				} else {
					$ip = getenv('REMOTE_ADDR');
				}
			}
			return $ip;
		}
	}
}

load_plugin_textdomain('hgkff', false, basename(dirname(__FILE__)) . '/langs');

if (class_exists("Hgk_Feedback_Form")) {
	$hgk_feedback_form_instance = new Hgk_Feedback_Form();
}

if (isset($hgk_feedback_form_instance)) {
	$hgk_feedback_form_instance->init();
}
?>
