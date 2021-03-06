<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

declare(strict_types=1);

use Forum\ForumUtils;
use Forum\Models\ForumTopic;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Forum\Models\ForumSection;
use Johncms\Counters;
use Johncms\NavChain;
use Johncms\System\Legacy\Tools;
use Johncms\Users\GuestSession;
use Johncms\Users\User;

/**
 * @var Tools $tools
 * @var Counters $counters
 * @var NavChain $nav_chain
 * @var $config
 * @var $id
 */

/** @var User $user */
$user = di(User::class);

try {
    $current_section = (new ForumSection())->withCount('sectionFiles')->findOrFail($id);
} catch (ModelNotFoundException $exception) {
    ForumUtils::notFound();
}

// Build breadcrumbs
ForumUtils::buildBreadcrumbs($current_section->parent, $current_section->name);

// List of forum topics
$topics = (new ForumTopic())
    ->read()
    ->where('section_id', '=', $id)
    ->orderByDesc('pinned')
    ->orderByDesc('last_post_date')
    ->paginate($user->set_user->kmess);

// Check access to create topic
$create_access = false;
if (($user->is_valid && ! isset($user->ban['1']) && ! isset($user->ban['11']) && $config['mod_forum'] !== 4) || $user->rights > 0) {
    $create_access = true;
}

unset($_SESSION['fsort_id'], $_SESSION['fsort_users']);

// Считаем пользователей онлайн
$online = [
    'online_u' => (new User())->online()->where('place', 'like', '/forum%')->count(),
    'online_g' => (new GuestSession())->online()->where('place', 'like', '/forum%')->count(),
];

echo $view->render(
    'forum::topics',
    [
        'pagination'    => $topics->render(),
        'id'            => $id,
        'create_access' => $create_access,
        'title'         => $current_section->name,
        'page_title'    => $current_section->name,
        'topics'        => $topics->getItems(),
        'online'        => $online,
        'total'         => $topics->total(),
        'files_count'   => $tools->formatNumber($current_section->section_files_count),
        'unread_count'  => $tools->formatNumber($counters->forumUnreadCount()),
    ]
);
