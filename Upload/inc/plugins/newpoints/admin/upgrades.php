<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/upgrades.php)
 *    Author: Pirata Nervo
 *    Copyright: © 2009 Pirata Nervo
 *    Copyright: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

use function Newpoints\Core\language_load;
use function Newpoints\Core\run_hooks;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb;

language_load();

run_hooks('admin_upgrades_begin');

if (!$mybb->get_input('action')) // view upgrades
{
    $page->add_breadcrumb_item($lang->newpoints_upgrades, 'index.php?module=newpoints-upgrades');

    $page->output_header($lang->newpoints_upgrades);

    $sub_tabs['newpoints_upgrades'] = [
        'title' => $lang->newpoints_upgrades,
        'link' => 'index.php?module=newpoints-upgrades',
        'description' => $lang->newpoints_upgrades_description
    ];

    $page->output_nav_tabs($sub_tabs, 'newpoints_upgrades');

    echo "<p class=\"notice\">{$lang->newpoints_upgrades_notice}</p>";

    $upgrades = newpoints_get_upgrades();

    // table
    $table = new Table();
    $table->construct_header($lang->newpoints_upgrades_name, ['width' => '70%']);
    $table->construct_header($lang->options, ['width' => '30%', 'class' => 'align_center']);

    if (!empty($upgrades)) {
        foreach ($upgrades as $upgrade) {
            $codename = str_replace('.php', '', $upgrade);
            require_once MYBB_ROOT . 'inc/plugins/newpoints/upgrades/' . $upgrade;
            $infofunc = $codename . '_info';
            if (!function_exists($infofunc)) {
                continue;
            }

            $upgradeinfo = $infofunc();

            $table->construct_cell("{$upgradeinfo['name']} <br /><small>{$upgradeinfo['description']}</small>");
            $table->construct_cell(
                "<a href=\"index.php?module=newpoints-upgrades&amp;action=run&amp;upgrade_file=" . $codename . "&amp;my_post_key={$mybb->post_code}\" target=\"_self\">{$lang->newpoints_run}</a>",
                ['class' => 'align_center']
            ); // delete button

            $table->construct_row();
        }
    } else {
        $table->construct_cell($lang->newpoints_no_upgrades, ['colspan' => 2]);
        $table->construct_row();
    }

    $table->output($lang->newpoints_upgrades);
} elseif ($mybb->get_input('action') == 'run') {
    if ($mybb->get_input('no')) // user clicked no
    {
        admin_redirect('index.php?module=newpoints-upgrades');
    }

    if ($mybb->request_method == 'post') {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input('my_post_key')) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-upgrades');
        }

        $upgrade = $mybb->get_input('upgrade_file');

        require_once MYBB_ROOT . 'inc/plugins/newpoints/upgrades/' . $upgrade . '.php';
        $runfunc = $upgrade . '_run';
        if (!function_exists($runfunc)) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-upgrades');
        }

        $runfunc();

        flash_message($lang->newpoints_upgrades_ran, 'success');
        admin_redirect('index.php?module=newpoints-upgrades');
    }

    $page->add_breadcrumb_item($lang->newpoints_upgrades, 'index.php?module=newpoints-upgrades');

    $page->output_header($lang->newpoints_upgrades);

    $form = new Form(
        'index.php?module=newpoints-upgrades&amp;action=run&amp;upgrade_file=' . str_replace(
            '.php',
            '',
            htmlspecialchars($mybb->get_input('upgrade_file'))
        ) . "&amp;my_post_key={$mybb->post_code}", 'post'
    );
    echo "<div class=\"confirm_action\">\n";
    echo "<p>{$lang->newpoints_upgrades_confirm_run}</p>\n";
    echo "<br />\n";
    echo "<p class=\"buttons\">\n";
    echo $form->generate_submit_button($lang->yes, ['class' => 'button_yes']);
    echo $form->generate_submit_button($lang->no, ['name' => 'no', 'class' => 'button_no']);
    echo "</p>\n";
    echo "</div>\n";
    $form->end();
}

run_hooks('admin_upgrades_terminate');

$page->output_footer();

function newpoints_get_upgrades()
{
    $upgrades_list = [];

    // open directory
    $dir = @opendir(MYBB_ROOT . 'inc/plugins/newpoints/upgrades/');

    // browse upgrades directory
    if ($dir) {
        while ($file = readdir($dir)) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            if (!is_dir(MYBB_ROOT . 'inc/plugins/newpoints/upgrades/' . $file)) {
                $ext = get_extension($file);
                if ($ext == 'php') {
                    $upgrades_list[] = $file;
                }
            }
        }
        @sort($upgrades_list);
        @closedir($dir);
    }

    return $upgrades_list;
}