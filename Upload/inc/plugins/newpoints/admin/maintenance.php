<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/maintenance.php)
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

use function Newpoints\Core\get_income_value;
use function Newpoints\Core\language_load;
use function Newpoints\Core\points_add;
use function Newpoints\Core\rules_get_all;
use function Newpoints\Core\rules_get_group_rate;
use function Newpoints\Core\rules_group_get;
use function Newpoints\Core\run_hooks;

use const Newpoints\Core\INCOME_TYPE_POLL_VOTE;
use const Newpoints\Core\INCOME_TYPE_POST_MINIMUM_CHARACTERS;
use const Newpoints\Core\INCOME_TYPE_POST_NEW;
use const Newpoints\Core\INCOME_TYPE_POST_PER_REPLY;
use const Newpoints\Core\INCOME_TYPE_USER_REGISTRATION;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb;

language_load();

run_hooks('admin_maintenance_begin');

$page->add_breadcrumb_item($lang->newpoints_maintenance, 'index.php?module=newpoints-maintenance');

$page->output_header($lang->newpoints_maintenance);

$sub_tabs['newpoints_maintenance'] = [
    'title' => $lang->newpoints_maintenance,
    'link' => 'index.php?module=newpoints-maintenance',
    'description' => $lang->newpoints_maintenance_description
];

$sub_tabs['newpoints_maintenance_edituser'] = [
    'title' => $lang->newpoints_maintenance_edituser,
    'link' => 'index.php?module=newpoints-maintenance&amp;action=edituser',
    'description' => $lang->newpoints_maintenance_edituser_desc
];

if (!$mybb->get_input('action')) // show page with various actions that can be taken
{
    run_hooks('admin_maintenance_start');

    $page->output_nav_tabs($sub_tabs, 'newpoints_maintenance');

    $form = new Form('index.php?module=newpoints-maintenance&amp;action=recount', 'post', 'newpoints');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $form_container = new FormContainer($lang->newpoints_recount);
    $form_container->output_row(
        $lang->newpoints_recount_per_page,
        $lang->newpoints_recount_per_page_desc,
        $form->generate_text_box('per_page', 50, ['id' => 'per_page']),
        'per_page'
    );
    $form_container->end();

    $buttons = [];
    $buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
    $buttons[] = $form->generate_reset_button($lang->newpoints_reset_button);
    $form->output_submit_wrapper($buttons);
    $form->end();

    echo '<br />';

    $form = new Form('index.php?module=newpoints-maintenance&amp;action=reset', 'post', 'newpoints');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $form_container = new FormContainer($lang->newpoints_reset);
    $form_container->output_row(
        $lang->newpoints_reset_per_page,
        $lang->newpoints_reset_per_page_desc,
        $form->generate_text_box('per_page', 500, ['id' => 'per_page']),
        'per_page'
    );
    $form_container->output_row(
        $lang->newpoints_reset_points,
        $lang->newpoints_reset_points_desc,
        $form->generate_text_box('points', 0, ['id' => 'points']),
        'points'
    );
    $form_container->end();

    $buttons = [];
    $buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
    $buttons[] = $form->generate_reset_button($lang->newpoints_reset_button);
    $form->output_submit_wrapper($buttons);
    $form->end();

    echo '<br />';

    $form = new Form('index.php?module=newpoints-maintenance&amp;action=edituser', 'post', 'newpoints');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $form_container = new FormContainer($lang->newpoints_edituser);
    $form_container->output_row(
        $lang->newpoints_edituser_uid,
        $lang->newpoints_edituser_uid_desc,
        $form->generate_text_box('uid', 0, ['id' => 'uid']),
        'uid'
    );
    $form_container->end();

    $buttons = [];
    $buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
    $buttons[] = $form->generate_reset_button($lang->newpoints_reset_button);
    $form->output_submit_wrapper($buttons);
    $form->end();

    run_hooks('admin_maintenance_end');
} elseif ($mybb->get_input('action') == 'edituser') {
    run_hooks('admin_maintenance_edituser_start');

    $page->output_nav_tabs($sub_tabs, 'newpoints_maintenance_edituser');

    if (!$mybb->get_input('uid', MyBB::INPUT_INT) || !($user = get_user($mybb->get_input('uid', MyBB::INPUT_INT)))) {
        flash_message($lang->newpoints_invalid_user, 'error');
        admin_redirect('index.php?module=newpoints-maintenance');
    }

    if ($mybb->request_method == 'post' && $mybb->get_input('do_change')) {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input('my_post_key')) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-maintenance');
        }

        $updates = ['newpoints' => $mybb->get_input('points', MyBB::INPUT_FLOAT)];

        run_hooks('admin_maintenance_edituser_commit');

        $db->update_query('users', $updates, 'uid=\'' . $mybb->get_input('uid', MyBB::INPUT_INT) . '\'');

        flash_message($lang->newpoints_user_edited, 'success');
        admin_redirect('index.php?module=newpoints-maintenance');
    }

    $form = new Form('index.php?module=newpoints-maintenance&amp;action=edituser', 'post', 'newpoints');

    echo $form->generate_hidden_field('uid', $mybb->get_input('uid', MyBB::INPUT_INT));
    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);
    echo $form->generate_hidden_field('do_change', 1);

    $form_container = new FormContainer($lang->newpoints_edituser);
    $form_container->output_row(
        $lang->newpoints_edituser_points,
        $lang->newpoints_edituser_points_desc,
        $form->generate_text_box(
            'points',
            round((float)$user['newpoints'], intval($mybb->settings['newpoints_main_decimal'])),
            ['id' => 'points']
        ),
        'points'
    );
    $form_container->end();

    run_hooks('admin_maintenance_edituser_form');

    $buttons = [];
    $buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
    $buttons[] = $form->generate_reset_button($lang->newpoints_reset_button);
    $form->output_submit_wrapper($buttons);

    $form->end();

    run_hooks('admin_maintenance_edituser_end');
} elseif ($mybb->get_input('action') == 'recount') {
    run_hooks('admin_maintenance_recount_start');

    if ($mybb->get_input('no')) // user clicked no
    {
        admin_redirect('index.php?module=newpoints-maintenance');
    }

    if ($mybb->request_method == 'post') {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input(
                'my_post_key'
            ) || !$mybb->get_input('per_page', MyBB::INPUT_INT)) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-maintenance');
        }

        if ($mybb->get_input('start', MyBB::INPUT_INT) > 0) {
            $start = $mybb->get_input('start', MyBB::INPUT_INT);
        } else {
            $start = 0;
        }

        if ($mybb->get_input('per_page', MyBB::INPUT_INT) > 0) {
            $per_page = $mybb->get_input('per_page', MyBB::INPUT_INT);
        } else {
            $per_page = 50;
        }

        $query = $db->simple_select('users', 'COUNT(*) as users');
        $total_users = $db->fetch_field($query, 'users');

        $allforumrules = rules_get_all('forum');

        $query = $db->simple_select(
            'users',
            'uid,usergroup,additionalgroups',
            '',
            ['order_by' => 'uid', 'order_dir' => 'ASC', 'limit' => "{$start}, {$per_page}"]
        );
        while ($user = $db->fetch_array($query)) {
            // recount points
            $points = 0;

            $group_rate = rules_get_group_rate($user);

            if (!$group_rate) {
                continue;
            }

            $firstposts = [0];

            // threads and polls
            $totalthreads_query = $db->simple_select(
                'threads',
                'firstpost,fid,poll',
                "uid='" . $user['uid'] . "' AND visible=1"
            );
            while ($thread = $db->fetch_array($totalthreads_query)) {
                if (!get_income_value(INCOME_TYPE_THREAD_NEW)) {
                    continue;
                }

                if (!$allforumrules[$thread['fid']]) {
                    $allforumrules[$thread['fid']]['rate'] = 1;
                } // no rule set so default income rate is 1

                // if the forum rate is 0, nothing is going to be added so let's just skip to the next post
                if ($allforumrules[$thread['fid']]['rate'] == 0) {
                    continue;
                }

                // calculate points ber character bonus
                // let's see if the number of characters in the thread is greater than the minimum characters
                if (($charcount = my_strlen(
                        $mybb->get_input('message')
                    )) >= get_income_value(INCOME_TYPE_POST_MINIMUM_CHARACTERS)) {
                    $bonus = $charcount * get_income_value(INCOME_TYPE_POST_PER_CHARACTER);
                } else {
                    $bonus = 0;
                }

                // give points to the author of the new thread
                $points += (get_income_value(INCOME_TYPE_THREAD_NEW) + $bonus) * $allforumrules[$thread['fid']]['rate'];

                if ($thread['poll'] != 0) // has a poll
                {
                    $points += get_income_value(INCOME_TYPE_POLL_NEW) * $allforumrules[$thread['fid']]['rate'];
                }

                $firstposts[] = $thread['firstpost'];
            }

            // posts
            $totalposts_query = $db->simple_select(
                'posts',
                'fid,message',
                "uid='" . $user['uid'] . "' AND pid NOT IN(" . implode(',', $firstposts) . ') AND visible=1'
            );
            while ($post = $db->fetch_array($totalposts_query)) {
                if (!get_income_value(INCOME_TYPE_POST_NEW)) {
                    continue;
                }

                if (!$allforumrules[$post['fid']]) {
                    $allforumrules[$post['fid']]['rate'] = 1;
                } // no rule set so default income rate is 1

                // if the forum rate is 0, nothing is going to be added so let's just skip to the next post
                if ($allforumrules[$post['fid']]['rate'] == 0) {
                    continue;
                }

                // calculate points ber character bonus
                // let's see if the number of characters in the post is greater than the minimum characters
                if (($charcount = my_strlen($post['message'])) >= get_income_value(
                        INCOME_TYPE_POST_MINIMUM_CHARACTERS
                    )) {
                    $bonus = $charcount * get_income_value(INCOME_TYPE_POST_PER_CHARACTER);
                } else {
                    $bonus = 0;
                }

                // give points to the poster
                $points += (get_income_value(INCOME_TYPE_POST_NEW) + $bonus) * $allforumrules[$post['fid']]['rate'];

                $thread = get_thread($post['tid']);
                if ($thread['uid'] != $user['uid']) {
                    // we are not the thread started so give points to him/her
                    if (get_income_value(INCOME_TYPE_POST_PER_REPLY)) {
                        points_add(
                            $thread['uid'],
                            get_income_value(INCOME_TYPE_POST_PER_REPLY),
                            $allforumrules[$post['fid']]['rate'],
                            $group_rate
                        );
                    }
                }
            }

            // poll votes
            if (get_income_value(INCOME_TYPE_POLL_VOTE)) {
                // just count the votes and don't get the poll and the thread (to calculate the correct income value  using the forum income rate but as it is a slow process, let's just not use forum rate here)
                $pollvotes_query = $db->simple_select('pollvotes', 'COUNT(*) AS votes', "uid='" . $user['uid'] . "'");
                $votes = $db->fetch_field($pollvotes_query, 'votes');

                $points += $votes * get_income_value(INCOME_TYPE_POLL_VOTE);
            }

            // private messages
            if (get_income_value(INCOME_TYPE_PRIVATE_MESSAGE_NEW)) {
                // count private messages this user has sent
                $pmssent_query = $db->simple_select(
                    'privatemessages',
                    'COUNT(*) AS numpms',
                    "fromid='" . $user['uid'] . "' AND toid!='" . $user['uid'] . "' AND receipt!='1'"
                );
                $pmssent = $db->fetch_field($pmssent_query, 'numpms');

                $points += $pmssent * get_income_value(INCOME_TYPE_PRIVATE_MESSAGE_NEW);
            }

            $db->update_query(
                'users',
                [
                    'newpoints' => floatval(
                            get_income_value(INCOME_TYPE_USER_REGISTRATION)
                        ) + $points * $group_rate
                ],
                'uid=\'' . $user['uid'] . '\''
            );
        }

        if ($total_users > $start + $mybb->get_input('per_page', MyBB::INPUT_INT)) {
            $form = new Form('index.php?module=newpoints-maintenance&amp;action=recount', 'post', 'newpoints');
            echo $form->generate_hidden_field('my_post_key', $mybb->post_code);
            echo $form->generate_hidden_field('start', $start + $mybb->get_input('per_page', MyBB::INPUT_INT));
            echo $form->generate_hidden_field('per_page', $mybb->get_input('per_page', MyBB::INPUT_INT));
            echo "<div class=\"confirm_action\">\n";
            echo "<p>{$lang->newpoints_click_continue}</p>\n";
            echo "<br />\n";
            echo "<p class=\"buttons\">\n";
            echo $form->generate_submit_button($lang->newpoints_continue_button, ['class' => 'button_yes']);
            echo "</p>\n";
            echo "</div>\n";

            $form->end();

            $page->output_footer();

            exit;
        }

        log_admin_action($lang->newpoints_recount_done);

        flash_message($lang->newpoints_recounted, 'success');
        admin_redirect('index.php?module=newpoints-maintenance');
    }

    $form = new Form(
        "index.php?module=newpoints-maintenance&amp;action=recount&amp;per_page={$mybb->get_input('per_page', MyBB::INPUT_INT)}&amp;my_post_key={$mybb->post_code}",
        'post'
    );
    echo "<div class=\"confirm_action\">\n";
    echo "<p>{$lang->newpoints_recountconfirm}</p>\n";
    echo "<br />\n";
    echo "<p class=\"buttons\">\n";
    echo $form->generate_submit_button($lang->yes, ['class' => 'button_yes']);
    echo $form->generate_submit_button($lang->no, ['name' => 'no', 'class' => 'button_no']);
    echo "</p>\n";
    echo "</div>\n";
    $form->end();

    run_hooks('admin_maintenance_recount_end');
} elseif ($mybb->get_input('action') == 'reset') {
    run_hooks('admin_maintenance_reset_start');

    if ($mybb->get_input('no')) // user clicked no
    {
        admin_redirect('index.php?module=newpoints-maintenance');
    }

    if ($mybb->request_method == 'post') {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input(
                'my_post_key'
            ) || !$mybb->get_input('per_page', MyBB::INPUT_INT)) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-maintenance');
        }

        $points = $mybb->get_input('points', MyBB::INPUT_FLOAT);

        if ($mybb->get_input('start', MyBB::INPUT_INT) > 0) {
            $start = $mybb->get_input('start', MyBB::INPUT_INT);
        } else {
            $start = 0;
        }

        if ($mybb->get_input('per_page', MyBB::INPUT_INT) > 0) {
            $per_page = $mybb->get_input('per_page', MyBB::INPUT_INT);
        } else {
            $per_page = 500;
        }

        $query = $db->simple_select('users', 'COUNT(*) as users');
        $total_users = $db->fetch_field($query, 'users');

        $query = $db->simple_select(
            'users',
            'uid',
            '',
            ['order_by' => 'uid', 'order_dir' => 'ASC', 'limit' => "{$start}, {$per_page}"]
        );
        while ($user = $db->fetch_array($query)) {
            // reset
            $db->update_query('users', ['newpoints' => $points], 'uid = \'' . $user['uid'] . '\'');
        }

        if ($total_users > $start + $mybb->get_input('per_page', MyBB::INPUT_INT)) {
            $form = new Form(
                "index.php?module=newpoints-maintenance&amp;action=reset&amp;my_post_key={$mybb->post_code}",
                'post',
                'newpoints'
            );
            echo $form->generate_hidden_field('my_post_key', $mybb->post_code);
            echo $form->generate_hidden_field('start', $start + $mybb->get_input('per_page', MyBB::INPUT_INT));
            echo $form->generate_hidden_field('per_page', $mybb->get_input('per_page', MyBB::INPUT_INT));
            echo $form->generate_hidden_field('points', $mybb->get_input('points', MyBB::INPUT_FLOAT));
            echo "<div class=\"confirm_action\">\n";
            echo "<p>{$lang->newpoints_click_continue}</p>\n";
            echo "<br />\n";
            echo "<p class=\"buttons\">\n";
            echo $form->generate_submit_button($lang->newpoints_continue_button, ['class' => 'button_yes']);
            echo "</p>\n";
            echo "</div>\n";

            $form->end();

            $page->output_footer();

            exit;
        }

        log_admin_action($lang->newpoints_reset_done);

        flash_message($lang->newpoints_reset_action, 'success');
        admin_redirect('index.php?module=newpoints-maintenance');
    }

    $form = new Form(
        "index.php?module=newpoints-maintenance&amp;action=recount&amp;per_page={$mybb->get_input('per_page', MyBB::INPUT_INT)}&amp;my_post_key={$mybb->post_code}",
        'post'
    );
    echo "<div class=\"confirm_action\">\n";
    echo "<p>{$lang->newpoints_resetconfirm}</p>\n";
    echo "<br />\n";
    echo "<p class=\"buttons\">\n";
    echo $form->generate_submit_button($lang->yes, ['class' => 'button_yes']);
    echo $form->generate_submit_button($lang->no, ['name' => 'no', 'class' => 'button_no']);
    echo "</p>\n";
    echo "</div>\n";
    $form->end();

    run_hooks('admin_maintenance_reset_start');
}

run_hooks('admin_maintenance_terminate');

$page->output_footer();