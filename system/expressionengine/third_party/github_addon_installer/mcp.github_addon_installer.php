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
		$this->EE =& get_instance();
		
		$this->base = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=github_addon_installer';
		
		$this->manifest = json_decode(file_get_contents(PATH_THIRD.'github_addon_installer/config/manifest.js'), TRUE);
		
		ksort($this->manifest);
	}
	
	public function index()
	{
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('github_addon_installer_module_name'));
		
		$this->EE->load->library('addons');
		
		$vars = array();
		$vars['addons'] = array();
		
		$this->EE->load->model('addons_model');
		
		$versions = array();
		
		//@TODO not works yet, must get leevi to require_once his EpiCurl lib
		if (FALSE && $this->EE->addons_model->accessory_installed('nsm_addon_updater'))
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
			$status = ($this->EE->addons->is_package($addon)) ? lang('addon_installed') : lang('addon_not_installed');
			
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
			
			$vars['addons'][] = array(
				'name' => $name,//.$description,
				'github_url' => anchor($url, $url, 'rel="external"'),
				'author' => $params['user'],
				'status' => $status,
				'install' => anchor($this->base.AMP.'method=install'.AMP.'addon='.$addon, lang('addon_install'))
			);
		}
		
		$this->EE->load->library('javascript');
		
		$this->EE->javascript->output('
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
					"",
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
		
		$this->EE->load->helper('array');
		
		return $this->EE->load->view('index', $vars, TRUE);
	}
	
	public function install()
	{
		$addon = $this->EE->input->get_post('addon');
		
		if ( ! isset($this->manifest[$addon]))
		{
			$this->EE->session->set_flashdata('message_success', FALSE);
			
			$this->EE->session->set_flashdata('message_failure', sprintf(lang('invalid_addon'), $addon));
		}
		else
		{
			$params = $this->manifest[$addon];
			
			$params['name'] = $addon;
			
			$this->EE->session->set_flashdata('addon', $addon);
			
			$this->EE->load->library('github_addon_installer');
			
			$repo = $this->EE->github_addon_installer->repo($params);
			
			$success = ($repo->install()) ? sprintf(lang('successfully_installed'), $addon) : FALSE;
			
			$this->EE->session->set_flashdata('message_success', $success);
			
			$this->EE->session->set_flashdata('message_failure', '<p>'.implode('</p><p>', $repo->errors()).'</p>');
			
			//reset the addons lib if already loaded, so it knows about our new install
			unset($this->EE->addons);
			
			$this->EE->load->library('addons');
			
			if ( ! isset($this->EE->addons))
			{
				$this->EE->addons = new EE_Addons;
			}
			
			$redirect = FALSE;//str_replace('&amp;', '&', $this->base).'&installed='.$addon;
			
			//we're checking to see if this addon is more than just a plugin
			//if so, we'll redirect to the package installer page
			if ($this->EE->addons->is_package($addon))
			{
				$components = $this->EE->addons->_packages[$addon];
				
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
			
			$this->EE->session->set_flashdata('redirect', $redirect);
		}
		
		$this->EE->functions->redirect(empty($redirect) ? $this->base : $redirect);
	}
}
/* End of file mcp.github_addon_installer.php */
/* Location: /system/expressionengine/third_party/github_addon_installer/mcp.github_addon_installer.php */