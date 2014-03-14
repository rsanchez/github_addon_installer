<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * GitHub Addon Installer Module Control Panel File
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Module
 * @author		Rob Sanchez
 * @link		http://github.com/rsanchez
 */

/**
 * @property CI_Controller $EE
 */
class Github_addon_installer_mcp
{
	private $base;

	private $manifest;

	private $temp_path;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->base = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=github_addon_installer';

		$this->manifest = json_decode(file_get_contents(PATH_THIRD.'github_addon_installer/config/manifest.js'), TRUE);

		ksort($this->manifest);
	}

	public function index()
	{
		ee()->view->cp_page_title = ee()->lang->line('github_addon_installer_module_name');

		ee()->load->library('addons');

		$vars = array();
		$vars['addons'] = array();

		ee()->load->model('addons_model');

		$versions = array();

		//@TODO not works yet, must get leevi to require_once his EpiCurl lib
		if (FALSE && ee()->addons_model->accessory_installed('nsm_addon_updater'))
		{
			$nsm_addon_updater = new Nsm_addon_updater_acc;

			if ($feeds = $nsm_addon_updater->_updateFeeds())
			{
				foreach ($feeds as $addon => $feed)
				{
					$namespaces = $feed->getNameSpaces(TRUE);

					$latest_version = 0;

					include PATH_THIRD.'/'.$addon.'/config.php';

					foreach ($feed->channel->item as $version)
					{
						$ee_addon = $version->children($namespaces['ee_addon']);

						$version_number = (string) $ee_addon->version;

						if (version_compare($version_number, $config['version'], '>') && version_compare($version_number, $latest_version, '>'))
						{
							$versions[$addon] = $version_number;
						}
					}
				}
			}

			unset($nsm_addon_updater);
		}

		foreach ($this->manifest as $addon => $params)
		{
			$name = (isset($params['name'])) ? $params['name'] : $addon;
			$description = (isset($params['description'])) ? br().$params['description'] : '';
			//$status = (in_array($addon, $current_addons)) ? lang('addon_installed') : lang('addon_not_installed');
			$status = (ee()->addons->is_package($addon)) ? lang('addon_installed') : lang('addon_not_installed');

			if (isset($versions[$addon]))
			{
				//$status = sprintf(lang('addon_update'), $versions[$addon]);
				$status = lang('addon_update');
			}

			//$install = (in_array($addon, $current_addons)) ? lang('addon_install') : lang('addon_reinstall');

			$url = 'https://github.com/'.$params['user'].'/'.$params['repo'];

			if (isset($params['branch']))
			{
				$url .= '/tree/'.$params['branch'];
			}

			$branch = isset($params['branch']) ? $params['branch'] : 'master';

			$vars['addons'][] = array(
				'name' => $name,//.$description,
				'github_url' => anchor($url, $url, 'rel="external"'),
				'branch' => form_input("", $branch, 'class="branch '.$addon.'-branch"'),
				'author' => $params['user'],
				'status' => $status,
				'install' => anchor($this->base.AMP.'method=install'.AMP.'addon='.$addon, lang('addon_install'), 'data-addon="'.$addon.'"')
			);
		}

		ee()->load->library('javascript');

		ee()->javascript->output('
			$("table#addons").tablesorter({
				headers: {1: {sorter: false}, 4: {sorter: false}},
				widgets: ["zebra"]
			});
			$("table#addons tr td.addon_install a").click(function(){
				var a = $(this);
				var tds = a.parents("tr").children("td");
				var statusTd = a.parents("td").siblings("td.addon_status");
				var originalColor = tds.css("backgroundColor");
				var originalText = a.text();
				tds.animate({backgroundColor:"#d0d0d0"});
				a.html("'.lang('addon_installing').'");
				$.get(
					$(this).attr("href"),
					{branch: $("."+$(this).data("addon")+"-branch").val()},
					function(data){
						tds.animate({backgroundColor:originalColor});
						a.html(originalText);
						if (data.message_success) {
							if (data.redirect) {
								window.location.href = data.redirect;
								return;
							}
							statusTd.html("'.lang('addon_installed').'");
							$.ee_notice(data.message_success, {"type":"success"});
						} else {
							$.ee_notice(data.message_failure, {"type":"error"});
							//td.animate({backgroundColor:"red"});
						}
					},
					"json"
				);
				return false;
			});
			$("select#addonFilter").change(function(){
				var filter = $(this).val();
				$("#addonKeyword").hide();
				$("table#addons tbody tr").show();
				if (filter == "") {
					$("table#addons tbody tr").show();
				} else if (filter == "keyword") {
					$("#addonKeyword").val("").show().focus();
				} else {
					$("td."+$(this.options[this.selectedIndex]).parents("optgroup").data("filter")).filter(function(){
						return $(this).text() != filter;
					}).parents("tr").hide();
				}
			});
			//add all values from the table to filter
			$("select#addonFilter optgroup").each(function(index, element){
				var values = [];
				$("td."+$(this).data("filter")).each(function(){
					if ($.inArray($(this).text(), values) === -1) {
						values.push($(this).text());
					}
				});
				//case insensitive sort
				values.sort(function(a, b){
					a = a.toLowerCase();
					b = b.toLowerCase();
					if (a > b) {
						return 1;
					}
					if (a < b) {
						return -1;
					}
					return 0;
				});
				for (i in values) {
					$(element).append($("<option>", {value: values[i], text: values[i]}));
				};
			});
			//case insensitive :contains
			$.extend($.expr[":"], {
				containsi: function(el, i, match, array) {
					return (el.textContent || el.innerText || "").toLowerCase().indexOf((match[3] || "").toLowerCase()) >= 0;
				}
			});
			$("input#addonKeyword").keyup(function(){
				if (this.value == "") {
					$("table#addons tbody tr").show();
				} else {
					$("table#addons tbody tr").hide().find("td:containsi(\'"+this.value.toLowerCase()+"\')").parents("tr").show();
				}
			}).trigger("focus");
		');

		ee()->load->helper('array');

		return ee()->load->view('index', $vars, TRUE);
	}

	public function validate_manifest()
	{
		$count = count($this->manifest);

		ee()->load->library('javascript');

		ee()->javascript->output('(function(addons) {
			var index = 0,
				$username = $("#github-username"),
				$password = $("#github-password"),
				$count = $("#manifest-count"),
				$button = $("#validate-manifest"),
				$loading = $("#manifest-loading-message"),
				base = '.json_encode(str_replace('&amp;', '&', $this->base)).',
				$messages = $("#manifest-validation-messages");

			function validate() {
				var addon;

				if (addons[index] === undefined) {
					return;
				}

				addon = addons[index];

				$loading.html("Checking "+addon+"...");

				$.get(
					base,
					{
						addon: addon,
						method: "validate",
						username: $username.val(),
						password: $password.val()
					},
					function(data) {
						if ( ! data.message_success) {
							$messages.append("<p class=\"notice\">"+data.message_failure+"</p>");
						}

						index++;

						$count.html(index);

						$loading.html("");

						validate();
					},
					"json"
				);
			}

			$button.on("click", function(event) {
				event.preventDefault();

				$button.prop("disabled", true);

				validate();
			});
		})('.json_encode(array_keys($this->manifest)).');');

		return '<p><input type="text" placeholder="Github Username" id="github-username" /><br /><br /><input type="password" placeholder="Github Password" id="github-password" /><br /><br /><input class="submit" type="submit" id="validate-manifest" value="Validate Manifest" /></p><div id="manifest-loading-message"></div><div id="manifest-validation-messages"></div><p><span id="manifest-count">0</span> / '.$count.' checked.</p>';
	}

	public function validate()
	{
		ee()->load->library('github_addon_installer');

		$addon = ee()->input->get_post('addon');
		$username = ee()->input->get_post('username');
		$password = ee()->input->get_post('password');

		if ($username && $password)
		{
			ee()->github_addon_installer->set_basic_auth($username, $password);
		}

		if ( ! isset($this->manifest[$addon]))
		{
			ee()->session->set_flashdata('message_success', FALSE);

			ee()->session->set_flashdata('message_failure', sprintf(lang('invalid_addon'), $addon));
		}
		else
		{
			$params = $this->manifest[$addon];

			$params['name'] = $addon;

			try
			{
				$repo = ee()->github_addon_installer->repo($params);

				ee()->session->set_flashdata('message_success', TRUE);

				ee()->session->set_flashdata('message_failure', '');
			}
			catch(Exception $e)
			{
				ee()->session->set_flashdata('message_success', FALSE);

				ee()->session->set_flashdata('message_failure', $e->getMessage());
			}
		}

		ee()->functions->redirect($this->base);
	}

	public function install()
	{
		$addon = ee()->input->get_post('addon');

		if ( ! isset($this->manifest[$addon]))
		{
			ee()->session->set_flashdata('message_success', FALSE);

			ee()->session->set_flashdata('message_failure', sprintf(lang('invalid_addon'), $addon));
		}
		else
		{
			$params = $this->manifest[$addon];

			$params['name'] = $addon;

			if (ee()->input->get('branch'))
			{
				$params['branch'] = ee()->input->get('branch');
			}

			ee()->session->set_flashdata('addon', $addon);

			ee()->load->library('github_addon_installer');

			$error = '';
			$success = FALSE;

			try
			{
				$repo = ee()->github_addon_installer->repo($params);

				try
				{
					$repo->install();

					$success = sprintf(lang('successfully_installed'), $addon);
				}
				catch(Exception $e)
				{
					$error = $e->getMessage();
				}
			}
			catch(Exception $e)
			{
				$error = $e->getMessage();
			}

			ee()->session->set_flashdata('message_success', $success);

			ee()->session->set_flashdata('message_failure', '<p>'.$error.'</p>');

			//reset the addons lib if already loaded, so it knows about our new install
			unset(ee()->addons);

			ee()->load->library('addons');

			if ( ! isset(ee()->addons))
			{
				ee()->addons = new EE_Addons;
			}

			$redirect = FALSE;//str_replace('&amp;', '&', $this->base).'&installed='.$addon;

			//we're checking to see if this addon is more than just a plugin
			//if so, we'll redirect to the package installer page
			if (ee()->addons->is_package($addon))
			{
				$components = ee()->addons->_packages[$addon];

				$plugin_only = TRUE;

				foreach ($components as $type => $data)
				{
					if ($type !== 'plugin')
					{
						$plugin_only = FALSE;
					}
				}

				if ( ! $plugin_only)
				{
					//go to the package installer
					//a double-url encoded return param
					$redirect = str_replace('&amp;', '&', BASE).'&C=addons&M=package_settings&package='.$addon.'&return=addons_modules%2526M%253Dshow_module_cp%2526module%253Dgithub_addon_installer';
				}
			}

			ee()->session->set_flashdata('redirect', $redirect);
		}

		ee()->functions->redirect(empty($redirect) ? $this->base : $redirect);
	}
}
/* End of file mcp.github_addon_installer.php */
/* Location: /system/expressionengine/third_party/github_addon_installer/mcp.github_addon_installer.php */