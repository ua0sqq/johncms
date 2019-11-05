<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

defined('_IN_JOHNCMS') || die('Error: restricted access');

$ban = isset($_GET['ban']) ? (int) ($_GET['ban']) : 0;

switch ($mod) {
    case 'do':
        // Баним пользователя (добавляем Бан в базу)
        if ($user->rights < 1 || ($user->rights < 6 && $foundUser['rights']) || ($user->rights <= $foundUser['rights'])) {
            echo $tools->displayError(_t('You do not have enought rights to ban this user'));
        } else {
            echo '<div class="phdr"><b>' . _t('Ban the User') . '</b></div>';
            echo '<div class="rmenu"><p>' . $tools->displayUser($foundUser) . '</p></div>';

            if (isset($_POST['submit'])) {
                $error = false;
                $term = isset($_POST['term']) ? (int) ($_POST['term']) : false;
                $timeval = isset($_POST['timeval']) ? (int) ($_POST['timeval']) : false;
                $time = isset($_POST['time']) ? (int) ($_POST['time']) : false;
                $reason = ! empty($_POST['reason']) ? trim($_POST['reason']) : '';
                $banref = isset($_POST['banref']) ? (int) ($_POST['banref']) : false;

                if (empty($reason) && empty($banref)) {
                    $reason = _t('Reason not specified');
                }

                if (empty($term) || empty($timeval) || empty($time) || $timeval < 1) {
                    $error = _t('There is no required data');
                }

                if ($user->rights == 1 && $term != 14 || $user->rights == 2 && $term != 12 || $user->rights == 3 && $term != 11 || $user->rights == 4 && $term != 16 || $user->rights == 5 && $term != 15) {
                    $error = _t('You have no rights to ban in this section');
                }

                if ($db->query("SELECT COUNT(*) FROM `cms_ban_users` WHERE `user_id` = '" . $foundUser['id'] . "' AND `ban_time` > '" . time() . "' AND `ban_type` = '${term}'")->fetchColumn()) {
                    $error = _t('Ban already active');
                }

                switch ($time) {
                    case 2:
                        // Часы
                        if ($timeval > 24) {
                            $timeval = 24;
                        }
                        $timeval = $timeval * 3600;
                        break;

                    case 3:
                        // Дни
                        if ($timeval > 30) {
                            $timeval = 30;
                        }
                        $timeval = $timeval * 86400;
                        break;

                    case 4:
                        // До отмены (на 10 лет)
                        $timeval = 315360000;
                        break;

                    default:
                        // Минуты
                        if ($timeval > 60) {
                            $timeval = 60;
                        }
                        $timeval = $timeval * 60;
                }

                if ($user->rights < 6 && $timeval > 86400) {
                    $timeval = 86400;
                }

                if ($user->rights < 7 && $timeval > 2592000) {
                    $timeval = 2592000;
                }

                if (! $error) {
                    // Заносим в базу
                    $stmt = $db->prepare('INSERT INTO `cms_ban_users` SET
                      `user_id` = ?,
                      `ban_time` = ?,
                      `ban_while` = ?,
                      `ban_type` = ?,
                      `ban_who` = ?,
                      `ban_reason` = ?
                    ');

                    $stmt->execute([
                        $foundUser['id'],
                        (time() + $timeval),
                        time(),
                        $term,
                        $user->name,
                        $reason,
                    ]);

                    if ($set_karma['on']) {
                        $points = $set_karma['karma_points'] * 2;
                        $stmt = $db->prepare('INSERT INTO `karma_users` SET
                          `user_id` = 0,
                          `name` = ?,
                          `karma_user` = ?,
                          `points` = ?,
                          `type` = 0,
                          `time` = ?,
                          `text` = ?
                        ');

                        $stmt->execute([
                            _t('System'),
                            $foundUser['id'],
                            $points,
                            time(),
                            _t('Ban'),
                        ]);

                        $db->exec('UPDATE `users` SET `karma_minus` = ' . (int) ($foundUser['karma_minus'] + $points) . ' WHERE `id` = ' . $foundUser['id']);
                    }

                    echo '<div class="rmenu"><p><h3>' . _t('User banned') . '</h3></p></div>';
                } else {
                    echo $tools->displayError($error);
                }
            } else {
                // Форма параметров бана
                echo '<form action="?act=ban&amp;mod=do&amp;user=' . $foundUser['id'] . '" method="post">' .
                    '<div class="menu"><p><h3>' . _t('Ban type') . '</h3>';

                if ($user->rights >= 6) {
                    // Блокировка
                    echo '<div><input name="term" type="radio" value="1" checked="checked" />&#160;' . _t('Full block') . '</div>';
                    // Приват
                    echo '<div><input name="term" type="radio" value="3" />&#160;' . _t('Private messages') . '</div>';
                    // Комментарии
                    echo '<div><input name="term" type="radio" value="10" />&#160;' . _t('Comments') . '</div>';
                    // Гостевая
                    echo '<div><input name="term" type="radio" value="13" />&#160;' . _t('Guestbook') . '</div>';
                }

                if ($user->rights == 3 || $user->rights >= 6) {
                    // Форум
                    echo '<div><input name="term" type="radio" value="11" ' . ($user->rights == 3 ? 'checked="checked"'
                            : '') . '/>&#160;' . _t('Forum') . '</div>';
                }

                if ($user->rights == 5 || $user->rights >= 6) {
                    // Библиотека
                    echo '<div><input name="term" type="radio" value="15" />&#160;' . _t('Library') . '</div>';
                }

                echo '</p><p><h3>' . _t('Ban time') . '</h3>' .
                    '&#160;<input type="text" name="timeval" size="2" maxlength="2" value="12"/><br>' .
                    '<input name="time" type="radio" value="1" />&#160;' . _t('Minutes (60 max.)') . '<br />' .
                    '<input name="time" type="radio" value="2" checked="checked" />&#160;' . _t('Hours (24 max.)') . '<br />';

                if ($user->rights >= 6) {
                    echo '<input name="time" type="radio" value="3" />&#160;' . _t('Days (30 max.)') . '<br />';
                }

                if ($user->rights >= 7) {
                    echo '<input name="time" type="radio" value="4" />&#160;<span class="red">' . _t('Till cancel') . '</span>';
                }

                echo '</p><p><h3>' . _t('Reason') . '</h3>';

                if (isset($_GET['fid'])) {
                    // Если бан из форума, фиксируем ID поста
                    $fid = (int) ($_GET['fid']);
                    echo '&#160;' . _t('Violation') . ' <a href="' . $config['homeurl'] . '/forum/?act=show_post&amp;id=' . $fid . '"></a><br />' .
                        '<input type="hidden" value="' . $fid . '" name="banref" />';
                }

                echo '&#160;<textarea rows="' . $user->config->fieldHeight . '" name="reason"></textarea>' .
                    '</p><p><input type="submit" value="' . _t('Apply Ban') . '" name="submit" />' .
                    '</p></div></form>';
            }
            echo '<div class="phdr"><a href="?user=' . $foundUser['id'] . '">' . _t('Profile') . '</a></div>';
        }
        break;

    case 'cancel':
        // Разбаниваем пользователя (с сохранением истории)
        if (! $ban || $foundUser['id'] == $user->id || $user->rights < 7) {
            echo $tools->displayError(_t('Wrong data'));
        } else {
            $req = $db->query("SELECT * FROM `cms_ban_users` WHERE `id` = '${ban}' AND `user_id` = " . $foundUser['id']);

            if ($req->rowCount()) {
                $res = $req->fetch();
                $error = false;

                if ($res['ban_time'] < time()) {
                    $error = _t('Ban not active');
                }

                if (! $error) {
                    echo '<div class="phdr"><b>' . _t('Ban termination') . '</b></div>';
                    echo '<div class="gmenu"><p>' . $tools->displayUser($foundUser) . '</p></div>';

                    if (isset($_POST['submit'])) {
                        $db->exec("UPDATE `cms_ban_users` SET `ban_time` = '" . time() . "' WHERE `id` = '${ban}'");
                        echo '<div class="gmenu"><p><h3>' . _t('Ban terminated') . '</h3></p></div>';
                    } else {
                        echo '<form action="?act=ban&amp;mod=cancel&amp;user=' . $foundUser['id'] . '&amp;ban=' . $ban . '" method="POST">' .
                            '<div class="menu"><p>' . _t('Ban time is going to the end. Infrigement will be saved in the bans history') . '</p>' .
                            '<p><input type="submit" name="submit" value="' . _t('Terminate Ban') . '" /></p>' .
                            '</div></form>' .
                            '<div class="phdr"><a href="?act=ban&amp;user=' . $foundUser['id'] . '">' . _t('Back') . '</a></div>';
                    }
                } else {
                    echo $tools->displayError($error);
                }
            } else {
                echo $tools->displayError(_t('Wrong data'));
            }
        }
        break;

    case 'delete':
        // Удаляем бан (с удалением записи из истории)
        if (! $ban || $user->rights < 9) {
            echo $tools->displayError(_t('Wrong data'));
        } else {
            $req = $db->query("SELECT * FROM `cms_ban_users` WHERE `id` = '${ban}' AND `user_id` = " . $foundUser['id']);

            if ($req->rowCount()) {
                $res = $req->fetch();
                echo '<div class="phdr"><b>' . _t('Delete Ban') . '</b></div>' .
                    '<div class="gmenu"><p>' . $tools->displayUser($foundUser) . '</p></div>';

                if (isset($_POST['submit'])) {
                    $db->exec("DELETE FROM `karma_users` WHERE `karma_user` = '" . $foundUser['id'] . "' AND `user_id` = '0' AND `time` = '" . $res['ban_while'] . "' LIMIT 1");
                    $points = $set_karma['karma_points'] * 2;
                    $db->exec("UPDATE `users` SET
                        `karma_minus` = '" . ($foundUser['karma_minus'] > $points ? $foundUser['karma_minus'] - $points : 0) . "'
                        WHERE `id` = " . $foundUser['id']);
                    $db->exec("DELETE FROM `cms_ban_users` WHERE `id` = '${ban}'");
                    echo '<div class="gmenu"><p><h3>' . _t('Ban deleted') . '</h3><a href="?act=ban&amp;user=' . $foundUser['id'] . '">' . _t('Continue') . '</a></p></div>';
                } else {
                    echo '<form action="?act=ban&amp;mod=delete&amp;user=' . $foundUser['id'] . '&amp;ban=' . $ban . '" method="POST">' .
                        '<div class="menu"><p>' . _t('Removing ban along with a record in the bans history') . '</p>' .
                        '<p><input type="submit" name="submit" value="' . _t('Delete') . '" /></p>' .
                        '</div></form>' .
                        '<div class="phdr"><a href="?act=ban&amp;user=' . $foundUser['id'] . '">' . _t('Back') . '</a></div>';
                }
            } else {
                echo $tools->displayError(_t('Wrong data'));
            }
        }
        break;

    case 'delhist':
        // Очищаем историю нарушений юзера
        if ($user->rights == 9) {
            echo '<div class="phdr"><b>' . _t('Violations history') . '</b></div>' .
                '<div class="gmenu"><p>' . $tools->displayUser($foundUser) . '</p></div>';

            if (isset($_POST['submit'])) {
                $db->exec('DELETE FROM `cms_ban_users` WHERE `user_id` = ' . $foundUser['id']);
                echo '<div class="gmenu"><h3>' . _t('Violations history cleared') . '</h3></div>';
            } else {
                echo '<form action="?act=ban&amp;mod=delhist&amp;user=' . $foundUser['id'] . '" method="post">' .
                    '<div class="menu"><p>' . _t('Are you sure want to clean entire history of user violations?') . '</p>' .
                    '<p><input type="submit" value="' . _t('Clear') . '" name="submit" />' .
                    '</p></div></form>';
            }

            $total = $db->query("SELECT COUNT(*) FROM `cms_ban_users` WHERE `user_id` = '" . $foundUser['id'] . "'")->fetchColumn();
            echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>' .
                '<p>' . ($total
                    ? '<a href="?act=ban&amp;user=' . $foundUser['id'] . '">' . _t('Violations history') . '</a><br />'
                    : '') .
                '<a href="../admin/?act=ban_panel">' . _t('Ban Panel') . '</a></p>';
        } else {
            echo $tools->displayError(_t('Violations history can be cleared by Supervisor only'));
        }
        break;

    default:
        // История нарушений
        echo '<div class="phdr"><a href="?user=' . $foundUser['id'] . '"><b>' . _t('Profile') . '</b></a> | ' . _t('Violations History') . '</div>';
        // Меню
        $menu = [];

        if ($user->rights >= 6) {
            $menu[] = '<a href="../admin/?act=ban_panel">' . _t('Ban Panel') . '</a>';
        }

        if ($user->rights == 9) {
            $menu[] = '<a href="?act=ban&amp;mod=delhist&amp;user=' . $foundUser['id'] . '">' . _t('Clear history') . '</a>';
        }

        if (! empty($menu)) {
            echo '<div class="topmenu">' . implode(' | ', $menu) . '</div>';
        }

        if ($foundUser['id'] != $user->id) {
            echo '<div class="user"><p>' . $tools->displayUser($foundUser) . '</p></div>';
        } else {
            echo '<div class="list2"><p>' . _t('My Violations') . '</p></div>';
        }

        $total = $db->query("SELECT COUNT(*) FROM `cms_ban_users` WHERE `user_id` = '" . $foundUser['id'] . "'")->fetchColumn();

        if ($total) {
            $req = $db->query("SELECT * FROM `cms_ban_users` WHERE `user_id` = '" . $foundUser['id'] . "' ORDER BY `ban_time` DESC LIMIT ${start}, ${kmess}");
            $i = 0;

            $types = [
                1  => 'Full block',
                2  => 'Private messages',
                10 => 'Comments',
                11 => 'Forum',
                13 => 'Guestbook',
                15 => 'Library',
            ];

            while ($res = $req->fetch()) {
                $remain = $res['ban_time'] - time();
                $period = $res['ban_time'] - $res['ban_while'];
                echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
                echo '<img src="../images/' . ($remain > 0 ? 'red'
                        : 'green') . '.gif" width="16" height="16" align="left" />&#160;' .
                    '<b>' . $types[$res['ban_type']] . '</b>' .
                    ' <span class="gray">(' . date('d.m.Y / H:i', $res['ban_while']) . ')</span>' .
                    '<br />' . $tools->checkout($res['ban_reason']) .
                    '<div class="sub">';

                if ($user->rights > 0) {
                    echo '<span class="gray">' . _t('Who applied the Ban?') . ':</span> ' . $res['ban_who'] . '<br />';
                }

                echo '<span class="gray">' . _t('Time') . ':</span> '
                    . ($period < 86400000 ? $tools->timecount($period) : _t('Till cancel'));

                if ($remain > 0) {
                    echo '<br /><span class="gray">' . _t('Remains') . ':</span> ' . $tools->timecount($remain);
                }

                // Меню отдельного бана
                $menu = [];

                if ($user->rights >= 7 && $remain > 0) {
                    $menu[] = '<a href="?act=ban&amp;mod=cancel&amp;user=' . $foundUser['id'] . '&amp;ban=' . $res['id'] . '">' . _t('Cancel Ban') . '</a>';
                }

                if ($user->rights == 9) {
                    $menu[] = '<a href="?act=ban&amp;mod=delete&amp;user=' . $foundUser['id'] . '&amp;ban=' . $res['id'] . '">' . _t('Delete Ban') . '</a>';
                }

                if (! empty($menu)) {
                    echo '<div>' . implode(' | ', $menu) . '</div>';
                }

                echo '</div></div>';
                ++$i;
            }
        } else {
            echo '<div class="menu"><p>' . _t('The list is empty') . '</p></div>';
        }

        echo '<div class="phdr">' . _t('Total') . ': ' . $total . '</div>';

        if ($total > $kmess) {
            echo '<p>' . $tools->displayPagination('?act=ban&amp;user=' . $foundUser['id'] . '&amp;', $start, $total, $kmess) . '</p>' .
                '<p><form action="?act=ban&amp;user=' . $foundUser['id'] . '" method="post">' .
                '<input type="text" name="page" size="2"/>' .
                '<input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/></form></p>';
        }
}
