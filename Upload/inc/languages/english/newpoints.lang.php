<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/languages/english/newpoints.lang.php)
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

$l['newpoints'] = 'NewPoints';
$l['newpoints_home'] = 'Home';
$l['newpoints_menu'] = 'Menu';
$l['newpoints_donate'] = 'Donate';
$l['newpoints_donated'] = 'You have successfully donated {1} to the selected user.';
$l['newpoints_user'] = 'User';
$l['newpoints_user_desc'] = 'Enter the user name of the user you want to send a donation.';
$l['newpoints_amount'] = 'Amount';
$l['newpoints_amount_desc'] = 'Enter the amount of points you want to send to the user.';
$l['newpoints_reason'] = 'Reason';
$l['newpoints_reason_desc'] = '(Optional) Enter a reason for the donation.';
$l['newpoints_submit'] = 'Submit';
$l['newpoints_donate_subject'] = 'New donation';
$l['newpoints_donate_message'] = 'Hello, I\'ve just sent you a donation of {1}.';
$l['newpoints_donate_message_reason'] = 'Hello, I\'ve just sent you a donation of {1}. Reason:[quote]{2}[/quote]';
$l['newpoints_donations_disabled'] = 'Donations have been disabled by the administrator.';
$l['newpoints_cant_donate_self'] = 'You can\'t send a donation to yourself.';
$l['newpoints_invalid_amount'] = 'You have entered an invalid amount of points.';
$l['newpoints_invalid_user'] = 'You have entered an invalid user name.';
$l['newpoints_donate_log'] = '{1}-{2}-{3}';
$l['newpoints_stats_disabled'] = 'Statistics have been disabled by the administrator.';
$l['newpoints_statistics'] = 'Statistics';
$l['newpoints_richest_users'] = 'Richest Users';
$l['newpoints_last_donations'] = 'Last Donations';
$l['newpoints_from'] = 'From';
$l['newpoints_to'] = 'To';
$l['newpoints_noresults'] = 'No results found.';
$l['newpoints_date'] = 'Date';
$l['newpoints_not_enough_points'] = 'You don\'t have enough points. Required: {1}';
$l['newpoints_amount_paid'] = 'Amount Paid';
$l['newpoints_source'] = 'Source';
$l['newpoints_home_desc'] = 'NewPoints is a complex points system for MyBB software.';
$l['newpoints_home_description_primary'] = 'There are some options on the menu on the left that you can use.';
$l['newpoints_home_description_header'] = 'How do you earn points?';
$l['newpoints_home_description_secondary'] = '';
$l['newpoints_home_description_footer'] = 'Contact your administrator if you have any questions.<br />This software was written by <strong>Pirata Nervo</strong> for <a href="https://mybb.com">MyBB</a>.';
$l['newpoints_action'] = 'Action';
$l['newpoints_chars'] = 'Chars';
$l['newpoints_max_donations_control'] = 'You have reached the maximum of {1} over the last 15 minutes. Please wait before making a new one.';

// Settings translation
$l['newpoints_income_source'] = 'Source';
$l['newpoints_income_amount'] = '{1} Received';
$l['newpoints_income_newpost_title'] = 'New Post';
$l['newpoints_income_newpost_desc'] = 'Amount of points received on new post.';
$l['newpoints_income_newthread_title'] = 'New Thread';
$l['newpoints_income_newthread_desc'] = 'Amount of points received on new thread.';
$l['newpoints_income_newpoll_title'] = 'New Poll';
$l['newpoints_income_newpoll_desc'] = 'Amount of points received on new poll.';
$l['newpoints_income_perchar_title'] = 'Per Character';
$l['newpoints_income_perchar_desc'] = 'Amount of points received per character (in new thread and new post).';
$l['newpoints_income_minchar_title'] = 'Minimum Characters';
$l['newpoints_income_minchar_desc'] = 'Minimum characters required in order to receive the amount of points per character.';
$l['newpoints_income_newreg_title'] = 'New Registration';
$l['newpoints_income_newreg_desc'] = 'Amount of points received by the user when registering.';
$l['newpoints_income_pervote_title'] = 'Per Poll Vote';
$l['newpoints_income_pervote_desc'] = 'Amount of points received by the user who votes.';
$l['newpoints_income_perreply_title'] = 'Per Reply';
$l['newpoints_income_perreply_desc'] = 'Amount of points received by the author of the thread, when someone replies to it.';
$l['newpoints_income_pmsent_title'] = 'Per PM Sent';
$l['newpoints_income_pmsent_desc'] = 'Amount of points received everytime a user sends a private message.';
$l['newpoints_income_perrate_title'] = 'Per Rate';
$l['newpoints_income_perrate_desc'] = 'Amount of points received everytime a user rates a thread.';
$l['newpoints_income_pageview_title'] = 'Per Page View';
$l['newpoints_income_pageview_desc'] = 'Amount of points received everytime a user views a page.';
$l['newpoints_income_visit_title'] = 'Per Visit';
$l['newpoints_income_visit_desc'] = 'Amount of points received everytime a user visits the forum. ("visits" = new MyBB session (expires after 15 minutes))';
$l['newpoints_income_referral_title'] = 'Per Referral';
$l['newpoints_income_referral_desc'] = 'Amount of points received everytime a user is referred. (the referred user is who receives the points)';


$l['newpoints_search_user'] = 'Search for an user..';

$l['newpoints_task_ran'] = 'Backup NewPoints task ran';
$l['newpoints_task_main_ran'] = 'Main NewPoints task ran';

$l['setting_newpoints_allowance'] = 'Allowance';
$l['setting_newpoints_allowance_desc'] = 'Amount of points received every {1} minutes.';

$l['newpoints_page_confirm_table_cancel_title'] = 'Confirm Cancel';
$l['newpoints_page_confirm_table_cancel_button'] = 'Cancel Order';

$l['newpoints_page_confirm_table_purchase_title'] = 'Confirm Purchase';
$l['newpoints_page_confirm_table_purchase_button'] = 'Purchase';