<?php

class PublishQuote extends Plugin
{
	static $default_content_template = "<blockquote cite=\"{\$url}\">{\$quote}</blockquote>\n<a href=\"{\$url}\" title=\"{\$title}\">{\$title}</a>\n";
	static $default_title_template = "{\$title}";

	/**
	 * Create the bookmarklet that is appropriate for the client's User Agent
	 *
	 * @return array The array of actions to attach to the specified $plugin_id
	 */
	private function get_bookmarklet()
	{
		$admin_url = Site::get_url( 'admin' );
		$link_name = Options::get( 'title' );
		$user = User::identify();
		$content_type = ( isset( $user->info->content_type ) ) ? "content_type={$user->info->content_type}&" : '';
		$bookmarklet = "
			<p>Bookmark this link to leave the page when quoting:
			<a href=\"javascript:var w=window,d=document,gS='getSelection';location.href='{$admin_url}/publish?{$content_type}quote='+encodeURIComponent((''+(w[gS]?w[gS]():d[gS]?d[gS]():d.selection.createRange().text)).replace(/(^\s+|\s+$)/g,''))+'&url='+encodeURIComponent(location.href)+'&title='+encodeURIComponent(d.title);\">Quote on {$link_name}</a>
			<br>
			Bookmark this link to open a new tab or window when quoting:
			<a href=\"javascript:var w=window,d=document,gS='getSelection';window.open('{$admin_url}/publish?{$content_type}quote='+encodeURIComponent((''+(w[gS]?w[gS]():d[gS]?d[gS]():d.selection.createRange().text)).replace(/(^\s+|\s+$)/g,''))+'&url='+encodeURIComponent(location.href)+'&title='+encodeURIComponent(d.title));void(0);\">Quote on {$link_name}</a>
			</p>";
		return $bookmarklet;
	}

	/**
	 * Add actions to the plugin page for this plugin
	 *
	 * @param array $actions An array of actions that apply to this plugin
	 * @return array The array of actions to attach to the specified $plugin_id
	 */
	public function filter_plugin_config( $actions )
	{
		$actions['configure'] = _t( 'Configure' );
		return $actions;
	}

	/**
	 * Respond to the user selecting an action on the plugin page
	 * 
	 * CNS: Customised this so you can select a default content type - heavily geared towards using the linkblog plugin
	 */
	public function action_plugin_ui_configure()
	{
		echo $this->get_bookmarklet();
		
		// Get currently available content types
		$ctmp = array_keys( Post::list_active_post_types() );
		array_shift( $ctmp );   // pop off the 0 => any option
		$ct = array();
		foreach ( $ctmp as $x ) {
			$ct[$x] = $x;
		}
		$form = new FormUI( strtolower( __CLASS__ ) );
		$form->append( 'static', 'instructions', '<strong><small>(Substitute {$quote}, {$title}, and {$url} in templates as desired)</small></strong>' );
		$form->append( 'text', 'title__template', 'user:title__template', 'The title defaults to the title of the page being quoted:<br> ' );
		if ( !$form->title__template->value ) {
			$form->title__template->value = self::$default_title_template;
		}
		$form->append( 'static', 'slug_info', '<small>If there\'s HTML in your title, you should set the slug to something without HTML. Like {title}.</small>' );
		$form->append( 'text', 'slug__template', 'user:slug__template', 'Leave blank for the default slug:<br> ' );
		$form->append( 'textarea', 'content__template', 'user:content__template', 'This is how the incoming quote will be inserted into the form:' );
		if ( !$form->content__template->value ) {
			$form->content__template->value = self::$default_content_template;
		}
		$form->append( 'text', 'quote__tags', 'user:quote__tags', 'Tags to attach to quote posts, comma separated: ' );
		$form->append( 'select', 'content_type', 'user:content_type', 'The default content type to use:<br >', $ct );

		$form->append( 'submit', 'save', _t( 'Save' ) );
		$form->out();
	}

	/**
	 * Add additional controls to the publish page tab
	 *
	 * @param FormUI $form The form that is used on the publish page
	 * @param Post $post The post being edited
	 */
	public function action_form_publish( $form, $post )
	{
		// If quote has been sent in the URL, insert the quote data in the post
		$this->handler_vars = Controller::get_handler_vars();
		if ( array_key_exists( 'quote', $this->handler_vars ) ) {
			$form->title->value = $this->handler_vars['title'];
			// Get the user so that we can grab the saved templates and tags
			$user = User::identify();

			$title = $user->info->title__template;
			if ( !$title ) {
				$title = self::$default_title_template;
			}
			// Filter the quote title using the title template
			$form->title->value = preg_replace_callback( '%\{\$(.+?)\}%', array( &$this, 'replace_parts' ), $title );

			// Only change the slug if the template is set. For example, you might have HTML in the title.
			$slug = $user->info->slug__template;
			if ( $slug ) {
				// Filter the quote title using the title template
				$form->newslug->value = preg_replace_callback( '%\{\$(.+?)\}%', array( &$this, 'replace_parts' ), $slug );
			}

			$content = $user->info->content__template;
			if ( !$content ) {
				$content = self::$default_content_template;
			}
			// Filter the quote content using the content template
			$form->content->value = preg_replace_callback( '%\{\$(.+?)\}%', array( &$this, 'replace_parts' ), $content );

			// Add tags for quotes
			$tags = $user->info->quote__tags;
			if ( $tags ) {
				$form->tags->value = $tags;
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
	public function replace_parts( $parts )
	{
		return $this->handler_vars[$parts[1]];
	}
}

?>
