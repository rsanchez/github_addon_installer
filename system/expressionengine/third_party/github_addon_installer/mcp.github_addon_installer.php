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
		
		$this->EE->load->library('table');
		
		$this->EE->table->set_template(array (
			'table_open' => '<table class="mainTable" border="0" cellspacing="0" cellpadding="0">',
			'row_start' => '<tr class="even">',
			'row_alt_start' => '<tr class="odd">',
		));
		
		$this->EE->table->set_heading(lang('addon'), lang('github_url'), lang('author'), lang('addon_status'));
		
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
			
			$this->EE->table->add_row(
				$name,//.$description,
				anchor($url, $url, 'rel="external"'),
				$params['user'],
				anchor($this->base.AMP.'method=install'.AMP.'addon='.$addon, $status)
			);
		}
		
		$this->EE->load->library('javascript');
		
		$this->EE->javascript->output('
			$("#mainContent .mainTable").tablesorter({
				headers: {1: {sorter: false}},
				widgets: ["zebra"]
			}).find("tr").each(function(){
				$(this).children("td:first").css({"font-weight":"bold"});
			});
			$("#mainContent .mainTable tr td:nth-child(4) a").click(function(){
				var a = $(this);
				var td = $(this).parents("tr").children("td");
				var originalColor = td.css("backgroundColor");
				var originalText = a.text();
				td.animate({backgroundColor:"#ddd"});
				a.html("'.lang('addon_installing').'");
				$.get(
					$(this).attr("href"),
					"",
					function(data){
						td.animate({backgroundColor:originalColor});
						if (data.message_success) {
							window.location.href = EE.BASE+"&C=addons&M=package_settings&package="+data.addon+"&return=addons_modules%2526M%253Dshow_module_cp%2526module%253Dgithub_addon_installer";
						} else {
							$.ee_notice(data.message_failure, {"type":"error"});
							a.html(originalText);
							//td.animate({backgroundColor:"red"});
						}
					},
					"json"
				);
				return false;
			});
		');
		
		return $this->EE->table->generate();
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
		}
		
		$this->EE->functions->redirect($this->base);
	}
}
/* End of file mcp.github_addon_installer.php */
/* Location: /system/expressionengine/third_party/github_addon_installer/mcp.github_addon_installer.php */