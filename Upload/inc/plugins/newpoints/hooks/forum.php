<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/hooks/forum.php)
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

namespace Newpoints\Hooks\Forum;

use MyBB;

use function Newpoints\Core\count_characters;
use function Newpoints\Core\get_income_value;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\Core\load_set_guest_data;
use function Newpoints\Core\main_file_name;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_format;
use function Newpoints\Core\templates_get;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\url_handler_build;
use function Newpoints\Core\user_can_get_points;
use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\Core\INCOME_TYPE_PAGE_VIEW;
use const Newpoints\Core\INCOME_TYPE_POLL;
use const Newpoints\Core\INCOME_TYPE_POLL_VOTE;
use const Newpoints\Core\INCOME_TYPE_POST;
use const Newpoints\Core\INCOME_TYPE_POST_CHARACTER;
use const Newpoints\Core\INCOME_TYPE_THREAD_REPLY;
use const Newpoints\Core\INCOME_TYPE_THREAD_RATE;
use const Newpoints\Core\INCOME_TYPE_THREAD;
use const Newpoints\Core\INCOME_TYPE_VISIT;

// Loads plugins from global_start and runs a new hook called 'newpoints_global_start' that can be used by NewPoints plugins (instead of global_start)
// global_start can't be used by NP plugins
// todo, fix plugins not being able to use global_start by loading plugins before
function global_start(): bool
{
    global $templatelist;

    if (isset($templatelist)) {
        $templatelist .= ',';
    }

    load_set_guest_data();

    $template_list = [
        'global' => [
            'newpoints_header_menu',
            'newpoints_points_format',
            'multipage_page_current',
            'multipage_page',
            'multipage_nextpage',
            'multipage',
        ],
        'newpoints.php' => [
            'newpoints_menu_category',
            'newpoints_page_pagination',
        ],
        'showthread.php' => [
            'newpoints_postbit',
            'newpoints_donate_inline'
        ],
        'member.php' => [
            'newpoints_profile',
            'newpoints_donate_inline'
        ]
    ];

    $template_list = run_hooks('global_start', $template_list);

    foreach ($template_list as $script_name => $templates) {
        if (!is_array($templates)) {
            $templatelist .= ',' . $templates;

            continue;
        }

        if ($script_name === 'global' || $script_name === THIS_SCRIPT) {
            $templatelist .= ',' . implode(',', $templates);
        }
    }

    //users_update();
    return true;
}

function global_intermediate(): bool
{
    global $mybb;
    global $newpoints_header_menu;

    $newpoints_header_menu = '';

    if (!empty($mybb->user['uid'])) {
        global $lang;

        $newpoints_file = main_file_name();

        language_load();

        $newpoints_header_menu = eval(templates_get('header_menu'));
    }

    return true;
}

function global_end(): bool
{
    global $mybb;

    if (empty($mybb->user['uid'])) {
        return false;
    }

    $user_id = (int)$mybb->user['uid'];

    if (empty($mybb->usergroup['newpoints_can_get_points'])) {
        return false;
    }

    if (get_income_value(INCOME_TYPE_PAGE_VIEW)) {
        points_add_simple(
            $user_id,
            get_income_value(INCOME_TYPE_PAGE_VIEW)
        );
    }

    if (get_income_value(INCOME_TYPE_VISIT)) {
        if ((TIME_NOW - $mybb->user['lastactive']) > $mybb->usergroup['newpoints_income_visit_minutes'] * 60) {
            points_add_simple(
                $user_id,
                get_income_value(INCOME_TYPE_VISIT)
            );
        }
    }

    return true;
}

// Loads plugins from xmlhttp and runs a new hook called 'newpoints_xmlhttp' that can be used by NewPoints plugins (instead of xmlhttp)
// xmlhttp can't be used by NP plugins
// todo, fix plugins not being able to use xmlhttp by loading plugins before
function xmlhttp(): bool
{
    load_set_guest_data();

    run_hooks('xmlhttp');

    return true;
}

// Loads plugins when in archive and runs a new hook called 'newpoints_archive_start' that can be used by NewPoints plugins (instead of archive_start)
// todo, fix plugins not being able to use archive_start by loading plugins before
function archive_start(): bool
{
    load_set_guest_data();

    run_hooks('archive_start');

    return true;
}

function postbit(array &$post): array
{
    global $mybb, $currency, $points, $donate, $lang, $uid;

    $post['newpoints_postbit'] = $points = $post['newpoints_balance_formatted'] = '';

    if (empty($post['uid'])) {
        return $post;
    }

    language_load();

    $newpoints_file = main_file_name();

    $currency = get_setting('main_curname');

    $points = $post['newpoints_balance_formatted'] = points_format((float)$post['newpoints']);

    $uid = intval($post['uid']);

    if (!empty($mybb->usergroup['newpoints_can_donate']) && !empty($mybb->user['uid']) && $uid !== (int)$mybb->user['uid']) {
        $donate = eval(templates_get('donate_inline'));
    } else {
        $donate = '';
    }

    $post['newpoints_postbit'] = eval(templates_get('postbit'));

    return $post;
}

function postbit_prev(array &$post): array
{
    return postbit($post);
}

function postbit_pm(array &$post): array
{
    return postbit($post);
}

function postbit_announcement(array &$post): array
{
    return postbit($post);
}

function member_profile_end(): bool
{
    global $mybb, $currency, $points, $memprofile, $newpoints_profile, $lang, $uid;

    $newpoints_profile = '';

    global $newpoints_profile_user_balance_formatted;

    language_load();

    $newpoints_file = main_file_name();

    $currency = get_setting('main_curname');

    $points = $newpoints_profile_user_balance_formatted = points_format((float)$memprofile['newpoints']);

    $uid = intval($memprofile['uid']);

    if (!empty($mybb->usergroup['newpoints_can_donate']) && !empty($mybb->user['uid']) && $uid !== $mybb->user['uid']) {
        $donate = eval(templates_get('donate_inline'));
    } else {
        $donate = '';
    }

    $newpoints_profile = eval(templates_get('profile'));

    return true;
}

// todo, I'm unsure how this is necessary if we already hook at the data handler
// edit post - counts less chars on edit because of \n\r being deleted
function xmlhttp_edit_post_end(): bool
{
    global $mybb, $post, $lang, $charset;

    if (empty($mybb->user['uid'])) {
        return false;
    }

    $post_user_id = (int)$post['uid'];

    $user_data = get_user($post_user_id);

    if (!user_can_get_points($post_user_id)) {
        return false;
    }

    if (!get_income_value(INCOME_TYPE_POST_CHARACTER)) {
        return false;
    }

    if ($mybb->get_input('do') != 'update_post') {
        return false;
    }

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        xmlhttp_error($lang->invalid_post_code);
    }

    $old_character_count = count_characters($post['message']);

    $message = strval($_POST['value']);
    if (my_strtolower($charset) != 'utf-8') {
        if (function_exists('iconv')) {
            $message = iconv($charset, 'UTF-8//IGNORE', $message);
        } elseif (function_exists('mb_convert_encoding')) {
            $message = mb_convert_encoding($message, $charset, 'UTF-8');
        } elseif (my_strtolower($charset) == 'iso-8859-1') {
            $message = utf8_decode($message);
        }
    }

    $new_character_count = count_characters($message);

    $bonus_income = 0;

    // calculate points per character bonus
    // let's see if the number of characters in the post is greater than the minimum characters
    if ($new_character_count !== $old_character_count) {
        $bonus_income = ($new_character_count - $old_character_count) * get_income_value(
                INCOME_TYPE_POST_CHARACTER
            );
    }

    if (!empty($bonus_income)) {
        points_add_simple((int)$user_data['uid'], $bonus_income, (int)$post['fid']);
    }

    return true;
}

function class_moderation_delete_post_start(int $pid): int
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $pid;
    }

    if (!get_income_value(INCOME_TYPE_POST)) {
        return $pid;
    }

    $post = get_post($pid);
    // It's currently soft deleted, so we do nothing as we already subtracted points when doing that
    // If it's not visible (unapproved) we also don't take out any money
    if ($post['visible'] == -1 || $post['visible'] == 0) {
        return $pid;
    }

    $post_user_id = (int)$post['uid'];

    $fid = (int)$fid;

    $thread = get_thread($post['tid']);

    $user_group_permissions = users_get_group_permissions($post_user_id);

    // calculate points per character bonus
    // let's see if the number of characters in the post is greater than the minimum characters
    if (($charcount = count_characters(
            $post['message']
        )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
        $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
    } else {
        $bonus = 0;
    }

    if ($thread['uid'] != $post['uid']) {
        // we are not the thread started so remove points from him/her
        if (get_income_value(INCOME_TYPE_THREAD_REPLY)) {
            $thread_user_id = (int)$thread['uid'];

            if (user_can_get_points($thread_user_id)) {
                points_add_simple(
                    $thread_user_id,
                    -get_income_value(INCOME_TYPE_THREAD_REPLY),
                    $fid
                );
            }
        }
    }

    if (!user_can_get_points($post_user_id)) {
        return $pid;
    }

    // remove points from the poster
    points_add_simple(
        $post_user_id,
        -get_income_value(INCOME_TYPE_POST) - (float)$bonus,
        $fid
    );

    return $pid;
}

function class_moderation_soft_delete_posts(array $pids): array
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $pids;
    }

    if (!get_income_value(INCOME_TYPE_POST)) {
        return $pids;
    }

    $fid = (int)$fid;

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            $post_user_id = (int)$post['uid'];

            $user_group_permissions = users_get_group_permissions($post_user_id);

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so remove points from him/her
                if (get_income_value(INCOME_TYPE_THREAD_REPLY)) {
                    $thread_user_id = (int)$thread['uid'];

                    if (user_can_get_points($thread_user_id)) {
                        points_add_simple(
                            $thread_user_id,
                            -get_income_value(INCOME_TYPE_THREAD_REPLY),
                            $fid
                        );
                    }
                }
            }

            if (!user_can_get_points($post_user_id)) {
                continue;
            }

            // remove points from the poster
            points_add_simple(
                $post_user_id,
                -get_income_value(INCOME_TYPE_POST) - (float)$bonus,
                $fid
            );
        }
    }

    return $pids;
}

function class_moderation_restore_posts($pids): array
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $pids;
    }

    if (!get_income_value(INCOME_TYPE_POST)) {
        return $pids;
    }

    $fid = (int)$fid;

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            $post_user_id = (int)$post['uid'];

            $user_group_permissions = users_get_group_permissions($post_user_id);

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so give points to them
                if (get_income_value(INCOME_TYPE_THREAD_REPLY)) {
                    $thread_user_id = (int)$thread['uid'];

                    if (user_can_get_points($thread_user_id)) {
                        points_add_simple(
                            $thread_user_id,
                            get_income_value(INCOME_TYPE_THREAD_REPLY),
                            $fid
                        );
                    }
                }
            }

            if (!user_can_get_points($post_user_id)) {
                continue;
            }

            // give points to the author of the post
            points_add_simple(
                $post_user_id,
                get_income_value(INCOME_TYPE_POST) + (float)$bonus,
                $fid
            );
        }
    }

    return $pids;
}

function class_moderation_approve_threads(array $tids): array
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $tids;
    }

    if (!get_income_value(INCOME_TYPE_THREAD)) {
        return $tids;
    }

    $fid = (int)$fid;

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            $post_user_id = (int)$post['uid'];

            $user_group_permissions = users_get_group_permissions($post_user_id);

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            if (!user_can_get_points($post_user_id)) {
                continue;
            }

            // add points to the poster
            points_add_simple(
                $post_user_id,
                get_income_value(INCOME_TYPE_THREAD) + (float)$bonus,
                $fid
            );
        }
    }

    return $tids;
}

function class_moderation_approve_posts(array $pids): array
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $pids;
    }

    if (!get_income_value(INCOME_TYPE_POST)) {
        return $pids;
    }

    $fid = (int)$fid;

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            $post_user_id = (int)$post['uid'];

            $user_group_permissions = users_get_group_permissions($post_user_id);

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so give points to them
                if (get_income_value(INCOME_TYPE_THREAD_REPLY)) {
                    $thread_user_id = (int)$thread['uid'];

                    if (user_can_get_points($thread_user_id)) {
                        points_add_simple(
                            $thread_user_id,
                            get_income_value(INCOME_TYPE_THREAD_REPLY),
                            $fid
                        );
                    }
                }
            }

            if (!user_can_get_points($post_user_id)) {
                continue;
            }

            // give points to the author of the post
            points_add_simple(
                $post_user_id,
                get_income_value(INCOME_TYPE_POST) + (float)$bonus,
                $fid
            );
        }
    }

    return $pids;
}

function class_moderation_unapprove_threads(array $tids): array
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $tids;
    }

    if (!get_income_value(INCOME_TYPE_THREAD)) {
        return $tids;
    }

    $fid = (int)$fid;

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            $post_user_id = (int)$post['uid'];

            $user_group_permissions = users_get_group_permissions($post_user_id);

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            if (!user_can_get_points($post_user_id)) {
                continue;
            }

            // add points to the poster
            points_add_simple(
                $post_user_id,
                -get_income_value(INCOME_TYPE_THREAD) - (float)$bonus,
                $fid
            );
        }
    }

    return $tids;
}

function class_moderation_unapprove_posts(array $pids): array
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $pids;
    }

    if (!get_income_value(INCOME_TYPE_POST)) {
        return $pids;
    }

    $fid = (int)$fid;

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            $post_user_id = (int)$post['uid'];

            $user_group_permissions = users_get_group_permissions($post_user_id);

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so remove points from them
                if (get_income_value(INCOME_TYPE_THREAD_REPLY)) {
                    $thread_user_id = (int)$thread['uid'];

                    if (user_can_get_points($thread_user_id)) {
                        points_add_simple(
                            $thread_user_id,
                            -get_income_value(INCOME_TYPE_THREAD_REPLY),
                            $fid
                        );
                    }
                }
            }

            if (!user_can_get_points($post_user_id)) {
                continue;
            }

            // give points to the author of the post
            points_add_simple(
                $post_user_id,
                -get_income_value(INCOME_TYPE_POST) - (float)$bonus,
                $fid
            );
        }
    }

    return $pids;
}

function class_moderation_delete_thread(int $tid): int
{
    global $db, $mybb;

    if (empty($mybb->user['uid'])) {
        return $tid;
    }

    if (!get_income_value(INCOME_TYPE_THREAD)) {
        return $tid;
    }

    // even though the thread was deleted it was previously cached so we can use get_thread
    $thread = get_thread($tid);
    $fid = (int)$thread['fid'];

    // It's currently soft deleted, so we do nothing as we already subtracted points when doing that
    // If it's not visible (unapproved) we also don't take out any money
    if ($thread['visible'] == -1 || $thread['visible'] == 0) {
        return $tid;
    }

    // get post of the thread
    $post = get_post($thread['firstpost']);

    $thread_user_id = (int)$thread['uid'];

    $user_group_permissions = users_get_group_permissions($thread_user_id);

    // calculate points per character bonus
    // let's see if the number of characters in the thread is greater than the minimum characters
    if (($charcount = count_characters(
            $post['message']
        )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
        $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
    } else {
        $bonus = 0;
    }

    if (!user_can_get_points($thread_user_id)) {
        return $tid;
    }

    if ($thread['poll'] != 0) {
        // if this thread has a poll, remove points from the author of the thread

        points_add_simple(
            $thread_user_id,
            -get_income_value(INCOME_TYPE_POLL),
            $fid
        );
    }

    $q = $db->simple_select(
        'posts',
        'COUNT(*) as total_replies',
        'uid!=' . (int)$thread['uid'] . ' AND tid=' . (int)$thread['tid']
    );
    $thread['replies'] = (int)$db->fetch_field($q, 'total_replies');

    points_add_simple(
        $thread_user_id,
        -(float)($thread['replies'] * get_income_value(INCOME_TYPE_THREAD_REPLY)),
        $fid
    );

    // take out points from the author of the thread
    points_add_simple(
        $thread_user_id,
        -get_income_value(INCOME_TYPE_THREAD) - (float)$bonus,
        $fid
    );

    return $tid;
}

function class_moderation_soft_delete_threads(array $tids): array
{
    global $db, $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $tids;
    }

    if (!get_income_value(INCOME_TYPE_THREAD)) {
        return $tids;
    }

    $fid = (int)$fid;

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            $post_user_id = (int)$post['uid'];

            $user_group_permissions = users_get_group_permissions($post_user_id);

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so remove points from him/her
                if (get_income_value(INCOME_TYPE_THREAD_REPLY)) {
                    $thread_user_id = (int)$thread['uid'];

                    if (user_can_get_points($thread_user_id)) {
                        points_add_simple(
                            $thread_user_id,
                            -get_income_value(INCOME_TYPE_THREAD_REPLY),
                            $fid
                        );
                    }
                }
            }

            if (!user_can_get_points($thread_user_id)) {
                continue;
            }

            // remove points from the poster
            points_add_simple(
                $post_user_id,
                -get_income_value(INCOME_TYPE_THREAD) - (float)$bonus,
                $fid
            );
        }
    }

    return $tids;
}

function class_moderation_restore_threads(array $tids): array
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return $tids;
    }

    if (!get_income_value(INCOME_TYPE_THREAD)) {
        return $tids;
    }

    $fid = (int)$fid;

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            $post_user_id = (int)$post['uid'];

            $user_group_permissions = users_get_group_permissions($post_user_id);

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $charcount * get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so give points to them
                if (get_income_value(INCOME_TYPE_THREAD_REPLY)) {
                    $thread_user_id = (int)$thread['uid'];

                    if (user_can_get_points($thread_user_id)) {
                        points_add_simple(
                            $thread_user_id,
                            get_income_value(INCOME_TYPE_THREAD_REPLY),
                            $fid
                        );
                    }
                }
            }

            if (!user_can_get_points($post_user_id)) {
                continue;
            }

            // give points to the author of the post
            points_add_simple(
                $post_user_id,
                get_income_value(INCOME_TYPE_THREAD) + (float)$bonus,
                $fid
            );
        }
    }

    return $tids;
}

function polls_do_newpoll_process(): bool
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return false;
    }

    if (!get_income_value(INCOME_TYPE_POLL)) {
        return false;
    }

    $fid = (int)$fid;

    $user_id = (int)$mybb->user['uid'];

    if (!user_can_get_points($user_id)) {
        return false;
    }

    // give points to the author of the new polls
    points_add_simple(
        $user_id,
        get_income_value(INCOME_TYPE_POLL),
        $fid
    );

    return true;
}

function class_moderation_delete_poll(int $pid): int
{
    global $db, $mybb;

    if (empty($mybb->user['uid'])) {
        return $pid;
    }

    if (!get_income_value(INCOME_TYPE_POLL)) {
        return $pid;
    }

    $query = $db->simple_select('polls', '*', "pid='{$pid}'");
    $poll = $db->fetch_array($query);

    $fid = (int)$poll['fid'];

    $poll_user_id = (int)$poll['uid'];

    if (!user_can_get_points($poll_user_id)) {
        return $pid;
    }

    // remove points from the author by deleting the poll
    points_add_simple(
        $poll_user_id,
        -get_income_value(INCOME_TYPE_POLL),
        $fid
    );

    return $pid;
}

function polls_vote_process(): bool
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return false;
    }

    if (get_income_value(INCOME_TYPE_POLL_VOTE)) {
        return false;
    }

    $fid = (int)$fid;

    $user_id = (int)$mybb->user['uid'];

    if (!user_can_get_points($user_id)) {
        return false;
    }

    // give points to us as we're voting in a poll
    points_add_simple(
        $user_id,
        get_income_value(INCOME_TYPE_POLL_VOTE),
        $fid
    );

    return true;
}

function ratethread_process(): bool
{
    global $mybb, $fid;

    if (empty($mybb->user['uid'])) {
        return false;
    }

    if (!get_income_value(INCOME_TYPE_THREAD_RATE)) {
        return false;
    }

    $fid = (int)$fid;

    $user_id = (int)$mybb->user['uid'];

    if (!user_can_get_points($user_id)) {
        return false;
    }

    // give points us, as we're rating a thread
    points_add_simple(
        $user_id,
        get_income_value(INCOME_TYPE_THREAD_RATE),
        $fid
    );

    return true;
}

function forumdisplay_start(): bool
{
    global $mybb;

    _helper_evaluate_forum_view_lock($mybb->get_input('fid', MyBB::INPUT_INT));

    return true;
}

function showthread_start(): bool
{
    global $forum;

    _helper_evaluate_forum_view_lock((int)$forum['fid']);

    return true;
}

function editpost_start(): bool
{
    global $mybb;

    $post_id = $mybb->get_input('pid', MyBB::INPUT_INT);

    $post_data = get_post($post_id);

    _helper_evaluate_forum_view_lock((int)$post_data['fid']);

    return true;
}

function sendthread_do_sendtofriend_start(): bool
{
    global $thread;

    _helper_evaluate_forum_view_lock((int)$thread['fid']);

    return true;
}

function sendthread_start(): bool
{
    return sendthread_do_sendtofriend_start();
}

function archive_forum_start(): bool
{
    global $forum;

    _helper_evaluate_forum_view_lock((int)$forum['fid']);

    return true;
}

function archive_thread_start(): bool
{
    return archive_forum_start();
}

function printthread_end(): bool
{
    global $thread;

    _helper_evaluate_forum_view_lock((int)$thread['fid']);

    return true;
}

function newreply_start(): bool
{
    global $fid;

    _helper_evaluate_forum_post_lock((int)$fid);

    return true;
}

function newreply_do_newreply_start(): bool
{
    return newreply_start();
}

function newthread_start(): bool
{
    return newreply_start();
}

function newthread_do_newthread_start(): bool
{
    return newreply_start();
}

function _helper_evaluate_forum_view_lock(int $forum_id): bool
{
    $forum_data = get_forum($forum_id);

    if (empty($forum_data['newpoints_view_lock_points'])) {
        return false;
    }

    global $mybb, $lang;

    if ($forum_data['newpoints_view_lock_points'] > $mybb->user['newpoints']) {
        language_load();

        error(
            $lang->sprintf(
                $lang->newpoints_not_enough_points,
                points_format((float)$forum_data['newpoints_view_lock_points'])
            )
        );
    }

    return true;
}

function _helper_evaluate_forum_post_lock(int $forum_id): bool
{
    $forum_data = get_forum($forum_id);

    if (empty($forum_data['newpoints_post_lock_points'])) {
        return false;
    }

    global $mybb, $lang;

    if ($forum_data['newpoints_post_lock_points'] > $mybb->user['newpoints']) {
        language_load();

        error(
            $lang->sprintf(
                $lang->newpoints_not_enough_points,
                points_format((float)$forum_data['newpoints_post_lock_points'])
            )
        );
    }

    return true;
}

function fetch_wol_activity_end(array &$hook_parameters): array
{
    global $lang;

    if (my_strpos($hook_parameters['location'], main_file_name()) === false) {
        return $hook_parameters;
    }

    $hook_parameters['activity'] = 'newpoints_home';

    if (my_strpos($hook_parameters['location'], 'action=stats') !== false) {
        $hook_parameters['activity'] = 'newpoints_stats';
    }

    if (my_strpos($hook_parameters['location'], 'action=donate') !== false) {
        $hook_parameters['activity'] = 'newpoints_donation';
    }

    if (my_strpos($hook_parameters['location'], 'action=logs') !== false) {
        $hook_parameters['activity'] = 'newpoints_logs';
    }

    return $hook_parameters;
}

function build_friendly_wol_location_end(array &$hook_parameters): array
{
    global $mybb, $lang;

    language_load();

    switch ($hook_parameters['user_activity']['activity']) {
        case 'newpoints_home':
            $hook_parameters['location_name'] = $lang->sprintf(
                $lang->newpoints_wol_location_home,
                $mybb->settings['bburl'],
                main_file_name()
            );
            break;
        case 'newpoints_stats':
            $hook_parameters['location_name'] = $lang->sprintf(
                $lang->newpoints_wol_location_stats,
                $mybb->settings['bburl'],
                url_handler_build(['action' => 'stats'])
            );
            break;
        case 'newpoints_donation':
            $hook_parameters['location_name'] = $lang->sprintf(
                $lang->newpoints_wol_location_donation,
                $mybb->settings['bburl'],
                url_handler_build(['action' => 'donate'])
            );
            break;
        case 'newpoints_logs':
            $hook_parameters['location_name'] = $lang->sprintf(
                $lang->newpoints_wol_location_logs,
                $mybb->settings['bburl'],
                url_handler_build(['action' => 'logs'])
            );
            break;
    }

    return $hook_parameters;
}

function memberlist_start(): bool
{
    global $mybb;

    if ($mybb->get_input('sort') === 'newpoints') {
        global $newpointsMemberListSort;

        $newpointsMemberListSort = true;
    }

    return true;
}

function memberlist_intermediate(): bool
{
    global $newpointsMemberListSort;

    if (!empty($newpointsMemberListSort)) {
        global $mybb;
        global $sort, $sort_field;

        $sort_field = 'u.newpoints';

        $sort = $mybb->input['sort'] = 'newpoints';
    }

    return true;
}

function memberlist_user(array &$user_data): array
{
    $user_data['newpoints'] = $user_data['newpoints'] ?? 0;

    $user_data['newpoints_formatted'] = points_format((float)$user_data['newpoints']);

    return $user_data;
}