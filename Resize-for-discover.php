<?php
/*
  Plugin Name: Resize for discover
  Plugin URI:
  Description: You can resize images for google discover and AMP⚡ by using this wp plugin.
  Text Domain: resize-for-discover
  Domain Path: /languages/
  Version: 0.0.1
  Author:  xingxingst
  Author URI: 
  License: GPL
  https://developers.google.com/search/docs/data-types/article?#article_types
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class resizeForDiscover{

    const NAME = 'resize-for-discover';
    const INITIALCOLOR = '#000000';

    public function __construct(){
        require_once(  __DIR__. '/resizer.php' );
        require_once(ABSPATH . 'wp-admin/includes/image.php'); 
        add_action( 'plugins_loaded', array( $this, 'load_lang_strings' ) );
        add_action( 'admin_print_footer_scripts', array( $this, 'wpColorPickerScript' ),9);
        add_action( 'after_setup_theme', array( $this, 'addFeaturedImageSupport' ), 11 );
        add_action( 'after_setup_theme', array( $this, 'add_image_sizes' ), 12 );
    }

    public static function ratios(){
        return [
            '1:1', '4:3', '16:9',
            __('closer 16:9 or 4:3',self::NAME),
            __('closer 16:9 or 1:1',self::NAME),
            __('closer 4:3 or 1:1',self::NAME),
            __('closer 16:9 or 4:3 or 1:1',self::NAME),
            __('fixed, to >=1200 x >=1200',self::NAME),
            __('fixed, to >=1200 x >=900',self::NAME),
            __('fixed, to >=1200 x >=675',self::NAME),
        ];
    }
    
    public static function registerImage($attachment_id, $imagepath){
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $imagepath );
        wp_update_attachment_metadata( $attachment_id,  $attach_data );
    }

    public static function insertAttachment($imagepath, $id=0){
        $filetype = wp_check_filetype( basename( $imagepath ), null );
        $wp_upload_dir = wp_upload_dir();

        $attachment = array(
            'guid'           => $wp_upload_dir['url'] . '/' . basename( $imagepath ), 
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $imagepath ) ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $imagepath, $id );

        return $attach_id;
    }

    public static function randomString($length = 3){
        return substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, $length);
    }

    /**
     * remove backslash etc.
     *
     * @param [string] $path
     * @return string
     */
    public static function toWPlocalPath($path){
        $pathInfo = ImageResizerForDiscover::mb_pathinfo($path);
        return $pathInfo['dirname'] . '/' . $pathInfo['basename'];
    }

    public function wpColorPickerScript(){
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style( 'wp-color-picker' );
    }


    public function addFeaturedImageSupport(){
        global $_wp_theme_features;

        if( empty($_wp_theme_features['post-thumbnails']) )
            $_wp_theme_features['post-thumbnails'] = array( array('resize-for-discover') );
    
        elseif( true === $_wp_theme_features['post-thumbnails'])
            return;
    
        elseif( is_array($_wp_theme_features['post-thumbnails'][0]) )
            $_wp_theme_features['post-thumbnails'][0][] = 'resize-for-discover';
    
    }

    public function add_image_sizes(){
        $saveNewSize = get_option( resizeForDiscoverSettingsPage::OPTION );
        if(empty($saveNewSize['field1']['resize-for-discover-newsize']))
            return;

        $width = 1200;
        add_image_size( 'resize_for_discover_hd', $width, 675, true );
        add_image_size( 'resize_for_discover_crt', $width, 900, true );
        add_image_size( 'resize_for_discover_rect', $width, $width, true );
    
    }

    public function load_lang_strings(){
        load_plugin_textdomain( self::NAME, false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
    }
}



class resizeForDiscoverAttachmentPage{

    public function __construct(){
        add_filter( 'attachment_fields_to_edit',array( $this, 'add_attachment_color_field' ), 10, 2 );
        add_filter( 'attachment_fields_to_edit',array( $this, 'add_attachment_overwrite_field' ), 10, 2 );
        add_filter( 'attachment_fields_to_edit',array( $this, 'add_attachment_resize_field' ), 10, 2 );
        add_action( 'edit_attachment', array( $this, 'save_attachment_resize' )  );
        add_action( 'admin_head',  array( $this,'admin_media_custom_head'));
        add_action( 'admin_print_footer_scripts', array( $this, 'resizeBackgroundColorScript' ), 99999);
    }


    function admin_media_custom_head() {
    ?>
        <style type="text/css">
        .compat-field-resize-for-discover-background td.field{width: 100%;}
        </style>
    <?php
    }

    function resizeBackgroundColorScript(){
        ?>
        <script>
        jQuery(function ($) {
            // Extend wpColorPicker to trigger change on close.
            $.widget('custom.myColorPicker', $.wp.wpColorPicker, {
                close: function () {
                    this.element.hide();
                    if ( this.element.iris( 'instance' ) ) {
                        this.element.iris( 'toggle' );
                    }
                    this.button.addClass( 'hidden' );
                    this.toggler.removeClass( 'wp-picker-open' );
                    $( 'body' ).off( 'click.wpcolorpicker', this.close );
                    if (this.initialValue !== this.element.val()) {
                        this.element.change();
                    }
                }
            });
        });
        </script>
        <?php
    }

    private function getInitialColor(){
        $saveColor = get_option( resizeForDiscoverSettingsPage::OPTION );

        return empty($saveColor['field1']['resize-for-discover-background'])
        ? resizeForDiscover::INITIALCOLOR
        : $saveColor['field1']['resize-for-discover-background'];
    }

    private function getFileType($post){
        $imagepath= get_attached_file($post->ID);
        $type = wp_check_filetype($imagepath);

        return $type['ext'];
    }


    private function isImage($type){
        return $type === 'png' || $type === 'jpg'
        ||  $type === 'jpeg' || $type === 'gif';
    }

    function add_attachment_color_field( $form_fields, $post ) {
        $filetype = $this->getFileType($post);
        if(!$this->isImage($filetype)) return $form_fields;
        $field_value = get_post_meta( $post->ID, 'resize-for-discover-background', true );
        $savedColor = $this->getInitialColor();
        $viewColor = $field_value ? $field_value : $savedColor;
        $transparentBool = $field_value === 'transparent';
        $form_fields['resize-for-discover-background'] = array(
            'input' => 'text',
            'value' => $transparentBool ? '' : $viewColor,
            'label' => __( 'Background color for resize',resizeForDiscover::NAME),
        );

        ob_start();
        ?>
        <script>
         let existSaveWaiting = function (records){
            // search past 'save-waiting' class
            let len = records.length;
            let exist = false;
            for( let i=0; i<len; i++) {
                if(records[i].oldValue.indexOf('save-waiting') > 0){
                    exist = true;
                    break;
                }
            }
            return exist;
         }

         let mediaReloader = function (){
            // get wp outside iframe
            var wp = parent.wp;

            // switch tabs (required for the code below)
            wp.media.frame.setState('insert');

            // refresh
            if( wp.media.frame.content.get() !== null) {
                wp.media.frame.content.get().collection.props.set({ignore: (+ new Date())});
                wp.media.frame.content.get().options.selection.reset();
            } else {
                wp.media.frame.library.props.set ({ignore: (+ new Date())});
            }

         }
         let autoReload = function (records){
            if(!existSaveWaiting(records)){
                jQuery('.resize-for-discover-spinner').addClass('save-waiting');
                return;
            }
            mediaReloader();
        };
        jQuery(function($){
            let nowPage = location.href.indexOf('upload.php');
            let reloderElm = $('.resize-for-discover-reloader');
            $('[name$="[resize-for-discover-background]"]').myColorPicker();
            if(nowPage > 0){
                reloderElm.hide();                
                $('.reloader-help').hide();
            }else{
                reloderElm.on('click',mediaReloader);
            }
            
            //reload attachment page 
            $('.resize-for-discover-select').change(function(){
                let reloadFlg = false;
                let observer;
                let modal;
                if(nowPage == -1 && $('.media-sidebar').length>0){
                    reloadFlg = true;
                    modals = document.getElementsByClassName('media-sidebar');
                    let length = modals.length;
                    for (let index = 0; index < length; index++) {
                        modal = modals[index].querySelector('.attachment-details');
                        if(modal){
                            break;
                        }
                    }
                    observer = new MutationObserver(function(records){autoReload(records)});
                }else if(nowPage > 0 && $('.edit-attachment-frame').length>0){
                    reloadFlg = true;
                    modal = document.getElementsByClassName('edit-attachment-frame')[0].querySelector('.attachment-details');
                    observer = new MutationObserver(function(records){
                        if(!existSaveWaiting(records)){return;}
                        location.reload();
                    });
                }
                if(reloadFlg){
                    if(modal){
                        observer.observe(modal, {
                            attributes: true,
                            attributeOldValue :true,
                            attributeFilter: ['class']
                        });
                    }else{
                        console.warn('This window cannnot be reloaded.');
                    }
                }
            });
        });
        </script>
        <?php
        $text_color_js = ob_get_clean();
        $form_fields['text_color_js'] = array(
            'tr' => $text_color_js, // Adds free-form stuff to table.
        );

        //for transparent checkbox
        if($filetype === 'png' || $filetype === 'gif'){
            $saveTransparent = get_option( resizeForDiscoverSettingsPage::OPTION );
            $saveTransparent = empty($saveTransparent['field1']['resize-for-discover-transparent']) ? '' :  'transparent';
            $field_value = $field_value === '' ? $saveTransparent : $field_value;
            $form_fields['resize-for-discover-transparent'] = array(
                'input' => 'html',
                'html'  => $this->fieldCheckboxHTML(
                    $post->ID,
                    'resize-for-discover-transparent',
                    'transparent',
                    $field_value,
                    __('Transparent', resizeForDiscover::NAME)
                ),
                'helps' => __('If you fill the checkbox, This setting takes priority.',resizeForDiscover::NAME),
                'value' => 'transparent',
                'label' => '',
            );

        }else{
            $form_fields['resize-for-discover-transparent'] = array('input' => 'hidden', 'value' => '');
        }

        return $form_fields;
    }

    function add_attachment_overwrite_field( $form_fields, $post ) {
        $filetype = $this->getFileType($post);
        if(!$this->isImage($filetype)) return $form_fields;
        $field_value = get_post_meta( $post->ID, 'resize-for-discover-overwrite', true );
        $saveOverwrite = get_option( resizeForDiscoverSettingsPage::OPTION );
        $saveOverwrite = empty($saveOverwrite['field1']['resize-for-discover-overwrite']) ? 0 :  1;
        $field_value =  $field_value === '' ? $saveOverwrite : $field_value;
        $form_fields['resize-for-discover-overwrite'] = array(
            'input' => 'html',
            'html'  => $this->fieldCheckboxHTML($post->ID, 'resize-for-discover-overwrite', 1, $field_value, ''),
            // 'value' => 'transparent',
            'label' => __('Overwrite',resizeForDiscover::NAME),
        );
        return $form_fields;
    }

    private function fieldCheckboxHTML( $postID, $index, $value, $field_value, $text) {
        $label = $index .'-'. $postID;
        $checked =  $field_value== $value ? " checked='checked'" : '';
        return "<label for='{$label}'>
            <input type='checkbox' id='{$label}' name='attachments[{$postID}][{$index}]' value='{$value}'{$checked}
            /> {$text} 
        </label>";
        
    }

    function add_attachment_resize_field( $form_fields, $post ) {
        $filetype = $this->getFileType($post);
        if(!$this->isImage($filetype)) return $form_fields;
        $field_value = get_post_meta( $post->ID, 'resize-for-discover', true );
        $form_fields['resize-for-discover'] = array(
            'input' => 'html',
            'label' => __( 'Raito', resizeForDiscover::NAME),
            // 'helps' => 
            // __( 'If this width is shorter than 1200px or pixel is smaller than 800000 pixel,the picture will be resized to this conditions.',
            // resizeForDiscover::NAME)
        );

        $form_fields['resize-for-discover']['html']
        = "<select class='resize-for-discover-select' name='attachments[{$post->ID}][resize-for-discover]' id='attachments[{$post->ID}][resize-for-discover]' style='font-size:1em;'>\n";
        $field_value = $field_value === "" ? '-1' : $field_value; 
        $form_fields['resize-for-discover']['html']
        .="<option value='-1'>".__('select', resizeForDiscover::NAME)."</option>\n";
        
        foreach(resizeForDiscover::ratios() as $key => $value){
            $selected = (int)$field_value === $key ? 'selected' : '';
            $text = $value;
            $form_fields['resize-for-discover']['html'] .= "<option value='{$key}' {$selected}>{$text}</option>\n";
        }
        $form_fields['resize-for-discover']['html'].="</select>\n";
        $form_fields['resize-for-discover']['html'].=
        '<p class="help">' . __( 'If this width is shorter than 1200px or pixel is smaller than 800000 pixel,the picture will be resized to this conditions.',resizeForDiscover::NAME) . '</p>';
        $style =
        'position: initial; overflow: initial; top: initial; bottom: initial; right: initial; left: initial; box-shadow: initial;';
        $form_fields['resize-for-discover']['html'] .= 
        '<span class="attachment-details resize-for-discover-spinner" style="'. $style . '">
            <span class="settings-save-status" role="status">
                <span class="spinner"></span>
                <span class="saved">'.  esc_html__( 'Saved.') .'</span>
            </span>
        </span>';
        $form_fields['resize-for-discover']['html'] .=
        '<button class="resize-for-discover-reloader button" type="button">'.
             __( 'Reload window', resizeForDiscover::NAME) . 
        '</button>';
        $form_fields['resize-for-discover']['html'].=
        '<p class="help reloader-help">' . 
        __( 'Press this button when you select the above ratio but the screen does not reload.',resizeForDiscover::NAME) . 
        '</p>';

        return $form_fields;
    }

    function save_attachment_resize( $attachment_id ) {
        $color= '';
        if(isset($_REQUEST['attachments'][$attachment_id])){
            $values = $_REQUEST['attachments'][$attachment_id];
        }else{
            return;
        }
        
        if(!empty($values['resize-for-discover-transparent'])){
            $color =$values['resize-for-discover-transparent'];
        }elseif (!empty( $values['resize-for-discover-background'])) {
            $color = $values['resize-for-discover-background'];
        }else{
            $color = $this->getInitialColor();
        }
        update_post_meta( $attachment_id, 'resize-for-discover-background', $color );

        $overwrite = empty($values['resize-for-discover-overwrite'] ) ? 0 : 1;
        update_post_meta( $attachment_id, 'resize-for-discover-overwrite', $overwrite );

        if ( isset( $values['resize-for-discover'] )) {
            $mode = $values['resize-for-discover'];
            if(!isset($mode) || $mode === "") return;
            update_post_meta( $attachment_id, 'resize-for-discover', $mode );
            if($mode === "-1") return;
            $imagepath = get_attached_file($attachment_id);
            $color = empty($color) ? get_post_meta($attachment_id, 'resize-for-discover-background', true ) : $color;
            // $color = empty($color) ? $this->getInitialColor() : $color ;
            $resizeIns = new ImageResizerForDiscover($imagepath,$overwrite, $color);

            if(!$overwrite) 
                $resizeIns->setSuffix('-'. ltrim($color,'#') . '-' . $mode);
                //重複を防ぐ
                $resizeIns->setSavePath();
                if(file_exists($resizeIns->getSavePath())) return;

            try {
                $saveResult = $resizeIns ->resizeForDiscover($mode, true);
                if($saveResult){
                    $imagepath = $resizeIns->getSavePath();
                    $imagepath = resizeForDiscover::toWPlocalPath($imagepath);
                    if(!$overwrite) {
                        update_post_meta( $attachment_id, 'resize-for-discover', -1 );
                        $attachment_id = resizeForDiscover::insertAttachment($imagepath);
                        update_post_meta( $attachment_id, 'resize-for-discover-background', $color );
                        update_post_meta( $attachment_id, 'resize-for-discover', $mode );
                    }
                    resizeForDiscover::registerImage($attachment_id, $imagepath);
                }
            } catch (Exception $th) {
                error_log(print_r($th->getMessage(),true));
            }
        }
    }
}



class resizeForDiscoverSettingsPage
{
    private $options;
    const SLUG = 'resize-thumbnail-discover-settings';
    const OPTION = 'resize_thumbnail_discover_options';

	public function __construct() {
		add_action( 'admin_menu',  array( $this, 'add_menu' ) );
        add_action( 'admin_init',  array( $this, 'init_page' ) );
	}

	function add_menu() {
        add_options_page( 'Resize For Discover Settings', 'Resize For Discover', 'manage_options', self::SLUG, array( $this, 'create_page' ) );
	}

	function create_page() {
		?>
		<div class="wrap">
			<h1><?php _e("Resize For Discover Settings", resizeForDiscover::NAME); ?></h1>
			<?php $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'tab-page1'; ?>
			<h2 class="nav-tab-wrapper">
				<a href="?page=<?=self::SLUG?>&amp;tab=tab-page1" class="nav-tab <?php echo $active_tab == 'tab-page1' ? 'nav-tab-active' : ''; ?>"><?php _e("Initial setting", resizeForDiscover::NAME); ?></a>
				<a href="?page=<?=self::SLUG?>&amp;tab=tab-page2" class="nav-tab <?php echo $active_tab == 'tab-page2' ? 'nav-tab-active' : ''; ?>"><?php _e("Resize post thumbnails", resizeForDiscover::NAME); ?></a>
			</h2>
			<form method="post" action="options.php">
			<?php
				settings_fields( 'resize_for_discover_settings' );
				do_settings_sections( self::SLUG );
				submit_button();
			?>
			</form>
		</div>
		<?php
	}

	function init_page() {
		$this->options = get_option( self::OPTION );
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'tab-page1';
		switch ( $active_tab ){
			case 'tab-page1':
				add_settings_section( 'resize_for_discover_section1', __("Initial setting", resizeForDiscover::NAME), '__return_false', self::SLUG);
				add_settings_field( 'field1', '', array( $this, 'display_field1' ), self::SLUG, 'resize_for_discover_section1' , array('field'=>'field1'));
				break;
			case 'tab-page2':
				add_settings_section( 'resize_for_discover_section2',  __('Resize and register all post thumbail', resizeForDiscover::NAME), '__return_false', self::SLUG );
				add_settings_field( 'field2', '', array( $this, 'display_field2' ), self::SLUG, 'resize_for_discover_section2' );
				break;
		}
		register_setting( 'resize_for_discover_settings' , self::OPTION, array( $this, 'sanitize' ) );
	}

	function sanitize( $input ) {
        $output = get_option( self::OPTION );

		if ( isset( $input['field1'] ) ){
            $output['field1']['resize-for-discover-background'] = $input['field1']['resize-for-discover-background'];

            $output['field1']['resize-for-discover-transparent'] = 
            empty($input['field1']['resize-for-discover-transparent'])
            ? '' : $input['field1']['resize-for-discover-transparent'];

            $output['field1']['resize-for-discover-overwrite'] = 
            empty($input['field1']['resize-for-discover-overwrite'])
            ? 0 : 1;

            $output['field1']['resize-for-discover-newsize'] = 
            empty($input['field1']['resize-for-discover-newsize'])
            ? 0 : 1;
        }
        if ( isset( $input['field2'] ) ){
            if(isset( $input['field2']['all']) === !empty($input['field2']['ids'])){
                add_settings_error( self::SLUG, 'message', __('Either check all resize or Input Post IDs.'));
                return $output;
            }
            
            $postIDs = isset($input['field2']['all']) ? array_column(get_posts(), 'ID') :
            explode(",", sanitize_text_field($input['field2']['ids'])) ;

            $color = empty($input['field2']['resize-for-discover-background'])
            ? resizeForDiscover::INITIALCOLOR 
            : $input['field2']['resize-for-discover-background'];

            $transparent = empty($input['field2']['transparent'])
            ? '' : $input['field2']['transparent'];

            $this->resizeAndInsertThumbFromID($input['field2']['ratio'], $postIDs, $color, $transparent);
        }
		return $output;
    }

    private function resizeAndInsertThumbFromID($mode, $postIDs, $color, $transparent){
        $resizedImage=[];
        $error = '';
        $updateFlg = false;
        foreach ($postIDs as $id) {
            $thumbID = get_post_thumbnail_id($id);
            if(!$thumbID){
                $error .= 'Post ID ' . $id . __(' does not have eyecatch or does not exist.') . "<br>\n";
                continue;
            }
            if(isset($resizedImage[$thumbID])) continue; //same image isn't resized.
            $imagepath= get_attached_file($thumbID);
            $type = wp_check_filetype($imagepath);
            $applyColor = $transparent && $type['ext'] === 'png'
            ? $transparent : $color;
            $resizeIns = new ImageResizerForDiscover($imagepath, false, $applyColor);
            try {
                // throw new Exception('exception'); //for messege check
                $saveResult = $resizeIns ->resizeForDiscover((int)$mode);
                if($saveResult){
                    $imagepath = $resizeIns->getSavePath();
                    $imagepath = resizeForDiscover::toWPlocalPath($imagepath);
                    $attach_id = resizeForDiscover::insertAttachment($imagepath, $id);
                    if($attach_id) {
                        resizeForDiscover::registerImage($attach_id, $imagepath);
                        set_post_thumbnail( $id, $attach_id );
                        $resizedImage[$thumbID] = true;
                    }
                    $updateFlg = true;
                }else{
                    $error .= 'Post ID '. $id .__("'s eyecactch is not resized. Because This image is too big.<br>\n");
                }
            } catch (Exception $th) {
                $errorString = $th->getMessage();
                error_log(print_r($errorString,true));
                $error .= "Post ID {$id} error:{$errorString} <br> \n";
            }

        }
        if(!empty($error)){
            $type = $updateFlg ? 'updated' : 'error';
            add_settings_error( self::SLUG, 'message', $error, $type);
        }
    }

    private function ratioListSelect($name,$checkedRatio=0){
        ?>
        <select name="<?= $name ?>" >
            <?php 
                foreach (resizeForDiscover::ratios() as $val => $txt) {
            ?>
                <option value="<?=$val?>"
                <?= $checkedRatio == $val ? 'selected' : '' ?>>
                <?=$txt?>
            </label>
            <?php
            }
            ?>
        </select>
		<?php
    }



    private function input_color($array){
        $color = empty($this->options) || empty($this->options['field1']['resize-for-discover-background'])
        ? resizeForDiscover::INITIALCOLOR
        : $this->options['field1']['resize-for-discover-background'];
        ?>
        <th scope="row"><?php _e('Letter box color', resizeForDiscover::NAME); ?></th>
        <td>
            <input type="text" name="<?= self::OPTION . '[' . $array['field'] ?>][resize-for-discover-background]" value="<?= $color ?>"> 
        </td>
        <script>
            jQuery(document).ready(function($){
                $('[name$="[resize-for-discover-background]"]').wpColorPicker();
            });
        </script>
        <?php
    }

    private function checkbox_transparent($array){
        $transparentChecked = empty($this->options)
        || empty($this->options['field1']['resize-for-discover-transparent']) 
        ? '' : ' checked="checked"';
        ?>
        <th scope="row"><label for="transparent"><?php _e('Transparent', resizeForDiscover::NAME); ?></label></th>
        <td>
            <input id="transparent" type="checkbox" name="<?= self::OPTION . '[' . $array['field'] ?>][resize-for-discover-transparent]" value="transparent"<?= $transparentChecked ?>>
        </td>
        <?php
    }


    private function checkbox_overwrite($array){
        $overwriteChecked = empty($this->options)
        || empty($this->options['field1']['resize-for-discover-overwrite'])
        ? '' : ' checked="checked"';
        ?>
        <th scope="row"><label for="overwrite"><?php _e('Overwrite', resizeForDiscover::NAME); ?></label></th>
        <td>
            <input id="overwrite" type="checkbox" name="<?= self::OPTION . '[' . $array['field'] ?>][resize-for-discover-overwrite]" value="1"<?= $overwriteChecked ?>>
        </td>
        <?php
    }

	function display_field1($array) {
        $newSizeChecked = empty($this->options)
        || empty($this->options['field1']['resize-for-discover-newsize'])
        ? '' : ' checked="checked"';
        ?>
        <div style="margin-left: -200px;">
            <table>
                <tr>
                    <?php $this->input_color($array); ?>
                </tr>
                <tr>
                    <?php $this->checkbox_transparent($array); ?>
                </tr>
                <tr>
                    <?php $this->checkbox_overwrite($array); ?>
                </tr>
                <tr>
                    <th scope="row"><label for="new-image-size"><?php _e('Register new Image Size', resizeForDiscover::NAME); ?></label></th>
                    <td>
                        <div style="margin-bottom: 1em;"><input id="new-image-size" type="checkbox" name="<?= self::OPTION ?>[field1][resize-for-discover-newsize]" value="1"<?= $newSizeChecked ?>></div>
                        <div>
                            <p><?php _e('Registers Below new Image Size.', resizeForDiscover::NAME); ?></p>
                            <p>
                                <?php _e('1200 x 675', resizeForDiscover::NAME); ?><br>
                                <?php _e('1200 x 900', resizeForDiscover::NAME); ?><br>
                                <?php _e('1200 x 1200', resizeForDiscover::NAME); ?>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

	function display_field2() {
        $indexArray = ['field'=>'field2'];
		?>
        <div style="margin-left: -200px;">
            <p><?php _e("Resize images that are less than 1200px wide or less than 800000px.", resizeForDiscover::NAME); ?>
            <table>
                <tr>
                    <th scope="row"><?php _e('ratio', resizeForDiscover::NAME); ?></th>
                    <td>
                        <?php $this->ratioListSelect(self::OPTION . '[field2][ratio]'); ?>
                    </td>
                </tr>
                <tr>
                    <?php $this->input_color($indexArray); ?>
                </tr>
                <tr>
                    <?php $this->checkbox_transparent($indexArray); ?>
                </tr>
                <!-- <tr> If there is demand -->
                    <?php // $this->checkbox_overwrite($indexArray); ?>
                <!-- </tr> -->
                <tr>
                    <th scope="row"><label for="all-resize"><?php _e('Resize and register all post thumbnails', resizeForDiscover::NAME); ?></label></th>
                    <td>
                        <input type="checkbox" id="all-resize" name="<?= self::OPTION ?>[field2][all]" value="1">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="resize-post-id"><?php _e("Post ID", resizeForDiscover::NAME); ?></label></th>
                    <td>
                        <input type="text" pattern="^[0-9,]+$" id="resize-post-id" name="<?= self::OPTION ?>[field2][ids]"><br>
                        <p><?php _e("Input post IDs with thumbnail you want to resize, <br> separated by commas.", resizeForDiscover::NAME); ?></p>
                    </td>
                </tr>
            </table>
		</div>
		<?php
	}
}

if( is_admin() ) {
    new resizeForDiscover();
    new resizeForDiscoverAttachmentPage();
    new resizeForDiscoverSettingsPage();
}
