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

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Johncms\Api\UserInterface $systemUser */
$systemUser = $container->get(Johncms\Api\UserInterface::class);

/** @var Johncms\Api\ConfigInterface $config */
$config = $container->get(Johncms\Api\ConfigInterface::class);

$req_down = $db->query("SELECT * FROM `download__files` WHERE `id` = '" . $id . "' AND (`type` = 2 OR `type` = 3)  LIMIT 1");
$res_down = $req_down->fetch();

if (! $req_down->rowCount() || ! is_file($res_down['dir'] . '/' . $res_down['name']) || ($res_down['type'] == 3 && $systemUser->rights < 6 && $systemUser->rights != 4)) {
    $error = true;
} else {
    $link = $res_down['dir'] . '/' . $res_down['name'];
}

$more = isset($_GET['more']) ? abs((int) ($_GET['more'])) : false;

if ($more) {
    $req_more = $db->query("SELECT * FROM `download__more` WHERE `refid` = '" . $id . "' AND `id` = '${more}' LIMIT 1");
    $res_more = $req_more->fetch();

    if (! $req_more->rowCount() || ! is_file($res_down['dir'] . '/' . $res_more['name'])) {
        $error = true;
    } else {
        $link = $res_down['dir'] . '/' . $res_more['name'];
    }
}

if ($error) {
    header('Location: ' . $config['homeurl'] . '/404');
} else {
    if (! isset($_SESSION['down_' . $id])) {
        $db->exec('UPDATE `download__files` SET `field`=`field`+1 WHERE `id`=' . $id);
        $_SESSION['down_' . $id] = 1;
    }

    header('Location: ' . $link);
}
