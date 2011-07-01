<?php
/**
 * 
 *
 *  PageLines Template Class
 *
 *
 *  @package PageLines Core
 *  @subpackage Sections
 *  @since 4.0
 *
 */
class PageLinesTemplate {

	var $id;		// Root id for section.
	var $name;		// Name for this section.
	var $settings;	// Settings for this section
	
	var $layout;	// Layout type selected
	var $sections = array(); // HTML sections/effects for the page
	
	var $tsections = array(); 
	var $allsections = array();
	var $default_allsections = array();
	
	
	/**
	 * PHP5 constructor
	 *
	 */
	function __construct( $template_type = false ) {
		
		global $pl_section_factory;
		$this->factory = $pl_section_factory->sections;
		
		// All section control settings
		$this->scontrol = pagelines_option('section-control');
		
		
		/**
		 * Template Type
		 * This is how we decide which sections belong on the page
		 */
		if( $template_type != false )
			$this->template_type = $template_type;
	
		else{
			if(is_admin())
				$this->template_type = $this->admin_page_type_breaker();
			else
				$this->template_type = $this->page_type_breaker();
		}
		
		$this->main_type = $this->template_type;

		$this->map = $this->get_map();
	
		$this->load_sections_on_hook_names();
		
	}

	/**
	 * Returns template type based on WordPress conditionals
	 */
	function page_type_breaker(){
		global $post;
		
		if(is_404())
			return '404';
		elseif(is_tag())
			return 'tag';
		elseif(is_search())
			return 'search';
		elseif(is_category())
			return 'category';
		elseif(is_archive())
			return 'archive';
		elseif(is_home())
			return 'posts';
		elseif(is_single())
			return 'single';
		elseif(is_page_template()){
			/*
				Strip the page. and .php from page.[template-name].php
			*/
			$page_filename = str_replace('.php', '', get_post_meta($post->ID,'_wp_page_template',true));
			$template_name = str_replace('page.', '', $page_filename);
			return $template_name;
		}else
			return 'default';
	}
	
	/**
	 * Returns template type based on elements in WP admin
	 */
	function admin_page_type_breaker(){
		global $post;
		if ( !is_object( $post ) ) 
			return 'default';
		
		if(isset($post) && $post->post_type == 'post')
			return 'single';
		elseif( isset($_GET['page']) && $_GET['page'] == 'pagelines' )
			return 'posts';
		elseif(isset($post) && !empty($post->page_template) && $post->post_type == "page") {
			$page_filename = str_replace('.php', '', $post->page_template);
			$template_name = str_replace('page.', '', $page_filename);
			return $template_name;
		} elseif(isset($post) && get_post_meta($post->ID,'_wp_page_template',true)){
			$page_filename = str_replace('.php', '', get_post_meta($post->ID,'_wp_page_template',true));
			$template_name = str_replace('page.', '', $page_filename);
			return $template_name;
		} else 
			return 'default';
		
	}
	

		
	
	/**
	 *
	 * Load sections on to class attributes the correspond w/ hook args
	 *
	 * TODO Account for different types of loads. e.g sidebar2 should only load if it is shown in the layout
	 *
	 */
	function load_sections_on_hook_names(){
		
		foreach( $this->map as $hook => $h ){
			
			$tsections = $this->sections_at_hook( $hook, $h );
			
			// Set All Sections As Defined By the Map
			if( is_array($tsections) ) 
				$this->default_allsections = array_merge($this->default_allsections, $tsections);
			
			// Remove sections deactivated by Section Control
			$tsections = $this->unset_hidden_sections($tsections, $hook);
			
			// Set Property after Template Hook Args
			$this->$hook = $tsections;
		
			// Create an array with all sections used on current page - 
			if(is_array($this->$hook)) 
				$this->allsections = array_merge($this->allsections, $this->$hook);
			
		}
		
	}
	
	
	/**
	 * For a given hook, see which sections are placed there and return them
	 */
	function sections_at_hook( $hook, $h ){
		
		if( $hook == 'main' ){
	
			if(isset($h['templates'][$this->main_type]['sections']))
				$tsections = $h['templates'][$this->main_type]['sections'];
			elseif(isset($h['templates']['default']['sections']))
				$tsections = $h['templates']['default']['sections'];
			
		} elseif( $hook == 'templates' ) {
			
			if(isset($h['templates'][$this->template_type]['sections']))
				$tsections = $h['templates'][$this->template_type]['sections'];
			elseif(isset($h['templates']['default']['sections']))
				$tsections = $h['templates']['default']['sections'];
			
		} elseif(isset($h['sections'])) { 
			
			// Get Sections assigned in map
			$tsections = $h['sections'];

		} else {
			
			$tsections = array();
			
		}
		
		return $tsections;
	}
	
	/**
	 * Unset sections based on section
	 */
	function unset_hidden_sections($ta_sections, $hook_id){
			
		global $post;
		
		// Non-meta page
		if ( !is_object( $post ) ) 
			return $ta_sections;
	
			
		if(is_array($ta_sections)){
		
			foreach($ta_sections as $key => $section){
				
				$sc = $this->sc_settings( $hook_id, $section );
				
				if($this->unset_section($section, $sc))
					unset($ta_sections[$key]);
				
			}
			
		}
		
		return $ta_sections;
		
	}
	
	/**
	 * Get Section Control Settings for Section
	 */
	function sc_settings( $hook_id, $section ){
		
		// Get template slug
		if($hook_id == 'templates')
			$template_slug = $hook_id.'-'.$this->template_type;
		elseif ($hook_id == 'main')
			$template_slug = $hook_id.'-'.$this->main_type;
		else
			$template_slug = $hook_id;
			
		$sc = (isset($this->scontrol[$template_slug][$section])) ? $this->scontrol[$template_slug][$section] : null;
	
		return $sc;	
		
	}
	
	/**
	 * Unset section based on Section Control
	 */
	function unset_section( $section, $sc ){
		
		// General hide + show options
		$general_hide = (isset($sc['hide'])) ? true : false;
		$meta_reverse = (isset($post) && m_pagelines('_show_'.$section, $post->ID )) ? true : false;
		$blog_page_reverse = (!is_home() || ( is_home() && isset($sc['posts-page']['show']))) ? true : false;
		
		// Hiding on meta
		$meta_hide = (isset($post) && m_pagelines('_hide_'.$section, $post->ID )) ? true : false;
		$blog_page_hide = (is_home() && isset($sc['posts-page']['hide'])) ? true : false;
		
		if( $general_hide && !$meta_reverse && !$blog_page_reverse)
			return true;
			
		elseif($meta_hide || $blog_page_hide)
			return true;
		
	}
	

	/**
	 * Hook up sections to hooks throughout the theme
	 * Specifically, the hooks should link w/ template map slugs
	 */
	function hook_and_print_sections(){
		
		foreach( $this->map as $hook_id => $h ){

			if( isset($h['hook']) )
				add_action( $h['hook'], array(&$this, 'print_section_html') );

		}		
		
	}

	/**
	 * Print section HTML from hooks.
	 */
	function print_section_html( $hook ){
	
		global $post;
		global $pagelines_post;		
		

		/**
		 * Sections assigned to array already in get_loaded_sections
		 */
		if( is_array( $this->$hook ) ){

			$markup_type = $this->map[$hook]['markup'];

			/**
			 * Parse through sections assigned to this hooks
			 */
			foreach( $this->$hook as $sid ){

				$sc = $this->sc_settings( $hook, $sid );
				
				/**
				 * If this is a cloned element, remove the clone flag before instantiation here.
				 */
				$pieces = explode("ID", $sid);		
				$section = $pieces[0];
				$clone_id = (isset($pieces[1])) ? $pieces[1] : null;
				
				if( $this->in_factory( $section ) ){
					$this->factory[ $section ]->before_section( $markup_type, $clone_id);
				
					$this->factory[ $section ]->section_template_load(); // If in child theme get that, if not load the class template function
					
					$this->factory[ $section ]->after_section( $markup_type );
				}
			
				$post = $pagelines_post; // Set the $post variable back to the default for the page (prevents sections from messing with others)
	
			}
		}
	}
	
	/**
	 * Tests if the section is in the factory singleton
	 * @since 1.0.0
	 */
	function in_factory( $section ){
		
		return ( isset($this->factory[ $section ]) && is_object($this->factory[ $section ]) ) ? true : false;
	}
	
	/*
		Used for when the default map is updated 
	*/
	function update_template_config($map){
		
		foreach(the_template_map() as $hook_id => $hook_info){
			
			if( !isset( $map[$hook_id] ) || !is_array( $map[$hook_id] ) )
				$map[$hook_id] = $hook_info;
		
			$map[$hook_id]['name'] = $hook_info['name'];
			$map[$hook_id]['hook'] = $hook_info['hook'];
			$map[$hook_id]['markup'] = $hook_info['markup'];
			
			if(isset($hook_info['templates']) && is_array($hook_info['templates'])){
				
				foreach($hook_info['templates'] as $sub_template => $stemplate){
					
					if( !isset( $map[$hook_id]['templates'][$sub_template] ) )
						$map[$hook_id]['templates'][$sub_template] = $stemplate;
					
					$map[$hook_id]['templates'][$sub_template]['name'] = $stemplate['name'];
				}
				
			}
		}
		
		return $map;
		
	}
	
	/**
	 * Gets template map, sets option if not present
	 * @since 1.0.0
	 */
	function get_map(){
		
		// Get Section / Layout Map
		if(get_option('pagelines_template_map') && is_array(get_option('pagelines_template_map'))){
			$map = get_option('pagelines_template_map');
			return $this->update_template_config($map);
			
		}else{
			update_option('pagelines_template_map', the_template_map());
			return the_template_map();
		}
	}
	
	function reset_templates_to_default(){
		update_option('pagelines_template_map', the_template_map());
	}

	function print_template_section_headers(){

		if(is_array($this->allsections)){ 
			
			foreach($this->allsections as $section){
				
				if( $this->in_factory( $section ) )
					$this->factory[$section]->section_head();
					
			}
			
		}
		
	}
	
	/**
	 * Runs the options w/ cloning
	 *
	 * @package PageLines Core
	 * @subpackage Sections
	 * @since 2.0.b3
	 */
	function load_section_optionator(){
	
		foreach( $this->default_allsections as $section_slug ){
			
			$pieces = explode("ID", $section_slug);		
			$section = (string) $pieces[0];
			$clone_id = (isset($pieces[1])) ? $pieces[1] : 1;
			
			if(isset($this->factory[$section]))
				$this->factory[$section]->section_optionator( array( 'clone_id' => $clone_id ) );
		
			
		}
	
		// Get inactive
		foreach( $this->factory as $key => $section ){
			
			$inactive = ( !in_array( $key, $this->default_allsections) ) ? true : false;
			
			if($inactive)
				$section->section_optionator( array('clone_id' => $clone_id, 'active' => false) );
		}

	}
	
	
	/**
	 * Print Section Styles (Hooked to wp_print_styles)
	 *
	 */
	function print_template_section_styles(){
	
		if(is_array($this->allsections)){
			foreach($this->allsections as $section){
				
				if($this->in_factory( $section )) 
					$this->factory[$section]->section_styles();
					
			}	
		}
	
	}
	

	function print_template_section_scripts(){


		foreach($this->allsections as $section){

			if($this->in_factory( $section )){
				
				$section_scripts = $this->factory[$section]->section_scripts();
				
				
				if(is_array( $section_scripts )){
					
					foreach( $section_scripts as $js_id => $js_atts){
						
						$defaults = array(
								'version' => '1.0',
								'dependancy' => null,
								'footer' => true
							);

						$parsed_js_atts = wp_parse_args($js_atts, $defaults);
						
						wp_register_script($js_id, $parsed_js_atts['file'], $parsed_js_atts['dependancy'], $parsed_js_atts['version'], true);

						wp_print_scripts($js_id);

					}

				}
			}

		}
	}
	
	/**
	 * This was taken from core WP because the function hasn't loaded yet, and isn't accessible.
	 */
	function get_page_templates() {
		$themes = get_themes();
		$theme = get_current_theme();
		$templates = $themes[$theme]['Template Files'];
		$page_templates = array();

		if ( is_array( $templates ) ) {
			$base = array( trailingslashit(get_template_directory()), trailingslashit(get_stylesheet_directory()) );

			foreach ( $templates as $template ) {
				$basename = str_replace($base, '', $template);

				// don't allow template files in subdirectories
				if ( false !== strpos($basename, '/') )
					continue;

				if ( 'functions.php' == $basename )
					continue;

				$template_data = implode( '', file( $template ));

				$name = '';
				if ( preg_match( '|Template Name:(.*)$|mi', $template_data, $name ) )
					$name = _cleanup_header_comment($name[1]);

				if ( !empty( $name ) ) {
					$page_templates[trim( $name )] = $basename;
				}
			}
		}

		return $page_templates;
	}

} /* ------ END CLASS ------ */


/**
 * PageLines Template Object 
 * @global object $pagelines_template
 * @since 1.0.0
 */
function build_pagelines_template(){	
	$GLOBALS['pagelines_template'] = new PageLinesTemplate;	
}

/**
 * Save Site Template Map
 *
 * @since 1.0.0
 */
function save_template_map($templatemap){	
	update_option('pagelines_template_map', $templatemap);
}


/**
 *  Used to parse section HTML for hooks
 *
 * @since 4.0.0
 */
function pagelines_ob_section_template($section){

	/*
		Start Output Buffering
	*/
	ob_start();
	
	/*
		Run The Section Template
	*/
	$section->section_template( true );

	/*
		Clean Up Buffered Output
	*/
	ob_end_clean();

	
}

function reset_templates_to_default(){	
	PageLinesTemplate::reset_templates_to_default();
}


/**
 *  Workaround for warning on WP login page when pagelines_template variable doesn't exist
 * Due to there being no "pagelines_before_html" hook present. Not ideal; but best solution for now.
 *
 * @since 4.0.0
 */
function workaround_pagelines_template_styles(){	
	global $pagelines_template; 
	if(is_object($pagelines_template)){
		$pagelines_template->print_template_section_styles();
	}
	else return;
}

