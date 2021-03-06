<?php

namespace Bims;

use Bims\Helpers\Encryptor;

class WPeopleAPISetting
{
    private $wpeopleapi_setting_options,
        $key = 'BWpeople',
        $authorizer = false,
        $tokenInfo = false,
        $base_url = false;

    public function __construct($authorizer = false)
    {
        if ($authorizer) {
            $this->authorizer = $authorizer;
        }

        add_action('admin_menu', array($this, 'wpeopleapi_setting_add_plugin_page'));
        add_action('admin_init', array($this, 'wpeopleapi_setting_page_init'));
    }

    public function wpeopleapi_setting_add_plugin_page()
    {
        add_options_page(
            'WPeopleAPI Setting', // page_title
            'WPeopleAPI Setting', // menu_title
            'manage_options', // capability
            'wpeopleapi-setting', // menu_slug
            array($this, 'wpeopleapi_setting_create_admin_page') // function
        );
    }

    public function wpeopleapi_setting_create_admin_page()
    {
        $this->wpeopleapi_setting_options = get_option('wpeopleapi_setting_option_name'); ?>

        <div class="wrap">
            <h2>WPeopleAPI Setting</h2>
            <p>Here to setting your WPeopleAPI. For documentation visit <a target="_blank" href="https://github.com/ajid2/WPeopleAPI">here</a></p>
            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('wpeopleapi_setting_option_group');
                do_settings_sections('wpeopleapi-setting-admin');
                ?>
                <p class="submit">
                    <input type="submit" name="wpeopleapi_setting_option_name[submit_wpeopleapi]" id="submit" class="button button-primary" value="Simpan Perubahan">
                    <input type="submit" name="wpeopleapi_setting_option_name[update_token]" id="update_token" class="button button-primary" value="Update Token Only">
                </p>
            </form>
        </div>
<?php }

    public function wpeopleapi_setting_page_init()
    {
        register_setting(
            'wpeopleapi_setting_option_group', // option_group
            'wpeopleapi_setting_option_name', // option_name
            array($this, 'wpeopleapi_setting_sanitize') // sanitize_callback
        );

        add_settings_section(
            'wpeopleapi_setting_setting_section', // id
            'Settings', // title
            array($this, 'wpeopleapi_setting_section_info'), // callback
            'wpeopleapi-setting-admin' // page
        );

        add_settings_field(
            'the_client_id', // id
            'Client Id', // title
            array($this, 'the_client_id_callback'), // callback
            'wpeopleapi-setting-admin', // page
            'wpeopleapi_setting_setting_section' // section
        );

        add_settings_field(
            'the_client_secret', // id
            'Client Secret', // title
            array($this, 'the_client_secret_callback'), // callback
            'wpeopleapi-setting-admin', // page
            'wpeopleapi_setting_setting_section' // section
        );

        add_settings_field(
            'authorization_token', // id
            'Authorization Token', // title
            array($this, 'authorization_token'), // callback
            'wpeopleapi-setting-admin', // page
            'wpeopleapi_setting_setting_section' // section
        );
    }

    public function wpeopleapi_setting_sanitize($input)
    {
        $sanitary_values = array();

        if (!isset($input['update_token'])) {
            if (isset($input['the_client_id'])) {
                $sanitary_values['the_client_id'] = Encryptor::encrypt(sanitize_text_field($input['the_client_id']), $this->key);
            }

            if (isset($input['the_client_secret'])) {
                $sanitary_values['the_client_secret'] = Encryptor::encrypt(sanitize_text_field($input['the_client_secret']), $this->key);
            }

            if (isset($input['authorization_token'])) {
                $sanitary_values['authorization_token'] = sanitize_text_field($input['authorization_token']);
            }
        }else{
            if (isset($input['authorization_token'])) {
                $sanitary_values['authorization_token'] = sanitize_text_field($input['authorization_token']);
            }
            $opt = get_option('wpeopleapi_setting_option_name');

            $sanitary_values['the_client_id']       = (isset($opt['the_client_id'])) ? $opt['the_client_id'] : '';
            $sanitary_values['the_client_secret']   = (isset($opt['the_client_secret'])) ? $opt['the_client_secret'] : '';
        }

        return $sanitary_values;
    }

    public function wpeopleapi_setting_section_info()
    {
    }

    public function the_client_id_callback()
    {
        printf(
            '<input class="regular-text" type="text" name="wpeopleapi_setting_option_name[the_client_id]" id="the_client_id" value="%s" required="true">',
            isset($this->wpeopleapi_setting_options['the_client_id']) ? esc_attr($this->wpeopleapi_setting_options['the_client_id']) : ''
        );
    }

    public function the_client_secret_callback()
    {
        printf(
            '<input class="regular-text" type="password" name="wpeopleapi_setting_option_name[the_client_secret]" id="the_client_secret" value="%s" required="true">',
            isset($this->wpeopleapi_setting_options['the_client_secret']) ? esc_attr($this->wpeopleapi_setting_options['the_client_secret']) : ''
        );
    }

    public function authorization_token()
    {
        printf(
            '<input class="regular-text" type="password" name="wpeopleapi_setting_option_name[authorization_token]" id="authorization_token" value="%s" required="true">
            <p style="font-style: italic;color: #888;">The authorization token used to validate your request. Put this token to your request HTTP_AUTHORIZATION and use <b>Bearer</b> token</p>',
            isset($this->wpeopleapi_setting_options['authorization_token']) ? esc_attr($this->wpeopleapi_setting_options['authorization_token']) : ''
        );
    }

    public function setAuthorizer($authorizer)
    {
        return $this->authorizer = $authorizer;
    }

    public function setTokenInfo($tokenInfo)
    {
        return $this->tokenInfo = $tokenInfo;
    }

    public function setBaseUrl($base_url)
    {
        return $this->base_url = $base_url;
    }

    public function authorizer()
    {
        $that = &$this;
        add_action('admin_init', function () use ($that) {
            add_settings_field(
                'authorize_2', // id
                'Authorization', // title
                array($that, 'authorizer_callback'), // callback
                'wpeopleapi-setting-admin', // page
                'wpeopleapi_setting_setting_section' // section
            );
        });
    }

    public function authorizer_callback()
    {
        if ($this->authorizer) {
            if ($this->tokenInfo) {
                echo '<div>Authorized as ' . $this->tokenInfo->email . '<a class="button" style="vertical-align: middle;margin-left: 10px;background: #DC3232;border:none;color:#fff" href="?page=wpeopleapi-setting&removeAuthWPeopleAPI=true">Remove</a></div>';
                return;
            }
            echo '<a class="button button-primary" href="' . $this->authorizer . '">Authorize</a>';
            return;
        } else {
            echo "Not available";
            return false;
        }
    }

    public function showBaseUrl()
    {
        $that = &$this;
        add_action('admin_init', function () use ($that) {
            add_settings_field(
                'base_url', // id
                'Authorized redirect URI', // title
                array($that, 'base_url_callback'), // callback
                'wpeopleapi-setting-admin', // page
                'wpeopleapi_setting_setting_section' // section
            );
        });
    }

    public function base_url_callback()
    {
        if ($this->base_url) {
            echo '<input type="text" readonly="readonly" onfocus="this.select();" class="regular-text" value="' . $this->base_url . '">';
            echo '<p style="font-style: italic;color: #888;">Please copy this URL into the "Authorized redirect URIs" field of your Google web application.</p>';
            return;
        }
        return false;
    }
}
