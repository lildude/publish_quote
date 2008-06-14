<?php

class PublishQuote extends Plugin
{
	static $default_content_template = "<blockquote cite=\"{\$url}\">{\$quote}</blockquote>\n<a href=\"{\$url}\" title=\"{\$title}\">{\$title}</a>\n";
	static $default_title_template = "{\$title}";

	/**
	* Required Plugin Information
	*/
	function info()
	{
		return array(
			'name' => 'Publish Quote',
			'license' => 'Apache License 2.0',
			'url' => 'http://habariproject.org/',
			'author' => 'Habari Community',
			'authorurl' => 'http://habariproject.org/',
			'version' => '1.0',
			'description' => 'Allows users to post quotes with a bookmarklet',
			'copyright' => '2008'
		);
	}

	/**
	* Create the bookmarklet that is appropriate for the client's User Agent
	*
	* @return array The array of actions to attach to the specified $plugin_id
	*/
	private function get_bookmarklet()
	{
		$admin_url = Site::get_url('admin');
		$link_name = Options::get('title');
		$bookmarklet = "
		<p>Bookmark this link to leave the page when quoting:
		<a href=\"javascript:var w=window,d=document,gS='getSelection';location.href='{$admin_url}/publish?quote='+encodeURIComponent((''+(w[gS]?w[gS]():d[gS]?d[gS]():d.selection.createRange().text)).replace(/(^\s+|\s+$)/g,''))+'&url='+encodeURIComponent(location.href)+'&title='+encodeURIComponent(d.title);\">Quote on {$link_name}</a>
		<br>
		Bookmark this link to open a new tab or window when quoting:
		<a href=\"javascript:var w=window,d=document,gS='getSelection';window.open('{$admin_url}/publish?quote='+encodeURIComponent((''+(w[gS]?w[gS]():d[gS]?d[gS]():d.selection.createRange().text)).replace(/(^\s+|\s+$)/g,''))+'&url='+encodeURIComponent(location.href)+'&title='+encodeURIComponent(d.title));void(0);\">Quote on {$link_name}</a>
		</p>";
		return $bookmarklet;
	}

	/**
	* Add actions to the plugin page for this plugin
	*
	* @param array $actions An array of actions that apply to this plugin
	* @param string $plugin_id The string id of a plugin, generated by the system
	* @return array The array of actions to attach to the specified $plugin_id
	*/
	public function filter_plugin_config($actions, $plugin_id)
	{
		if ($plugin_id == $this->plugin_id()) {
			$actions[] = 'Configure';
		}

		return $actions;
	}

	/**
	* Respond to the user selecting an action on the plugin page
	*
	* @param string $plugin_id The string id of the acted-upon plugin
	* @param string $action The action string supplied via the filter_plugin_config hook
	*/
	public function action_plugin_ui($plugin_id, $action)
	{
		if ( $plugin_id == $this->plugin_id() ) {
			switch ($action) {
				case 'Create Bookmarklet' :
					echo $this->get_bookmarklet();
					$form = new FormUI(strtolower(get_class($this)));
					$form->append( 'static', 'instructions', '<strong><small>(Substitute {$quote}, {$title}, and {$url} in templates as desired)</small></strong>' );
					$form->append( 'text', 'title__template', 'user:title__template', 'The title defaults to the title of the page being quoted:<br> ' );
					if ( !$form->title__template->value ) {
						$form->title__template->value= self::$default_title_template;
					}
					$form->append( 'textarea', 'content__template', 'user:content__template', 'This is how the incoming quote will be inserted into the form:' );
					if ( !$form->content__template->value ) {
						$form->content__template->value= self::$default_content_template;
					}
					$form->append( 'text', 'quote__tags', 'user:quote__tags', 'Tags to attach to quote posts, comma separated: ' );
					$form->append( 'submit', 'save', _t( 'Save' ) );
					$form->out();
					break;
			}
		}
	}

	/**
	* Add additional controls to the publish page tab
	*
	* @param FormUI $form The form that is used on the publish page
	* @param Post $post The post being edited
	**/
	public function action_form_publish($form, $post)
	{
		// If quote has been sent in the URL, insert the quote data in the post
		$this->handler_vars = Controller::get_handler_vars();
		if ( array_key_exists('quote', $this->handler_vars) ) {
			$form->title->value= $this->handler_vars['title'];
			// Get the user so that we can grab the saved templates and tags
			$user= User::identify();

			$title= $user->info->title__template;
			if ( !$title ) {
				$title= self::$default_title_template;
			}
			// Filter the quote title using the title template
			$form->title->value= preg_replace_callback('%\{\$(.+?)\}%', array(&$this, 'replace_parts'), $title);

			$content= $user->info->content__template;
			if ( !$content ) {
				$content= self::$default_content_template;
			}
			// Filter the quote content using the content template
			$form->content->value= preg_replace_callback('%\{\$(.+?)\}%', array(&$this, 'replace_parts'), $content);

			// Add tags for quotes
			$tags= $user->info->quote__tags;
			if ( $tags ) {
				$form->tags->value= $tags;
			}
		}
	}

	/**
	* Returns the replacement results for a preg_replace_callback
	*
	* @param array $parts The matches form the expression
	* @return The handlervar with that name
	* @see PublishQuote::add_quote_to_post()
	*/
	public function replace_parts($parts)
	{
		return $this->handler_vars[$parts[1]];
	}

	/**
	* Registers this plugin for updates against the beacon
	*/
	public function action_update_check()
	{
		Update::add('Publish Quote', 'ac3d6784-57c2-4dfa-9c24-3a09d0296a6f', $this->info->version);
	}
}

?>