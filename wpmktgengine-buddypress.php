<?php
/*
    Plugin Name: BuddyPress - WPMktgEngine | Genoo Extension
    Description: Genoo, LLC
    Author:  Genoo, LLC
    Author URI: http://www.genoo.com/
    Author Email: info@genoo.com
    Version: 1.0.9
    License: GPLv2
*/
/*
    Copyright 2015  WPMKTENGINE, LLC  (web : http://www.genoo.com/)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * On activation
 */

register_activation_hook(__FILE__, function () {
    // Basic extension data
    $fileFolder = basename(dirname(__FILE__));
    $file = basename(__FILE__);
    $filePlugin = $fileFolder . DIRECTORY_SEPARATOR . $file;
    // Activate?
    $activate = false;
    $isGenoo = false;
    // Get api / repo
    if (
        class_exists('\WPME\ApiFactory') &&
        class_exists('\WPME\RepositorySettingsFactory')
    ) {
        $activate = true;
        $repo = new \WPME\RepositorySettingsFactory();
        $api = new \WPME\ApiFactory($repo);
        if (class_exists('\Genoo\Api')) {
            $isGenoo = true;
        }
    } elseif (
        class_exists('\Genoo\Api') &&
        class_exists('\Genoo\RepositorySettings')
    ) {
        $activate = true;
        $repo = new \Genoo\RepositorySettings();
        $api = new \Genoo\Api($repo);
        $isGenoo = true;
    } elseif (
        class_exists('\WPMKTENGINE\Api') &&
        class_exists('\WPMKTENGINE\RepositorySettings')
    ) {
        $activate = true;
        $repo = new \WPMKTENGINE\RepositorySettings();
        $api = new \WPMKTENGINE\Api($repo);
    }
    // 1. First protectoin, no WPME or Genoo plugin
    if ($activate == false) {
        genoo_wpme_deactivate_plugin(
            $filePlugin,
            'This extension requires Wpmktgengine or Genoo plugin to work with.'
        );
    } else {
        if (!$api) {
            return;
        }
        // Right on, let's run the tests etc.
        // 2. Second test, can we activate this extension?
        // Active
        $active = get_option('wpmktengine_extension_forums', null);
        if ($isGenoo === true) {
            $active = true;
        }
        if ($active === null) {
            // Oh oh, no value, lets add one
            try {
                // Might be older package
                if (method_exists($api, 'getPackageForums')) {
                    $active = $api->getPackageForums();
                } else {
                    $active = false;
                }
            } catch (\Exception $e) {
                $active = false;
            }
            // Save new value
            update_option('wpmktengine_extension_forums', $active, true);
        }
        // 3. Check if we can activate the plugin after all
        if ($active == false) {
            genoo_wpme_deactivate_plugin(
                $filePlugin,
                'This extension is not allowed as part of your package.'
            );
        } else {
            // 4. After all we can activate, that's great, lets add those calls
            try {
                $api->setStreamTypes([
                    [
                        'name' => 'started discussion',
                        'description' => '',
                    ],
                    [
                        'name' => 'replied in discussion',
                        'description' => '',
                    ],
                ]);
            } catch (\Exception $e) {
                // Decide later
            }
        }
    }
});

/**
 * Plugin loaded
 */

add_action(
    'wpmktengine_init',
    function ($repositarySettings, $api, $cache) {
        /**
         * Started Discussion (name of topic - name of Forum)
         */

        add_action(
            'bp_forums_new_topic',
            function ($topic_id) use ($api) {
                // Get user
                $user = wp_get_current_user();
                $topic = get_post($topic_id);
                //$api->putActivityByMail($user->user_email, 'started discussion', '' . $topic->post_title, '', get_permalink($topic->ID));
            },
            10,
            1
        );

        /**
         * Replied in Discussion (name of topic - name of Forum)
         */

        add_action(
            'bp_forums_new_post',
            function ($topic_id) use ($api) {
                // Get user
                $user = wp_get_current_user();
                $topic = get_post($topic_id);
                //$api->putActivityByMail($user->user_email, 'replied in discussion', '' . $topic->post_title, '', get_permalink($topic->ID));
            },
            10,
            1
        );

        /**
         * Group created
         */

        add_action(
            'groups_create_group',
            function ($groupid, $member, $group) use ($api) {
                // Get who created it
                $creator = get_userdata($member->user_id);
                // ->user_email
                // ->user_meta
                $name = $group->name;
                $id = $group->id;
                //$creator->user_email . ' created a group ' . $name;
            },
            20,
            3
        );

        /**
         * User joins group
         */

        add_action(
            'groups_join_group',
            function ($group_id, $user_id) use ($api) {
                $user = get_userdata($user_id);
                $name = new BP_Groups_Group($group_id, []);
                $name = $name->name;
            },
            10,
            2
        );

        /**
         * User left group
         */

        add_action(
            'groups_leave_group',
            function ($group_id, $user_id) use ($api) {
                $user = get_userdata($user_id);
                $name = new BP_Groups_Group($group_id, []);
                $name = $name->name;
            },
            10,
            2
        );

        //add_action('groups_promoted_member', '', 10, 2 );

        //add_action('groups_banned_member', '', 10, 2 );

        //add_action('groups_invite_user', '', 10, 2 );

        //add_action('groups_membership_rejected', '', 10, 2 );

        add_filter(
            'wpmktengine_tools_extensions_widget',
            function ($array) {
                $array['BuddyPress - WPMktgEngine | Genoo Extension'] =
                    '<span style="color:green">Active</span>';
                return $array;
            },
            10,
            1
        );
    },
    10,
    3
);

/**
 * Genoo / WPME deactivation function
 */
if (!function_exists('genoo_wpme_deactivate_plugin')) {
    /**
     * @param $file
     * @param $message
     * @param string $recover
     */

    function genoo_wpme_deactivate_plugin($file, $message, $recover = '')
    {
        // Require files
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        // Deactivate plugin
        deactivate_plugins($file);
        unset($_GET['activate']);
        // Recover link
        if (empty($recover)) {
            $recover =
                '</p><p><a href="' .
                admin_url('plugins.php') .
                '">&laquo; ' .
                __('Back to plugins.', 'wpmktengine') .
                '</a>';
        }
        // Die with a message
        wp_die($message . $recover);
        exit();
    }
}
