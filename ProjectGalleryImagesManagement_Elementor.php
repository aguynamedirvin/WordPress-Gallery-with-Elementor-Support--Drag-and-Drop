<?php 

/** 
 * WordPress Sortable Gallery Script
 * ---------------------------------
 * Purpose:
 *     This script facilitates the creation of a responsive and sortable image gallery 
 *     specifically designed for WordPress websites. The gallery is fully compatible 
 *     with the Elementor page builder, allowing users to seamlessly integrate and 
 *     showcase their media content.
 * 
 * Features:
 *     - Drag & Drop functionality for easy image arrangement.
 *     - Responsive design ensures optimal display across various devices.
 *     - Elementor support offers enhanced customization and flexibility.
 *     - Modern and user-friendly interface for improved user experience.
 * 
 * Usage:
 *     To utilize this script, embed it within your WordPress theme or plugin, 
 *     ensuring Elementor is also installed and activated. Follow the accompanying 
 *     documentation for detailed setup and customization instructions.
 * 
 * Author:
 *     Irvin Dominguez / SquarePixl
 *
 * Version:
 *    0.6.0 (Initial release)
**/

class ProjectImagesMetaBox {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'register_project_images_meta_box']);
        add_action('save_post', [$this, 'save_project_images_meta_box']);
        add_action('wp_ajax_remove_project_image', [$this, 'ajax_remove_project_image']);
    }

    public function register_project_images_meta_box() {
        add_meta_box('project_images_meta_box', 'Project Images', [$this, 'project_images_meta_box_callback'], 'project', 'normal', 'high');
    }

    // Utility function to render image box
    private function render_image_box($image_id) {
        $image_array = wp_get_attachment_image_src($image_id, 'medium');
        $image_url = $image_array[0];
        return '<div class="image-wrapper" data-id="' . esc_attr($image_id) . '">'
            . '<img src="' . esc_url($image_url) . '" />'
            . '<span class="dashicons dashicons-no remove-image"></span>'
            . '</div>';
    }

    public function project_images_meta_box_callback($post) {
        wp_nonce_field('save_project_images_meta', 'project_images_meta_box_nonce');
        $image_ids = $this->get_image_ids($post->ID);

        echo '
		<style>
            #project_images_container {
                margin-bottom: 20px;
            }
			.sortable-container {
				display: grid;
				grid-template-columns: repeat(6, 1fr); /* 4-column grid by default */
				gap: 16px;
			}
			/* Tablet to Medium Desktop view */
			@media screen and (max-width: 1768px) {
				.sortable-container {
					grid-template-columns: repeat(4, 1fr); /* 4-column grid */
				}
			}
			/* Mobile view */
			@media screen and (max-width: 767px) {
				.sortable-container {
					grid-template-columns: 1fr 1fr; /* Two column grid */
				}
			}
			.image-wrapper {
				border: 1px solid #ccc;
				padding: 8px;
				position: relative;
                height: 250px;
			}
            .image-wrapper:hover {
                cursor: grab;
            }
            .image-wrapper:active {
                cursor: grabbing;
            }
			.image-wrapper img {
				width: 100%;
                height: 100%;
                object-fit: contain;
			}
			.remove-image {
				position: absolute;
				top: 4px;
				right: 4px;
				cursor: pointer;
				color: red;
			}
		</style>';

        echo '<div id="project_images_container" class="sortable-container">';
        foreach ($image_ids as $image_id) {
            if ($image_id) {
                echo $this->render_image_box($image_id);
            }
        }
        echo '</div>';

        echo '<input type="hidden" name="project_image_ids" id="project_image_ids" value="' . esc_attr(implode(',', $image_ids)) . '" />';
        echo '<input type="button" id="select_images_button" class="button" value="Select Images" />';
        ?>
        <script>
            jQuery(document).ready(function($) {
                var selectImagesFrame;
                var singleImageFrame;

                var image_ids_field = $('#project_image_ids');
                var existing_image_ids = image_ids_field.val().split(',');

                // Function to show attachment details
                function showAttachmentDetails(imageId) {
                    if (wp.media) {
                        // Get attachment details
                        var attachment = wp.media.attachment(imageId);
                        
                        // Wait for request to complete
                        attachment.fetch().done(function() {
                            // Open the modal
                            var detailsFrame = wp.media({
                                frame: 'image',
                                state: 'image-details',
                                metadata: attachment.toJSON()
                            });
                            
                            detailsFrame.open();
                        });
                    }
                }

                // Click event on image to show attachment details
                $('#project_images_container').on('click', 'img', function() {
                    var imageId = $(this).parent('.image-wrapper').data('id');
                    showAttachmentDetails(imageId);
                });

                // Click event on "Select Images" button
                $('#select_images_button').on('click', function(e) {
                    e.preventDefault();

                    // Initialize or reuse the frame suitable for selecting multiple images
                    if (selectImagesFrame) {
                        selectImagesFrame.open();
                        return;
                    }

                    selectImagesFrame = wp.media({
                        title: 'Select Images',
                        button: { text: 'Insert Images' },
                        multiple: 'add',
                        library: { type: 'image' }
                    });

                    // Pre-select existing images
                    selectImagesFrame.on('open', function() {
                        var selection = selectImagesFrame.state().get('selection');
                        $.each(existing_image_ids, function(index, id) {
                            var attachment = wp.media.attachment(id);
                            attachment.fetch();
                            selection.add(attachment ? [attachment] : []);
                        });
                    });

                    selectImagesFrame.on('select', function() {
                        var attachments = selectImagesFrame.state().get('selection').toJSON();
                        var attachment_ids = [];

                        $('#project_images_container').html('');  // Clear current images

                        /**$.each(attachments, function(key, attachment) {
                            attachment_ids.push(attachment.id);
                            $('#project_images_container').append('<div class="image-wrapper" data-id="' + attachment.id + '"><img src="' + attachment.url + '" /><span class="dashicons dashicons-no remove-image"></span></div>');
                        });**/

                        $.each(attachments, function(key, attachment) {
                            attachment_ids.push(attachment.id);
                            var thumbnail_url = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
                            $('#project_images_container').append('<div class="image-wrapper" data-id="' + attachment.id + '"><img src="' + thumbnail_url + '" /><span class="dashicons dashicons-no remove-image"></span></div>');
                        });

                        // Update the hidden field with new selected images
                        image_ids_field.val(attachment_ids.join(','));
                        existing_image_ids = image_ids_field.val().split(',');
                    });

                    selectImagesFrame.open();
                });

                // Make images sortable
                $('#project_images_container').sortable({
                    update: function(event, ui) {
                        var sortedIDs = [];
                        $('#project_images_container .image-wrapper').each(function(){
                            sortedIDs.push($(this).data('id'));
                        });
                        image_ids_field.val(sortedIDs.join(','));
                    }
                });

                
                // Function to remove image
                $('#project_images_container').on('click', '.remove-image', function() {
                    var parentDiv = $(this).parent('.image-wrapper');
                    var imageId = parentDiv.data('id');
                    var existingIds = image_ids_field.val().split(',');

                    // Remove from array
                    var index = existingIds.indexOf(imageId.toString());
                    if (index > -1) {
                        existingIds.splice(index, 1);
                    }

                    image_ids_field.val(existingIds.join(',')); // Update hidden field
                    existing_image_ids = image_ids_field.val().split(',');

                    // Remove image from post meta via AJAX
                    $.post(ajaxurl, {
                        action: 'remove_project_image',
                        post_id: <?php echo $post->ID; ?>,
                        image_id: imageId
                    }, function(response) {
                        if (response.success) {
                            parentDiv.remove(); // Remove image div only if successfully removed from DB
                        } else {
                            alert('Could not remove image. Please try again.');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function save_project_images_meta_box($post_id) {
        if (!$this->is_valid_save($post_id, 'save_project_images_meta', 'project_images_meta_box_nonce')) {
            return;
        }

        $image_ids = isset($_POST['project_image_ids']) ? explode(',', $_POST['project_image_ids']) : [];
        $image_ids = array_filter($image_ids);
        update_post_meta($post_id, '_project_image_ids', $image_ids);
    }

    public function ajax_remove_project_image() {
        if (!isset($_POST['post_id'], $_POST['image_id'])) {
            wp_send_json_error('Missing post ID or image ID');
        }

        $post_id = intval($_POST['post_id']);
        $image_id = intval($_POST['image_id']);
        $image_ids = $this->get_image_ids($post_id);

        if (($key = array_search($image_id, $image_ids)) !== false) {
            unset($image_ids[$key]);
            update_post_meta($post_id, '_project_image_ids', implode(',', $image_ids));
            wp_send_json_success('Image removed successfully');
        } else {
            wp_send_json_error('Image not found');
        }
    }

    private function get_image_ids($post_id) {
        $image_ids = get_post_meta($post_id, '_project_image_ids', true);
        return is_array($image_ids) ? $image_ids : explode(',', $image_ids);
    }

    private function is_valid_save($post_id, $action, $nonce_field) {
        if (!isset($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $action)) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if ($post_id === get_option('page_for_posts')) {
            return false;
        }

        if ($parent_id = wp_is_post_revision($post_id)) {
            $post_id = $parent_id;
        }

        return true;
    }
}

new ProjectImagesMetaBox();




// Registering Dynamic Tag with Elementor
use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use ElementorPro\Modules\DynamicTags\Module as TagsModule;

class Project_Images_Tag extends Data_Tag {

    public function get_name() {
        return 'project-images';
    }

    public function get_title() {
        return __( 'Project Images', 'text-domain' );
    }

    public function get_group() {
        return 'project-group';
    }

    public function get_categories() {
        return [ TagsModule::GALLERY_CATEGORY ];
    }

    protected function register_controls() {
        $this->add_control(
            'choose_gallery',
            [
                'label' => __( 'Choose Gallery', 'text-domain' ),
                'type' => Controls_Manager::GALLERY,
                'default' => [],
            ]
        );
    }

	public function get_value( array $options = [] ) {
        $current_post_id = get_the_ID();  // Get the current post ID
        $image_ids = get_post_meta( $current_post_id, '_project_image_ids', true );  // Fetch image IDs from post meta
        $image_ids = is_array($image_ids) ? $image_ids : explode(',', $image_ids);  // Ensure $image_ids is an array

        $images = [];
        foreach ( $image_ids as $image_id ) {
            if ($image_id) {
                $images[] = [
                    'id' => $image_id,
                ];
            }
        }

        return $images;
    }

}

function register_project_images_tag( $dynamic_tags_manager ) {
    $dynamic_tags_manager->register_group(
        'project-group',
        [
            'title' => __( 'Project Group', 'text-domain' )
        ]
    );

    $dynamic_tags_manager->register( new Project_Images_Tag );
}

add_action( 'elementor/dynamic_tags/register', 'register_project_images_tag' );









/** Back to Top Button **/
function output_back_to_top_button() {
    echo '<div id="back-to-top" class="hidden">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 15L12 9L6 15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>';

    echo "
    <script type='text/javascript'>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('back-to-top');

            window.addEventListener('scroll', function () {
                if (window.scrollY > 100) {
                    btn.classList.add('visible');
                } else {
                    btn.classList.remove('visible');
                }
            });

            btn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    </script>
    ";
}
add_action('wp_footer', 'output_back_to_top_button');
