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
		
		include PATH_THIRD.'github_addon_installer/config/manifest.php';
		
		$this->manifest = $manifest;
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
		
		$this->EE->table->set_heading(lang('addon'), lang('author'), lang('addon_status'));
		
		foreach ($this->manifest as $addon => $params)
		{
			$name = (isset($params['name'])) ? $params['name'] : $addon;
			$description = (isset($params['description'])) ? br().$params['description'] : '';
			$status = (in_array($addon, $current_addons)) ? lang('addon_update') : lang('addon_install');
			
			$this->EE->table->add_row(
				'<strong>'.anchor('https://github.com/'.$params['user'].'/'.$params['repo'], $name, 'rel="external"').'</strong>'.$description,
				$params['user'],
				anchor($this->base.AMP.'method=install'.AMP.'addon='.$addon, $status)
			);
		}
		
		//@TODO removed ajax for now
		/*
		$this->EE->load->library('javascript');
		
		$this->EE->javascript->output('
			$("#mainContent .mainTable a").click(function(){
				var a = $(this);
				var td = $(this).parents("tr").children("td");
				var orig = td.css("backgroundColor");
				td.animate({backgroundColor:"#ddd"});
				$.get(
					$(this).attr("href"),
					"",
					function(data){
					console.log(data);
					EE.saveData = data;
						delete EE.flashdata.message_success;
						delete EE.flashdata.message_failure;
						td.animate({backgroundColor:orig});
						if (data.message_success) {
							a.html("'.lang('addon_update').'");
							EE.flashdata.message_success = data.message_success;
						} else {
							EE.flashdata.message_failure = data.message_failure;
							//td.animate({backgroundColor:"red"});
						}
						EE.cp.display_notices();
					},
					"json"
				);
				return false;
			});
		');
		*/
		
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
			
			$this->EE->load->library('github_addon_installer');
			
			$repo = $this->EE->github_addon_installer->repo($params);
			
			$success = ($repo->install()) ? sprintf(lang('successfully_installed'), $addon) : FALSE;
			
			$this->EE->session->set_flashdata('message_success', $success);
			
			$this->EE->session->set_flashdata('message_failure', implode('<br>', $repo->errors()));
		}
		
		$this->EE->functions->redirect($this->base);
	}
}
/* End of file mcp.github_addon_installer.php */
/* Location: /system/expressionengine/third_party/github_addon_installer/mcp.github_addon_installer.php */