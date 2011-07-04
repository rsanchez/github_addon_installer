<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

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
		
		$current_addons = scandir(PATH_THIRD);
		
		$vars = array();
		$vars['addons'] = array();
		
		foreach ($this->manifest as $addon => $params)
		{
			$name = (isset($params['name'])) ? $params['name'] : $addon;
			$description = (isset($params['description'])) ? br().$params['description'] : '';
			$status = (in_array($addon, $current_addons)) ? lang('addon_update') : lang('addon_install');
			
			$url = 'https://github.com/'.$params['user'].'/'.$params['repo'];
			
			if (isset($params['branch']))
			{
				$url .= '/tree/'.$params['branch'];
			}
			
			$vars['addons'][] = array(
				'name' => $name,//.$description,
				'github_url' => anchor($url, $url, 'rel="external"'),
				'author' => $params['user'],
				'status' => anchor($this->base.AMP.'method=install'.AMP.'addon='.$addon, $status)
			);
		}
		
		$this->EE->load->library('javascript');
		
		$this->EE->javascript->output('
			$("#mainContent .mainTable").tablesorter({
				headers: {1: {sorter: false}},
				widgets: ["zebra"]
			});
			$("#mainContent .mainTable tr td:nth-child(4) a").click(function(){
				var a = $(this);
				var td = $(this).parents("tr").children("td");
				var originalColor = td.css("backgroundColor");
				var originalText = a.text();
				td.animate({backgroundColor:"#d0d0d0"});
				a.html("'.lang('addon_installing').'");
				$.get(
					$(this).attr("href"),
					"",
					function(data){
						td.animate({backgroundColor:originalColor});
						a.html(originalText);
						if (data.message_success) {
							if (data.redirect) {
								window.location.href = data.redirect;
								return;
							}
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
			$("#addonFilter").change(function(){
				var filter = $(this).val();
				$("#addonKeyword").hide();
				$("table#addons tbody tr").show();
				if (filter == "") {
					$("table#addons tbody tr").show();
				} else if (filter == "keyword") {
					$("#addonKeyword").val("").show().focus();
				} else {
					$("td."+$(this.options[this.selectedIndex]).parents("optgroup").data("filter")).each(function(){
						if ($(this).text() != filter) {
							$(this).parents("tr").hide();
						}
					});
				}
			});
			$("#addonFilter optgroup").each(function(index, element){
				var values = [];
				$("td."+$(this).data("filter")).each(function(){
					if ($.inArray($(this).text(), values) == -1) {
						values.push($(this).text());
					}
				});
				values.sort();
				$.each(values, function(i, value){
					$(element).append($("<option>", {value: value, text: value}));
				});
			});
			$("#addonKeyword").keyup(function(){
				$("table#addons tbody tr").show();
				if ( ! $(this).val()) {
					return true;
				}
				$("table#addons tbody tr td.addon_name").each(function(){
					var regex = new RegExp($("#addonKeyword").val(), "gi");
					if ( ! $(this).text().match(regex) && ! $(this).siblings("td.addon_author").text().match(regex)) {
						$(this).parents("tr").hide();
					}
				});	
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