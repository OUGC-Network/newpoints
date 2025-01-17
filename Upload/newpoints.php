<?php

/***************************************************************************
 *
 *    NewPoints plugin (/newpoints.php)
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

use function Newpoints\Core\get_income_types;
use function Newpoints\Core\get_income_value;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\Core\log_add;
use function Newpoints\Core\log_delete;
use function Newpoints\Core\log_get;
use function Newpoints\Core\main_file_name;
use function Newpoints\Core\page_build_menu;
use function Newpoints\Core\page_build_menu_options;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_format;
use function Newpoints\Core\points_subtract;
use function Newpoints\Core\post_parser;
use function Newpoints\Core\private_message_send;
use function Newpoints\Core\templates_get;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\url_handler_build;
use function Newpoints\Core\url_handler_set;
use function Newpoints\Core\users_get_by_username;

use const Newpoints\Core\INCOME_TYPE_POST;
use const Newpoints\Core\INCOME_TYPE_POST_CHARACTER;
use const Newpoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const Newpoints\Core\INCOME_TYPE_THREAD;
use const Newpoints\Core\INCOME_TYPE_THREAD_REPLY;
use const Newpoints\Core\INCOME_TYPE_USER_REFERRAL;
use const Newpoints\Core\INCOME_TYPE_USER_REGISTRATION;
use const Newpoints\Core\LOGGING_TYPE_INCOME;
use const Newpoints\Core\LOGGING_TYPE_CHARGE;

const IN_MYBB = 1;

const THIS_SCRIPT = 'newpoints.php';

const NP_DISABLE_GUESTS = false;

$templatelist = 'newpoints_option, newpoints_menu, newpoints_home_income_row, newpoints_home_income_table, newpoints_home, newpoints_statistics_richest_user, newpoints_no_results, newpoints_statistics, newpoints_donate_form, newpoints_donate, newpoints_option_selected, newpoints_logs_table_row, newpoints_logs_table, newpoints_button_manage, newpoints_input_select_option, newpoints_input_select, newpoints_logs_filter_table, newpoints_page';

require_once './global.php';

if (!function_exists('\Newpoints\Core\language_load')) {
    error_no_permission();
}

global $mybb, $plugins, $lang, $db, $templates;

$mybb->input['action'] = $mybb->get_input('action');

$newpoints_file = main_file_name();

url_handler_set($newpoints_file);

run_hooks('begin');

// Allow guests here? Some plugins may allow guest access and they may hook to newpoints_start
if (empty($mybb->usergroup['newpoints_can_see_page'])) {
    error_no_permission();
}

language_load();

$options = page_build_menu_options();

$newpoints_menu = page_build_menu();

$newpoints_errors = '';

add_breadcrumb($lang->newpoints, $newpoints_file);

$newpoints_additional = '';

$current_user_id = (int)$mybb->user['uid'];

$newpoints_pagination = $newpoints_buttons = '';

run_hooks('start');

// Block guests here
if (!$current_user_id) {
    error_no_permission();
}

// no action=home
if (!$mybb->get_input('action')) {
    $income_settings = '';

    run_hooks('home_start');

    $income_amount = $lang->sprintf(
        $lang->newpoints_income_amount,
        get_setting('main_curname')
    );

    $latest_transactions = [];

    $income_setting_params = [];

    run_hooks('home_intermediate');

    foreach (get_income_types() as $income_type => $income_params) {
        $income_setting_params["newpoints_income_{$income_type}"] = [];

        foreach ($income_params as $param_key => $param_type) {
            switch ($param_type) {
                case 'numeric':
                    $income_setting_params["newpoints_income_{$income_type}"][$param_key] = my_number_format(
                        $mybb->usergroup["newpoints_income_{$param_key}"]
                    );
                    break;
            }
        }
    }

    $user_group_rate_addition = (float)$mybb->usergroup['newpoints_rate_addition'];

    $user_group_rate_subtraction = $mybb->usergroup['newpoints_rate_subtraction'] / 100;

    $user_rate_description = $lang->sprintf(
        $lang->newpoints_home_user_rate_description,
        $user_group_rate_addition,
        $user_group_rate_subtraction
    );

    run_hooks('home_end');

    foreach ($income_setting_params as $income_key => $income_setting) {
        $constant_name = my_strtoupper(str_replace('newpoints_income_', 'INCOME_TYPE_', $income_key));

        $income_value = get_income_value(constant('\Newpoints\Core\\' . $constant_name));

        if (empty($income_value)) {
            continue;
        }

        $setting['title'] = $lang->{"{$income_key}"};

        $setting['description'] = $lang->{"{$income_key}_desc"};

        $i = 1;

        foreach ($income_setting as $value) {
            $setting['description'] = str_replace("{{$i}}", $value, $setting['description']);

            ++$i;
        }

        $value = points_format($income_value);

        $income_settings .= eval(templates_get('home_income_row'));
    }

    $latest_transactions = implode(' ', $latest_transactions);

    $income_settings = eval(templates_get('home_income_table'));

    $newpoints_home_desc = $lang->newpoints_home_desc;

    $page = eval(templates_get('home'));

    output_page($page);
}

if ($mybb->get_input('action') == 'stats') {
    add_breadcrumb($lang->newpoints_statistics, url_handler_build(['action' => 'stats']));

    if (empty($mybb->usergroup['newpoints_can_see_stats'])) {
        error($lang->newpoints_stats_disabled);
    }

    $richest_users = '';
    $bgcolor = alt_trow();

    $fields = ['uid', 'username', 'newpoints', 'usergroup', 'displaygroup'];

    $statistics_items = [];

    run_hooks('stats_start');

    // get richest users
    $query = $db->simple_select(
        'users',
        implode(',', $fields),
        '',
        [
            'order_by' => 'newpoints',
            'order_dir' => 'DESC',
            'limit' => (int)get_setting('main_stats_richestusers')
        ]
    );
    while ($user = $db->fetch_array($query)) {
        $bgcolor = alt_trow();

        $user['username'] = build_profile_link(
            format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']),
            intval($user['uid'])
        );
        $user['newpoints'] = points_format((float)$user['newpoints']);

        run_hooks('stats_richest_users');

        $richest_users .= eval(templates_get('statistics_richest_user'));
    }

    if ($richest_users == '') {
        $colspan = 2;
        $no_results = $lang->newpoints_noresults;
        $richest_users = eval(templates_get('no_results'));
    }

    run_hooks('stats_middle');

    $last_donations = '';
    $bgcolor = alt_trow();

    // get latest donations
    $query = $db->query(
        "
        SELECT l.*, u.usergroup, u.displaygroup, l.log_primary_id AS to_uid, tu.username AS to_username, tu.usergroup AS to_usergroup, tu.displaygroup AS to_displaygroup
		FROM {$db->table_prefix}newpoints_log l
		LEFT JOIN {$db->table_prefix}users u ON (u.uid=l.uid)
		LEFT JOIN {$db->table_prefix}users tu ON (tu.uid=l.log_primary_id)
		WHERE l.action='donation'
		ORDER BY l.date DESC
		LIMIT " . (int)get_setting('donations_stats_latest')
    );

    while ($donation = $db->fetch_array($query)) {
        $bgcolor = alt_trow();
        $data = explode('-', $donation['data']);

        $donation['to'] = build_profile_link(
            htmlspecialchars_uni($donation['to_username'] ?? ($data[0] ?? '')),
            $donation['to_uid'] ?? ($data[1] ?? 0)
        );

        $donation['from'] = build_profile_link(
            format_name(htmlspecialchars_uni($donation['username']), $donation['usergroup'], $donation['displaygroup']),
            intval($donation['uid'])
        );

        $donation['amount'] = points_format((float)($donation['points'] ?? ($data[2] ?? 0)));
        $donation['date'] = my_date(
                $mybb->settings['dateformat'],
                intval($donation['date']),
                '',
                false
            ) . ', ' . my_date($mybb->settings['timeformat'], intval($donation['date']));

        run_hooks('stats_last_donations');

        $last_donations .= eval(templates_get('statistics_donation'));
    }

    if ($last_donations == '') {
        $colspan = 4;
        $no_results = $lang->newpoints_noresults;
        $last_donations = eval(templates_get('no_results'));
    }

    $statistics_items = implode('', $statistics_items);

    $newpoints_content = eval(templates_get('statistics'));

    $page_title = $lang->newpoints_statistics;

    $page = eval(templates_get('page'));

    run_hooks('stats_end');

    output_page($page);
} elseif ($mybb->get_input('action') == 'donate') {
    if (empty($mybb->usergroup['newpoints_can_donate'])) {
        error($lang->newpoints_donations_disabled);
    }

    run_hooks('donate_start');

    // make sure wen're trying to send a donation to ourselves
    $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
    $user = get_user($uid);
    if (!empty($user['username'])) {
        $user['username'] = htmlspecialchars_uni($user['username']);
    } else {
        $user['username'] = '';
    }

    if ($uid == $current_user_id || $user['username'] == $mybb->user['username']) {
        error($lang->newpoints_cant_donate_self);
    }

    $pid = $mybb->get_input('pid', 1);

    $form = eval(templates_get('donate_form'));

    if ($mybb->get_input('modal', 1)) {
        $code = $form;

        $modal = eval(templates_get('modal', false));

        echo $modal;

        exit;
    }

    $page = eval(templates_get('donate'));

    run_hooks('donate_end');

    output_page($page);
} elseif ($mybb->get_input('action') == 'do_donate') {
    if (empty($mybb->usergroup['newpoints_can_donate'])) {
        error($lang->newpoints_donations_disabled);
    }

    verify_post_check($mybb->get_input('postcode'));

    run_hooks('do_donate_start');

    if ($mybb->user['usergroup'] != 4) {
        $q = $db->simple_select(
            'newpoints_log',
            'COUNT(*) as donations',
            'action=\'donation\' AND date>' . (constant(
                    'TIME_NOW'
                ) - (int)get_setting('donations_flood_minutes') * 60 * 60) . ' AND uid=' . $current_user_id
        );
        $totaldonations = (int)$db->fetch_field($q, 'donations');
        if ($totaldonations >= (int)get_setting('donations_flood_limit')) {
            error($lang->sprintf($lang->newpoints_max_donations_control, $totaldonations));
        }
    }

    $amount = round($mybb->get_input('amount', MyBB::INPUT_FLOAT), (int)get_setting('main_decimal'));

    // do we have enough points?
    if ($amount <= 0 || $amount > $mybb->user['newpoints']) {
        error($lang->newpoints_invalid_amount);
    }

    // make sure we're sending points to a valid user
    $touser = users_get_by_username($mybb->get_input('username'), 'uid,username');

    if (!$touser) {
        error($lang->newpoints_invalid_user);
    }

    $to_user_id = (int)$touser['uid'];

    // make sure we're not trying to send a donation to ourselves
    if ($current_user_id === $to_user_id) {
        error($lang->newpoints_cant_donate_self);
    }

    // remove points from us
    points_subtract($current_user_id, $amount);

    // give points to user
    points_add_simple($to_user_id, $amount);

    // send pm to the user if the "Send PM on donate" setting is set to Yes
    if (get_setting('donations_send_private_message')) {
        if ($mybb->get_input('reason')) {
            $message = $lang->sprintf(
                $lang->newpoints_donate_message_reason,
                points_format($amount),
                htmlspecialchars_uni($mybb->get_input('reason'))
            );
        } else {
            $message = $lang->sprintf(
                $lang->newpoints_donate_message,
                points_format($amount)
            );
        }

        private_message_send(
            [
                'subject' => $lang->newpoints_donate_subject,
                'message' => $message,
                'receivepms' => 1,
                'touid' => $to_user_id
            ]
        );
    }

    log_add(
        'donation_sent',
        $mybb->get_input('reason'),
        $mybb->user['username'],
        $current_user_id,
        $amount,
        $to_user_id,
        0,
        0,
        LOGGING_TYPE_CHARGE
    );

    log_add(
        'donation',
        $mybb->get_input('reason'),
        $touser['username'],
        $to_user_id,
        $amount,
        $current_user_id,
        0,
        0,
        LOGGING_TYPE_INCOME
    );

    run_hooks('do_donate_end');

    $link = $mybb->settings['bburl'] . '/newpoints.php';

    if ($post = get_post($mybb->get_input('pid', 1))) {
        $link = get_post_link($post['pid'], $post['tid']) . '#pid' . $post['pid'];
    }

    redirect($link, $lang->sprintf($lang->newpoints_donated, points_format($amount)));
} elseif ($mybb->get_input('action') == 'logs') {
    $url_params = ['action' => 'logs'];

    $is_manage_page = false;

    $mybb->input['manage'] = $mybb->get_input('manage', MyBB::INPUT_INT);

    $is_moderator = is_member(get_setting('logs_manage_groups'));

    if ($mybb->input['manage'] && $is_moderator) {
        $url_params['manage'] = 1;

        $is_manage_page = true;
    }

    add_breadcrumb(
        $lang->newpoints_logs_page_breadcrumb,
        $mybb->settings['bburl'] . '/' . url_handler_build($url_params)
    );

    if ($is_manage_page) {
        add_breadcrumb(
            $lang->newpoints_manage_page_breadcrumb
        );
    }

    $page_url = url_handler_build($url_params);

    $per_page = (int)get_setting('logs_per_page');

    if ($per_page < 1) {
        $per_page = 10;
    }

    $errors = $where_clauses = [];

    if ($mybb->request_method && $is_moderator && $is_manage_page) {
        if ($mybb->get_input('view') === 'delete') {
            $log_id = $mybb->get_input('log_id', MyBB::INPUT_INT);

            $log_data = log_get($log_id);

            if ($log_data) {
                log_delete($log_id);

                redirect($page_url, $lang->newpoints_logs_page_success_log_deleted);
            } else {
                $errors[] = $lang->newpoints_logs_page_errors_no_logs_selected;
            }
        }
    }

    $filters = $mybb->get_input('filter', MyBB::INPUT_ARRAY);

    $filter_user_name = '';

    if ($is_moderator && $is_manage_page && !empty($filters['username'])) {
        $user_data = get_user_by_username($filters['username']);

        if (empty($user_data['uid'])) {
            $errors[] = $lang->newpoints_logs_page_errors_invalid_user_name;
        } else {
            $user_id = (int)$user_data['uid'];

            $where_clauses['user'] = "l.uid='{$user_id}'";

            $url_params['filter[username]'] = $filters['username'];

            $filter_user_name = htmlspecialchars_uni($filters['username']);
        }
    }

    if (!isset($where_clauses['user']) && !$is_manage_page) {
        $where_clauses['user'] = "l.uid='{$current_user_id}'";
    }

    if (isset($filters['actions'])) {
        $filter_actions = array_map([$db, 'escape_string'], $filters['actions']);

        $filter_actions = implode("','", $filter_actions);

        $where_clauses[] = "action IN ('{$filter_actions}')";

        foreach ($filters['actions'] as $action) {
            $url_params["filter[actions][{$action}]"] = $action;
        }
    }

    if ($errors) {
        $newpoints_errors = inline_error($errors);
    }

    $query = $db->simple_select('newpoints_log l', 'COUNT(lid) as total_logs', implode(' AND ', $where_clauses));

    $total_logs = (int)$db->fetch_field($query, 'total_logs');

    $current_page = $mybb->get_input('page', MyBB::INPUT_INT);

    $pages = $total_logs / $per_page;

    $pages = ceil($pages);

    if ($current_page > $pages || $current_page <= 0) {
        $current_page = 1;
    }

    if ($current_page) {
        $limit_start = ($current_page - 1) * $per_page;
    } else {
        $limit_start = 0;

        $current_page = 1;
    }

    if ($total_logs > $per_page) {
        $newpoints_pagination = multipage(
            $total_logs,
            $per_page,
            $current_page,
            url_handler_build($url_params)
        );

        if ($newpoints_pagination) {
            $newpoints_pagination = eval(templates_get('page_pagination'));
        }
    }

    $query = $db->simple_select(
        "newpoints_log l LEFT JOIN {$db->table_prefix}users u ON (u.uid=l.uid)",
        'l.lid, l.action, l.points, l.date, l.log_primary_id, l.log_secondary_id, l.log_tertiary_id, l.log_type, u.uid, u.username, u.usergroup, u.displaygroup',
        implode(' AND ', $where_clauses),
        ['order_by' => 'date', 'order_dir' => 'desc', 'limit' => $per_page, 'limit_start' => $limit_start]
    );

    $alternative_background = alt_trow(true);

    $logs_rows = '';

    $column_span = 8;

    $thead_user = $thead_options = '';

    if ($is_moderator && $is_manage_page) {
        $column_span += 2;

        $thead_user = eval(templates_get('logs_table_thead_user'));

        $delete_url = url_handler_build(array_merge($url_params, ['view' => 'delete']));

        $thead_options = eval(templates_get('logs_table_thead_delete'));
    }

    while ($log_data = $db->fetch_array($query)) {
        $log_id = (int)$log_data['lid'];

        $log_id = my_number_format($log_id);

        $log_action = htmlspecialchars_uni($log_data['action']);

        $log_points = points_format((float)$log_data['points']);

        $log_date = my_date('normal', $log_data['date']);

        $log_primary = $log_secondary = $log_tertiary = $log_type = '-';

        switch ($log_data['log_type']) {
            case LOGGING_TYPE_INCOME:
                $log_type = $lang->newpoints_logs_page_table_action_type_income;
                break;
            case LOGGING_TYPE_CHARGE:
                $log_type = $lang->newpoints_logs_page_table_action_type_charge;
                break;
        }

        run_hooks('logs_log_row');

        switch ($log_data['action']) {
            case 'donation':
            case 'donation_sent':
                $donation_user_data = get_user($log_data['log_primary_id']);

                $log_primary = build_profile_link(
                    htmlspecialchars_uni($donation_user_data['username'] ?? ''),
                    $donation_user_data['uid'] ?? 0
                );
                break;
        }

        switch ($log_data['action']) {
            case 'donation':
                $log_action = $lang->newpoints_logs_page_table_action_donation_received;
                break;
            case 'donation_sent':
                $log_action = $lang->newpoints_logs_page_table_action_donation_sent;
                break;
        }

        foreach (
            [
                INCOME_TYPE_THREAD => function (array &$log_data) use (&$log_primary, &$log_secondary): array {
                    global $mybb, $lang;

                    $thread_data = get_thread($log_data['log_primary_id']);

                    $forum_data = get_forum($log_data['log_secondary_id']);

                    if (!empty($thread_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_thread,
                            $mybb->settings['bburl'],
                            get_thread_link($thread_data['tid']),
                            post_parser()->parse_badwords($thread_data['subject'])
                        );
                    }

                    if (!empty($forum_data)) {
                        $log_secondary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_forum2,
                            $mybb->settings['bburl'],
                            get_forum_link($forum_data['fid']),
                            htmlspecialchars_uni(strip_tags($forum_data['name']))
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_THREAD_REPLY => function (array &$log_data) use (
                    &$log_primary,
                    &$log_secondary,
                    &$log_tertiary
                ): array {
                    global $mybb, $lang;

                    $thread_data = get_thread($log_data['log_primary_id']);

                    $post_data = get_forum($log_data['log_secondary_id']);

                    $forum_data = get_forum($log_data['log_tertiary_id']);

                    if (!empty($thread_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_thread,
                            $mybb->settings['bburl'],
                            get_thread_link($thread_data['tid']),
                            post_parser()->parse_badwords($thread_data['subject'])
                        );
                    }

                    if (!empty($post_data)) {
                        $log_secondary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_post,
                            $mybb->settings['bburl'],
                            get_post_link($post_data['pid']) . "#pid{$post_data['pid']}",
                            post_parser()->parse_badwords($post_data['subject'])
                        );
                    }

                    if (!empty($forum_data)) {
                        $log_tertiary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_forum,
                            $mybb->settings['bburl'],
                            get_forum_link($forum_data['fid']),
                            htmlspecialchars_uni(strip_tags($forum_data['name']))
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_POST => function (array &$log_data) use (
                    &$log_primary,
                    &$log_secondary,
                    &$log_tertiary
                ): array {
                    global $mybb, $lang;

                    $post_data = get_forum($log_data['log_primary_id']);

                    $thread_data = get_thread($log_data['log_secondary_id']);

                    $forum_data = get_forum($log_data['log_tertiary_id']);

                    if (!empty($post_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_post,
                            $mybb->settings['bburl'],
                            get_post_link($post_data['pid']) . "#pid{$post_data['pid']}",
                            post_parser()->parse_badwords($post_data['subject'])
                        );
                    }

                    if (!empty($thread_data)) {
                        $log_secondary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_thread,
                            $mybb->settings['bburl'],
                            get_thread_link($thread_data['tid']),
                            post_parser()->parse_badwords($thread_data['subject'])
                        );
                    }

                    if (!empty($forum_data)) {
                        $log_tertiary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_forum2,
                            $mybb->settings['bburl'],
                            get_forum_link($forum_data['fid']),
                            htmlspecialchars_uni(strip_tags($forum_data['name']))
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_POST_CHARACTER => function (array &$log_data) use (
                    &$log_primary,
                    &$log_secondary,
                    &$log_tertiary
                ): array {
                    global $mybb, $lang;

                    $thread_data = get_thread($log_data['log_primary_id']);

                    $post_data = get_forum($log_data['log_secondary_id']);

                    $forum_data = get_forum($log_data['log_tertiary_id']);

                    if (!empty($thread_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_thread,
                            $mybb->settings['bburl'],
                            get_thread_link($thread_data['tid']),
                            post_parser()->parse_badwords($thread_data['subject'])
                        );
                    }

                    if (!empty($post_data)) {
                        $log_secondary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_post,
                            $mybb->settings['bburl'],
                            get_post_link($post_data['pid']) . "#pid{$post_data['pid']}",
                            post_parser()->parse_badwords($post_data['subject'])
                        );
                    }

                    if (!empty($forum_data)) {
                        $log_tertiary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_forum2,
                            $mybb->settings['bburl'],
                            get_forum_link($forum_data['fid']),
                            htmlspecialchars_uni(strip_tags($forum_data['name']))
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_USER_REGISTRATION => function (array &$log_data): array {
                    return $log_data;
                },
                INCOME_TYPE_USER_REFERRAL => function (array &$log_data) use (&$log_primary): array {
                    global $mybb, $lang;

                    $user_data = get_user($log_data['log_primary_id']);

                    if (!empty($user_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_user,
                            $mybb->settings['bburl'],
                            build_profile_link(
                                format_name(
                                    htmlspecialchars_uni($user_data['username']),
                                    $user_data['usergroup'],
                                    $user_data['displaygroup']
                                ),
                                $user_data['uid']
                            )
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_PRIVATE_MESSAGE => function (array &$log_data) use (&$log_primary): array {
                    global $mybb, $lang, $db;

                    $private_message_id = (int)($log_data['log_primary_id'] ?? (
                        $log_data['log_secondary_id'] ?? (
                        $log_data['log_tertiary_id'] ?? 0
                    )
                    ));

                    if (!empty($private_message_id)) {
                        $query = $db->simple_select(
                            'privatemessages',
                            'toid',
                            "pmid='{$private_message_id}'"
                        );

                        $user_data = get_user($db->fetch_field($query, 'toid'));
                    }

                    if (!empty($user_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_user,
                            $mybb->settings['bburl'],
                            build_profile_link(
                                format_name(
                                    htmlspecialchars_uni($user_data['username']),
                                    $user_data['usergroup'],
                                    $user_data['displaygroup']
                                ),
                                $user_data['uid']
                            )
                        );
                    }

                    return $log_data;
                },
            ] as $income_type => $log_function
        ) {
            if ($log_data['action'] === "income_{$income_type}") {
                $language_variable = "newpoints_logs_page_table_action_income_{$income_type}";

                $log_action = $lang->{$language_variable};

                if (is_callable($log_function)) {
                    $log_data = $log_function($log_data);
                }
            }
        }

        $column_user = $column_options = '';

        if ($is_moderator && $is_manage_page) {
            $user_name = '';

            if (!empty($log_data['uid'])) {
                $user_name = build_profile_link(
                    format_name(
                        htmlspecialchars_uni($log_data['username']),
                        $log_data['usergroup'],
                        $log_data['displaygroup']
                    ),
                    $log_data['uid']
                );
            }

            $column_user = eval(templates_get('logs_table_row_user'));

            $column_options = eval(templates_get('logs_table_row_delete'));
        }

        $logs_rows .= eval(templates_get('logs_table_row'));

        $alternative_background = alt_trow();
    }

    if (!$logs_rows) {
        $logs_rows = eval(templates_get('logs_table_empty'));
    }

    $page_title = $lang->newpoints_logs_page_title;

    $newpoints_content = eval(templates_get('logs_table'));

    $action_types = [];

    $query = $db->simple_select('newpoints_log', 'action', '', ['group_by' => 'action']);

    while ($action = $db->fetch_field($query, 'action')) {
        $action_types[htmlspecialchars_uni($action)] = htmlspecialchars_uni($action);

        switch ($action) {
            case 'donation':
                $action_types[htmlspecialchars_uni(
                    $action
                )] = $lang->newpoints_logs_page_table_action_donation_received;
                break;
            case 'donation_sent':
                $action_types[htmlspecialchars_uni($action)] = $lang->newpoints_logs_page_table_action_donation_sent;
                break;
        }

        foreach (
            [
                INCOME_TYPE_THREAD,
                INCOME_TYPE_THREAD_REPLY,
                INCOME_TYPE_POST,
                INCOME_TYPE_POST_CHARACTER,
                INCOME_TYPE_USER_REGISTRATION,
                INCOME_TYPE_USER_REFERRAL,
                INCOME_TYPE_PRIVATE_MESSAGE,
            ] as $income_type
        ) {
            if ($action === "income_{$income_type}") {
                $language_variable = "newpoints_logs_page_table_action_income_{$income_type}";

                $action_types[$action] = $lang->{$language_variable};
            }
        }
    }

    if ($is_moderator && !$is_manage_page) {
        $manage_url = url_handler_build(array_merge($url_params, ['manage' => 1]));

        $newpoints_buttons = eval(templates_get('button_manage'));
    }

    run_hooks('logs_end');

    $actions_select = (function () use ($action_types, $filters): string {
        $select_name = 'filter[actions][]';

        $select_options = '';

        $select_multiple = 'multiple="multiple"';

        foreach ($action_types as $option_value => $option_name) {
            $selected_element = '';

            if (isset($filters['actions']) && in_array($option_value, $filters['actions'])) {
                $selected_element = 'selected="selected"';
            }

            $select_options .= eval(templates_get('input_select_option'));
        }

        return eval(templates_get('input_select'));
    })();

    $newpoints_additional = eval(templates_get('logs_filter_table'));

    $page_contents = eval(templates_get('page'));

    output_page($page_contents);

    exit;
}

run_hooks('terminate');

exit;