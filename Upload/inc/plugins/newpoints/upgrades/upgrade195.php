<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/upgrades/upgrade195.php)
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

use function Newpoints\Core\rules_rebuild_cache;
use function Newpoints\Core\settings_rebuild_cache;

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

if (!defined('IN_ADMINCP')) {
    die('This file must be accessed from the Administrator Panel.');
}

function upgrade195_info()
{
    return [
        'new_version' => '1.9.5',
        'name' => 'Upgrade to 1.9.5',
        'description' => 'Upgrade NewPoints 1.9.4 to NewPoints 1.9.5.<br />Cache entries will be created.'
    ];
}

// upgrade function
function upgrade195_run()
{
    settings_rebuild_cache();

    rules_rebuild_cache();
}